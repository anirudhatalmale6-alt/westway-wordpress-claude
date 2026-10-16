<?php
/**
 * Plugin Name: Westway Site
 * Description: Westway sections, site details, enquiry forms and "Create with Claude" (pages and posts written by Claude from the site's own sections).
 * Version: 0.1.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Text Domain: westway-site
 */

defined( 'ABSPATH' ) || exit;

define( 'WW_SITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WW_SITE_URL', plugin_dir_url( __FILE__ ) );

require WW_SITE_DIR . 'inc/blocks.php';
require WW_SITE_DIR . 'inc/sections.php';
require WW_SITE_DIR . 'inc/details.php';
require WW_SITE_DIR . 'inc/forms.php';
require WW_SITE_DIR . 'inc/claude.php';
require WW_SITE_DIR . 'inc/setup.php';

/* "Westway sections" in the block inserter. */
add_action( 'init', function () {
	register_block_pattern_category( 'westway', array( 'label' => 'Westway sections' ) );
	foreach ( ww_pattern_sections() as $slug => $p ) {
		register_block_pattern(
			'westway/' . str_replace( '_', '-', $slug ),
			array(
				'title'      => $p['title'],
				'categories' => array( 'westway' ),
				'content'    => ww_render_section( $p['section'] ),
			)
		);
	}
} );

/* SEO title and meta description saved with each page (only when no SEO plugin is doing it). */
add_action( 'init', function () {
	foreach ( array( 'page', 'post' ) as $type ) {
		register_post_meta( $type, '_ww_seo_title', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => function () { return current_user_can( 'edit_posts' ); } ) );
		register_post_meta( $type, '_ww_meta_description', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => function () { return current_user_can( 'edit_posts' ); } ) );
	}
} );

function ww_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
}

add_filter( 'pre_get_document_title', function ( $title ) {
	if ( is_singular() && ! ww_seo_plugin_active() ) {
		$t = get_post_meta( get_queried_object_id(), '_ww_seo_title', true );
		if ( $t ) {
			return ww_replace_tokens( $t );
		}
	}
	return $title;
} );

add_action( 'wp_head', function () {
	if ( is_singular() && ! ww_seo_plugin_active() ) {
		$d = get_post_meta( get_queried_object_id(), '_ww_meta_description', true );
		if ( $d ) {
			echo '<meta name="description" content="' . esc_attr( ww_replace_tokens( $d ) ) . '">' . "\n";
		}
	}
}, 1 );
