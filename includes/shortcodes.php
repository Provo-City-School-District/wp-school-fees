<?php
defined('ABSPATH') or die();

add_shortcode('school_fees_year_list', 'pcsd_school_fees_year_list_shortcode');
add_shortcode('pagos_escolares_year_list', 'pcsd_pagos_escolares_year_list_shortcode');

function pcsd_school_fees_year_list_shortcode() {
	return pcsd_render_year_list('school-fees', 'School Fees Fiscal Year');
}

function pcsd_pagos_escolares_year_list_shortcode() {
	return pcsd_render_year_list('pagos-escolares', 'Cuotas Escolares Año Fiscal');
}

function pcsd_render_year_list($url_prefix, $label_prefix) {
	$terms = get_terms([
		'taxonomy'   => 'school_fee_year',
		'hide_empty' => false,
	]);

	if (is_wp_error($terms) || empty($terms)) {
		return '';
	}

	$terms = array_filter($terms, fn($t) => !get_term_meta($t->term_id, 'archived', true));

	if (empty($terms)) {
		return '';
	}

	usort($terms, fn($a, $b) => strcmp($b->slug, $a->slug));

	$items = '';
	foreach ($terms as $term) {
		$url    = esc_url(home_url('/' . $url_prefix . '-' . $term->slug . '/'));
		$label  = esc_html($label_prefix . ' ' . $term->name);
		$items .= "\t<li><a href=\"{$url}\">{$label}</a></li>\n";
	}

	return "<ul>\n{$items}</ul>";
}
