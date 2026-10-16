<?php
/**
 * Westway theme setup.
 */

add_action( 'after_setup_theme', function () {
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap', 'assets/westway.css' ) );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'westway-fonts', 'https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap', array(), null );
	wp_enqueue_style( 'westway', get_theme_file_uri( 'assets/westway.css' ), array(), wp_get_theme()->get( 'Version' ) );
} );
