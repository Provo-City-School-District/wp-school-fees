<?php
defined('ABSPATH') or die('No script kiddies please!');

add_action('restrict_manage_posts', 'pcsd_school_fees_year_filter');
add_filter('parse_query', 'pcsd_school_fees_year_filter_query');

function pcsd_school_fees_year_filter()
{
	global $typenow;
	if (!in_array($typenow, ['school_fees', 'pagos_escolares'], true)) {
		return;
	}

	$taxonomy = 'school_fee_year';
	$selected = isset($_GET[$taxonomy]) ? sanitize_text_field(wp_unslash($_GET[$taxonomy])) : '';
	$terms = get_terms([
		'taxonomy' => $taxonomy,
		'hide_empty' => false,
		'orderby' => 'name',
		'order' => 'DESC',
	]);

	if (empty($terms) || is_wp_error($terms)) {
		return;
	}

	echo '<select name="' . esc_attr($taxonomy) . '">';
	echo '<option value="">' . esc_html__('All Years', 'pcsd-school-fees') . '</option>';
	foreach ($terms as $term) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr($term->slug),
			selected($selected, $term->slug, false),
			esc_html($term->name)
		);
	}
	echo '</select>';
}

function pcsd_school_fees_year_filter_query($query)
{
	global $pagenow, $typenow;
	if ($pagenow !== 'edit.php' || !in_array($typenow, ['school_fees', 'pagos_escolares'], true)) {
		return;
	}
	if (empty($query->query_vars['school_fee_year'])) {
		return;
	}
	// Already a slug from the dropdown — let the default tax_query mechanism handle it.
}
