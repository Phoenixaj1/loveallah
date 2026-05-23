<?php
/**
 * Lectures page — long-form scholarly talks for deeper learning.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
la_render_feed_main( 'lecture' );
get_footer();
