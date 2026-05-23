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
			// Re-seed scholars + duas on every DB version bump (both idempotent).
			// Keeps the curated lists in sync with the codebase as we add content.
			self::seed_scholars();
			self::seed_duas();
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
			cta_label varchar(80) DEFAULT NULL,
			cta_url varchar(500) DEFAULT NULL,
			tag varchar(40) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY mosque_id (mosque_id),
			KEY starts_at (starts_at)
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
			sort_order int NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY category (category)
		) $charset_collate;" );
	}

	public static function seed() {
		self::seed_dhikr();
		self::seed_mosque();
		self::seed_scholars();
		self::seed_feed_posts();
		self::seed_duas();
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
		$scholars = [
			// REMINDERS — short bite-size shorts that work via /shorts tab
			[
				'username' => 'muftimenk',
				'display_name' => 'Mufti Menk',
				'bio' => 'Dr Mufti Ismail Menk — globally renowned Islamic scholar from Zimbabwe.',
				'account_type' => 'verified',
				'source_url' => 'https://www.youtube.com/@muftimenk',
				'default_content_type' => 'reminder',
				'associated_charity' => 'ArRahma',
			],
			[
				'username' => 'yaqeen',
				'display_name' => 'Yaqeen Institute',
				'bio' => 'Yaqeen Institute for Islamic Research — Imam Omar Suleiman & team.',
				'account_type' => 'curated',
				'source_url' => 'https://www.youtube.com/@yaqeeninstitute',
				'default_content_type' => 'reminder',
			],
			[
				'username' => 'yasirqadhi',
				'display_name' => 'Shaykh Yasir Qadhi',
				'bio' => 'American Muslim scholar — Dean of the Islamic Seminary of America.',
				'account_type' => 'curated',
				'source_url' => 'https://www.youtube.com/@yasirqadhi',
				'default_content_type' => 'reminder',
			],

			// NASHEEDS — vocal music, falls back to /videos tab for longer-form
			[
				'username' => 'samiyusuf',
				'display_name' => 'Sami Yusuf',
				'bio' => 'British-Azerbaijani composer and artist — pioneering nasheed and Islamic spiritual music.',
				'account_type' => 'curated',
				'source_url' => 'https://www.youtube.com/@samiyusufofficial',
				'default_content_type' => 'nasheed',
			],
			[
				'username' => 'maherzain',
				'display_name' => 'Maher Zain',
				'bio' => 'Swedish-Lebanese R&B / nasheed artist — uplifting Islamic music for the world.',
				'account_type' => 'curated',
				'source_url' => 'https://www.youtube.com/@maherzainofficial',
				'default_content_type' => 'nasheed',
			],

			// DHIKR / QIRAA — Quran reciters with calming voices
			[
				'username' => 'misharyalafasy',
				'display_name' => 'Mishary Rashed Alafasy',
				'bio' => 'Kuwaiti Imam and renowned Qur\'an reciter — voice of Masjid al-Kabeer.',
				'account_type' => 'curated',
				'source_url' => 'https://www.youtube.com/@AlafasyChannel',
				'default_content_type' => 'qirat',
			],
			[
				'username' => 'bukhatir',
				'display_name' => 'Ahmed Bukhatir',
				'bio' => 'Emirati nasheed singer and businessman — pioneer of contemporary Islamic vocal music.',
				'account_type' => 'curated',
				'source_url' => 'https://www.youtube.com/@AhmedBukhatir',
				'default_content_type' => 'dhikr',
			],
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
