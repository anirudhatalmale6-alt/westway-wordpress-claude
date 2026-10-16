<?php
/**
 * Create with Claude.
 *
 * Describe a page or post, Claude plans it from the Westway sections and
 * writes the words, and it is saved as a draft built from the same blocks as
 * the homepage. Nothing is ever published automatically.
 */

defined( 'ABSPATH' ) || exit;

const WW_API_URL = 'https://api.anthropic.com/v1/messages';

function ww_models() {
	// Prices are US$ per million tokens (input, output), used only for the cost estimate shown in the log.
	return array(
		'claude-opus-5-5'   => array( 'Claude Opus 5.5 (best writing)', 4.00, 20.00 ),
		'claude-sonnet-5-5' => array( 'Claude Sonnet 5.5 (faster, about half the cost)', 2.00, 10.00 ),
	);
}

function ww_claude_settings() {
	$defaults = array(
		'api_key' => '',
		'model'   => 'claude-opus-5-5',
		'style'   => "Plain British English. Friendly, calm and professional, like an experienced builder explaining things to a homeowner. Short sentences. No slang, no hype, no jargon unless it is explained.",
		'facts'   => ww_default_facts(),
		'length'  => 'medium',
	);
	return wp_parse_args( get_option( 'ww_claude', array() ), $defaults );
}

function ww_default_facts() {
	return implode(
		"\n",
		array(
			'Business: Westway Loft Conversion, part of Westway Construction. Loft conversions across West London.',
			'Conversions offered: rear dormer, hip-to-gable (often with a dormer), mansard, roof light (Velux-style windows, no change to roof shape).',
			'Areas covered: Hillingdon, Ealing, Hammersmith & Fulham, Kensington & Chelsea, Brent, Hounslow, Harrow, Westminster.',
			'How it works: 1) free survey - measure the loft, check head height and roof structure, fixed written price; 2) design & approvals - drawings, structural calcs, planning or lawful development certificate, party wall notices where needed; 3) build - mostly from scaffolding so homeowners can usually stay at home, weekly updates from the site manager; 4) sign-off - building control completion certificate and a workmanship guarantee.',
			'Promises: fixed price in writing after the survey, one project manager from first visit to sign-off, pay in stages.',
			'NOT YET CONFIRMED (always use a [placeholder]): prices, build times, years trading, number of lofts completed, guarantee length and provider, FMB / TrustMark membership, Google rating, review quotes, staff names, address, phone and email.',
		)
	);
}

/* ---- Settings page ------------------------------------------------------ */

add_action( 'admin_post_ww_save_claude', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'ww_save_claude' );
	$cur = ww_claude_settings();
	$in  = isset( $_POST['ww'] ) ? (array) wp_unslash( $_POST['ww'] ) : array();
	$key = isset( $in['api_key'] ) ? trim( sanitize_text_field( $in['api_key'] ) ) : '';
	$out = array(
		'api_key' => '' !== $key ? $key : ( empty( $in['forget_key'] ) ? $cur['api_key'] : '' ),
		'model'   => isset( $in['model'] ) && isset( ww_models()[ $in['model'] ] ) ? $in['model'] : 'claude-opus-5-5',
		'style'   => isset( $in['style'] ) ? sanitize_textarea_field( $in['style'] ) : $cur['style'],
		'facts'   => isset( $in['facts'] ) ? sanitize_textarea_field( $in['facts'] ) : $cur['facts'],
		'length'  => isset( $in['length'] ) && in_array( $in['length'], array( 'short', 'medium', 'long' ), true ) ? $in['length'] : 'medium',
	);
	update_option( 'ww_claude', $out, false );
	$note = 'saved';
	if ( ! empty( $_POST['ww_test'] ) ) {
		$r    = ww_claude_test();
		$note = is_wp_error( $r ) ? 'testfail' : 'testok';
		set_transient( 'ww_test_msg', is_wp_error( $r ) ? $r->get_error_message() : $r, 300 );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=westway-claude&' . $note . '=1' ) );
	exit;
} );

function ww_render_claude_settings_page() {
	ww_admin_css();
	$s = ww_claude_settings();
	echo '<div class="wrap ww-admin"><h1>Claude settings</h1>';
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
	}
	if ( isset( $_GET['testok'] ) ) {
		echo '<div class="notice notice-success"><p><strong>Connected.</strong> ' . esc_html( get_transient( 'ww_test_msg' ) ) . '</p></div>';
	}
	if ( isset( $_GET['testfail'] ) ) {
		echo '<div class="notice notice-error"><p><strong>Could not connect.</strong> ' . esc_html( get_transient( 'ww_test_msg' ) ) . '</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'ww_save_claude' );
	echo '<input type="hidden" name="action" value="ww_save_claude">';

	echo '<div class="ww-box"><h2>Your Anthropic API key</h2>';
	if ( $s['api_key'] ) {
		echo '<p>A key is saved (ending <code>…' . esc_html( substr( $s['api_key'], -4 ) ) . '</code>). Leave the box empty to keep it.</p>';
	} else {
		echo '<p>Paste the key from your Anthropic account (console.anthropic.com → API keys). Pages you create are billed to that account.</p>';
	}
	echo '<div class="ww-field"><label for="ww-key">API key</label><input type="password" id="ww-key" name="ww[api_key]" value="" autocomplete="off" placeholder="sk-ant-..."></div>';
	if ( $s['api_key'] ) {
		echo '<label><input type="checkbox" name="ww[forget_key]" value="1"> Remove the saved key</label>';
	}
	echo '</div>';

	echo '<div class="ww-box"><h2>How Claude writes</h2>';
	echo '<div class="ww-field"><label for="ww-model">Model</label><select id="ww-model" name="ww[model]">';
	foreach ( ww_models() as $id => $m ) {
		echo '<option value="' . esc_attr( $id ) . '"' . selected( $s['model'], $id, false ) . '>' . esc_html( $m[0] ) . '</option>';
	}
	echo '</select></div>';
	echo '<div class="ww-field"><label for="ww-length">Usual page length</label><select id="ww-length" name="ww[length]">';
	foreach ( array( 'short' => 'Short (3–4 sections)', 'medium' => 'Medium (5–6 sections)', 'long' => 'Long (7–9 sections)' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['length'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></div>';
	echo '<div class="ww-field"><label for="ww-style">Writing style</label><textarea id="ww-style" name="ww[style]" rows="4">' . esc_textarea( $s['style'] ) . '</textarea><p class="ww-help">Tell Claude how the site should sound. It follows this on every page.</p></div>';
	echo '<div class="ww-field"><label for="ww-facts">Business facts Claude may use</label><textarea id="ww-facts" name="ww[facts]" rows="10">' . esc_textarea( $s['facts'] ) . '</textarea><p class="ww-help">Only what is true goes here. Claude will not make up prices, guarantees, memberships, reviews or numbers - anything it does not find here comes out as a [placeholder] for you to fill in. As details are confirmed, add them here.</p></div>';
	echo '</div>';

	echo '<p>';
	submit_button( 'Save settings', 'primary', 'submit', false );
	echo ' ';
	submit_button( 'Save and test connection', 'secondary', 'ww_test', false );
	echo '</p></form></div>';
}

/* ---- Create page -------------------------------------------------------- */

function ww_render_create_page() {
	ww_admin_css();
	$s = ww_claude_settings();
	echo '<div class="wrap ww-admin"><h1>Create with Claude</h1>';

	if ( isset( $_GET['ww_done'] ) ) {
		$id = (int) $_GET['ww_done'];
		$p  = get_post( $id );
		if ( $p ) {
			preg_match_all( '/\[[^\]\n]{1,80}\]/u', wp_strip_all_tags( $p->post_content ), $m );
			$ph = array_unique( $m[0] );
			echo '<div class="ww-box ww-result"><h2 style="margin-top:0">Draft ready: ' . esc_html( get_the_title( $p ) ) . '</h2>';
			echo '<p>Saved as a draft ' . esc_html( 'page' === $p->post_type ? 'page' : 'blog post' ) . '. Nothing is live yet.</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( get_preview_post_link( $p ) ) . '" target="_blank">Preview it</a> ';
			echo '<a class="button" href="' . esc_url( get_edit_post_link( $p ) ) . '">Edit and publish</a></p>';
			if ( $ph ) {
				echo '<p><strong>' . count( $ph ) . ' detail' . ( count( $ph ) > 1 ? 's' : '' ) . ' to fill in</strong> before you publish (Claude left these as placeholders rather than guess): ' . esc_html( implode( '  ', array_slice( $ph, 0, 12 ) ) ) . ( count( $ph ) > 12 ? ' …' : '' ) . '</p>';
			}
			echo '</div>';
		}
	}
	if ( isset( $_GET['ww_error'] ) ) {
		echo '<div class="ww-box ww-err"><p><strong>Claude could not create the page.</strong> ' . esc_html( get_transient( 'ww_err_' . get_current_user_id() ) ) . '</p></div>';
	}
	if ( ! $s['api_key'] ) {
		echo '<div class="ww-box ww-warn"><p>First add your Anthropic API key under <a href="' . esc_url( admin_url( 'admin.php?page=westway-claude' ) ) . '">Westway → Claude settings</a>.</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ww-box" id="ww-create">';
	wp_nonce_field( 'ww_create' );
	echo '<input type="hidden" name="action" value="ww_create">';
	echo '<div class="ww-field"><label for="ww-brief">What do you want?</label><textarea id="ww-brief" name="brief" rows="5" required placeholder="e.g. A page about loft conversions in Ealing: what most Ealing houses suit, planning in Ealing, and why people choose a rear dormer there."></textarea>';
	echo '<p class="ww-help">Describe it the way you would brief a copywriter. Mention anything it must include.</p></div>';
	echo '<div class="ww-field"><label>Create a</label><label style="display:inline;font-weight:400;margin-right:16px"><input type="radio" name="kind" value="page" checked> Page</label><label style="display:inline;font-weight:400"><input type="radio" name="kind" value="post"> Blog post</label></div>';
	echo '<div class="ww-field"><label for="ww-len">Length</label><select id="ww-len" name="length">';
	foreach ( array( 'short' => 'Short', 'medium' => 'Medium', 'long' => 'Long' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['length'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></div>';
	submit_button( 'Create draft', 'primary', 'submit', false, $s['api_key'] ? array() : array( 'disabled' => 'disabled' ) );
	echo '</form>';
	echo '<div class="ww-busy" id="ww-busy"><div class="ww-spin"></div><div>Claude is writing your page…</div><div style="color:#646970;font-size:13px">This usually takes 30–90 seconds. Please keep this tab open.</div></div>';
	echo '<script>document.getElementById("ww-create").addEventListener("submit",function(){document.getElementById("ww-busy").classList.add("on")});</script>';

	$log = get_option( 'ww_claude_log', array() );
	if ( $log ) {
		echo '<div class="ww-box"><h2 style="margin-top:0">Recent</h2><table class="widefat striped"><thead><tr><th>When</th><th>Request</th><th>Result</th><th>Approx. cost</th></tr></thead><tbody>';
		foreach ( $log as $row ) {
			$res = ! empty( $row['post'] ) && get_post( $row['post'] ) ? '<a href="' . esc_url( get_edit_post_link( $row['post'] ) ) . '">' . esc_html( get_the_title( $row['post'] ) ) . '</a>' : esc_html( isset( $row['error'] ) ? $row['error'] : '' );
			echo '<tr><td>' . esc_html( human_time_diff( $row['time'] ) ) . ' ago</td><td>' . esc_html( wp_trim_words( $row['brief'], 14 ) ) . '</td><td>' . $res . '</td><td>' . esc_html( isset( $row['cost'] ) ? '$' . number_format( $row['cost'], 3 ) : '' ) . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table></div>';
	}
	echo '</div>';
}

add_action( 'admin_post_ww_create', function () {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'ww_create' );
	@set_time_limit( 300 ); // phpcs:ignore
	$brief  = isset( $_POST['brief'] ) ? sanitize_textarea_field( wp_unslash( $_POST['brief'] ) ) : '';
	$kind   = isset( $_POST['kind'] ) && 'post' === $_POST['kind'] ? 'post' : 'page';
	$length = isset( $_POST['length'] ) && in_array( $_POST['length'], array( 'short', 'medium', 'long' ), true ) ? $_POST['length'] : 'medium';

	$result = ww_claude_create( $brief, $kind, $length );
	if ( is_wp_error( $result ) ) {
		set_transient( 'ww_err_' . get_current_user_id(), $result->get_error_message(), 600 );
		wp_safe_redirect( admin_url( 'admin.php?page=westway&ww_error=1' ) );
		exit;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=westway&ww_done=' . (int) $result ) );
	exit;
} );

/* ---- Talking to Claude -------------------------------------------------- */

function ww_section_schema() {
	$str  = array( 'type' => 'string' );
	$strs = array( 'type' => 'array', 'items' => $str );
	$obj  = function ( array $props ) {
		return array( 'type' => 'object', 'properties' => $props, 'required' => array_keys( $props ), 'additionalProperties' => false );
	};
	$list = function ( array $props ) use ( $obj ) {
		return array( 'type' => 'array', 'items' => $obj( $props ) );
	};
	$sec = function ( $type, array $props ) use ( $obj ) {
		return $obj( array_merge( array( 'type' => array( 'type' => 'string', 'const' => $type ) ), $props ) );
	};
	return array(
		'anyOf' => array(
			$sec( 'page_hero', array( 'kicker' => $str, 'title' => $str, 'intro' => $str, 'button_text' => $str ) ),
			$sec( 'text', array( 'heading' => $str, 'paragraphs' => $strs, 'bullets' => $strs ) ),
			$sec( 'benefits', array( 'heading' => $str, 'items' => $list( array( 'title' => $str, 'text' => $str ) ) ) ),
			$sec( 'cards', array( 'heading' => $str, 'intro' => $str, 'items' => $list( array( 'title' => $str, 'text' => $str, 'footnote' => $str ) ) ) ),
			$sec( 'steps', array( 'heading' => $str, 'items' => $list( array( 'title' => $str, 'text' => $str ) ) ) ),
			$sec( 'price_table', array( 'heading' => $str, 'paragraphs' => $strs, 'button_text' => $str, 'rows' => $list( array( 'name' => $str, 'price' => $str, 'time' => $str ) ) ) ),
			$sec( 'before_after', array( 'heading' => $str, 'intro' => $str, 'items' => $list( array( 'title' => $str, 'caption' => $str ) ) ) ),
			$sec( 'areas', array( 'heading' => $str, 'areas' => $strs ) ),
			$sec( 'faq', array( 'heading' => $str, 'items' => $list( array( 'q' => $str, 'a' => $str ) ) ) ),
			$sec( 'photo', array( 'description' => $str ) ),
			$sec( 'cta', array( 'heading' => $str, 'text' => $str ) ),
		),
	);
}

function ww_page_schema() {
	return array(
		'type'                 => 'object',
		'properties'           => array(
			'title'            => array( 'type' => 'string' ),
			'slug'             => array( 'type' => 'string' ),
			'seo_title'        => array( 'type' => 'string' ),
			'meta_description' => array( 'type' => 'string' ),
			'sections'         => array( 'type' => 'array', 'items' => ww_section_schema() ),
		),
		'required'             => array( 'title', 'slug', 'seo_title', 'meta_description', 'sections' ),
		'additionalProperties' => false,
	);
}

function ww_system_prompt( array $s ) {
	return 'You write web pages and blog posts for a WordPress website, ' . get_bloginfo( 'name' ) . ". The site's design is made of fixed section types; you choose the sections and write their words, and the website lays them out.

SECTION TYPES
- page_hero: the top of every page. kicker = short label above the title (e.g. \"Loft conversions in Ealing\"), title = the page headline (a short, plain sentence ending in a full stop, like \"More room, without moving house.\"), intro = 1-2 sentences, button_text = e.g. \"Book a free survey\" (or empty string for no button).
- text: a heading (may be empty) with 1-4 paragraphs and optional bullet points (empty array for none). Use for explanations and blog post body text.
- benefits: heading + 3 or 4 short points (title + one or two sentences).
- cards: heading + intro + 2-4 cards (title, text, footnote). Footnote is a short line such as \"From [£PRICE]\" or an empty string.
- steps: heading + 3 or 4 numbered steps (title + text). Shown as a dark band.
- price_table: heading, 1-2 paragraphs, button_text, and rows of name / price / time. Prices and times are almost always placeholders.
- before_after: heading, intro and 2-3 projects (title like \"[Dormer] · [Area]\", caption like \"[Added: bedroom + en-suite] · [X] m² · [X] weeks\"). Photos are added later by the owner.
- areas: heading + list of area names.
- faq: heading + 3-6 questions and answers.
- photo: a wide photo slot; description says what photo should go there.
- cta: closing call to action - heading + one short line. End every page with one.

HOW TO BUILD A PAGE
- Start with page_hero and end with cta. Vary the middle sections to suit the subject; do not use every type.
- Blog posts: page_hero, then mostly text sections (each with a heading), optionally one faq, then cta.
- Use {phone} where the phone number should appear, {email} for the email address and {company} for the company name. The site fills these in.
- seo_title: under 60 characters, includes the main topic and the business name where it fits. meta_description: under 155 characters, plain and specific. slug: lowercase words joined by hyphens.
- Plain text only in every field: no markdown, no HTML, no emoji.

TRUTHFULNESS - THE MOST IMPORTANT RULE
Only state facts about this business that appear in BUSINESS FACTS below. Never invent prices, costs, percentages, timescales, years in business, numbers of projects, guarantees, insurance, accreditations or memberships, awards, staff names, addresses, phone numbers, customer reviews or quotes, or statistics. Where the page needs one of these and it is not in BUSINESS FACTS, write a short placeholder in square brackets instead, e.g. [£PRICE], [X] weeks, [GUARANTEE LENGTH], [PROJECT MANAGER NAME]. The owner fills these in before publishing.
General, well-established information about loft conversions in England (permitted development, building regulations, party wall notices, typical roof types) is fine; keep it general and accurate, and say the survey confirms what applies to each house. If a page is a legal page such as a privacy policy or terms, write a clear plain-English draft, use placeholders for anything specific, and add a text section that says [Have this checked by a solicitor before publishing].

WRITING STYLE
" . $s['style'] . "

BUSINESS FACTS
" . $s['facts'];
}

function ww_length_hint( $length, $kind ) {
	if ( 'post' === $kind ) {
		$map = array( 'short' => 'about 400 words', 'medium' => 'about 700 words', 'long' => 'about 1,100 words' );
	} else {
		$map = array( 'short' => '3 to 4 sections in total', 'medium' => '5 to 6 sections in total', 'long' => '7 to 9 sections in total' );
	}
	return $map[ $length ];
}

/**
 * POST to the Messages API. On a 400 the request steps down to simpler forms,
 * so one unsupported option can never stop a page being written.
 */
function ww_claude_request( array $s, array $body, $want_json_schema = null ) {
	$attempts = array();
	$full     = $body;
	if ( $want_json_schema ) {
		$full['output_config']['format'] = array( 'type' => 'json_schema', 'schema' => $want_json_schema );
	}
	$attempts[] = array( $full, true );
	$attempts[] = array( $full, false );
	if ( $want_json_schema ) {
		$plain = $body;
		$plain['system'] .= "\n\nOUTPUT FORMAT\nReply with only a JSON object (no code fences, no other text) matching this JSON schema:\n" . wp_json_encode( ww_page_schema() );
		$attempts[] = array( $plain, false );
		unset( $plain['output_config'] );
		$attempts[] = array( $plain, false );
	}

	$last = null;
	foreach ( $attempts as $a ) {
		list( $req, $with_fallbacks ) = $a;
		$headers = array(
			'x-api-key'         => $s['api_key'],
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
			// Lets the request run from a browser-based WordPress (e.g. WordPress Playground). Harmless on a normal server.
			'anthropic-dangerous-direct-browser-access' => 'true',
		);
		if ( $with_fallbacks ) {
			$req['fallbacks']         = 'default';
			$headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
		}
		$res = wp_remote_post(
			WW_API_URL,
			array(
				'timeout' => 240,
				'headers' => $headers,
				'body'    => wp_json_encode( $req ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'ww_net', 'Could not reach Claude: ' . $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 === $code && is_array( $data ) ) {
			return $data;
		}
		$msg  = isset( $data['error']['message'] ) ? $data['error']['message'] : 'HTTP ' . $code;
		$last = new WP_Error( 'ww_api', ww_friendly_error( $code, $msg ) );
		if ( 400 !== $code ) {
			return $last;
		}
	}
	return $last;
}

function ww_friendly_error( $code, $msg ) {
	if ( 401 === $code ) {
		return 'The API key was not accepted. Check it under Westway → Claude settings.';
	}
	if ( 403 === $code ) {
		return 'This API key is not allowed to use Claude yet (' . $msg . ').';
	}
	if ( 429 === $code ) {
		return 'Too many requests, or the account has run out of credit. Wait a minute and try again, or check billing in your Anthropic account. (' . $msg . ')';
	}
	if ( $code >= 500 ) {
		return 'Claude is busy right now. Please try again in a minute. (' . $msg . ')';
	}
	if ( false !== stripos( $msg, 'credit' ) ) {
		return 'Your Anthropic account needs credit before it can create pages. (' . $msg . ')';
	}
	return $msg;
}

function ww_response_text( array $data ) {
	$text = '';
	foreach ( (array) ( isset( $data['content'] ) ? $data['content'] : array() ) as $block ) {
		if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
			$text .= $block['text'];
		}
	}
	return $text;
}

function ww_log_run( array $row ) {
	$log = get_option( 'ww_claude_log', array() );
	array_unshift( $log, array_merge( array( 'time' => time() ), $row ) );
	update_option( 'ww_claude_log', array_slice( $log, 0, 15 ), false );
}

function ww_usage_cost( array $data, $model ) {
	$m = ww_models();
	if ( empty( $data['usage'] ) || ! isset( $m[ $model ] ) ) {
		return null;
	}
	$in  = (int) ( isset( $data['usage']['input_tokens'] ) ? $data['usage']['input_tokens'] : 0 );
	$out = (int) ( isset( $data['usage']['output_tokens'] ) ? $data['usage']['output_tokens'] : 0 );
	return ( $in * $m[ $model ][1] + $out * $m[ $model ][2] ) / 1000000;
}

function ww_claude_test() {
	$s = ww_claude_settings();
	if ( ! $s['api_key'] ) {
		return new WP_Error( 'ww_key', 'Add your API key first.' );
	}
	$data = ww_claude_request(
		$s,
		array(
			'model'         => $s['model'],
			'max_tokens'    => 2000,
			'output_config' => array( 'effort' => 'low' ),
			'system'        => 'Reply with exactly: OK',
			'messages'      => array( array( 'role' => 'user', 'content' => 'Connection test.' ) ),
		)
	);
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	return 'Claude replied using ' . ( isset( $data['model'] ) ? $data['model'] : $s['model'] ) . '. You are ready to create pages.';
}

function ww_claude_create( $brief, $kind, $length ) {
	$s = ww_claude_settings();
	if ( ! $s['api_key'] ) {
		return new WP_Error( 'ww_key', 'Add your Anthropic API key under Westway → Claude settings first.' );
	}
	if ( '' === trim( $brief ) ) {
		return new WP_Error( 'ww_brief', 'Please describe the page you want.' );
	}
	$what = 'post' === $kind ? 'a blog post' : 'a website page';
	$user = "Create {$what}.\n\nBrief from the site owner:\n{$brief}\n\nLength: " . ww_length_hint( $length, $kind ) . '.';

	$data = ww_claude_request(
		$s,
		array(
			'model'         => $s['model'],
			'max_tokens'    => 16000,
			'output_config' => array( 'effort' => 'medium' ),
			'system'        => ww_system_prompt( $s ),
			'messages'      => array( array( 'role' => 'user', 'content' => $user ) ),
		),
		ww_page_schema()
	);
	if ( is_wp_error( $data ) ) {
		ww_log_run( array( 'brief' => $brief, 'error' => $data->get_error_message() ) );
		return $data;
	}
	$stop = isset( $data['stop_reason'] ) ? $data['stop_reason'] : '';
	if ( 'refusal' === $stop ) {
		$e = new WP_Error( 'ww_refusal', 'Claude declined this request. Try rewording the brief.' );
		ww_log_run( array( 'brief' => $brief, 'error' => $e->get_error_message() ) );
		return $e;
	}
	if ( 'max_tokens' === $stop ) {
		$e = new WP_Error( 'ww_long', 'The page came out too long and was cut off. Try "Short" or "Medium" length.' );
		ww_log_run( array( 'brief' => $brief, 'error' => $e->get_error_message() ) );
		return $e;
	}
	$page = ww_parse_page_json( ww_response_text( $data ) );
	if ( is_wp_error( $page ) ) {
		ww_log_run( array( 'brief' => $brief, 'error' => $page->get_error_message() ) );
		return $page;
	}

	$id = wp_insert_post(
		wp_slash( array(
			'post_type'    => $kind,
			'post_status'  => 'draft',
			'post_title'   => $page['title'],
			'post_name'    => sanitize_title( $page['slug'] ? $page['slug'] : $page['title'] ),
			'post_content' => ww_render_sections( $page['sections'] ),
		) ),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_ww_seo_title', $page['seo_title'] );
	update_post_meta( $id, '_ww_meta_description', $page['meta_description'] );
	update_post_meta( $id, '_ww_created_by_claude', array( 'model' => isset( $data['model'] ) ? $data['model'] : $s['model'], 'brief' => $brief, 'time' => time() ) );
	ww_log_run( array( 'brief' => $brief, 'post' => $id, 'cost' => ww_usage_cost( $data, $s['model'] ) ) );
	return $id;
}

/** Turn Claude's JSON into clean section data the renderers can trust. */
function ww_parse_page_json( $text ) {
	$text  = trim( $text );
	$start = strpos( $text, '{' );
	$end   = strrpos( $text, '}' );
	$json  = ( false !== $start && false !== $end ) ? json_decode( substr( $text, $start, $end - $start + 1 ), true ) : null;
	if ( ! is_array( $json ) || empty( $json['sections'] ) || ! is_array( $json['sections'] ) ) {
		return new WP_Error( 'ww_parse', 'Claude replied, but not in the expected format. Please try again.' );
	}
	$clean = function ( $v ) use ( &$clean ) {
		if ( is_array( $v ) ) {
			return array_map( $clean, $v );
		}
		return is_scalar( $v ) ? trim( wp_strip_all_tags( (string) $v ) ) : '';
	};
	$json     = $clean( $json );
	$allowed  = array( 'page_hero', 'text', 'benefits', 'cards', 'steps', 'price_table', 'before_after', 'areas', 'faq', 'photo', 'cta' );
	$sections = array();
	foreach ( $json['sections'] as $sec ) {
		if ( is_array( $sec ) && isset( $sec['type'] ) && in_array( $sec['type'], $allowed, true ) ) {
			$sections[] = $sec;
		}
	}
	if ( ! $sections ) {
		return new WP_Error( 'ww_parse', 'Claude replied without any usable sections. Please try again.' );
	}
	foreach ( array( 'title', 'slug', 'seo_title', 'meta_description' ) as $k ) {
		$json[ $k ] = isset( $json[ $k ] ) ? (string) $json[ $k ] : '';
	}
	if ( '' === $json['title'] ) {
		$json['title'] = 'New page';
	}
	$json['sections'] = $sections;
	return $json;
}
