<?php
/*
	Template Name: Pagos Escolares - By Location (Spanish)
*/

/*
 * Spanish parallel to template-school-fees-by-location.php.
 */

get_header();

$page_id = get_the_ID();
$location_field = get_field('location_of_fees_to_display');
if (is_array($location_field)) {
	$location_value = $location_field['value'] ?? '';
	$location_label = $location_field['label'] ?? '';
} else {
	$location_value = is_string($location_field) ? $location_field : '';
	$choices = function_exists('pcsd_school_fees_location_choices') ? pcsd_school_fees_location_choices() : [];
	$location_label = $choices[$location_value] ?? $location_value;
}

$school_level = function_exists('pcsd_school_fees_location_level')
	? pcsd_school_fees_location_level($location_value)
	: 'other';

$year_slug = '';
$parent_id = $page_id ? wp_get_post_parent_id($page_id) : 0;
$parent = $parent_id ? get_post($parent_id) : null;
if ($parent && preg_match('/^pagos-escolares-(\d{2}-\d{2})$/', $parent->post_name, $m)) {
	$year_slug = $m[1];
} elseif (preg_match('/^pagos-escolares-(\d{2}-\d{2})$/', get_post()->post_name ?? '', $m)) {
	$year_slug = $m[1];
}
?>
<main id="mainContent" class="sidebar">
	<ol class="breadcrumbs" id="breadcrumbs">
		<li><a href="<?php echo esc_url(home_url('/')); ?>">Home</a> / </li>
		<li><a href="<?php echo esc_url(home_url('/pagos-escolares/')); ?>">Pagos Escolares</a> / </li>
		<?php if ($year_slug) : ?>
			<li><a href="<?php echo esc_url(home_url('/pagos-escolares-' . $year_slug . '/')); ?>"><?php echo esc_html('Pagos Escolares ' . $year_slug); ?></a> / </li>
		<?php endif; ?>
		<li><?php single_post_title(); ?></li>
	</ol>
	<div id="currentPage">
		<article id="activePost" class="activePost feePost noprior">
			<h1><?php the_title(); ?></h1>
			<p>Tarifas listadas son las máximas y pueden no reflejar la cantidad real a pagar.</p>

			<?php
			$pdf_field = get_field('fee_summary_pdf');
			$pdf_url   = !empty($pdf_field['url']) ? $pdf_field['url'] : null;
			$pdf_label = !empty($pdf_field['title']) ? $pdf_field['title'] : 'Resumen de tarifas - ' . $location_label;

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
				echo '<p><em>No se pudo determinar a qué año escolar pertenece esta página.</em></p>';
				echo '<div class="clear"></div></article></div>';
				get_sidebar();
				echo '</main>';
				get_footer();
				return;
			}

			if (in_array($school_level, ['middle', 'high', 'elementary'], true)) {
				$general_query = new WP_Query([
					'post_type' => 'pagos_escolares',
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
					$level_label_es = [
						'middle' => 'Escuelas Intermedias',
						'high' => 'Escuelas Secundarias',
						'elementary' => 'Escuelas Primarias',
					][$school_level] ?? '';
					echo '<h2>' . esc_html('Tarifa general requerida - ' . $level_label_es) . '</h2>';

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
												<th>Descripción de tarifa</th>
												<th>Tarifa</th>
												<th>Notas</th>
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

			$programs_query = new WP_Query([
				'post_type' => 'pagos_escolares',
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
											<th>Descripción de tarifa</th>
											<th>Tarifa</th>
											<th>Notas</th>
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
				echo '<p>No se encontraron tarifas escolares para esta ubicación.</p>';
			}
			?>
			<div class="clear"></div>
		</article>
	</div>
	<?php get_sidebar(); ?>
</main>
<?php
get_footer();
