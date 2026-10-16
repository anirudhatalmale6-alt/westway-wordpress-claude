<?php
/**
 * Site details: phone, email, address and so on, kept in one place.
 * Type {phone}, {email} etc. anywhere on the site and the current value
 * shows up, so changing the number here changes it everywhere.
 */

defined( 'ABSPATH' ) || exit;

function ww_detail_fields() {
	return array(
		'phone'      => array( 'Phone number', '[PHONE NUMBER]', 'Shown in the header, buttons and footer.' ),
		'email'      => array( 'Email address', '[EMAIL]', 'Shown in the footer. Enquiries are emailed here too.' ),
		'company'    => array( 'Company name', 'Westway Construction [Ltd]', 'As registered, e.g. Westway Construction Ltd.' ),
		'address'    => array( 'Registered address', '[Registered address]', '' ),
		'company_no' => array( 'Company number', '[X]', '' ),
		'rating'     => array( 'Google rating', '[4.9]', 'Copy it from your Google Business profile.' ),
		'reviews'    => array( 'Number of Google reviews', '[N]', '' ),
	);
}

function ww_detail( $key ) {
	$fields = ww_detail_fields();
	$saved  = get_option( 'ww_details', array() );
	if ( isset( $saved[ $key ] ) && '' !== trim( $saved[ $key ] ) ) {
		return $saved[ $key ];
	}
	return isset( $fields[ $key ] ) ? $fields[ $key ][1] : '';
}

function ww_token_map() {
	$phone   = ww_detail( 'phone' );
	$company = ww_detail( 'company' );
	return array(
		'{phone}'         => esc_html( $phone ),
		'{phone_link}'    => esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ),
		'{email}'         => esc_html( ww_detail( 'email' ) ),
		'{company}'       => esc_html( $company ),
		'{company_short}' => esc_html( trim( preg_replace( '/\s*(\[?Ltd\]?|Limited)\.?$/i', '', $company ) ) ),
		'{address}'       => esc_html( ww_detail( 'address' ) ),
		'{company_no}'    => esc_html( ww_detail( 'company_no' ) ),
		'{rating}'        => esc_html( ww_detail( 'rating' ) ),
		'{reviews}'       => esc_html( ww_detail( 'reviews' ) ),
		'{site_name}'     => esc_html( get_bloginfo( 'name' ) ),
	);
}

function ww_replace_tokens( $html ) {
	$html = (string) $html;
	if ( false !== strpos( $html, '{' ) ) {
		$html = strtr( $html, ww_token_map() );
	}
	// Root-relative links ("/#quote") follow the site address, even when WordPress lives in a sub-folder.
	if ( false !== strpos( $html, '="/' ) ) {
		$home = untrailingslashit( home_url() );
		if ( '' !== wp_parse_url( $home, PHP_URL_PATH ) && null !== wp_parse_url( $home, PHP_URL_PATH ) ) {
			$html = preg_replace( '#\b(href|src|action)="/(?!/)#', '$1="' . esc_url( $home ) . '/', $html );
		}
	}
	return $html;
}

/* Photo placeholders: /?ww_ph=label&w=..&h=..&f=hex returns a simple SVG. */
add_action( 'init', function () {
	if ( ! isset( $_GET['ww_ph'] ) ) {
		return;
	}
	$label = sanitize_text_field( rawurldecode( wp_unslash( $_GET['ww_ph'] ) ) );
	$w     = max( 50, min( 3000, (int) ( isset( $_GET['w'] ) ? $_GET['w'] : 1200 ) ) );
	$h     = max( 50, min( 3000, (int) ( isset( $_GET['h'] ) ? $_GET['h'] : 520 ) ) );
	$fill  = isset( $_GET['f'] ) && preg_match( '/^[0-9a-fA-F]{6}$/', $_GET['f'] ) ? $_GET['f'] : 'E8E0CE';
	$size  = max( 14, round( $w / 60 ) );
	header( 'Content-Type: image/svg+xml; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );
	echo "<svg xmlns='http://www.w3.org/2000/svg' width='{$w}' height='{$h}' viewBox='0 0 {$w} {$h}'><rect width='100%' height='100%' fill='#{$fill}'/><text x='50%' y='50%' text-anchor='middle' dominant-baseline='middle' font-family='system-ui,sans-serif' font-size='{$size}' fill='#5A6158'>" . esc_html( $label ) . '</text></svg>';
	exit;
}, 0 );

/* Front end only: in the editor the tokens stay visible so they can be moved around. */
add_filter( 'render_block', function ( $html ) {
	return is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? $html : ww_replace_tokens( $html );
}, 20 );

/* ---- Admin: Westway menu ------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_menu_page( 'Westway', 'Westway', 'edit_pages', 'westway', 'ww_render_create_page', 'dashicons-admin-home', 3 );
	add_submenu_page( 'westway', 'Create with Claude', 'Create with Claude', 'edit_pages', 'westway', 'ww_render_create_page' );
	add_submenu_page( 'westway', 'Site details', 'Site details', 'manage_options', 'westway-details', 'ww_render_details_page' );
	add_submenu_page( 'westway', 'Claude settings', 'Claude settings', 'manage_options', 'westway-claude', 'ww_render_claude_settings_page' );
} );

add_action( 'admin_post_ww_save_details', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'ww_save_details' );
	$in  = isset( $_POST['ww'] ) ? (array) wp_unslash( $_POST['ww'] ) : array();
	$out = array();
	foreach ( ww_detail_fields() as $key => $f ) {
		$out[ $key ] = isset( $in[ $key ] ) ? sanitize_text_field( $in[ $key ] ) : '';
	}
	update_option( 'ww_details', $out );
	wp_safe_redirect( admin_url( 'admin.php?page=westway-details&saved=1' ) );
	exit;
} );

function ww_admin_css() {
	echo '<style>
	.ww-admin{max-width:860px}.ww-admin .ww-box{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px 24px;margin:16px 0}
	.ww-admin .ww-field{margin:0 0 16px}.ww-admin .ww-field label{display:block;font-weight:600;margin-bottom:4px}
	.ww-admin .ww-field input[type=text],.ww-admin .ww-field input[type=password],.ww-admin .ww-field textarea,.ww-admin .ww-field select{width:100%;max-width:560px}
	.ww-admin .ww-help{color:#646970;font-size:13px;margin:4px 0 0}.ww-admin code{font-size:12px}
	.ww-admin .ww-tokens td{padding:4px 12px 4px 0}
	.ww-busy{display:none;position:fixed;inset:0;background:rgba(255,255,255,.85);z-index:99999;align-items:center;justify-content:center;flex-direction:column;gap:14px;font-size:16px}
	.ww-busy.on{display:flex}.ww-spin{width:38px;height:38px;border:4px solid #c3c4c7;border-top-color:#1E3A2F;border-radius:50%;animation:wwspin 1s linear infinite}
	@keyframes wwspin{to{transform:rotate(360deg)}}
	.ww-result{border-left:4px solid #1E3A2F}.ww-warn{border-left:4px solid #dba617}.ww-err{border-left:4px solid #d63638}
	</style>';
}

function ww_render_details_page() {
	ww_admin_css();
	echo '<div class="wrap ww-admin"><h1>Site details</h1>';
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success"><p>Saved. The site now shows the new details everywhere.</p></div>';
	}
	echo '<p>These appear all over the site. Change them here once and every page updates. Anything still in [square brackets] is a placeholder waiting for the real detail.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ww-box">';
	wp_nonce_field( 'ww_save_details' );
	echo '<input type="hidden" name="action" value="ww_save_details">';
	$saved = get_option( 'ww_details', array() );
	foreach ( ww_detail_fields() as $key => $f ) {
		$val = isset( $saved[ $key ] ) ? $saved[ $key ] : $f[1];
		echo '<div class="ww-field"><label for="ww-' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label>';
		echo '<input type="text" id="ww-' . esc_attr( $key ) . '" name="ww[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '">';
		echo '<p class="ww-help">' . esc_html( $f[2] ) . ' Use <code>{' . esc_html( $key ) . '}</code> in any text to show it.</p></div>';
	}
	submit_button( 'Save details' );
	echo '</form></div>';
}
