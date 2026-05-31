<?php
/**
 * Shared dhikr scene library (Wave 95).
 *
 * Originally lived inside page-dhikr.php for Solitude only. Pulled out
 * so Pulse (and any future dhikr mode) can reuse the same YouTube
 * ambient backdrops without duplicating the data definition.
 *
 * Usage:
 *   require_once get_template_directory() . '/inc/dhikr-scenes-data.php';
 *   // now $la_scenes + $la_scene_genres are in scope
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! isset( $la_scenes ) ) {
	$la_scenes = [
		// ─── NATURAL SOUNDS (no music) ───────────────────────────
		[ 'key' => 'ocean',   'genre' => 'nature',  'emoji' => '🌊', 'label' => 'Ocean',
		  'desc' => 'Waves only — no music',     'video' => 'NJXzcQJi_A8' ],
		[ 'key' => 'forest',  'genre' => 'nature',  'emoji' => '🌿', 'label' => 'Forest',
		  'desc' => 'Birds at dawn — no music',  'video' => 'BHACKCNDMW8' ],
		[ 'key' => 'none',    'genre' => 'silence', 'emoji' => '🌑', 'label' => 'Stillness',
		  'desc' => 'Pure dark, no sound',       'video' => '' ],

		// ─── AMBIENT MUSIC ───────────────────────────────────────
		[ 'key' => 'cosmos',  'genre' => 'ambient', 'emoji' => '✨', 'label' => 'Cosmos',
		  'desc' => 'Deep space — Hubble + ambient music', 'video' => 'Y_plhk1FUQA' ],
		[ 'key' => 'desert',  'genre' => 'ambient', 'emoji' => '🌅', 'label' => 'Sahara',
		  'desc' => 'Dunes at first light — cinematic score', 'video' => 'gFmDx9oj3DU' ],

		// ─── NASHEEDS (duff + vocals — no pitched instruments) ───
		[ 'key' => 'duff_astaghfirullah', 'genre' => 'nasheed', 'emoji' => '🥁',
		  'label' => 'Astaghfirullah', 'desc' => 'Mevlan Kurtishi — duff + vocal',
		  'video' => 'r4YrbaVbqPk' ],
		[ 'key' => 'duff_takbir',         'genre' => 'nasheed', 'emoji' => '🪘',
		  'label' => 'Allahu Akbar', 'desc' => 'Takbir nasheed — duff drumming',
		  'video' => 'n9oLl0HjV3Y' ],
		[ 'key' => 'duff_salawat',        'genre' => 'nasheed', 'emoji' => '🌙',
		  'label' => 'Salawat', 'desc' => 'Omar Hisham — 1hr salawat loop',
		  'video' => 'maHPe1byTfk' ],
		[ 'key' => 'kaaba',               'genre' => 'nasheed', 'emoji' => '🕋',
		  'label' => 'Haram', 'desc' => 'Live tawaf from Makkah',
		  'video' => 'bNY8a2BB5Gc' ],
	];
}

if ( ! isset( $la_scene_genres ) ) {
	$la_scene_genres = [
		'nature'  => [ 'label' => 'Natural sounds', 'sub' => 'No music — waves, birds, silence' ],
		'silence' => [ 'label' => 'Silence',         'sub' => 'No audio at all' ],
		'ambient' => [ 'label' => 'Ambient music',   'sub' => 'Cinematic visuals + soundtrack' ],
		'nasheed' => [ 'label' => 'Nasheeds',        'sub' => 'Duff + vocal — no melodic instruments' ],
	];
}
