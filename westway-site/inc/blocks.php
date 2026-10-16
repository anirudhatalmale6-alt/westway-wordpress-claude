<?php
/**
 * Tiny helpers that write core-block markup exactly the way the block editor
 * saves it, so every section opens in the editor as normal, editable blocks.
 */

defined( 'ABSPATH' ) || exit;

function ww_attrs( array $attrs ) {
	$attrs = array_filter( $attrs, function ( $v ) { return null !== $v && '' !== $v; } );
	return $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
}

function ww_t( $text ) {
	return esc_html( (string) $text );
}

/** Group block. $tag: div|section|figure|article. */
function ww_group( $inner, $class = '', $tag = 'div', $anchor = null ) {
	$attrs = array();
	if ( 'div' !== $tag ) {
		$attrs['tagName'] = $tag;
	}
	if ( $anchor ) {
		$attrs['anchor'] = $anchor;
	}
	if ( $class ) {
		$attrs['className'] = $class;
	}
	$attrs['layout'] = array( 'type' => 'default' );
	$id    = $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '';
	$cls   = trim( 'wp-block-group ' . $class );
	return '<!-- wp:group' . ww_attrs( $attrs ) . " -->\n<{$tag}{$id} class=\"" . esc_attr( $cls ) . "\">" . implode( "\n\n", (array) $inner ) . "</{$tag}>\n<!-- /wp:group -->";
}

function ww_heading( $text, $level = 2, $class = '' ) {
	$attrs = array();
	if ( 2 !== $level ) {
		$attrs['level'] = $level;
	}
	if ( $class ) {
		$attrs['className'] = $class;
	}
	$cls = trim( 'wp-block-heading ' . $class );
	return '<!-- wp:heading' . ww_attrs( $attrs ) . " -->\n<h{$level} class=\"" . esc_attr( $cls ) . "\">" . ww_t( $text ) . "</h{$level}>\n<!-- /wp:heading -->";
}

/** Paragraph. $html is already-safe inline HTML (use ww_t() for plain text). */
function ww_p_html( $html, $class = '' ) {
	$attrs = $class ? array( 'className' => $class ) : array();
	$open  = $class ? '<p class="' . esc_attr( $class ) . '">' : '<p>';
	return '<!-- wp:paragraph' . ww_attrs( $attrs ) . " -->\n{$open}{$html}</p>\n<!-- /wp:paragraph -->";
}

function ww_p( $text, $class = '' ) {
	return ww_p_html( ww_t( $text ), $class );
}

/** List block. Items are already-safe inline HTML. */
function ww_list_html( array $items, $class = '', $ordered = false ) {
	$attrs = array();
	if ( $ordered ) {
		$attrs['ordered'] = true;
	}
	if ( $class ) {
		$attrs['className'] = $class;
	}
	$tag = $ordered ? 'ol' : 'ul';
	$lis = array();
	foreach ( $items as $item ) {
		$lis[] = "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->";
	}
	$cls = trim( 'wp-block-list ' . $class );
	return '<!-- wp:list' . ww_attrs( $attrs ) . " -->\n<{$tag} class=\"" . esc_attr( $cls ) . "\">" . implode( "\n\n", $lis ) . "</{$tag}>\n<!-- /wp:list -->";
}

function ww_list( array $items, $class = '' ) {
	return ww_list_html( array_map( 'ww_t', $items ), $class );
}

/** Buttons. $buttons: [ [text, url, outline?], ... ] */
function ww_buttons( array $buttons ) {
	$out = array();
	foreach ( $buttons as $b ) {
		$outline = ! empty( $b[2] );
		$attrs   = $outline ? array( 'className' => 'is-style-outline' ) : array();
		$cls     = $outline ? 'wp-block-button is-style-outline' : 'wp-block-button';
		$out[]   = '<!-- wp:button' . ww_attrs( $attrs ) . " -->\n<div class=\"{$cls}\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_attr( $b[1] ) . '">' . ww_t( $b[0] ) . "</a></div>\n<!-- /wp:button -->";
	}
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">" . implode( "\n\n", $out ) . "</div>\n<!-- /wp:buttons -->";
}

function ww_details( $summary, $answer ) {
	return "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . ww_t( $summary ) . "</summary>" . ww_p( $answer ) . "</details>\n<!-- /wp:details -->";
}

function ww_shortcode( $code ) {
	return "<!-- wp:shortcode -->\n{$code}\n<!-- /wp:shortcode -->";
}

/** Table block with a header row. */
function ww_table( array $head, array $rows ) {
	$h = '<tr>' . implode( '', array_map( function ( $c ) { return '<th>' . ww_t( $c ) . '</th>'; }, $head ) ) . '</tr>';
	$b = '';
	foreach ( $rows as $r ) {
		$b .= '<tr>' . implode( '', array_map( function ( $c ) { return '<td>' . ww_t( $c ) . '</td>'; }, $r ) ) . '</tr>';
	}
	return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\"><thead>{$h}</thead><tbody>{$b}</tbody></table></figure>\n<!-- /wp:table -->";
}

/** A beige photo placeholder (served by the plugin) that the editor can swap for a real photo. */
function ww_placeholder_src( $label, $w = 1200, $h = 520, $fill = 'E8E0CE' ) {
	return add_query_arg(
		array( 'ww_ph' => rawurlencode( $label ), 'w' => (int) $w, 'h' => (int) $h, 'f' => ltrim( $fill, '#' ) ),
		home_url( '/' )
	);
}

function ww_image( $src, $alt, $caption = '', $class = '' ) {
	$attrs = $class ? array( 'className' => $class ) : array();
	$cls   = trim( 'wp-block-image ' . $class );
	$cap   = '' !== $caption ? '<figcaption class="wp-element-caption">' . ww_t( $caption ) . '</figcaption>' : '';
	return '<!-- wp:image' . ww_attrs( $attrs ) . " -->\n<figure class=\"" . esc_attr( $cls ) . '"><img src="' . esc_attr( $src ) . '" alt="' . esc_attr( $alt ) . "\"/>{$cap}</figure>\n<!-- /wp:image -->";
}

/** Outer section + centred inner wrapper, the shape every section shares. */
function ww_section( $type_class, array $inner, $anchor = null, $wrap_class = '' ) {
	return ww_group( ww_group( $inner, trim( 'ww-wrap ' . $wrap_class ) ), 'ww-sec ' . $type_class, 'section', $anchor );
}
