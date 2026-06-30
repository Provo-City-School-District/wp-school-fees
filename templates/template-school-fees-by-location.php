<?php
/*
	Template Name: School Fees - By Location
*/

/*
 * Per-school fees page. Year from parent slug; location from ACF field.
 */

get_header();

$page_id = get_the_ID();
$location_field = get_field('location_of_fees_to_display');
if (is_array($location_field)) {
	$location_value = $location_field['value'] ?? '';
	$location_label = $location_field['label'] ?? '';
} else {
	// Defensive: if the page's ACF reference still points at the deleted legacy field group,
	// get_field returns the raw stored value (the slug) rather than the array form.
	$location_value = is_string($location_field) ? $location_field : '';
	$choices = function_exists('pcsd_school_fees_location_choices') ? pcsd_school_fees_location_choices() : [];
	$location_label = $choices[$location_value] ?? $location_value;
}

$school_level = function_exists('pcsd_school_fees_location_level')
	? pcsd_school_fees_location_level($location_value)
	: 'other';

// Derive year from parent page slug. Falls back to the current page's own slug for the rare
// case where the year landing page itself is what's being viewed.
$year_slug = '';
$parent_id = $page_id ? wp_get_post_parent_id($page_id) : 0;
$parent = $parent_id ? get_post($parent_id) : null;
if ($parent && preg_match('/^school-fees-(\d{2}-\d{2})$/', $parent->post_name, $m)) {
	$year_slug = $m[1];
} elseif (preg_match('/^school-fees-(\d{2}-\d{2})$/', get_post()->post_name ?? '', $m)) {
	$year_slug = $m[1];
}
?>
<main id="mainContent" class="sidebar">
	<ol class="breadcrumbs" id="breadcrumbs">
		<li><a href="<?php echo esc_url(home_url('/')); ?>">Home</a> / </li>
		<li><a href="<?php echo esc_url(home_url('/school-fees/')); ?>">School Fees</a> / </li>
		<?php if ($year_slug) : ?>
			<li><a href="<?php echo esc_url(home_url('/school-fees-' . $year_slug . '/')); ?>"><?php echo esc_html('School Fees ' . $year_slug); ?></a> / </li>
		<?php endif; ?>
		<li><?php single_post_title(); ?></li>
	</ol>
	<div id="currentPage">
		<article id="activePost" class="activePost feePost noprior">
			<h1><?php the_title(); ?></h1>
			<p>Fees listed are maximum fees and may not reflect actual fees paid.</p>

			<?php
			$pdf_field = get_field('fee_summary_pdf');
			$pdf_url   = !empty($pdf_field['url']) ? $pdf_field['url'] : null;
			$pdf_label = !empty($pdf_field['title']) ? $pdf_field['title'] : $location_label . ' Fee Summary';
			if (!$pdf_url && $year_slug && in_array($school_level, ['middle', 'high'], true) && $location_value) {
				$pdf_url   = 'https://globalassets.provo.edu/fee-summary/' . $year_slug . '/fee_summary_' . $location_value . '.pdf';
				$pdf_label = $location_label . ' Fee Summary';
			}

				$documents = [];
				if ($pdf_url) {
					$documents[] = ['label' => $pdf_label, 'url' => $pdf_url];
				}
				$additional = get_field('additional_documents');
				if (is_array($additional)) {
					foreach ($additional as $doc) {
						if (!empty($doc['url']) && !empty($doc['label'])) {
							$documents[] = ['label' => $doc['label'], 'url' => $doc['url']];
						}
					}
				}
				?>
				<?php if ($documents) : ?>
					<ul>
						<?php foreach ($documents as $doc) : ?>
							<li>
								<a href="<?php echo esc_url($doc['url']); ?>"><?php echo esc_html($doc['label']); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

			<?php
			if (!$year_slug) {
				echo '<p><em>Could not determine which school year this page belongs to. Page slug must follow the school-fees-YY-YY pattern, or its parent page must.</em></p>';
				echo '<div class="clear"></div></article></div>';
				get_sidebar();
				echo '</main>';
				get_footer();
				return;
			}

			// General district fee programs (e.g. "General Required Fee - Middle Schools").
			// Only relevant for middle/high — elementary doesn't have a general fee in the legacy data.
			if (in_array($school_level, ['middle', 'high', 'elementary'], true)) {
				$general_query = new WP_Query([
					'post_type' => 'school_fees',
					'post_status' => 'publish',
					'posts_per_page' => -1,
					'tax_query' => [
						['taxonomy' => 'school_fee_year', 'field' => 'slug', 'terms' => $year_slug],
					],
					'meta_query' => [
						'relation' => 'AND',
						['key' => 'is_general_district_fee', 'value' => '1', 'compare' => '='],
						['key' => 'school_level', 'value' => $school_level, 'compare' => '='],
					],
				]);

				if ($general_query->have_posts()) {
					$level_label = ucfirst($school_level) . ' Schools';
					echo '<h2>' . esc_html('General Required Fee - ' . $level_label) . '</h2>';

					while ($general_query->have_posts()) {
						$general_query->the_post();
						$yd = get_field('yearly_data');
						if (!is_array($yd)) {
							continue;
						}
						foreach ($yd as $row) {
							if (empty($row['year']) || !is_object($row['year']) || $row['year']->slug !== $year_slug) {
								continue;
							}
							$locs = is_array($row['location_specific_fees'] ?? null) ? $row['location_specific_fees'] : [];
							foreach ($locs as $loc) {
								if (($loc['location'] ?? '') !== $location_label) {
									continue;
								}
								if (empty($loc['breakdown_of_fees'])) {
									continue;
								}
							?>
								<section class="feeDisplay feeBreakdown">
									<table>
										<thead>
											<tr>
												<th>Fee Description</th>
												<th>Fee</th>
												<th>Notes</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($loc['breakdown_of_fees'] as $fee) : ?>
												<tr>
													<th><?php echo $fee['fee_description'] ?? ''; ?></th>
													<td class="textright"><?php echo $fee['fee'] ?? ''; ?></td>
													<td><?php echo $fee['notes'] ?? ''; ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</section>
							<?php
							}
						}
					}
					wp_reset_postdata();
				}
			}

			// Non-general fee programs. Iterate everything in the year, then filter in PHP
			// to programs whose yearly_data row for this year contains a location matching
			// this page's location.
			$programs_query = new WP_Query([
				'post_type' => 'school_fees',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'tax_query' => [
					['taxonomy' => 'school_fee_year', 'field' => 'slug', 'terms' => $year_slug],
				],
				'meta_query' => [
					'relation' => 'OR',
					['key' => 'is_general_district_fee', 'compare' => 'NOT EXISTS'],
					['key' => 'is_general_district_fee', 'value' => '0', 'compare' => '='],
				],
				'orderby' => 'title',
				'order' => 'ASC',
			]);

			$rendered_any = false;
			if ($programs_query->have_posts()) {
				while ($programs_query->have_posts()) {
					$programs_query->the_post();
					$yd = get_field('yearly_data');
					if (!is_array($yd)) {
						continue;
					}
					foreach ($yd as $row) {
						if (empty($row['year']) || !is_object($row['year']) || $row['year']->slug !== $year_slug) {
							continue;
						}
						$locs = is_array($row['location_specific_fees'] ?? null) ? $row['location_specific_fees'] : [];
						foreach ($locs as $loc) {
							if (($loc['location'] ?? '') !== $location_label) {
								continue;
							}
							if (empty($loc['breakdown_of_fees'])) {
								continue;
							}
							$rendered_any = true;
							?>
							<h2><?php the_title(); ?></h2>
							<section class="feeDisplay feeBreakdown">
								<table>
									<thead>
										<tr>
											<th>Fee Description</th>
											<th>Fee</th>
											<th>Notes</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($loc['breakdown_of_fees'] as $fee) :
											$bold = !empty($fee['bold_line']);
										?>
											<tr>
												<th><?php
													if ($bold) echo '<strong>';
													echo $fee['fee_description'] ?? '';
													if ($bold) echo '</strong>';
												?></th>
												<td class="textright"><?php
													if ($bold) echo '<strong>';
													echo $fee['fee'] ?? '';
													if ($bold) echo '</strong>';
												?></td>
												<td><?php
													if ($bold) echo '<strong>';
													echo $fee['notes'] ?? '';
													if ($bold) echo '</strong>';
												?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</section>
							<?php
						}
					}
				}
				wp_reset_postdata();
			}

			if (!$rendered_any) {
				echo '<p>No school fees currently found for this location.</p>';
			}
			?>
			<div class="clear"></div>
		</article>
	</div>
	<?php get_sidebar(); ?>
</main>
<?php
get_footer();
