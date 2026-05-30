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

/**
 * Wave 94b: add a `page-{slug}` body class for every WordPress page.
 *
 * WordPress's default body_class output emits page-id-{N} + page-template-
 * default, but it does NOT add a slug-based class. Several Wave 92/93/94
 * CSS rules need to scope to specific pages (body.page-dhikr → green
 * accent override, body.page-duas → potentially blue, etc.). Without
 * this filter every per-page theme override silently no-ops because the
 * selector never matches.
 *
 * Idempotent + cheap — just appends one extra class per page render.
 */
add_filter( 'body_class', function( $classes ) {
	if ( is_singular() ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
		if ( $slug ) {
			$classes[] = 'page-' . sanitize_html_class( $slug );
		}
	}
	return $classes;
} );
