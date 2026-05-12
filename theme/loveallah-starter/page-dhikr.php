<?php
/**
 * Dhikr page — curated dhikr recitations only.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
la_render_feed_main( 'dhikr' );
get_footer();
