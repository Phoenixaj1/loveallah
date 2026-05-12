<?php
/**
 * Homepage — main feed (all content types interleaved with dhikr cards).
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
la_render_feed_main( '' );
get_footer();
