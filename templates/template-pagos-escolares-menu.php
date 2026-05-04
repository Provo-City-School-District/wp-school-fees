<?php
/*
	Template Name: Pagos Escolares - Year Menu (Spanish)
*/

/*
 * Spanish year landing page. Mirror of template-school-fee-menu.php.
 */

get_header();

$page_id = get_the_ID();
$year_slug = '';
if (preg_match('/^pagos-escolares-(\d{2}-\d{2})$/', get_post()->post_name ?? '', $m)) {
	$year_slug = $m[1];
}

$child_pages = get_pages([
	'parent' => $page_id,
	'post_status' => 'publish',
	'sort_column' => 'post_title',
	'sort_order' => 'ASC',
]);
?>
<main id="mainContent" class="sidebar">
	<ol class="breadcrumbs" id="breadcrumbs">
		<li><a href="<?php echo esc_url(home_url('/')); ?>">Home</a> / </li>
		<li><a href="<?php echo esc_url(home_url('/pagos-escolares/')); ?>">Pagos Escolares</a> / </li>
		<li><?php echo $year_slug ? esc_html('Pagos Escolares ' . $year_slug) : esc_html(get_the_title()); ?></li>
	</ol>
	<div id="currentPage">
		<article class="activePost schoolFeesMenu">
			<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
				<h1><?php the_title(); ?></h1>
				<div>
					<p>
						Tarifas listadas son las máximas y pueden no reflejar la cantidad real a pagar.
						<?php if ($year_slug) : ?>
							<span class="right">
								<a href="<?php echo esc_url(home_url('/school-fees-' . $year_slug . '/')); ?>">
									<?php echo esc_html('School Fees ' . $year_slug); ?>
								</a>
							</span>
						<?php endif; ?>
					</p>

					<?php the_content(); ?>

					<?php
					$fee_categories = get_terms([
						'taxonomy'   => 'school_fees_categories',
						'hide_empty' => true,
						'orderby'    => 'name',
						'order'      => 'ASC',
					]);
					if (!is_wp_error($fee_categories) && !empty($fee_categories)) :
					?>
					<h2>Por Categoría</h2>
					<div class="postgrid grid3 altColors">
						<?php foreach ($fee_categories as $cat) :
							$cat_url = esc_url(get_term_link($cat) . ($year_slug ? '?fee_year=' . $year_slug . '&lang=es' : '?lang=es'));
						?>
							<article class="post">
								<a href="<?php echo $cat_url; ?>"><?php echo esc_html($cat->name); ?></a>
							</article>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<h2>Por Ubicación</h2>
					<?php if (empty($child_pages)) : ?>
						<p><em>Aún no se han creado páginas escolares para este año.</em></p>
					<?php else : ?>
						<div class="postgrid grid3 altColors">
							<?php foreach ($child_pages as $child) : ?>
								<article class="post">
									<a href="<?php echo esc_url(get_permalink($child->ID)); ?>"><?php echo esc_html($child->post_title); ?></a>
								</article>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endwhile;
			else : ?>
				<p>No se encontraron tarifas escolares para este año.</p>
			<?php endif; ?>
			<div class="clear"></div>
		</article>
	</div>
	<?php get_sidebar(); ?>
</main>
<?php
get_footer();
