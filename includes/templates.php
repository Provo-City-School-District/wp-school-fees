<?php
defined('ABSPATH') or die('No script kiddies please!');

// Register plugin-owned page templates so they appear in the Page Attributes dropdown.
add_filter('theme_page_templates', 'pcsd_school_fees_register_page_templates');

// Serve templates from the plugin's templates/ directory.
add_filter('template_include', 'pcsd_school_fees_template_include');

function pcsd_school_fees_register_page_templates($templates)
{
	$templates['template-school-fee-menu.php']           = 'School Fees - Year Menu';
	$templates['template-school-fees-by-location.php']   = 'School Fees - By Location';
	$templates['template-pagos-escolares-menu.php']      = 'Pagos Escolares - Year Menu (Spanish)';
	$templates['template-pagos-escolares-by-location.php'] = 'Pagos Escolares - By Location (Spanish)';
	return $templates;
}

function pcsd_school_fees_template_include($template)
{
	if (is_page()) {
		$post     = get_queried_object();
		$assigned = get_post_meta($post->ID, '_wp_page_template', true);

		// Explicit template assignment (pages created/updated via the dropdown).
		$page_templates = [
			'template-school-fee-menu.php',
			'template-school-fees-by-location.php',
			'template-pagos-escolares-menu.php',
			'template-pagos-escolares-by-location.php',
		];
		if (in_array($assigned, $page_templates, true)) {
			$file = PCSD_SCHOOL_FEES_DIR . 'templates/' . $assigned;
			if (file_exists($file)) {
				return $file;
			}
		}

		// Slug-based fallback for legacy pages that never had a template assigned.
		$post_slug = $post->post_name;
		if (preg_match('/^school-fees-\d{2}-\d{2}$/', $post_slug)) {
			return PCSD_SCHOOL_FEES_DIR . 'templates/template-school-fee-menu.php';
		}
		if (preg_match('/^pagos-escolares-\d{2}-\d{2}$/', $post_slug)) {
			return PCSD_SCHOOL_FEES_DIR . 'templates/template-pagos-escolares-menu.php';
		}

		// Child of a year menu page → by-location template.
		if ($post->post_parent) {
			$parent_slug = get_post_field('post_name', $post->post_parent);
			if (preg_match('/^school-fees-\d{2}-\d{2}$/', $parent_slug)) {
				return PCSD_SCHOOL_FEES_DIR . 'templates/template-school-fees-by-location.php';
			}
			if (preg_match('/^pagos-escolares-\d{2}-\d{2}$/', $parent_slug)) {
				return PCSD_SCHOOL_FEES_DIR . 'templates/template-pagos-escolares-by-location.php';
			}
		}
	}

	if (is_singular('school_fees')) {
		$file = PCSD_SCHOOL_FEES_DIR . 'templates/single-school_fees.php';
		if (file_exists($file)) {
			return $file;
		}
	}

	if (is_singular('pagos_escolares')) {
		$file = PCSD_SCHOOL_FEES_DIR . 'templates/single-pagos_escolares.php';
		if (file_exists($file)) {
			return $file;
		}
	}

	if (is_tax('school_fees_categories')) {
		$file = PCSD_SCHOOL_FEES_DIR . 'templates/taxonomy-school_fees_categories.php';
		if (file_exists($file)) {
			return $file;
		}
	}

	return $template;
}
