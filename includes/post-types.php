<?php
defined('ABSPATH') or die('No script kiddies please!');

add_action('init', 'pcsd_school_fees_register_post_types');

function pcsd_school_fees_register_post_types()
{
	$icon = 'https://globalassets.provo.edu/image/icons/pcsd-icon-16x16.png';

	register_post_type('school_fees', [
		'label' => __('School Fees', 'pcsd-school-fees'),
		'labels' => [
			'name' => __('School Fees', 'pcsd-school-fees'),
			'singular_name' => __('Program', 'pcsd-school-fees'),
			'add_new_item' => __('Add New Program', 'pcsd-school-fees'),
			'edit_item' => __('Edit Program', 'pcsd-school-fees'),
			'new_item' => __('New Program', 'pcsd-school-fees'),
			'all_items' => __('All Programs', 'pcsd-school-fees'),
			'menu_name' => __('School Fees', 'pcsd-school-fees'),
		],
		'description' => __('A school fee program (e.g. "Culinary"). One post per program; each program holds yearly fee data in the Yearly Fee Data repeater.', 'pcsd-school-fees'),
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true,
		'show_in_rest' => true,
		'rest_controller_class' => 'WP_REST_Posts_Controller',
		'has_archive' => false,
		'show_in_menu' => true,
		'show_in_nav_menus' => true,
		'delete_with_user' => false,
		'exclude_from_search' => false,
		'capability_type' => 'post',
		'map_meta_cap' => true,
		'hierarchical' => false,
		'rewrite' => [
			'slug' => 'school-fees',
			'with_front' => true,
		],
		'query_var' => true,
		'menu_icon' => $icon,
		'supports' => ['title', 'thumbnail'],
		'taxonomies' => ['school_fee_year'],
	]);

	register_post_type('pagos_escolares', [
		'label' => __('Pagos Escolares', 'pcsd-school-fees'),
		'labels' => [
			'name' => __('Pagos Escolares', 'pcsd-school-fees'),
			'singular_name' => __('Programa', 'pcsd-school-fees'),
			'add_new_item' => __('Añadir Nuevo Programa', 'pcsd-school-fees'),
			'edit_item' => __('Editar Programa', 'pcsd-school-fees'),
			'new_item' => __('Nuevo Programa', 'pcsd-school-fees'),
			'all_items' => __('Todos los Programas', 'pcsd-school-fees'),
			'menu_name' => __('Pagos Escolares', 'pcsd-school-fees'),
		],
		'description' => __('Spanish-language fee programs. Parallel to school_fees; remove this post type and its templates if/when TranslatePress takes over translation.', 'pcsd-school-fees'),
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true,
		'show_in_rest' => true,
		'rest_controller_class' => 'WP_REST_Posts_Controller',
		'has_archive' => false,
		'show_in_menu' => true,
		'show_in_nav_menus' => true,
		'delete_with_user' => false,
		'exclude_from_search' => false,
		'capability_type' => 'post',
		'map_meta_cap' => true,
		'hierarchical' => false,
		'rewrite' => [
			'slug' => 'pagos-escolares',
			'with_front' => true,
		],
		'query_var' => true,
		'menu_icon' => $icon,
		'supports' => ['title', 'thumbnail'],
		'taxonomies' => ['school_fee_year'],
	]);
}
