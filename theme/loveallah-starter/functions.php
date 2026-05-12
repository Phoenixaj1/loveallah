<?php
/**
 * Love Allah Starter — theme functions.
 *
 * @package LoveAllah
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'after_setup_theme', function() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ] );

	register_nav_menus( [
		'primary' => 'Primary Menu',
	] );
} );

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'loveallah-theme', get_stylesheet_uri(), [ 'loveallah' ], '0.1.0' );
} );
