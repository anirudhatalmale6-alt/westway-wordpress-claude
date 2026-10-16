<?php
/**
 * The Westway section library.
 *
 * One renderer per section. The homepage, the "Westway sections" patterns in
 * the editor and every page Claude creates are all built from these, which is
 * why new pages look like the homepage.
 */

defined( 'ABSPATH' ) || exit;

function ww_render_section( array $s ) {
	$type = isset( $s['type'] ) ? $s['type'] : '';
	$fn   = 'ww_sec_' . $type;
	return function_exists( $fn ) ? $fn( $s ) : '';
}

function ww_render_sections( array $sections ) {
	return implode( "\n\n", array_filter( array_map( 'ww_render_section', $sections ) ) );
}

function ww_v( array $s, $key, $default = '' ) {
	return isset( $s[ $key ] ) && '' !== $s[ $key ] ? $s[ $key ] : $default;
}

/** Fill in any missing keys so a half-formed item can never break a page. */
function ww_item( $it ) {
	$it = is_array( $it ) ? $it : array();
	foreach ( array( 'title', 'text', 'footnote', 'q', 'a', 'caption', 'name', 'price', 'time', 'quote', 'detail' ) as $k ) {
		if ( ! isset( $it[ $k ] ) || ! is_scalar( $it[ $k ] ) ) {
			$it[ $k ] = '';
		}
	}
	return $it;
}

/* ---- Renderers --------------------------------------------------------- */

function ww_sec_hero( $s ) {
	$inner = array(
		ww_p( ww_v( $s, 'kicker' ), 'ww-kicker' ),
		ww_heading( ww_v( $s, 'title' ), 1 ),
		ww_p( ww_v( $s, 'intro' ), 'ww-lead' ),
		ww_shortcode( '[westway_quote_form]' ),
	);
	if ( ! empty( $s['trust'] ) ) {
		$inner[] = ww_list( $s['trust'], 'ww-trust' );
	}
	return ww_section( 'ww-hero', $inner, 'top' );
}

function ww_sec_page_hero( $s ) {
	$inner = array();
	if ( ww_v( $s, 'kicker' ) ) {
		$inner[] = ww_p( $s['kicker'], 'ww-kicker' );
	}
	$inner[] = ww_heading( ww_v( $s, 'title' ), 1 );
	if ( ww_v( $s, 'intro' ) ) {
		$inner[] = ww_p( $s['intro'], 'ww-lead' );
	}
	if ( ww_v( $s, 'button_text' ) ) {
		$inner[] = ww_buttons( array( array( $s['button_text'], home_url( '/#quote' ) ) ) );
	}
	return ww_section( 'ww-pagehero', $inner );
}

function ww_sec_photo( $s ) {
	$label = ww_v( $s, 'description', 'Photo' );
	return ww_section( 'ww-photo', array( ww_image( ww_placeholder_src( '[PHOTO — ' . $label . ']' ), 'Photo placeholder: ' . $label ) ) );
}

function ww_sec_benefits( $s ) {
	$items = array();
	foreach ( (array) ww_v( $s, 'items', array() ) as $it ) {
		$it = ww_item( $it );
		$items[] = ww_group( array( ww_heading( $it['title'], 3 ), ww_p( $it['text'] ) ), 'ww-benefit' );
	}
	return ww_section( 'ww-benefits', array( ww_heading( ww_v( $s, 'heading' ) ), ww_group( $items, 'ww-grid4' ) ) );
}

function ww_sec_cards( $s ) {
	$cards = array();
	foreach ( (array) ww_v( $s, 'items', array() ) as $it ) {
		$it = ww_item( $it );
		$c = array( ww_heading( $it['title'], 3 ), ww_p( $it['text'] ) );
		if ( ! empty( $it['footnote'] ) ) {
			$c[] = ww_p( $it['footnote'], 'ww-card-foot' );
		}
		$cards[] = ww_group( $c, 'ww-card', 'article' );
	}
	$head = array( ww_heading( ww_v( $s, 'heading' ) ) );
	if ( ww_v( $s, 'intro' ) ) {
		$head[] = ww_p( $s['intro'] );
	}
	return ww_section( 'ww-cards ww-border-top', array( ww_group( $head, 'ww-head' ), ww_group( $cards, 'ww-cards-grid' ) ), ww_v( $s, 'anchor', null ) );
}

function ww_sec_steps( $s ) {
	$steps = array();
	$n     = 0;
	foreach ( (array) ww_v( $s, 'items', array() ) as $it ) {
		$it = ww_item( $it );
		$n++;
		$steps[] = ww_group( array( ww_p( sprintf( '%02d', $n ), 'ww-step-no' ), ww_heading( $it['title'], 3 ), ww_p( $it['text'] ) ), 'ww-step' );
	}
	return ww_section( 'ww-steps', array( ww_heading( ww_v( $s, 'heading' ) ), ww_group( $steps, 'ww-steps-grid' ) ), ww_v( $s, 'anchor', null ) );
}

function ww_sec_price_table( $s ) {
	$copy = array( ww_heading( ww_v( $s, 'heading' ) ) );
	foreach ( (array) ww_v( $s, 'paragraphs', array() ) as $p ) {
		$copy[] = ww_p( $p );
	}
	$copy[] = ww_buttons( array( array( ww_v( $s, 'button_text', 'Book a free survey' ), home_url( '/#quote' ) ) ) );
	$rows = array();
	foreach ( (array) ww_v( $s, 'rows', array() ) as $r ) {
		$r = ww_item( $r );
		$rows[] = array( $r['name'], $r['price'], $r['time'] );
	}
	return ww_section( 'ww-pricing', array( ww_group( $copy, 'ww-pricing-copy' ), ww_table( array( 'Conversion', 'Guide price', 'Build time' ), $rows ) ), ww_v( $s, 'anchor', null ) );
}

function ww_sec_before_after( $s ) {
	$figs = array();
	foreach ( (array) ww_v( $s, 'items', array() ) as $i => $it ) {
		$it = ww_item( $it );
		$pair   = ww_group(
			array(
				ww_image( ww_placeholder_src( '[BEFORE PHOTO]', 300, 400, 'E8E0CE' ), 'Before photo placeholder', 'Before' ),
				ww_image( ww_placeholder_src( '[AFTER PHOTO]', 300, 400, 'DCD3BE' ), 'After photo placeholder', 'After' ),
			),
			'ww-pair'
		);
		$figs[] = ww_group( array( $pair, ww_p( $it['title'], 'ww-project-title' ), ww_p( $it['caption'], 'ww-project-meta' ) ), 'ww-project' );
	}
	$head = array( ww_group( array( ww_heading( ww_v( $s, 'heading' ) ), ww_p( ww_v( $s, 'intro' ), 'ww-lead' ) ), 'ww-head-stack' ) );
	if ( ! empty( $s['buttons'] ) ) {
		$head[] = ww_buttons( $s['buttons'] );
	}
	return ww_section( 'ww-projects ww-border-top', array( ww_group( $head, 'ww-head' ), ww_group( $figs, 'ww-projects-grid' ) ) );
}

function ww_sec_reviews( $s ) {
	$cards = array();
	foreach ( (array) ww_v( $s, 'items', array() ) as $it ) {
		$it = ww_item( $it );
		$cards[] = ww_group(
			array(
				ww_p( '“' . $it['quote'] . '”', 'ww-review-quote' ),
				ww_p_html( '<strong>' . ww_t( $it['name'] ) . '</strong><br><span>' . ww_t( $it['detail'] ) . '</span>', 'ww-review-by' ),
			),
			'ww-review',
			'figure'
		);
	}
	$rating = ww_group(
		array(
			ww_p( '{rating}', 'ww-rating-score' ),
			ww_p_html( '{reviews} Google reviews for {company_short} · <a href="#">Read them all</a>', 'ww-rating-text' ),
		),
		'ww-rating'
	);
	$inner  = array( ww_group( array( ww_heading( ww_v( $s, 'heading' ) ), $rating ), 'ww-head' ), ww_group( $cards, 'ww-reviews-grid' ) );
	if ( ! empty( $s['logos'] ) ) {
		$inner[] = ww_list( $s['logos'], 'ww-logos' );
	}
	return ww_section( 'ww-reviews ww-border-top', $inner );
}

function ww_sec_areas( $s ) {
	return ww_section( 'ww-areas', array( ww_heading( ww_v( $s, 'heading' ) ), ww_list( (array) ww_v( $s, 'areas', array() ), 'ww-chips' ) ), 'areas' );
}

function ww_sec_guide( $s ) {
	$copy = ww_group(
		array(
			ww_p( ww_v( $s, 'kicker', 'Free guide' ), 'ww-kicker' ),
			ww_heading( ww_v( $s, 'heading' ) ),
			ww_p( ww_v( $s, 'text' ) ),
		),
		'ww-guide-copy'
	);
	return ww_section( 'ww-guide', array( ww_group( array( $copy, ww_shortcode( '[westway_guide_form]' ) ), 'ww-guide-box' ) ) );
}

function ww_sec_faq( $s ) {
	$items = array();
	foreach ( (array) ww_v( $s, 'items', array() ) as $it ) {
		$it = ww_item( $it );
		$items[] = ww_details( $it['q'], $it['a'] );
	}
	return ww_section( 'ww-faq', array( ww_heading( ww_v( $s, 'heading', 'Common questions.' ) ), ww_group( $items, 'ww-faq-list' ) ) );
}

function ww_sec_cta( $s ) {
	$box = ww_group(
		array(
			ww_heading( ww_v( $s, 'heading', 'Find out what your loft could be.' ) ),
			ww_p( ww_v( $s, 'text', 'Free survey, fixed written price, no obligation.' ) ),
			ww_buttons( array( array( 'Book a free survey', home_url( '/#quote' ) ), array( 'Call {phone}', 'tel:{phone_link}', true ) ) ),
		),
		'ww-cta-box'
	);
	return ww_section( 'ww-cta', array( $box ) );
}

function ww_sec_text( $s ) {
	$inner = array();
	if ( ww_v( $s, 'heading' ) ) {
		$inner[] = ww_heading( $s['heading'] );
	}
	foreach ( (array) ww_v( $s, 'paragraphs', array() ) as $p ) {
		$inner[] = ww_p( $p );
	}
	if ( ! empty( $s['bullets'] ) ) {
		$inner[] = ww_list( $s['bullets'] );
	}
	return ww_section( 'ww-text', $inner );
}

/* ---- The homepage, exactly as designed ---------------------------------- */

function ww_homepage_sections() {
	return array(
		array(
			'type'   => 'hero',
			'kicker' => 'By Westway Construction · Building in West London since [20XX]',
			'title'  => 'More room, without moving house.',
			'intro'  => 'Dormer, hip-to-gable and mansard conversions from Westway Construction, building across West London for over a decade. Fixed prices, building control sign-off, and a site manager you can actually reach.',
			'trust'  => array( '{rating} on Google · {reviews} reviews', '[10+] years · [N] lofts completed', '[10]-year insurance-backed guarantee', 'Fixed price in writing', '[FMB · TrustMark] member' ),
		),
		array( 'type' => 'photo', 'description' => 'finished loft bedroom, wide shot, natural light' ),
		array(
			'type'    => 'benefits',
			'heading' => 'What you get with Westway.',
			'items'   => array(
				array( 'title' => 'Fixed price, in writing', 'text' => 'One number after the survey, with every inclusion and exclusion listed. No surprise extras halfway through.' ),
				array( 'title' => 'One project manager', 'text' => '[Name], one phone number, from first visit to sign-off. Photo updates every week.' ),
				array( 'title' => 'Pay in stages', 'text' => 'You pay as each stage is finished and checked. Never large sums up front.' ),
				array( 'title' => 'Insurance-backed guarantee', 'text' => '[10]-year cover on the work, which still protects you if the builder stops trading.' ),
			),
		),
		array(
			'type'    => 'cards',
			'anchor'  => 'types',
			'heading' => 'Four ways to convert a loft.',
			'intro'   => 'The right one depends on your roof shape, house type and planning position. We tell you which fits at the survey.',
			'items'   => array(
				array( 'title' => 'Rear dormer', 'text' => 'A box extension off the back slope. The most common choice for London terraces and semis. Adds full head height.', 'footnote' => 'From [£PRICE]' ),
				array( 'title' => 'Hip-to-gable', 'text' => 'Straightens a sloping side roof into a vertical wall. Suits semis and detached houses. Often paired with a dormer.', 'footnote' => 'From [£PRICE]' ),
				array( 'title' => 'Mansard', 'text' => 'Rebuilds the roof with a near-vertical back wall. The most space, but usually needs full planning permission.', 'footnote' => 'From [£PRICE]' ),
				array( 'title' => 'Roof light', 'text' => 'No change to the roof shape, just windows set into the slope. Cheapest and quickest, if you already have the height.', 'footnote' => 'From [£PRICE]' ),
			),
		),
		array(
			'type'    => 'steps',
			'anchor'  => 'process',
			'heading' => 'One team from survey to sign-off.',
			'items'   => array(
				array( 'title' => 'Free survey', 'text' => 'We measure the loft, check head height and roof structure, and give you a fixed written price.' ),
				array( 'title' => 'Design & approvals', 'text' => 'Drawings, structural calcs, planning or a lawful development certificate, and party wall notices where needed.' ),
				array( 'title' => 'Build', 'text' => 'Most of the work happens from scaffolding, so you can usually stay at home. Weekly updates from your site manager.' ),
				array( 'title' => 'Sign-off', 'text' => 'Building control completion certificate and a [X]-year workmanship guarantee.' ),
			),
		),
		array(
			'type'       => 'price_table',
			'anchor'     => 'pricing',
			'heading'    => 'Clear prices, before you commit.',
			'paragraphs' => array(
				'Guide prices for a typical West London house. Your survey gives you a fixed figure, with everything that is and isn\'t included written down.',
				'A loft can add around [X]% to a home\'s value ([SOURCE]). Ask us for a value estimate for your street.',
			),
			'rows'       => array(
				array( 'name' => 'Roof light', 'price' => '[£X – £Y]', 'time' => '[X] weeks' ),
				array( 'name' => 'Rear dormer', 'price' => '[£X – £Y]', 'time' => '[X] weeks' ),
				array( 'name' => 'Hip-to-gable + dormer', 'price' => '[£X – £Y]', 'time' => '[X] weeks' ),
				array( 'name' => 'Mansard', 'price' => '[£X – £Y]', 'time' => '[X] weeks' ),
			),
		),
		array(
			'type'    => 'before_after',
			'heading' => 'Before and after.',
			'intro'   => 'Real lofts near you, with what was added and how long it took.',
			'buttons' => array( array( 'Watch video tours', '#', true ), array( 'All projects', '#', true ) ),
			'items'   => array(
				array( 'title' => '[Dormer] · [Area]', 'caption' => '[Added: bedroom + en-suite] · [X] m² · [X] weeks' ),
				array( 'title' => '[Hip-to-gable] · [Area]', 'caption' => '[Added: bedroom + en-suite] · [X] m² · [X] weeks' ),
				array( 'title' => '[Mansard] · [Area]', 'caption' => '[Added: bedroom + en-suite] · [X] m² · [X] weeks' ),
			),
		),
		array(
			'type'    => 'reviews',
			'heading' => 'What homeowners say.',
			'items'   => array_fill( 0, 3, array( 'quote' => '[Paste a real review here. Two or three sentences is best.]', 'name' => '[Customer name]', 'detail' => '[Area] · [Loft type]' ) ),
			'logos'   => array( '[FMB logo]', '[TrustMark logo]', '[Guarantee provider logo]', '[Velux installer logo]', '[Insurance logo]' ),
		),
		array(
			'type'    => 'areas',
			'heading' => 'Working along the Westway and across West London.',
			'areas'   => ww_default_areas(),
		),
		array(
			'type'    => 'guide',
			'heading' => 'The West London Loft Guide',
			'text'    => 'Planning rules by borough, party wall steps, real costs, and a checklist for comparing builders\' quotes. Sent straight to your inbox.',
		),
		array(
			'type'    => 'faq',
			'heading' => 'Common questions.',
			'items'   => array(
				array( 'q' => 'Do I need planning permission?', 'a' => 'Many lofts fall under permitted development, so no full application is needed. Mansards, front-facing changes, flats and houses in conservation areas usually do need permission. We check this at the survey.' ),
				array( 'q' => 'Will I need a party wall agreement?', 'a' => 'If you share a wall with a neighbour, as most terraces and semis do, notice is normally required under the Party Wall Act. We handle the notices for you.' ),
				array( 'q' => 'Can we live at home during the build?', 'a' => 'Usually, yes. Access is from scaffolding until the stairs go in near the end.' ),
				array( 'q' => 'How much head height do I need?', 'a' => '[YOUR ANSWER — minimum height you work with, and options if the loft is too low]' ),
			),
		),
		array( 'type' => 'cta' ),
	);
}

function ww_default_areas() {
	return array( 'Hillingdon', 'Ealing', 'Hammersmith & Fulham', 'Kensington & Chelsea', 'Brent', 'Hounslow', 'Harrow', 'Westminster' );
}

/** Pattern list for the editor's "Westway sections" category. */
function ww_pattern_sections() {
	$home   = ww_homepage_sections();
	$titles = array(
		'hero'         => 'Hero with quote form',
		'photo'        => 'Wide photo',
		'benefits'     => 'What you get (4 points)',
		'cards'        => 'Cards (loft types)',
		'steps'        => 'Steps (dark band)',
		'price_table'  => 'Price table',
		'before_after' => 'Before and after',
		'reviews'      => 'Reviews and logos',
		'areas'        => 'Areas we cover',
		'guide'        => 'Free guide signup',
		'faq'          => 'Questions and answers',
		'cta'          => 'Call to action box',
	);
	$out = array();
	foreach ( $home as $s ) {
		$out[ $s['type'] ] = array( 'title' => $titles[ $s['type'] ], 'section' => $s );
	}
	$out['page_hero'] = array(
		'title'   => 'Page heading',
		'section' => array( 'type' => 'page_hero', 'kicker' => 'Loft conversions', 'title' => 'Page title goes here.', 'intro' => 'One or two sentences introducing the page.', 'button_text' => 'Book a free survey' ),
	);
	$out['text']      = array(
		'title'   => 'Text section',
		'section' => array( 'type' => 'text', 'heading' => 'A section heading', 'paragraphs' => array( 'Write a paragraph here.' ), 'bullets' => array( 'A point', 'Another point' ) ),
	);
	return $out;
}
