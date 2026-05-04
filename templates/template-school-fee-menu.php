<?php
/*
	Template Name: School Fees - Year Menu
*/

/*
 * Year landing page. Year parsed from page slug; "By Location" from child WP Pages.
 */

get_header();

$page_id = get_the_ID();
$year_slug = '';
if (preg_match('/^school-fees-(\d{2}-\d{2})$/', get_post()->post_name ?? '', $m)) {
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
		<li><a href="<?php echo esc_url(home_url('/school-fees/')); ?>">School Fees</a> / </li>
		<li><?php echo $year_slug ? esc_html('School Fees ' . $year_slug) : esc_html(get_the_title()); ?></li>
	</ol>
	<div id="currentPage">
		<article class="activePost schoolFeesMenu">
			<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
				<h1><?php the_title(); ?></h1>
				<div>
					<p>
						Fees listed are maximum fees and may not reflect actual fees paid.
						<?php if ($year_slug) : ?>
							<span class="right">
								<a href="<?php echo esc_url(home_url('/pagos-escolares-' . $year_slug . '/')); ?>">
									<?php echo esc_html('Pagos escolares ' . $year_slug); ?>
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
					<h2>By Category</h2>
					<div class="postgrid grid3 altColors">
						<?php foreach ($fee_categories as $cat) :
							$cat_url = esc_url(get_term_link($cat) . ($year_slug ? '?fee_year=' . $year_slug : ''));
						?>
							<article class="post">
								<a href="<?php echo $cat_url; ?>"><?php echo esc_html($cat->name); ?></a>
							</article>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<h2>By Location</h2>
					<?php if (empty($child_pages)) : ?>
						<p><em>No school pages have been created for this year yet. Use Tools &rarr; Generate School Fees Year (or add child pages manually) to populate this list.</em></p>
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
				<p>No school fees currently found for this year.</p>
			<?php endif; ?>
			<div class="clear"></div>
		</article>
	</div>
	<?php get_sidebar(); ?>
</main>
<?php
get_footer();
