<?php
defined('ABSPATH') or die('No script kiddies please!');

add_action('init', 'pcsd_school_fees_register_taxonomy');
add_action('school_fee_year_edit_form_fields', 'pcsd_school_fee_year_edit_archived_field', 10, 2);
add_action('school_fee_year_add_form_fields', 'pcsd_school_fee_year_add_archived_field');
add_action('edited_school_fee_year', 'pcsd_school_fee_year_save_archived_field');
add_action('create_school_fee_year', 'pcsd_school_fee_year_save_archived_field');
add_filter('manage_edit-school_fee_year_columns', 'pcsd_school_fee_year_archived_column');
add_filter('manage_school_fee_year_custom_column', 'pcsd_school_fee_year_archived_column_value', 10, 3);

function pcsd_school_fees_register_taxonomy()
{
	// Shared fee-category taxonomy. Slug matches the legacy DB value so existing
	// term_relationships are preserved. Rewrite slug is clean for public URLs.
	register_taxonomy('school_fees_categories', ['school_fees', 'pagos_escolares'], [
		'labels' => [
			'name'          => __('Fee Categories', 'pcsd-school-fees'),
			'singular_name' => __('Fee Category', 'pcsd-school-fees'),
			'menu_name'     => __('Categories', 'pcsd-school-fees'),
		],
		'public'            => true,
		'publicly_queryable' => true,
		'hierarchical'      => false,
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => ['slug' => 'school-fee-category'],
	]);

	register_taxonomy('school_fee_year', ['school_fees', 'pagos_escolares'], [
		'label' => __('School Fee Years', 'pcsd-school-fees'),
		'labels' => [
			'name' => __('School Fee Years', 'pcsd-school-fees'),
			'singular_name' => __('School Fee Year', 'pcsd-school-fees'),
			'add_new_item' => __('Add New Year', 'pcsd-school-fees'),
			'edit_item' => __('Edit Year', 'pcsd-school-fees'),
			'menu_name' => __('Years', 'pcsd-school-fees'),
		],
		'public' => true,
		'publicly_queryable' => true,
		'hierarchical' => false,
		'show_ui' => true,
		'show_in_menu' => true,
		'show_in_nav_menus' => true,
		'show_admin_column' => true,
		'show_in_rest' => true,
		'rest_base' => 'school_fee_year',
		'rest_controller_class' => 'WP_REST_Terms_Controller',
		'query_var' => true,
		// The year already appears in the post permalink (/school-fees/26-27/<slug>/),
		// so we don't need a separate /school_fee_year/26-27/ archive URL.
		'rewrite' => false,
		'show_in_quick_edit' => false,
	]);

	register_term_meta('school_fee_year', 'archived', [
		'type' => 'boolean',
		'description' => __('When true, the year is hidden from public year-pickers but URLs continue to work.', 'pcsd-school-fees'),
		'single' => true,
		'show_in_rest' => true,
		'default' => false,
	]);
}

function pcsd_school_fee_year_add_archived_field()
{
	?>
	<div class="form-field">
		<label for="archived">
			<input type="checkbox" name="archived" id="archived" value="1" />
			<?php esc_html_e('Archived', 'pcsd-school-fees'); ?>
		</label>
		<p><?php esc_html_e('Hide this year from public year-pickers. URLs and posts continue to work.', 'pcsd-school-fees'); ?></p>
	</div>
	<?php
}

function pcsd_school_fee_year_edit_archived_field($term)
{
	$value = (bool) get_term_meta($term->term_id, 'archived', true);
	?>
	<tr class="form-field">
		<th scope="row"><label for="archived"><?php esc_html_e('Archived', 'pcsd-school-fees'); ?></label></th>
		<td>
			<input type="checkbox" name="archived" id="archived" value="1" <?php checked($value, true); ?> />
			<p class="description"><?php esc_html_e('Hide this year from public year-pickers. URLs and posts continue to work.', 'pcsd-school-fees'); ?></p>
		</td>
	</tr>
	<?php
}

function pcsd_school_fee_year_save_archived_field($term_id)
{
	$value = isset($_POST['archived']) ? 1 : 0;
	update_term_meta($term_id, 'archived', $value);
}

function pcsd_school_fee_year_archived_column($columns)
{
	$columns['archived'] = __('Archived', 'pcsd-school-fees');
	return $columns;
}

function pcsd_school_fee_year_archived_column_value($content, $column_name, $term_id)
{
	if ($column_name === 'archived') {
		return get_term_meta($term_id, 'archived', true) ? esc_html__('Yes', 'pcsd-school-fees') : '';
	}
	return $content;
}
