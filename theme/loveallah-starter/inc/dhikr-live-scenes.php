<?php
/**
 * Shared scene library for the live dhikr modes (Wave 103).
 *
 * Solitude (page-dhikr.php), Pulse (dhikr-pulse.php), and Names
 * (dhikr-names.php) all use the same 6 ambient scenes:
 *
 *   • Ocean    🌊 — waves only, no music     (NJXzcQJi_A8)
 *   • Moonlit  🌙 — silence, gradient only   (no video)
 *   • Forest   🌿 — birds at dawn, no music  (BHACKCNDMW8)
 *   • Cosmos   ✨ — hubble + ambient music   (Y_plhk1FUQA)
 *   • Sahara   🌅 — dunes, cinematic score   (gFmDx9oj3DU)
 *   • Haram    🕋 — live tawaf from Makkah   (bNY8a2BB5Gc)
 *
 * Each scene carries:
 *   - id          machine-readable key
 *   - label       human-readable name shown in chip + dots
 *   - emoji       small glyph for the chip
 *   - bg          full-bleed CSS gradient (instant — always shown)
 *   - orb         hex colour the breath/pulse-core tints to
 *   - video       YouTube ID (empty = gradient-only, silent scene)
 *
 * Usage:
 *   require_once get_template_directory() . '/inc/dhikr-live-scenes.php';
 *   // now $la_dhikr_scenes is in scope.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! isset( $la_dhikr_scenes ) ) {
	$la_dhikr_scenes = [
		[ 'id' => 'ocean', 'label' => 'Ocean', 'emoji' => '🌊',
		  'bg'    => 'radial-gradient(85% 58% at 50% 24%, #155560 0%, #0c2f38 46%, #06151b 100%)',
		  'orb'   => '#bfeef0',
		  'video' => 'NJXzcQJi_A8' ],
		[ 'id' => 'moonlit', 'label' => 'Moonlit', 'emoji' => '🌙',
		  'bg'    => 'radial-gradient(80% 55% at 50% 26%, #33386a 0%, #1a1d3e 48%, #0a0b1c 100%)',
		  'orb'   => '#cfd6ff',
		  'video' => '' ],
		[ 'id' => 'forest', 'label' => 'Forest', 'emoji' => '🌿',
		  'bg'    => 'radial-gradient(85% 58% at 50% 26%, #265141 0%, #143026 46%, #08160f 100%)',
		  'orb'   => '#cdeed2',
		  'video' => 'BHACKCNDMW8' ],
		[ 'id' => 'cosmos', 'label' => 'Cosmos', 'emoji' => '✨',
		  'bg'    => 'radial-gradient(85% 58% at 50% 24%, #3a2a5c 0%, #1e1438 48%, #0a0712 100%)',
		  'orb'   => '#e6d4ff',
		  'video' => 'Y_plhk1FUQA' ],
		[ 'id' => 'dawn', 'label' => 'Sahara', 'emoji' => '🌅',
		  'bg'    => 'radial-gradient(90% 60% at 50% 30%, #6e4444 0%, #3a2330 46%, #160c18 100%)',
		  'orb'   => '#ffd9c2',
		  'video' => 'gFmDx9oj3DU' ],
		[ 'id' => 'haram', 'label' => 'Haram', 'emoji' => '🕋',
		  'bg'    => 'radial-gradient(85% 58% at 50% 26%, #3a2e1c 0%, #20180c 46%, #0a0805 100%)',
		  'orb'   => '#f0d8a8',
		  'video' => 'bNY8a2BB5Gc' ],
	];
}
