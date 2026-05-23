<?php
/**
 * Nasheed page — REDIRECTED to /feed.
 *
 * Nasheed artists were removed from the feed roster per the user's
 * direction (scholars + qaris only). This page now redirects any
 * incoming traffic to the main feed so old shared links don't 404.
 *
 * @package LoveAllah
 */
if ( ! defined( 'ABSPATH' ) ) exit;

wp_safe_redirect( home_url( '/' ), 301 );
exit;
