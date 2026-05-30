<?php
/**
 * Wave 90: Mainstream allowlist for the curated Shorts feed.
 *
 * Why this exists:
 *   User feedback after Wave 87's halaltube-import: the catalog had
 *   too much sufi qawwali / peer-saab devotional content + low-effort
 *   anonymous Islamic-reminder repost channels. The product positioning
 *   is mainstream Sunni: Quran recitation + well-known English speakers
 *   + production-quality institutional channels (OnePath, Yaqeen, Qalam,
 *   Bayyinah etc).
 *
 *   This file is the single source of truth for which channels are
 *   considered curated-mainstream. The admin "Restrict to mainstream"
 *   action hides every scholar NOT in this list, and inserts allowlist
 *   entries that aren't already in the DB.
 *
 *   Editable in-place — adding/removing a channel is a code change with
 *   PR review, not an admin-UI toggle. That's intentional: keeps the
 *   bar high.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LA_Curation {

	/**
	 * Mainstream allowlist. Keys are YouTube @handles (without the @),
	 * values describe the scholar/channel for seeding new rows.
	 *
	 * Categories represented:
	 *   - Quran reciters (qari) — top of the field, classical recitation
	 *   - English-language scholars — globally recognised, big production
	 *   - Institutional channels — Yaqeen, Qalam, Bayyinah, etc.
	 *   - UK-focused content networks — Eman Channel, Islam Channel, etc.
	 *
	 * Deliberately EXCLUDED:
	 *   - Sufi qawwali / devotional music channels
	 *   - Pakistani Sufi peer-saab content
	 *   - Anonymous "Islamic Reminders HD" / "Soft Hearted" reposters
	 *   - Polemic sectarian channels
	 *   - Channels with < 50k subscribers that aren't institutional
	 *
	 * To add a channel: append below + run "Restrict to mainstream".
	 */
	const ALLOWLIST = [
		// ── ENGLISH SPEAKERS — globally known ───────────────────────
		'muftimenkofficial'    => [ 'name' => 'Mufti Menk',                   'type' => 'reminder', 'bio' => 'Dr Mufti Ismail ibn Musa Menk — Grand Mufti of Zimbabwe' ],
		'OmarSuleimanOfficial' => [ 'name' => 'Sh. Omar Suleiman',            'type' => 'reminder', 'bio' => 'Founder of Yaqeen Institute, Imam at Valley Ranch' ],
		'BilalAssadOfficial'   => [ 'name' => 'Sh. Bilal Assad',              'type' => 'reminder', 'bio' => 'Australian scholar, popular YouTube speaker' ],
		'DrBilalPhilips'       => [ 'name' => 'Dr Bilal Philips',             'type' => 'lecture',  'bio' => 'Founder, International Open University' ],
		'ZaytunaCollege'       => [ 'name' => 'Zaytuna College',              'type' => 'lecture',  'bio' => 'America\'s first Muslim liberal arts college (Hamza Yusuf, Zaid Shakir)' ],
		'BayyinahInstitute'    => [ 'name' => 'Bayyinah Institute',           'type' => 'lecture',  'bio' => 'Ustadh Nouman Ali Khan\'s Quran-focused institute' ],
		'QalamInstitute'       => [ 'name' => 'Qalam Institute',              'type' => 'lecture',  'bio' => 'Abdul Nasir Jangda, Mikaeel Smith, Hussain Kamani' ],
		'AlMaghribInstitute'   => [ 'name' => 'AlMaghrib Institute',          'type' => 'lecture',  'bio' => 'Yasir Qadhi, Yaser Birjas, Said Rageah' ],
		'YaqeenInstitute'      => [ 'name' => 'Yaqeen Institute',             'type' => 'lecture',  'bio' => 'Islamic research + Omar Suleiman\'s home channel' ],
		'OnePathNetwork'       => [ 'name' => 'OnePath Network',              'type' => 'reminder', 'bio' => 'Australian Islamic media — high-production reminders' ],
		'SapienceInstitute'    => [ 'name' => 'Sapience Institute',           'type' => 'lecture',  'bio' => 'Hamza Tzortzis — dawah + Islamic philosophy' ],
		'iERAofficial'         => [ 'name' => 'iERA',                         'type' => 'lecture',  'bio' => 'Islamic Education and Research Academy' ],
		'CambridgeMuslimCollege' => [ 'name' => 'Cambridge Muslim College',   'type' => 'lecture',  'bio' => 'Abdal Hakim Murad (Tim Winter)' ],
		'AlSalamInstitute'     => [ 'name' => 'Al-Salam Institute',           'type' => 'lecture',  'bio' => 'Sh. Mohammad Akram Nadwi' ],
		'ThinkingMuslim'       => [ 'name' => 'The Thinking Muslim',          'type' => 'lecture',  'bio' => 'Muslim affairs podcast — Jasser Auda, Salman Sayyid, etc.' ],
		'SeekersGuidance'      => [ 'name' => 'SeekersGuidance',              'type' => 'lecture',  'bio' => 'Sh. Faraz Rabbani — classical Islamic studies' ],
		'TheDeenShowTV'        => [ 'name' => 'The Deen Show',                'type' => 'lecture',  'bio' => 'Eddie Redzovic — long-running dawah show' ],
		'DiscoverIslamUK'      => [ 'name' => 'Discover Islam UK',            'type' => 'lecture',  'bio' => 'UK dawah content' ],
		'MathabahOfficial'     => [ 'name' => 'Mathabah Foundation',          'type' => 'lecture',  'bio' => 'Canadian Islamic media' ],
		'EmanChannel'          => [ 'name' => 'Eman Channel',                 'type' => 'reminder', 'bio' => 'UK Islamic broadcaster' ],
		'IslamChannel'         => [ 'name' => 'Islam Channel',                'type' => 'lecture',  'bio' => 'UK Islamic TV channel — established mainstream broadcaster' ],
		'BritishIslamicTV'     => [ 'name' => 'British Islamic TV',           'type' => 'lecture',  'bio' => 'UK Islamic broadcaster' ],
		'assimalhakeem'        => [ 'name' => 'Sh. Assim Al-Hakeem',          'type' => 'lecture',  'bio' => 'Saudi-based English-speaking scholar' ],
		'QuranWeekly'          => [ 'name' => 'Quran Weekly',                 'type' => 'qirat',    'bio' => 'Tafsir + reflection on Quranic verses' ],
		'FreeQuranEducation'   => [ 'name' => 'Free Quran Education',         'type' => 'qirat',    'bio' => 'Free tajweed + Quran memorisation lessons' ],
		'Smile2Jannah'         => [ 'name' => 'Smile2Jannah',                 'type' => 'reminder', 'bio' => 'UK Muslim content creator' ],
		'TomFacchine'          => [ 'name' => 'Sh. Tom Facchine',             'type' => 'reminder', 'bio' => 'Imam at Utica Masjid, Yaqeen scholar' ],
		'AbdelRahmanMurphy'    => [ 'name' => 'Abdelrahman Murphy',           'type' => 'reminder', 'bio' => 'Imam, founder of Roots Community Tennessee' ],
		'Belal-Khan-Official'  => [ 'name' => 'Sh. Belal Khan',               'type' => 'reminder', 'bio' => 'Imam at Islamic Foundation North' ],
		'AmmarAlShukry'        => [ 'name' => 'Imam Ammar AlShukry',          'type' => 'reminder', 'bio' => 'AlMaghrib instructor, poetic delivery' ],
		'YasminMogahed'        => [ 'name' => 'Ust. Yasmin Mogahed',          'type' => 'reminder', 'bio' => 'Author, speaker on emotional + spiritual purification' ],
		'NavaidAziz'           => [ 'name' => 'Sh. Navaid Aziz',              'type' => 'reminder', 'bio' => 'Canadian-born scholar, comparative religion expert' ],
		'YasirQadhi'           => [ 'name' => 'Sh. Yasir Qadhi',              'type' => 'lecture',  'bio' => 'Resident scholar at EPIC Masjid, Sirah lectures' ],
		'EPICMasjid'           => [ 'name' => 'EPIC Masjid',                  'type' => 'lecture',  'bio' => 'Yasir Qadhi\'s mosque channel — Sirah, fiqh, tafsir' ],
		'Cliffe-Knechtle'      => [ 'name' => 'Mohammed Hijab',               'type' => 'lecture',  'bio' => 'British Muslim apologetics + comparative religion' ],
		'MuslimCentral'        => [ 'name' => 'Muslim Central',               'type' => 'lecture',  'bio' => 'Lecture aggregator for top Muslim speakers' ],

		// ── QURAN RECITERS (qaris) — top of the field ───────────────
		'AlafasyChannel'       => [ 'name' => 'Mishary Rashed Alafasy',       'type' => 'qirat',    'bio' => 'Kuwaiti qari — globally beloved recitation' ],
		'SaudAlShuraim'        => [ 'name' => 'Sh. Saud Al-Shuraim',          'type' => 'qirat',    'bio' => 'Imam of Makkah' ],
		'AbdulRahmanAlSudais'  => [ 'name' => 'Sh. Abdul Rahman Al-Sudais',   'type' => 'qirat',    'bio' => 'Imam of Makkah — voice of Tarawih' ],
		'MaherAlMueaqlyOfficial' => [ 'name' => 'Sh. Maher Al-Mu\'aiqly',      'type' => 'qirat',    'bio' => 'Imam of Makkah' ],
		'YasserAlDosari'       => [ 'name' => 'Sh. Yasser Al-Dosari',         'type' => 'qirat',    'bio' => 'Imam of Makkah' ],
		'saadalghamdi'         => [ 'name' => 'Sh. Saad Al-Ghamdi',           'type' => 'qirat',    'bio' => 'Renowned Saudi reciter' ],
		'salahbukhatir'        => [ 'name' => 'Sh. Salah Bukhatir',           'type' => 'qirat',    'bio' => 'UAE qari' ],
		'idrisabkar'           => [ 'name' => 'Sh. Idris Abkar',              'type' => 'qirat',    'bio' => 'Saudi reciter' ],
		'omarhishamalarabi'    => [ 'name' => 'Sh. Omar Hisham Al-Arabi',     'type' => 'qirat',    'bio' => 'Egyptian-American qari with viral recitations' ],
		'FatihSeferagicQuran'  => [ 'name' => 'Sh. Fatih Seferagić',          'type' => 'qirat',    'bio' => 'Bosnian-American qari' ],
		'maghamsi'             => [ 'name' => 'Sh. Saleh al-Maghamsi',        'type' => 'qirat',    'bio' => 'Imam of Quba Mosque, Madinah' ],
		'HaniRifaiOfficial'    => [ 'name' => 'Sh. Hani ar-Rifai',            'type' => 'qirat',    'bio' => 'Saudi reciter' ],
		'aljuhani'             => [ 'name' => 'Sh. Abdullah al-Juhani',       'type' => 'qirat',    'bio' => 'Imam of Makkah' ],
		'IslamSobhi'           => [ 'name' => 'Sh. Islam Sobhi',              'type' => 'qirat',    'bio' => 'Egyptian qari, viral on social media' ],
		'AhmadAjamy'           => [ 'name' => 'Sh. Ahmad Al-Ajmi',            'type' => 'qirat',    'bio' => 'Kuwaiti qari' ],
		'BandarBaleelah'       => [ 'name' => 'Sh. Bandar Baleelah',          'type' => 'qirat',    'bio' => 'Saudi qari with smooth recitation' ],
		'NasserAlQatami'       => [ 'name' => 'Sh. Nasser Al-Qatami',         'type' => 'qirat',    'bio' => 'Saudi reciter' ],
	];

	/**
	 * Returns the allowlist normalised so case-insensitive comparisons work.
	 * Map: lowercase handle => meta array (with 'handle' added).
	 */
	public static function allowlist_normalised() : array {
		$out = [];
		foreach ( self::ALLOWLIST as $handle => $meta ) {
			$meta['handle'] = $handle;
			$out[ strtolower( $handle ) ] = $meta;
		}
		return $out;
	}

	/**
	 * Applies the allowlist to the current scholars table:
	 *   1. Hides every scholar NOT in the allowlist (status='hidden')
	 *   2. Re-activates rows that exist + match the allowlist (status='active')
	 *   3. Inserts allowlist entries we don't have yet, with shorts_only=1
	 *      so the next cron tick syncs Shorts from them
	 *
	 * Returns a counters array suitable for streaming back to the admin.
	 */
	public static function apply() : array {
		global $wpdb;
		$t = LA_DB::tables();
		$allow = self::allowlist_normalised();

		// Step 1 — hide everything. Cheaper than per-row conditional logic.
		$wpdb->query( "UPDATE {$t['scholars']} SET status = 'hidden'" );

		// Step 2 — fetch existing usernames so we know which allowlist entries
		// to UPDATE vs INSERT. The username column stores the @handle (without
		// the @) and is case-insensitive in our schema's collation.
		$existing_rows = $wpdb->get_results(
			"SELECT id, username, source_url FROM {$t['scholars']}", OBJECT_K
		);
		// Build case-insensitive map from username → row.
		$by_username = [];
		foreach ( $existing_rows as $row ) {
			$by_username[ strtolower( (string) $row->username ) ] = $row;
		}
		// Also build a fallback map by @handle parsed out of source_url, since
		// some seed rows have username = display-slug instead of handle.
		$by_handle = [];
		foreach ( $existing_rows as $row ) {
			if ( preg_match( '#/@([A-Za-z0-9._-]+)#', (string) $row->source_url, $m ) ) {
				$by_handle[ strtolower( $m[1] ) ] = $row;
			}
		}

		$stats = [ 'allow' => count( $allow ), 'unhid' => 0, 'inserted' => 0, 'still_hidden' => 0 ];

		foreach ( $allow as $key => $meta ) {
			$row = $by_username[ $key ] ?? $by_handle[ $key ] ?? null;
			if ( $row ) {
				// UPDATE existing — un-hide + force shorts_only + sync the
				// source_url to the canonical @handle form so future audits
				// resolve it cleanly.
				$wpdb->update(
					$t['scholars'],
					[
						'status'       => 'active',
						'shorts_only'  => 1,
						'source_url'   => 'https://www.youtube.com/@' . $meta['handle'],
						'display_name' => $meta['name'],
						'default_content_type' => $meta['type'],
					],
					[ 'id' => (int) $row->id ]
				);
				$stats['unhid']++;
			} else {
				// INSERT new row. youtube_channel_id is left null — the next
				// sync_scholar call will resolve it via the API (Wave 87f).
				$wpdb->insert( $t['scholars'], [
					'username'             => $meta['handle'],
					'display_name'         => $meta['name'],
					'account_type'         => 'curated',
					'source_url'           => 'https://www.youtube.com/@' . $meta['handle'],
					'bio'                  => $meta['bio'],
					'default_content_type' => $meta['type'],
					'status'               => 'active',
					'shorts_only'          => 1,
				] );
				$stats['inserted']++;
			}
		}

		// Step 3 — final count of rows still marked hidden so the admin can
		// see at-a-glance how much the catalog shrank.
		$stats['still_hidden'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$t['scholars']} WHERE status = 'hidden'"
		);

		return $stats;
	}
}
