<?php
/**
 * The two forms on the homepage: the quote box and the Loft Guide signup.
 * Every submission is saved under Westway → Enquiries and emailed to the
 * address in Site details.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_post_type(
		'ww_enquiry',
		array(
			'labels'       => array( 'name' => 'Enquiries', 'singular_name' => 'Enquiry', 'all_items' => 'Enquiries', 'edit_item' => 'Enquiry', 'not_found' => 'No enquiries yet.' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'supports'     => array( 'title' ),
			'map_meta_cap' => true,
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
		)
	);
} );

function ww_form_notice( $form ) {
	$sent = isset( $_GET['ww_sent'] ) ? sanitize_key( $_GET['ww_sent'] ) : '';
	$err  = isset( $_GET['ww_err'] ) ? sanitize_key( $_GET['ww_err'] ) : '';
	if ( $sent === $form ) {
		$guide_msg = get_option( 'ww_guide_url', '' ) ? 'Thank you. The guide is on its way to your inbox.' : 'Thank you. We will email you the guide as soon as it is ready.';
		$msg       = 'quote' === $form ? 'Thank you. We have your details and will be in touch shortly to arrange your free survey.' : $guide_msg;
		return '<p class="ww-form-msg" role="status">' . esc_html( $msg ) . '</p>';
	}
	if ( $err === $form ) {
		$msg = 'quote' === $form ? 'Please add your name and a phone number or email so we can reply.' : 'Please enter a valid email address.';
		return '<p class="ww-form-msg is-error" role="alert">' . esc_html( $msg ) . '</p>';
	}
	return '';
}

add_shortcode( 'westway_quote_form', function () {
	ob_start();
	?>
	<form id="quote" class="ww-quote" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" aria-label="Start your quote">
		<?php echo ww_form_notice( 'quote' ); // phpcs:ignore ?>
		<input type="hidden" name="action" value="ww_quote">
		<input type="hidden" name="ww_back" value="<?php echo esc_attr( get_permalink() ); ?>">
		<input type="hidden" name="ww_t" value="<?php echo esc_attr( time() ); ?>">
		<label class="ww-hp" aria-hidden="true">Leave empty <input type="text" name="ww_company_site" tabindex="-1" autocomplete="off"></label>
		<label class="ww-q-label" for="ww-brief">Tell us about your loft</label>
		<textarea id="ww-brief" name="brief" rows="3" placeholder="e.g. 1930s semi in Ealing, want a rear dormer with a bedroom and en-suite…"></textarea>
		<div class="ww-q-row ww-q-contact">
			<div class="ww-q-pills">
				<label class="ww-pill"><span>Name</span><input type="text" name="name" placeholder="Your name" autocomplete="name"></label>
				<label class="ww-pill ww-pill-wide"><span>Phone or email</span><input type="text" name="contact" placeholder="So we can reply" autocomplete="email"></label>
			</div>
		</div>
		<div class="ww-q-row">
			<div class="ww-q-pills">
				<label class="ww-pill"><span>Postcode</span><input type="text" name="postcode" placeholder="UB8" autocomplete="postal-code"></label>
				<label class="ww-pill"><span>House type</span>
					<select name="house_type"><option>Terraced</option><option>Semi-detached</option><option>Detached</option><option>Bungalow</option></select>
				</label>
			</div>
			<button type="submit" class="ww-btn">Get my estimate
				<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
			</button>
		</div>
	</form>
	<style>.ww-q-contact{display:none}.ww-quote:focus-within .ww-q-contact,.ww-quote:has(textarea:not(:placeholder-shown)) .ww-q-contact,.ww-quote:has(.ww-q-contact input:not(:placeholder-shown)) .ww-q-contact,.ww-quote:has(.is-error) .ww-q-contact{display:flex}</style>
	<?php
	return ob_get_clean();
} );

add_shortcode( 'westway_guide_form', function () {
	ob_start();
	?>
	<form class="ww-guide-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" aria-label="Get the free guide">
		<?php echo ww_form_notice( 'guide' ); // phpcs:ignore ?>
		<input type="hidden" name="action" value="ww_guide">
		<input type="hidden" name="ww_back" value="<?php echo esc_attr( get_permalink() ); ?>">
		<input type="hidden" name="ww_t" value="<?php echo esc_attr( time() ); ?>">
		<label class="ww-hp" aria-hidden="true">Leave empty <input type="text" name="ww_company_site" tabindex="-1" autocomplete="off"></label>
		<label for="ww-guide-email">Email address</label>
		<input id="ww-guide-email" type="email" name="email" placeholder="you@example.com" required>
		<button type="submit">Send me the guide</button>
	</form>
	<?php
	return ob_get_clean();
} );

function ww_form_back( $form, $key ) {
	$back = isset( $_POST['ww_back'] ) ? esc_url_raw( wp_unslash( $_POST['ww_back'] ) ) : home_url( '/' );
	$back = wp_validate_redirect( $back, home_url( '/' ) );
	$anchor = 'quote' === $form ? '#quote' : '#ww-guide-email';
	wp_safe_redirect( add_query_arg( $key, $form, remove_query_arg( array( 'ww_sent', 'ww_err' ), $back ) ) . $anchor );
	exit;
}

function ww_is_bot() {
	if ( ! empty( $_POST['ww_company_site'] ) ) {
		return true;
	}
	$t = isset( $_POST['ww_t'] ) ? (int) $_POST['ww_t'] : 0;
	return $t && ( time() - $t ) < 2;
}

function ww_save_enquiry( $kind, $title, array $fields ) {
	$lines = array();
	foreach ( $fields as $label => $value ) {
		$lines[] = $label . ': ' . $value;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'ww_enquiry',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => implode( "\n", $lines ),
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_ww_kind', $kind );
		update_post_meta( $id, '_ww_fields', $fields );
	}
	$to = ww_detail( 'email' );
	if ( is_email( $to ) ) {
		wp_mail( $to, '[' . get_bloginfo( 'name' ) . '] ' . $title, implode( "\n", $lines ) . "\n\nSee all enquiries: " . admin_url( 'edit.php?post_type=ww_enquiry' ) );
	}
	return $id;
}

$ww_quote_handler = function () {
	if ( ww_is_bot() ) {
		ww_form_back( 'quote', 'ww_sent' );
	}
	$f = array();
	foreach ( array( 'name', 'contact', 'postcode', 'house_type' ) as $k ) {
		$f[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
	}
	$brief = isset( $_POST['brief'] ) ? sanitize_textarea_field( wp_unslash( $_POST['brief'] ) ) : '';
	if ( '' === $f['name'] || '' === $f['contact'] ) {
		ww_form_back( 'quote', 'ww_err' );
	}
	ww_save_enquiry(
		'quote',
		'Quote request from ' . $f['name'] . ( $f['postcode'] ? ' (' . strtoupper( $f['postcode'] ) . ')' : '' ),
		array(
			'Name'           => $f['name'],
			'Phone or email' => $f['contact'],
			'Postcode'       => strtoupper( $f['postcode'] ),
			'House type'     => $f['house_type'],
			'About the loft' => $brief,
		)
	);
	ww_form_back( 'quote', 'ww_sent' );
};
add_action( 'admin_post_ww_quote', $ww_quote_handler );
add_action( 'admin_post_nopriv_ww_quote', $ww_quote_handler );

$ww_guide_handler = function () {
	if ( ww_is_bot() ) {
		ww_form_back( 'guide', 'ww_sent' );
	}
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		ww_form_back( 'guide', 'ww_err' );
	}
	ww_save_enquiry( 'guide', 'Guide signup: ' . $email, array( 'Email' => $email ) );
	$guide = get_option( 'ww_guide_url', '' );
	if ( $guide ) {
		wp_mail( $email, 'Your West London Loft Guide', "Thanks for asking for the guide. You can download it here:\n\n" . $guide . "\n\n" . get_bloginfo( 'name' ) );
	}
	ww_form_back( 'guide', 'ww_sent' );
};
add_action( 'admin_post_ww_guide', $ww_guide_handler );
add_action( 'admin_post_nopriv_ww_guide', $ww_guide_handler );

/* Enquiries list: show the type and the details at a glance. */
add_action( 'admin_menu', function () {
	add_submenu_page( 'westway', 'Enquiries', 'Enquiries', 'edit_pages', 'edit.php?post_type=ww_enquiry' );
}, 20 );

/* Keep the Westway menu open while looking at enquiries. */
add_filter( 'parent_file', function ( $parent ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && 'ww_enquiry' === $screen->post_type ? 'westway' : $parent;
} );

add_filter( 'manage_ww_enquiry_posts_columns', function ( $cols ) {
	return array( 'cb' => $cols['cb'], 'title' => 'Enquiry', 'ww_kind' => 'Type', 'ww_details' => 'Details', 'date' => 'Received' );
} );
add_action( 'manage_ww_enquiry_posts_custom_column', function ( $col, $id ) {
	if ( 'ww_kind' === $col ) {
		echo 'guide' === get_post_meta( $id, '_ww_kind', true ) ? 'Guide signup' : 'Quote request';
	} elseif ( 'ww_details' === $col ) {
		echo nl2br( esc_html( get_post_field( 'post_content', $id ) ) );
	}
}, 10, 2 );
add_action( 'add_meta_boxes_ww_enquiry', function () {
	add_meta_box( 'ww_enquiry_details', 'Details', function ( $post ) {
		echo '<p style="font-size:14px;line-height:1.7">' . nl2br( esc_html( $post->post_content ) ) . '</p>';
	}, 'ww_enquiry', 'normal', 'high' );
} );
