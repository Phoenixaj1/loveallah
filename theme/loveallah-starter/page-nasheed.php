<?php
/**
 * Nasheed page — curated Islamic vocal music.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
la_render_feed_main( 'nasheed' );
get_footer();
