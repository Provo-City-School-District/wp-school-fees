<?php
defined('ABSPATH') or die('No script kiddies please!');

// ACF field groups are code-registered here. Edit fields in this file, not in the ACF UI.

add_action('acf/init', 'pcsd_school_fees_register_acf_fields');

function pcsd_school_fees_register_acf_fields()
{
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}

	// Two location rules — one per CPT — so the Spanish CPT can be removed cleanly
	// without orphaning the field group on a non-existent post type.
	$program_location = [
		[['param' => 'post_type', 'operator' => '==', 'value' => 'school_fees']],
		[['param' => 'post_type', 'operator' => '==', 'value' => 'pagos_escolares']],
	];

	acf_add_local_field_group([
		'key' => 'group_pcsd_school_fees_yearly_data',
		'title' => 'School Fees - Yearly Data',
		'fields' => [
			[
				'key' => 'field_pcsd_yearly_data',
				'label' => 'Yearly Fee Data',
				'name' => 'yearly_data',
				'type' => 'repeater',
				'instructions' => 'One row per school year this program has fees. Each row collapses to its year label so it stays scannable. The "Year" dropdown is sourced from School Fees → Years.',
				'collapsed' => 'field_pcsd_yearly_year',
				'min' => 0,
				'max' => 0,
				'layout' => 'block',
				'button_label' => 'Add Year',
				'sub_fields' => [
					[
						'key' => 'field_pcsd_yearly_year',
						'label' => 'Year',
						'name' => 'year',
						'type' => 'taxonomy',
						'taxonomy' => 'school_fee_year',
						'field_type' => 'select',
						'allow_null' => 0,
						'add_term' => 1,
						// We sync taxonomy terms via our own save hook (see includes/sync.php),
						// so ACF's built-in save_terms is disabled to avoid double-writing.
						'save_terms' => 0,
						'load_terms' => 0,
						'return_format' => 'object',
						'multiple' => 0,
						'instructions' => 'Which school year this fee data applies to.',
					],
					[
						'key' => 'field_pcsd_overall_activity_fee_amounts',
						'label' => 'Overall Activity Fee',
						'name' => 'overall_activity_fee_amounts',
						'type' => 'group',
						'layout' => 'table',
						'sub_fields' => [
							[
								'key' => 'field_pcsd_overall_activity_fee',
								'label' => 'Overall Activity Fee',
								'name' => 'overall_activity_fee',
								'type' => 'text',
							],
							[
								'key' => 'field_pcsd_overall_course_fundraising',
								'label' => 'Overall Course Fundraising',
								'name' => 'overall_course_fundraising',
								'type' => 'text',
							],
							[
								'key' => 'field_pcsd_overall_course_total',
								'label' => 'Overall Course Total',
								'name' => 'overall_course_total',
								'type' => 'text',
							],
							[
								'key' => 'field_pcsd_overall_notes',
								'label' => 'Notes',
								'name' => 'notes',
								'type' => 'text',
							],
						],
					],
					[
						'key' => 'field_pcsd_location_specific_fees',
						'label' => 'Location Specific Fees',
						'name' => 'location_specific_fees',
						'type' => 'repeater',
						'instructions' => 'One row per school. Each row collapses to its school name.',
						'collapsed' => 'field_pcsd_location_specific_location',
						'min' => 0,
						'max' => 0,
						'layout' => 'block',
						'button_label' => 'Add Location',
						'sub_fields' => [
							[
								'key' => 'field_pcsd_location_specific_location',
								'label' => 'Location',
								'name' => 'location',
								'type' => 'select',
								'choices' => pcsd_school_fees_location_choices(),
								'allow_null' => 0,
								'return_format' => 'label',
							],
							[
								'key' => 'field_pcsd_breakdown_of_fees',
								'label' => 'Breakdown of Fees',
								'name' => 'breakdown_of_fees',
								'type' => 'repeater',
								'layout' => 'table',
								'button_label' => 'Add Fee',
								'sub_fields' => [
									[
										'key' => 'field_pcsd_fee_description',
										'label' => 'Fee Description',
										'name' => 'fee_description',
										'type' => 'text',
									],
									[
										'key' => 'field_pcsd_fee',
										'label' => 'Fee',
										'name' => 'fee',
										'type' => 'text',
									],
									[
										'key' => 'field_pcsd_fundraising',
										'label' => 'Fundraising',
										'name' => 'fundraising',
										'type' => 'text',
									],
									[
										'key' => 'field_pcsd_total',
										'label' => 'Total',
										'name' => 'total',
										'type' => 'text',
									],
									[
										'key' => 'field_pcsd_breakdown_notes',
										'label' => 'Notes',
										'name' => 'notes',
										'type' => 'text',
									],
									[
										'key' => 'field_pcsd_prior_year_approved_fee',
										'label' => 'Prior Year Approved Fee',
										'name' => 'prior_year_approved_fee',
										'type' => 'text',
									],
									[
										'key' => 'field_pcsd_bold_line',
										'label' => 'Bold Row?',
										'name' => 'bold_line',
										'type' => 'true_false',
										'ui' => 1,
									],
								],
							],
						],
					],
				],
			],
		],
		'location' => $program_location,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => ['permalink', 'the_content', 'excerpt', 'discussion', 'comments', 'revisions', 'slug', 'author', 'format', 'page_attributes', 'tags', 'send-trackbacks'],
		'active' => true,
	]);

	acf_add_local_field_group([
		'key' => 'group_pcsd_school_fees_general_flag',
		'title' => 'School Fees - General District Fee',
		'fields' => [
			[
				'key' => 'field_pcsd_is_general_district_fee',
				'label' => 'Is General District Fee?',
				'name' => 'is_general_district_fee',
				'type' => 'true_false',
				'instructions' => 'Check if this program is a general district-level fee (e.g. "General Required Fee - Middle Schools"). Templates render these as a header section on per-school pages instead of in the activity list. Replaces the legacy hardcoded post IDs (81827, 81999) with a queryable flag.',
				'ui' => 1,
				'default_value' => 0,
			],
			[
				'key' => 'field_pcsd_school_level',
				'label' => 'School Level',
				'name' => 'school_level',
				'type' => 'select',
				'instructions' => 'Only when "Is General District Fee?" is checked. Determines which school pages display this general fee.',
				'choices' => [
					'middle' => 'Middle Schools',
					'high' => 'High Schools',
					'elementary' => 'Elementary Schools',
				],
				'allow_null' => 1,
				'return_format' => 'value',
				'conditional_logic' => [[[
					'field' => 'field_pcsd_is_general_district_fee',
					'operator' => '==',
					'value' => '1',
				]]],
			],
		],
		'location' => $program_location,
		'position' => 'side',
		'active' => true,
	]);

	// Code-registered replacement for the legacy "School Fees - Location Specific Fee Page
	// Location Selector" UI group. Attached to the new by-location templates so each per-school
	// per-year WP Page picks which school's fees to show.
	acf_add_local_field_group([
		'key' => 'group_pcsd_school_fees_page_location',
		'title' => 'School Fees - By-Location Page Selector',
		'fields' => [
			[
				'key' => 'field_pcsd_location_of_fees_to_display',
				'label' => 'Location of fees to display',
				'name' => 'location_of_fees_to_display',
				'type' => 'select',
				'instructions' => 'Which school this page displays fees for. Used by the by-location template.',
				'choices' => pcsd_school_fees_location_choices(),
				'allow_null' => 0,
				'multiple' => 0,
				'return_format' => 'array',
			],
			[
				'key' => 'field_pcsd_fee_summary_pdf',
				'label' => 'Fee Summary PDF',
				'name' => 'fee_summary_pdf',
				'type' => 'file',
				'instructions' => 'Upload the fee summary PDF for this school and year. If left blank, the page links to the standard fee summary URL on the district file server.',
				'return_format' => 'array',
				'library' => 'all',
				'mime_types' => 'pdf',
			],
			[
				'key' => 'field_pcsd_additional_documents',
				'label' => 'Additional Links',
				'name' => 'additional_documents',
				'type' => 'repeater',
				'instructions' => 'Extra links to list alongside the fee summary (label + URL). Each row renders as a link below the fee summary PDF.',
				'layout' => 'table',
				'button_label' => 'Add Link',
				'sub_fields' => [
					[
						'key' => 'field_pcsd_additional_document_label',
						'label' => 'Label',
						'name' => 'label',
						'type' => 'text',
						'required' => 1,
					],
					[
						'key' => 'field_pcsd_additional_document_url',
						'label' => 'URL',
						'name' => 'url',
						'type' => 'url',
						'required' => 1,
					],
				],
			],
		],
		'location' => [
			[['param' => 'page_template', 'operator' => '==', 'value' => 'template-school-fees-by-location.php']],
			[['param' => 'page_template', 'operator' => '==', 'value' => 'template-pagos-escolares-by-location.php']],
		],
		'position' => 'normal',
		'active' => true,
	]);
}
