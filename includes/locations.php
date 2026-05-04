<?php
defined('ABSPATH') or die('No script kiddies please!');

// Centralized location list. Used by the ACF location dropdowns and (future) by templates.
// Keys are the stable internal slugs that get persisted to postmeta; labels are display-only.
// Future cleanup: source from the existing `schools` CPT instead of this hardcoded list.
function pcsd_school_fees_location_choices()
{
	return [
		'global' => 'District Wide',
		'amelia_earhart' => 'Amelia Earhart',
		'canyon_crest' => 'Canyon Crest',
		'edgemont' => 'Edgemont',
		'franklin' => 'Franklin',
		'lakeview' => 'Lakeview',
		'provost' => 'Provost',
		'provo_peaks' => 'Provo Peaks',
		'rock_canyon' => 'Rock Canyon',
		'spring_creek' => 'Spring Creek',
		'sunset_view' => 'Sunset View',
		'timpanogos' => 'Timpanogos',
		'wasatch' => 'Wasatch',
		'westridge' => 'Westridge',
		'centennial_middle' => 'Centennial Middle',
		'dixon_middle' => 'Dixon Middle',
		'shoreline_middle' => 'Shoreline Middle',
		'independence_high' => 'Independence High',
		'provo_high' => 'Provo High',
		'timpview_high' => 'Timpview High',
		'ebph' => 'Provo Transition Services/EBPH',
		'eschool' => 'eSchool',
		'provo_adult_education' => 'Provo Adult Education',
		'preschools' => 'Preschools',
		'district' => 'District Office',
	];
}

function pcsd_school_fees_location_level($slug)
{
	$middle = ['centennial_middle', 'dixon_middle', 'shoreline_middle'];
	$high = ['independence_high', 'provo_high', 'timpview_high'];
	$elementary = ['amelia_earhart', 'canyon_crest', 'edgemont', 'franklin', 'lakeview', 'provost', 'provo_peaks', 'rock_canyon', 'spring_creek', 'sunset_view', 'timpanogos', 'wasatch', 'westridge'];

	if (in_array($slug, $middle, true)) {
		return 'middle';
	}
	if (in_array($slug, $high, true)) {
		return 'high';
	}
	if (in_array($slug, $elementary, true)) {
		return 'elementary';
	}
	return 'other';
}
