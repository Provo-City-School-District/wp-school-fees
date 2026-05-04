<?php
/*
 * Single program page. Yearly data stacked newest-first; archived years hidden.
 */

get_header();

$program_id = get_the_ID();
$yearly_data = get_field('yearly_data', $program_id);

$visible_years = [];
if (is_array($yearly_data)) {
	foreach ($yearly_data as $row) {
		if (empty($row['year']) || !is_object($row['year'])) {
			continue;
		}
		if ((bool) get_term_meta($row['year']->term_id, 'archived', true)) {
			continue;
		}
		$visible_years[] = $row;
	}
	usort($visible_years, function ($a, $b) {
		return strcmp($b['year']->slug, $a['year']->slug);
	});
}
?>
<main id="mainContent" class="sidebar">
	<ol class="breadcrumbs" id="breadcrumbs">
		<li><a href="<?php echo esc_url(home_url('/')); ?>">Home</a> / </li>
		<li><a href="<?php echo esc_url(home_url('/school-fees/')); ?>">School Fees</a> / </li>
		<li><?php single_post_title(); ?></li>
	</ol>
	<section id="currentPage">
		<article class="activePost feePost">
			<p class="lastmodified"><em>Last modified: <?php the_modified_date(); ?></em></p>
			<h1><?php single_post_title(); ?></h1>

			<?php if (empty($visible_years)) : ?>
				<p><em>No fee data available for this program.</em></p>
			<?php else : foreach ($visible_years as $year_row) :
				$year_term = $year_row['year'];
				$overall = is_array($year_row['overall_activity_fee_amounts'] ?? null) ? $year_row['overall_activity_fee_amounts'] : [];
				$locations = is_array($year_row['location_specific_fees'] ?? null) ? $year_row['location_specific_fees'] : [];
				$has_overall = !empty($overall['overall_activity_fee']) || !empty($overall['notes']);
			?>
				<section class="yearSection">
					<h2><?php echo esc_html($year_term->name); ?></h2>

					<?php if ($has_overall) : ?>
						<h3>Maximum Activity Fees</h3>
						<section class="feeDisplay">
							<table>
								<thead>
									<tr>
										<th>Fee (not to exceed)</th>
										<th>Notes</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><?php echo $overall['overall_activity_fee'] ?? ''; ?></td>
										<td><?php echo $overall['notes'] ?? ''; ?></td>
									</tr>
								</tbody>
							</table>
						</section>
					<?php endif; ?>

					<?php
					$rendered_breakdowns = false;
					foreach ($locations as $location) :
						if (empty($location['breakdown_of_fees'])) {
							continue;
						}
						if (!$rendered_breakdowns) {
							echo '<h3>Breakdown of Fees By Location</h3>';
							$rendered_breakdowns = true;
						}
					?>
						<h4><?php echo esc_html($location['location']); ?></h4>
						<section class="feeDisplay feeBreakdown">
							<table>
								<thead>
									<tr>
										<th>Fee Description</th>
										<th>Fee</th>
										<th>Notes</th>
										<th>Prior Year Approved Fee</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($location['breakdown_of_fees'] as $fee) : ?>
										<tr>
											<td><?php echo $fee['fee_description'] ?? ''; ?></td>
											<td><?php echo $fee['fee'] ?? ''; ?></td>
											<td><?php echo $fee['notes'] ?? ''; ?></td>
											<td><?php echo $fee['prior_year_approved_fee'] ?? ''; ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</section>
					<?php endforeach; ?>
				</section>
			<?php endforeach;
			endif; ?>

			<div class="bottom"></div>
		</article>
	</section>
	<?php get_sidebar(); ?>
</main>
<?php
get_footer();
