<?php
/*
 * Archive template for school_fees_categories taxonomy.
 * Linked from the year menu with ?year=XX-XX to filter programs to a specific year.
 * Without the param, lists all programs in the category across all years.
 */

get_header();

$term      = get_queried_object();
$year_slug = isset($_GET['fee_year']) ? sanitize_text_field($_GET['fee_year']) : '';
$year_term = $year_slug ? get_term_by('slug', $year_slug, 'school_fee_year') : null;
$lang      = isset($_GET['lang']) ? sanitize_key($_GET['lang']) : '';

if ($year_slug && !$year_term) {
	$year_slug = '';
}

$post_type = ($lang === 'es') ? 'pagos_escolares' : 'school_fees';

$tax_query = [
	[
		'taxonomy' => 'school_fees_categories',
		'field'    => 'slug',
		'terms'    => $term->slug,
	],
];

if ($year_term) {
	$tax_query['relation'] = 'AND';
	$tax_query[] = [
		'taxonomy' => 'school_fee_year',
		'field'    => 'slug',
		'terms'    => $year_slug,
	];
}

$programs = new WP_Query([
	'post_type'      => $post_type,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'posts_per_page' => -1,
	'tax_query'      => $tax_query,
]);

$year_label = $year_term ? $year_term->name : '';
if ($lang === 'es') {
	$menu_url   = $year_slug ? home_url('/pagos-escolares-' . $year_slug . '/') : home_url('/pagos-escolares/');
	$menu_label = $year_slug ? 'Pagos Escolares ' . $year_slug : 'Pagos Escolares';
} else {
	$menu_url   = $year_slug ? home_url('/school-fees-' . $year_slug . '/') : home_url('/school-fees/');
	$menu_label = $year_slug ? 'School Fees ' . $year_slug : 'School Fees';
}
?>
<main id="mainContent" class="sidebar">
	<ol class="breadcrumbs" id="breadcrumbs">
		<li><a href="<?php echo esc_url(home_url('/')); ?>">Home</a> / </li>
		<li><a href="<?php echo esc_url(home_url('/school-fees/')); ?>">School Fees</a> / </li>
		<?php if ($year_slug) : ?>
			<li><a href="<?php echo esc_url($menu_url); ?>"><?php echo esc_html($menu_label); ?></a> / </li>
		<?php endif; ?>
		<li><?php echo esc_html($term->name); ?></li>
	</ol>
	<div id="currentPage">
		<article class="activePost">
			<h1>
				<?php
				echo esc_html($term->name);
				if ($year_label) {
					echo ' &mdash; ' . esc_html($year_label);
				}
				?>
			</h1>
			<p>Fees listed are maximum fees and may not reflect actual fees paid.</p>
			<?php if ($programs->have_posts()) : ?>
				<ul>
					<?php while ($programs->have_posts()) : $programs->the_post(); ?>
						<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
					<?php endwhile; wp_reset_postdata(); ?>
				</ul>
			<?php else : ?>
				<p><em>No programs found<?php echo $year_label ? ' for ' . esc_html($year_label) : ''; ?> in this category.</em></p>
			<?php endif; ?>
		</article>
	</div>
	<?php get_sidebar(); ?>
</main>
<?php
get_footer();
