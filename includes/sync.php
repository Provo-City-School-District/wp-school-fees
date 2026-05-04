<?php
defined('ABSPATH') or die('No script kiddies please!');

// Tags each program with the union of all school_fee_year terms picked across its yearly_data
// rows. ACF's built-in save_terms handles one-term-per-field; we need one-per-repeater-row.

add_action('acf/save_post', 'pcsd_school_fees_sync_year_taxonomy', 20);

function pcsd_school_fees_sync_year_taxonomy($post_id)
{
	if (!is_numeric($post_id)) {
		return;
	}

	$post = get_post($post_id);
	if (!$post || !in_array($post->post_type, ['school_fees', 'pagos_escolares'], true)) {
		return;
	}

	$yearly_data = get_field('yearly_data', $post_id);

	if (empty($yearly_data) || !is_array($yearly_data)) {
		wp_set_object_terms($post_id, [], 'school_fee_year');
		return;
	}

	$year_term_ids = [];
	foreach ($yearly_data as $row) {
		if (empty($row['year'])) {
			continue;
		}
		$year = $row['year'];
		if (is_object($year) && isset($year->term_id)) {
			$year_term_ids[] = (int) $year->term_id;
		} elseif (is_array($year) && isset($year['term_id'])) {
			$year_term_ids[] = (int) $year['term_id'];
		} elseif (is_numeric($year)) {
			$year_term_ids[] = (int) $year;
		}
	}

	wp_set_object_terms($post_id, array_values(array_unique($year_term_ids)), 'school_fee_year');
}
