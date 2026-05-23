<?php
/**
 * DB schema, migrations, seeding.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_DB {

	public static function tables() {
		global $wpdb;
		return [
			'mosques'           => $wpdb->prefix . 'la_mosques',
			'prayer_times'      => $wpdb->prefix . 'la_prayer_times',
			'subscribers'       => $wpdb->prefix . 'la_subscribers',
			'content'           => $wpdb->prefix . 'la_content',
			'scholars'          => $wpdb->prefix . 'la_scholars',
			'feed_posts'        => $wpdb->prefix . 'la_feed_posts',
			'feed_interactions' => $wpdb->prefix . 'la_feed_interactions',
			'unlock_state'      => $wpdb->prefix . 'la_unlock_state',
			'pushes'            => $wpdb->prefix . 'la_pushes',
			'emails'            => $wpdb->prefix . 'la_emails',
			'events'            => $wpdb->prefix . 'la_events',
			'email_captures'    => $wpdb->prefix . 'la_email_captures',
			'prayer_log'        => $wpdb->prefix . 'la_prayer_log',
			'tasbeeh_log'       => $wpdb->prefix . 'la_tasbeeh_log',
			'duas'              => $wpdb->prefix . 'la_duas',
			'dua_ameen'         => $wpdb->prefix . 'la_dua_ameen',
			'event_rsvps'       => $wpdb->prefix . 'la_event_rsvps',
			'skill_listings'    => $wpdb->prefix . 'la_skill_listings',
		];
	}

	public static function install() {
		self::create_tables();
		update_option( 'la_db_version', LA_DB_VERSION );
	}

	public static function maybe_upgrade() {
		$current = (int) get_option( 'la_db_version', 0 );
		if ( $current < LA_DB_VERSION ) {
			self::create_tables();
			// Re-seed on every DB version bump. All of these are idempotent
			// (insert-or-update by unique key, or count-and-skip-if-exists)
			// so re-running them on existing installs is safe.
			self::seed_scholars();
			self::seed_duas();
			self::seed_skill_listings();
			// Events for the default mosque (idempotent — skips if any
			// events already exist for that mosque_id).
			$default_mosque_id = (int) ( get_option( 'la_default_mosque_id' ) ?: 1 );
			if ( class_exists( 'LA_Events' ) && $default_mosque_id ) {
				LA_Events::seed_for_mosque( $default_mosque_id );
			}
			update_option( 'la_db_version', LA_DB_VERSION );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$t = self::tables();

		dbDelta( "CREATE TABLE {$t['mosques']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(120) NOT NULL,
			name varchar(255) NOT NULL,
			address text,
			city varchar(120),
			country varchar(80),
			latitude decimal(10,7) DEFAULT NULL,
			longitude decimal(10,7) DEFAULT NULL,
			branding_logo varchar(500) DEFAULT NULL,
			branding_color_primary varchar(20) DEFAULT NULL,
			branding_banner varchar(500) DEFAULT NULL,
			jumuah_time time DEFAULT NULL,
			jumuah_khutbah_lang varchar(40) DEFAULT NULL,
			second_jumuah_time time DEFAULT NULL,
			claimed_user_id bigint(20) unsigned DEFAULT NULL,
			claimed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY geo (latitude, longitude)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['prayer_times']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mosque_id bigint(20) unsigned NOT NULL,
			date date NOT NULL,
			fajr time DEFAULT NULL,
			sunrise time DEFAULT NULL,
			dhuhr time DEFAULT NULL,
			asr time DEFAULT NULL,
			maghrib time DEFAULT NULL,
			isha time DEFAULT NULL,
			fajr_jamaat time DEFAULT NULL,
			dhuhr_jamaat time DEFAULT NULL,
			asr_jamaat time DEFAULT NULL,
			maghrib_jamaat time DEFAULT NULL,
			isha_jamaat time DEFAULT NULL,
			jumuah_time time DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY mosque_date (mosque_id, date)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['subscribers']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mosque_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(190) DEFAULT NULL,
			push_endpoint text DEFAULT NULL,
			push_p256dh varchar(255) DEFAULT NULL,
			push_auth varchar(255) DEFAULT NULL,
			subscribed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			unsubscribed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY mosque_id (mosque_id),
			KEY email (email)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['content']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mosque_id bigint(20) unsigned DEFAULT NULL,
			type varchar(40) NOT NULL,
			title varchar(255) DEFAULT NULL,
			body text,
			arabic_text text,
			transliteration varchar(255) DEFAULT NULL,
			translation varchar(500) DEFAULT NULL,
			video_url varchar(500) DEFAULT NULL,
			display_order int(11) NOT NULL DEFAULT 0,
			starts_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY type_order (type, display_order),
			KEY mosque_id (mosque_id)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['scholars']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			username varchar(120) NOT NULL,
			display_name varchar(255) NOT NULL,
			avatar varchar(500) DEFAULT NULL,
			bio text,
			account_type varchar(20) NOT NULL DEFAULT 'curated',
			source_url varchar(500) DEFAULT NULL,
			youtube_channel_id varchar(40) DEFAULT NULL,
			last_synced_at datetime DEFAULT NULL,
			default_content_type varchar(40) NOT NULL DEFAULT 'reminder',
			associated_charity varchar(255) DEFAULT NULL,
			associated_masjid_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY username (username),
			KEY youtube_channel_id (youtube_channel_id),
			KEY default_content_type (default_content_type)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['feed_posts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scholar_id bigint(20) unsigned NOT NULL,
			mosque_id bigint(20) unsigned DEFAULT NULL,
			type varchar(40) NOT NULL DEFAULT 'short',
			title varchar(255) DEFAULT NULL,
			caption text,
			video_url varchar(500) NOT NULL,
			thumbnail_url varchar(500) DEFAULT NULL,
			duration_sec int(11) DEFAULT NULL,
			original_source_url varchar(500) DEFAULT NULL,
			display_order int(11) NOT NULL DEFAULT 0,
			published_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime DEFAULT NULL,
			likes_count int(11) NOT NULL DEFAULT 0,
			saves_count int(11) NOT NULL DEFAULT 0,
			shares_count int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY published (published_at),
			KEY scholar_id (scholar_id),
			KEY mosque_id (mosque_id)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['feed_interactions']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			session_id varchar(64) DEFAULT NULL,
			post_id bigint(20) unsigned NOT NULL,
			action varchar(20) NOT NULL,
			occurred_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_action (post_id, action),
			KEY user_post (user_id, post_id)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['unlock_state']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			session_id varchar(64) DEFAULT NULL,
			date date NOT NULL,
			dhikr_completed int(11) NOT NULL DEFAULT 0,
			unlocked_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_date (session_id, date),
			KEY user_date (user_id, date)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['pushes']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mosque_id bigint(20) unsigned NOT NULL,
			body text NOT NULL,
			sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			recipients_count int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY mosque_id (mosque_id)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['emails']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mosque_id bigint(20) unsigned NOT NULL,
			subject varchar(255) NOT NULL,
			body_html longtext,
			sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			recipients_count int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY mosque_id (mosque_id)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['events']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			mosque_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL,
			description text,
			starts_at datetime NOT NULL,
			ends_at datetime DEFAULT NULL,
			location varchar(255) DEFAULT NULL,
			image_url varchar(500) DEFAULT NULL,
			poster_gradient varchar(120) DEFAULT NULL,
			cta_label varchar(80) DEFAULT NULL,
			cta_url varchar(500) DEFAULT NULL,
			tag varchar(40) DEFAULT NULL,
			rsvp_count int unsigned NOT NULL DEFAULT 0,
			fav_count  int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY mosque_id (mosque_id),
			KEY starts_at (starts_at)
		) $charset_collate;" );

		// RSVP / favourite log for events. status enum: 'rsvp' | 'fav'.
		// Unique key prevents duplicate rows so the toggle stays clean.
		dbDelta( "CREATE TABLE {$t['event_rsvps']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id bigint(20) unsigned NOT NULL,
			identity varchar(80) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'rsvp',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ev_identity_status (event_id, identity, status),
			KEY identity (identity)
		) $charset_collate;" );

		// Skills marketplace (Connect page). Anyone can post, money flows
		// through the platform to Islamic projects (% revenue share).
		// status: pending | active | rejected. Admins moderate.
		dbDelta( "CREATE TABLE {$t['skill_listings']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			identity varchar(80) DEFAULT NULL,
			category varchar(60) NOT NULL,
			title varchar(180) NOT NULL,
			blurb text,
			full_name varchar(120) NOT NULL,
			city varchar(120) DEFAULT NULL,
			country varchar(80) DEFAULT NULL,
			contact_email varchar(190) DEFAULT NULL,
			contact_whatsapp varchar(40) DEFAULT NULL,
			contact_url varchar(500) DEFAULT NULL,
			price_from int unsigned DEFAULT NULL,
			price_unit varchar(20) DEFAULT NULL,
			currency varchar(8) DEFAULT 'GBP',
			photo_url varchar(500) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			views_count int unsigned NOT NULL DEFAULT 0,
			contact_count int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY category (category),
			KEY status (status),
			KEY identity (identity)
		) $charset_collate;" );

		dbDelta( "CREATE TABLE {$t['email_captures']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			session_id varchar(64) DEFAULT NULL,
			source varchar(40) DEFAULT NULL,
			ip_hash varchar(64) DEFAULT NULL,
			referrer varchar(500) DEFAULT NULL,
			captured_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY session_id (session_id),
			KEY user_id (user_id)
		) $charset_collate;" );

		// Prayer log — each row is a single 'I prayed Fajr today' confirmation.
		// identity = 'u123' (user_id 123) or 's<session_id>'. Lets us serve
		// anonymous + logged-in identically and unique by (identity, date, prayer).
		dbDelta( "CREATE TABLE {$t['prayer_log']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			identity varchar(80) NOT NULL,
			date date NOT NULL,
			prayer varchar(20) NOT NULL,
			prayed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_identity_date_prayer (identity, date, prayer),
			KEY identity_date (identity, date)
		) $charset_collate;" );

		// Tasbeeh log — per-user lifetime + daily tasbeeh counts.
		// Stores aggregate counters; client persists per-second taps in localStorage.
		dbDelta( "CREATE TABLE {$t['tasbeeh_log']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			identity varchar(80) NOT NULL,
			date date NOT NULL,
			phrase varchar(40) NOT NULL,
			count int unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_identity_date_phrase (identity, date, phrase)
		) $charset_collate;" );

		// Duas — curated supplications library.
		dbDelta( "CREATE TABLE {$t['duas']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(80) NOT NULL,
			category varchar(40) NOT NULL,
			title varchar(190) NOT NULL,
			arabic text NOT NULL,
			transliteration text DEFAULT NULL,
			meaning text DEFAULT NULL,
			source varchar(120) DEFAULT NULL,
			repeat_count int unsigned NOT NULL DEFAULT 1,
			ameen_count int unsigned NOT NULL DEFAULT 0,
			sort_order int NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY category (category)
		) $charset_collate;" );

		// Per-identity Ameen log so we can show toggled state + prevent dupes.
		dbDelta( "CREATE TABLE {$t['dua_ameen']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			dua_id bigint(20) unsigned NOT NULL,
			identity varchar(80) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_dua_identity (dua_id, identity)
		) $charset_collate;" );
	}

	public static function seed() {
		self::seed_dhikr();
		self::seed_mosque();
		self::seed_scholars();
		self::seed_feed_posts();
		self::seed_duas();
		self::seed_skill_listings();
	}

	/** Seed 6 example Connect listings so the marketplace isn't empty
	 *  on first deploy. Idempotent — skips if any 'sample' listings exist. */
	private static function seed_skill_listings() {
		global $wpdb;
		$t = self::tables();
		$existing = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$t['skill_listings']}"
		);
		if ( $existing > 0 ) return;

		$samples = [
			[ 'category' => 'tutoring',  'title' => 'Qur\'an + Tajweed online', 'blurb' => 'Beginner-to-intermediate students welcome. Ijazah from Madinah-trained teachers. 30-min trial free.', 'full_name' => 'Ust. Bilal Yusuf', 'city' => 'Online', 'contact_email' => 'sample@loveallah.app', 'price_from' => 15, 'price_unit' => 'hour' ],
			[ 'category' => 'medical',   'title' => 'GP — same-day consultations', 'blurb' => 'NHS GP available privately on weekends for Muslim patients. Confidential, sister doctors available.', 'full_name' => 'Dr Aisha Khan', 'city' => 'Birmingham', 'contact_whatsapp' => '+447000000000', 'price_from' => 35, 'price_unit' => 'visit' ],
			[ 'category' => 'trades',    'title' => 'Plumbing + heating repairs', 'blurb' => 'Gas-safe registered. Emergency callouts. Halal pricing for masjid + community jobs.', 'full_name' => 'Umar Ahmed', 'city' => 'London', 'contact_email' => 'sample2@loveallah.app', 'price_from' => 60, 'price_unit' => 'hour' ],
			[ 'category' => 'design',    'title' => 'Logo + brand design for da\'wah projects', 'blurb' => 'I help Islamic non-profits and start-ups with brand identity. Discount for masjids and student projects.', 'full_name' => 'Sister Fatima', 'city' => 'Manchester', 'contact_email' => 'sample3@loveallah.app', 'price_from' => 250, 'price_unit' => 'project' ],
			[ 'category' => 'legal',     'title' => 'Solicitor — family + immigration', 'blurb' => 'Specialist in Muslim family law, wills (wasiyyah), nikkah agreements and immigration.', 'full_name' => 'Imran Choudhury', 'city' => 'Leicester', 'contact_email' => 'sample4@loveallah.app', 'price_from' => 90, 'price_unit' => 'consultation' ],
			[ 'category' => 'fitness',   'title' => 'Sisters-only personal training', 'blurb' => 'Female PT, modesty-respecting sessions, indoor + home gym. Group rates available.', 'full_name' => 'Sister Layla', 'city' => 'Bradford', 'contact_whatsapp' => '+447000000001', 'price_from' => 25, 'price_unit' => 'session' ],
		];
		foreach ( $samples as $s ) {
			$wpdb->insert( $t['skill_listings'], array_merge( [
				'status' => 'active',  // pre-approved samples
				'currency' => 'GBP',
				'identity' => 'sample',
			], $s ) );
		}
	}

	private static function seed_duas() {
		global $wpdb;
		$t = self::tables();
		$path = LA_DIR . 'inc/data/duas-seed.json';
		if ( ! file_exists( $path ) ) return;
		$json = file_get_contents( $path );
		$rows = json_decode( $json, true );
		if ( ! is_array( $rows ) ) return;

		$order = 0;
		foreach ( $rows as $r ) {
			$slug = sanitize_title( $r['slug'] ?? '' );
			if ( ! $slug ) continue;
			$data = [
				'slug'           => $slug,
				'category'       => sanitize_text_field( $r['category'] ?? 'general' ),
				'title'          => sanitize_text_field( $r['title'] ?? '' ),
				'arabic'         => wp_kses_post( $r['arabic'] ?? '' ),
				'transliteration'=> wp_kses_post( $r['transliteration'] ?? '' ),
				'meaning'        => wp_kses_post( $r['meaning'] ?? '' ),
				'source'         => sanitize_text_field( $r['source'] ?? '' ),
				'repeat_count'   => max( 1, (int) ( $r['repeat'] ?? 1 ) ),
				'sort_order'     => $order++,
			];
			$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['duas']} WHERE slug = %s", $slug ) );
			if ( $existing ) {
				$wpdb->update( $t['duas'], $data, [ 'id' => $existing ] );
			} else {
				$wpdb->insert( $t['duas'], $data );
			}
		}
	}

	private static function seed_dhikr() {
		global $wpdb;
		$t = self::tables();

		$dhikr = [
			[
				'type' => 'dhikr',
				'title' => 'The Testimony',
				'arabic_text' => 'لَا إِلٰهَ إِلَّا اللَّٰه',
				'transliteration' => 'Lā ilāha illa-llāh',
				'translation' => 'There is no god but Allah',
				'body' => 'The testimony of oneness. The first words a Muslim utters, the last a Muslim hopes to say.',
				'display_order' => 1,
			],
			[
				'type' => 'dhikr',
				'title' => 'Praise',
				'arabic_text' => 'ٱلْحَمْدُ لِلَّٰه',
				'transliteration' => 'Alḥamdulillāh',
				'translation' => 'All praise is for Allah',
				'body' => 'Gratitude opens the heart. Every breath, every blessing — all from Him.',
				'display_order' => 2,
			],
			[
				'type' => 'dhikr',
				'title' => 'Seeking Forgiveness',
				'arabic_text' => 'أَسْتَغْفِرُ ٱللَّٰه',
				'transliteration' => 'Astaghfirullāh',
				'translation' => 'I seek forgiveness from Allah',
				'body' => 'Return to your Lord. He is the Most Merciful, the Especially Merciful.',
				'display_order' => 3,
			],
			[
				'type' => 'dhikr',
				'title' => 'Glorification',
				'arabic_text' => 'سُبْحَانَ ٱللَّٰه',
				'transliteration' => 'Subḥānallāh',
				'translation' => 'Glory be to Allah',
				'body' => 'Free from every imperfection. The heavens and the earth glorify Him without pause.',
				'display_order' => 4,
			],
			[
				'type' => 'dhikr',
				'title' => 'Greatness',
				'arabic_text' => 'ٱللَّٰهُ أَكْبَر',
				'transliteration' => 'Allāhu akbar',
				'translation' => 'Allah is the Greatest',
				'body' => 'Greater than every fear, every worry, every distraction. Greater than this world.',
				'display_order' => 5,
			],
		];
		foreach ( $dhikr as $row ) {
			$wpdb->insert( $t['content'], $row );
		}

		$affirmations = [
			[ 'type' => 'affirmation', 'body' => 'I love my Lord.', 'display_order' => 1 ],
			[ 'type' => 'affirmation', 'body' => 'Bring yourself closer to Allah.', 'display_order' => 2 ],
			[ 'type' => 'affirmation', 'body' => 'My heart finds rest in His remembrance.', 'display_order' => 3 ],
			[ 'type' => 'affirmation', 'body' => 'He is closer to me than my jugular vein.', 'display_order' => 4 ],
		];
		foreach ( $affirmations as $row ) {
			$wpdb->insert( $t['content'], $row );
		}
	}

	private static function seed_mosque() {
		global $wpdb;
		$t = self::tables();
		$wpdb->insert( $t['mosques'], [
			'slug' => 'central-jamia-masjid-birmingham',
			'name' => 'Central Jamia Masjid Ghamkol Sharif',
			'address' => 'Golden Hillock Road',
			'city' => 'Birmingham',
			'country' => 'United Kingdom',
			'latitude' => 52.4567,
			'longitude' => -1.8606,
			'branding_color_primary' => '#ED1C6C',
		] );
	}

	private static function seed_scholars() {
		global $wpdb;
		$t = self::tables();
		// CURATED ROSTER — only globally recognised English-speaking scholars
		// (knowledge / du'aat / lecturers) AND qaris of the Haramain
		// and beyond (recitation). No nasheed artists, no entertainment
		// channels. The yt-dlp ingest pulls their /shorts and /videos
		// feeds on the la_youtube_sync cron (every 6h). The purge step at
		// the end of this seed removes any prior nasheed seeds from the DB.
		$scholars = [
			// ─── SCHOLARS / DU'AAT (reminders, lectures) ──────────────────
			[ 'username' => 'muftimenk',     'display_name' => 'Mufti Menk',
			  'bio' => 'Dr Mufti Ismail ibn Musa Menk — Grand Mufti of Zimbabwe, globally renowned du\'aat.',
			  'account_type' => 'verified',
			  'source_url' => 'https://www.youtube.com/@muftimenk',
			  'default_content_type' => 'reminder', 'associated_charity' => 'ArRahma' ],
			[ 'username' => 'omarsuleiman',  'display_name' => 'Sh. Omar Suleiman',
			  'bio' => 'Imam and President of Yaqeen Institute for Islamic Research.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@OmarSuleimanOfficial',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'yaqeen',        'display_name' => 'Yaqeen Institute',
			  'bio' => 'Research institute producing scholarly Islamic content with vetted scholars.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@yaqeeninstitute',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'yasirqadhi',    'display_name' => 'Sh. Yasir Qadhi',
			  'bio' => 'Dean of the Islamic Seminary of America; PhD Islamic Studies from Yale.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@yasirqadhi',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'noumanalikhan', 'display_name' => 'Ust. Nouman Ali Khan',
			  'bio' => 'Founder of Bayyinah Institute — focused on the linguistic miracle of the Qur\'an.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@BayyinahTV',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'bilalphilips',  'display_name' => 'Dr Bilal Philips',
			  'bio' => 'Founder of the Islamic Online University (IOU), comparative religion scholar.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@DrBilalPhilipsOfficial',
			  'default_content_type' => 'lecture' ],
			[ 'username' => 'hamzayusuf',    'display_name' => 'Sh. Hamza Yusuf',
			  'bio' => 'Co-founder and President of Zaytuna College — first Muslim liberal arts college in the US.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@ZaytunaCollege',
			  'default_content_type' => 'lecture' ],
			[ 'username' => 'bilalassad',    'display_name' => 'Sh. Bilal Assad',
			  'bio' => 'Australian-Lebanese imam, widely respected for sincere heart-focused reminders.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@ShaykhBilalAssad',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'assimalhakeem', 'display_name' => 'Sh. Assim Al-Hakeem',
			  'bio' => 'Saudi-based scholar; widely-followed Q&A on fiqh + aqeedah.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@assimalhakeem',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'timhumble',     'display_name' => 'Ust. Tim Humble',
			  'bio' => 'British scholar specialised in the sciences of Qur\'an and tafsir.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@MTHumble',
			  'default_content_type' => 'lecture' ],
			[ 'username' => 'yusufestes',    'display_name' => 'Sh. Yusuf Estes',
			  'bio' => 'American convert and former Christian minister; gentle reminders on tawheed.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@SheikhYusufEstes',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'aliibnshaykh',  'display_name' => 'Sh. Ali Hammuda',
			  'bio' => 'British imam at Al-Manar Mosque, Cardiff; rich tafsir-driven reminders.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@AliHammuda',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'akrnadwi',      'display_name' => 'Sh. Akram Nadwi',
			  'bio' => 'Oxford-based scholar of hadith; founder of the Cambridge Islamic College.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@CambridgeIslamicCollege',
			  'default_content_type' => 'lecture' ],
			[ 'username' => 'tahirwyatt',    'display_name' => 'Dr Tahir Wyatt',
			  'bio' => 'Former teacher in al-Masjid an-Nabawi, Madinah; founder of UMA Foundation.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@DrTahirWyatt',
			  'default_content_type' => 'lecture' ],
			[ 'username' => 'yasirbirjas',   'display_name' => 'Sh. Yaser Birjas',
			  'bio' => 'Resident scholar at Valley Ranch Islamic Center, Texas; AlMaghrib senior instructor.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@AlMaghribInstitute',
			  'default_content_type' => 'lecture' ],
			[ 'username' => 'sajidumar',     'display_name' => 'Dr Sajid Umar',
			  'bio' => 'Senior researcher at AlMaghrib Institute; international du\'aat.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@DrSajidUmar',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'yushaeevans',   'display_name' => 'Yusha Evans',
			  'bio' => 'American da\'ee specialising in reverts to Islam and inter-faith dialogue.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@YushaEvans',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'khalidyasin',   'display_name' => 'Sh. Khalid Yasin',
			  'bio' => 'American da\'ee delivering tawheed-focused street da\'wah for 40+ years.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@ShaykhKhalidYasin',
			  'default_content_type' => 'lecture' ],

			// ─── RECITERS / QARIS (qirat) ──────────────────────────────────
			[ 'username' => 'misharyalafasy','display_name' => 'Mishary Rashed Alafasy',
			  'bio' => 'Kuwaiti imam of Masjid al-Kabeer; one of the most-loved reciters worldwide.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@AlafasyChannel',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'sudais',        'display_name' => 'Sh. Abdul Rahman Al-Sudais',
			  'bio' => 'Imam of Masjid al-Haram, Makkah; the most recognised voice of the Haram.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@SheikhAbdurRahmanAsSudais',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'saadalghamdi',  'display_name' => 'Sh. Saad Al-Ghamdi',
			  'bio' => 'Renowned Saudi qari known for emotional, contemplative recitation.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@saadalghamdi',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'maheralmuaiqly','display_name' => 'Sh. Maher Al-Mu\'aiqly',
			  'bio' => 'Imam of Masjid al-Haram; loved for clear, measured recitation.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@MaherAlMueaqlyOfficial',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'yasseraldosari','display_name' => 'Sh. Yasser Al-Dosari',
			  'bio' => 'Imam of Masjid al-Haram; widely loved for his recitation of Surah ar-Rahman.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@YasserAlDosariOfficial',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'idrisabkar',    'display_name' => 'Sh. Idris Abkar',
			  'bio' => 'Saudi qari known for soulful, slow recitation suited to contemplation.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@idrisabkar',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'hanirifai',     'display_name' => 'Sh. Hani ar-Rifai',
			  'bio' => 'Imam of Masjid al-Hussein, Jeddah; deeply emotive recitation.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@HaniRifaiOfficial',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'shuraim',       'display_name' => 'Sh. Saud Al-Shuraim',
			  'bio' => 'Imam of Masjid al-Haram, Makkah; resonant, melodious style.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@SaudAlShuraimOfficial',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'alijaber',      'display_name' => 'Sh. Ali Jaber',
			  'bio' => 'Saudi qari (1955-2005); celebrated for sublime, classical recitation.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/results?search_query=ali+jaber+quran',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'aljuhani',      'display_name' => 'Sh. Abdullah Awad al-Juhani',
			  'bio' => 'Imam of Masjid al-Haram; loved for measured, devotional recitation.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@aljuhani',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'fatehseferagic','display_name' => 'Sh. Fatih Seferagić',
			  'bio' => 'Bosnian-American qari and huffaz; raised in Madinah, widely loved in the West.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@FatihSeferagicQuran',
			  'default_content_type' => 'qirat' ],
			[ 'username' => 'muhammadhoblos','display_name' => 'Sh. Muhammad Hoblos',
			  'bio' => 'Australian reminder-style speaker; sincere, heart-piercing delivery.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@hoblosvision',
			  'default_content_type' => 'reminder' ],
			[ 'username' => 'farraz',        'display_name' => 'Hafiz Fariz Asad',
			  'bio' => 'Young qari known for emotional Tarawih recitations.',
			  'account_type' => 'curated',
			  'source_url' => 'https://www.youtube.com/@FarizAsad',
			  'default_content_type' => 'qirat' ],

			// Nasheed artists intentionally NOT included — by user direction,
			// the feed surfaces only scholars and qaris (knowledge + recitation).
			// The cleanup below (purge_nasheed_artists) removes any that were
			// previously seeded so the feed stays on-mission.
		];
		foreach ( $scholars as $row ) {
			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t['scholars']} WHERE username = %s",
				$row['username']
			) );
			if ( $existing ) {
				// Update to keep production in sync with seed if we add/change channel URLs
				$wpdb->update( $t['scholars'], $row, [ 'id' => $existing ] );
			} else {
				$wpdb->insert( $t['scholars'], $row );
			}
		}

		// Purge nasheed artists that were seeded in earlier DB versions.
		// User direction: feed surfaces ONLY scholars + qaris (knowledge +
		// recitation). Music/nasheed content does NOT belong on the feed.
		self::purge_nasheed_artists();
	}

	/**
	 * Hard-remove any scholar rows tagged as nasheed artists, plus all
	 * their feed_posts. Run by seed_scholars() so re-applying the seed
	 * keeps production aligned with the curated roster.
	 */
	private static function purge_nasheed_artists() {
		global $wpdb;
		$t = self::tables();
		$nasheed_usernames = [
			'samiyusuf', 'maherzain', 'bukhatir',
			'mesutkurtis', 'harrisj', 'yusufislam',
		];
		foreach ( $nasheed_usernames as $u ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$t['scholars']} WHERE username = %s",
				$u
			) );
			if ( ! $id ) continue;
			// Drop their feed_posts first (foreign-key-safe)
			$wpdb->delete( $t['feed_posts'], [ 'scholar_id' => $id ] );
			// Then the scholar row
			$wpdb->delete( $t['scholars'], [ 'id' => $id ] );
		}
		// Belt-and-braces: also kill any scholar with default_content_type='nasheed'
		// in case other usernames were added through the admin.
		$nasheed_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$t['scholars']} WHERE default_content_type = %s",
			'nasheed'
		) );
		foreach ( $nasheed_ids as $sid ) {
			$wpdb->delete( $t['feed_posts'], [ 'scholar_id' => (int) $sid ] );
			$wpdb->delete( $t['scholars'], [ 'id' => (int) $sid ] );
		}
		// Also strip any orphan nasheed-typed feed posts left over
		$wpdb->delete( $t['feed_posts'], [ 'type' => 'nasheed' ] );
	}

	private static function seed_feed_posts() {
		global $wpdb;
		$t = self::tables();

		$menk     = (int) $wpdb->get_var( "SELECT id FROM {$t['scholars']} WHERE username='muftimenk'" );
		$omar     = (int) $wpdb->get_var( "SELECT id FROM {$t['scholars']} WHERE username='omarsuleiman'" );
		$bilal    = (int) $wpdb->get_var( "SELECT id FROM {$t['scholars']} WHERE username='bilalassad'" );

		$posts = [
			[
				'scholar_id' => $menk,
				'type' => 'reminder',
				'title' => 'Trust in Allah',
				'caption' => 'When the world feels heavy, return to Him. He never leaves those who seek Him.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@muftimenk',
				'duration_sec' => 60,
				'display_order' => 1,
			],
			[
				'scholar_id' => $menk,
				'type' => 'short',
				'title' => 'The dua you forgot',
				'caption' => 'A short reminder about a powerful supplication most of us neglect.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@muftimenk',
				'duration_sec' => 90,
				'display_order' => 2,
			],
			[
				'scholar_id' => $omar,
				'type' => 'lecture',
				'title' => 'The mercy of Allah',
				'caption' => 'His mercy precedes His wrath. Reflect.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@OmarSuleimanOfficial',
				'duration_sec' => 180,
				'display_order' => 3,
			],
			[
				'scholar_id' => $bilal,
				'type' => 'reminder',
				'title' => 'Your heart is a mirror',
				'caption' => 'Sins darken it. Dhikr polishes it. Choose carefully what touches it.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@BilalAssadOfficial',
				'duration_sec' => 75,
				'display_order' => 4,
			],
			[
				'scholar_id' => $menk,
				'type' => 'reminder',
				'title' => 'Reflect on the Qur\'an',
				'caption' => 'A single ayah, contemplated deeply, can change the course of a life.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@muftimenk',
				'duration_sec' => 120,
				'display_order' => 5,
			],
			[
				'scholar_id' => $omar,
				'type' => 'short',
				'title' => 'When you feel alone',
				'caption' => 'You are never truly alone. He hears every whisper of your heart.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@OmarSuleimanOfficial',
				'duration_sec' => 45,
				'display_order' => 6,
			],
			[
				'scholar_id' => $bilal,
				'type' => 'lecture',
				'title' => 'The signs around you',
				'caption' => 'Allah swt has placed signs everywhere — in the night, the day, the leaves, your own self.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@BilalAssadOfficial',
				'duration_sec' => 240,
				'display_order' => 7,
			],
			[
				'scholar_id' => $menk,
				'type' => 'qirat',
				'title' => 'Surah Al-Fātiḥah',
				'caption' => 'The opening — recited in every prayer, the heart of the Qur\'an.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@muftimenk',
				'duration_sec' => 90,
				'display_order' => 8,
			],
			[
				'scholar_id' => $omar,
				'type' => 'reminder',
				'title' => 'Tomorrow is not promised',
				'caption' => 'Make today count. Pray your prayers. Forgive who hurt you. Tell those you love that you love them.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@OmarSuleimanOfficial',
				'duration_sec' => 60,
				'display_order' => 9,
			],
			[
				'scholar_id' => $bilal,
				'type' => 'short',
				'title' => 'The sweetness of faith',
				'caption' => 'There is a sweetness in iman that nothing else in this world can match.',
				'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
				'original_source_url' => 'https://www.youtube.com/@BilalAssadOfficial',
				'duration_sec' => 70,
				'display_order' => 10,
			],
		];

		foreach ( $posts as $row ) {
			$wpdb->insert( $t['feed_posts'], $row );
		}
	}
}
