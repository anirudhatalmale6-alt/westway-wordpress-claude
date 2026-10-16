<?php
/**
 * One-off site setup: builds the homepage from the Westway sections and sets
 * the basics. Safe to run more than once - it updates rather than duplicates.
 */

defined( 'ABSPATH' ) || exit;

function ww_upsert_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$args     = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
	);
	if ( $existing ) {
		$args['ID'] = $existing->ID;
		return wp_update_post( wp_slash( $args ) );
	}
	return wp_insert_post( wp_slash( $args ) );
}

function ww_site_setup() {
	if ( ! get_current_user_id() ) {
		wp_set_current_user( 1 );
	}
	update_option( 'blogname', 'Westway Loft Conversion' );
	update_option( 'blogdescription', 'Loft conversions across West London' );
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	$home = ww_upsert_page( 'home', 'Home', ww_render_sections( ww_homepage_sections() ) );
	update_post_meta( $home, '_ww_seo_title', 'Loft Conversions in West London | Westway Loft Conversion' );
	update_post_meta( $home, '_ww_meta_description', 'Dormer, hip-to-gable and mansard loft conversions across West London. Free survey, fixed written price and one team from design to building control sign-off.' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home );

	$soon = function ( $what ) {
		return ww_render_sections(
			array(
				array( 'type' => 'page_hero', 'kicker' => 'Westway Loft Conversion', 'title' => $what . '.', 'intro' => '', 'button_text' => '' ),
				array( 'type' => 'text', 'heading' => '', 'paragraphs' => array( '[This page will be written with Create with Claude and checked before launch.]' ), 'bullets' => array() ),
			)
		);
	};
	ww_upsert_page( 'privacy', 'Privacy', $soon( 'Privacy policy' ) );
	ww_upsert_page( 'terms', 'Terms', $soon( 'Terms of business' ) );

	foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page' ) as $slug => $type ) {
		$p = get_page_by_path( $slug, OBJECT, $type );
		if ( $p ) {
			wp_delete_post( $p->ID, true );
		}
	}
	// Skip the block editor's welcome tour for the first admin.
	global $wpdb;
	update_user_meta( 1, $wpdb->get_blog_prefix() . 'persisted_preferences', array(
		'core/edit-post' => array( 'welcomeGuide' => false ),
		'core/edit-site' => array( 'welcomeGuide' => false, 'welcomeGuideStyles' => false, 'welcomeGuidePage' => false, 'welcomeGuideTemplate' => false ),
		'core'           => array( 'welcomeGuide' => false ),
		'_modified'      => gmdate( 'c' ),
	) );
	flush_rewrite_rules();
	return $home;
}
