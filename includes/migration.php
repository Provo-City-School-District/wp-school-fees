<?php
defined('ABSPATH') or die('No script kiddies please!');

// =====================================================================================
// Migrate Legacy School Fees
// =====================================================================================
// Reads orphaned data from the legacy school_fees_YY-YY and pagos_escolares_YYYY post
// types, groups posts by normalized title within each language, and creates new program
// posts in the unified school_fees / pagos_escolares CPTs with one yearly_data row per
// legacy year.
//
// Reads legacy postmeta directly rather than via get_field — the legacy ACF field keys
// don't map to the new code-registered field group, so ACF can't resolve them.
// Writes via update_field so the new postmeta gets the right field-key references.

add_action('admin_menu', 'pcsd_school_fees_migration_menu');

function pcsd_school_fees_migration_menu()
{
	add_management_page(
		__('Migrate Legacy School Fees', 'pcsd-school-fees'),
		__('Migrate Legacy School Fees', 'pcsd-school-fees'),
		'manage_options',
		'pcsd-school-fees-migration',
		'pcsd_school_fees_migration_admin_page'
	);
}

function pcsd_school_fees_migration_admin_page()
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('Insufficient permissions.', 'pcsd-school-fees'));
	}

	pcsd_school_fees_migration_register_legacy_cpts();

	$action = isset($_POST['pcsd_action']) ? sanitize_text_field(wp_unslash($_POST['pcsd_action'])) : '';
	$results      = null;
	$cat_results  = null;

	if ($action && check_admin_referer('pcsd_school_fees_migration')) {
		if (in_array($action, ['dry_run', 'execute'], true)) {
			$results = pcsd_school_fees_migration_run($action === 'execute');
		} elseif (in_array($action, ['cat_dry_run', 'cat_execute'], true)) {
			$cat_results = pcsd_school_fees_migration_sync_categories($action === 'cat_execute');
		}
	}

	pcsd_school_fees_migration_render_page($results, $cat_results);
}

function pcsd_school_fees_migration_legacy_cpts()
{
	global $wpdb;
	$types = $wpdb->get_col(
		"SELECT DISTINCT post_type
		FROM {$wpdb->posts}
		WHERE (post_type LIKE 'school\\_fees\\_%' OR post_type LIKE 'pagos\\_escolares\\_%')
			AND post_type != 'school_fees'
			AND post_type != 'pagos_escolares'
		ORDER BY post_type ASC"
	);
	return $types ?: [];
}

function pcsd_school_fees_migration_register_legacy_cpts()
{
	foreach (pcsd_school_fees_migration_legacy_cpts() as $cpt) {
		if (post_type_exists($cpt)) {
			continue;
		}
		register_post_type($cpt, [
			'public' => false,
			'publicly_queryable' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'query_var' => false,
			'rewrite' => false,
			'has_archive' => false,
			'hierarchical' => false,
			'supports' => ['title'],
		]);
	}
}

function pcsd_school_fees_migration_year_slug_from_post_type($post_type)
{
	if (strpos($post_type, 'school_fees_') === 0) {
		return substr($post_type, strlen('school_fees_'));
	}
	if (strpos($post_type, 'pagos_escolares_') === 0) {
		$suffix = substr($post_type, strlen('pagos_escolares_'));
		if (strlen($suffix) === 4) {
			return substr($suffix, 0, 2) . '-' . substr($suffix, 2, 2);
		}
		return $suffix;
	}
	return null;
}

function pcsd_school_fees_migration_target_cpt($legacy_cpt)
{
	return strpos($legacy_cpt, 'pagos_escolares') === 0 ? 'pagos_escolares' : 'school_fees';
}

function pcsd_school_fees_migration_normalize_title($title)
{
	return mb_strtolower(trim($title), 'UTF-8');
}

function pcsd_school_fees_migration_collect_legacy_posts()
{
	$by_target = ['school_fees' => [], 'pagos_escolares' => []];
	$skipped_empty_titles = ['school_fees' => 0, 'pagos_escolares' => 0];

	foreach (pcsd_school_fees_migration_legacy_cpts() as $cpt) {
		$posts = get_posts([
			'post_type' => $cpt,
			'post_status' => ['publish', 'draft', 'pending', 'private'],
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		]);
		$target = pcsd_school_fees_migration_target_cpt($cpt);
		foreach ($posts as $post) {
			if (trim($post->post_title) === '') {
				$skipped_empty_titles[$target]++;
				continue;
			}
			$by_target[$target][] = [
				'legacy_id' => $post->ID,
				'title' => $post->post_title,
				'post_status' => $post->post_status,
				'legacy_cpt' => $cpt,
				'year_slug' => pcsd_school_fees_migration_year_slug_from_post_type($cpt),
			];
		}
	}
	return ['by_target' => $by_target, 'skipped_empty_titles' => $skipped_empty_titles];
}

function pcsd_school_fees_migration_group_by_name($posts)
{
	$groups = [];
	foreach ($posts as $post) {
		$key = pcsd_school_fees_migration_normalize_title($post['title']);
		if (!isset($groups[$key])) {
			$groups[$key] = ['title' => trim($post['title']), 'legacy_posts' => []];
		}
		$groups[$key]['legacy_posts'][] = $post;
	}
	ksort($groups);
	return $groups;
}

function pcsd_school_fees_migration_check_target($title, $target_cpt)
{
	// Three-state check:
	//   - missing: no post with this title exists → create one
	//   - needs_fill: post exists but yearly_data is empty (e.g., a legacy post got its
	//     post_type changed but kept its flat-shape data) → write yearly_data on it
	//   - complete: post exists and yearly_data is populated → skip
	// We do this directly via SQL because WP_Query's 'title' parameter has shipped with
	// reliability issues across versions, and we need predictable behavior here.
	if (trim($title) === '') {
		return ['state' => 'invalid', 'post_id' => 0];
	}
	global $wpdb;
	$post_id = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		WHERE post_type = %s
			AND post_status NOT IN ('auto-draft', 'trash', 'inherit')
			AND post_title = %s
		ORDER BY ID ASC
		LIMIT 1",
		$target_cpt,
		$title
	));
	if (!$post_id) {
		return ['state' => 'missing', 'post_id' => 0];
	}
	$yearly_data_count = (int) get_post_meta($post_id, 'yearly_data', true);
	if ($yearly_data_count > 0) {
		return ['state' => 'complete', 'post_id' => $post_id];
	}
	return ['state' => 'needs_fill', 'post_id' => $post_id];
}

function pcsd_school_fees_migration_target_post_count($target_cpt)
{
	global $wpdb;
	$row = $wpdb->get_row($wpdb->prepare(
		"SELECT
			SUM(CASE WHEN post_status = 'publish' THEN 1 ELSE 0 END) AS published,
			SUM(CASE WHEN post_status = 'draft' THEN 1 ELSE 0 END) AS drafts,
			SUM(CASE WHEN post_status NOT IN ('auto-draft', 'trash', 'inherit') THEN 1 ELSE 0 END) AS total
		FROM {$wpdb->posts}
		WHERE post_type = %s",
		$target_cpt
	), ARRAY_A);
	return [
		'published' => (int) ($row['published'] ?? 0),
		'drafts' => (int) ($row['drafts'] ?? 0),
		'total' => (int) ($row['total'] ?? 0),
	];
}

function pcsd_school_fees_migration_ensure_year_term($year_slug)
{
	$term = get_term_by('slug', $year_slug, 'school_fee_year');
	if ($term) {
		return (int) $term->term_id;
	}
	$parts = explode('-', $year_slug);
	$label = (count($parts) === 2) ? '20' . $parts[0] . '-20' . $parts[1] : $year_slug;
	$created = wp_insert_term($label, 'school_fee_year', ['slug' => $year_slug]);
	if (is_wp_error($created)) {
		return null;
	}
	return (int) $created['term_id'];
}

function pcsd_school_fees_migration_read_legacy_data($post_id)
{
	$overall = [
		'overall_activity_fee' => get_post_meta($post_id, 'overall_activity_fee_amounts_overall_activity_fee', true),
		'overall_course_fundraising' => get_post_meta($post_id, 'overall_activity_fee_amounts_overall_course_fundraising', true),
		'overall_course_total' => get_post_meta($post_id, 'overall_activity_fee_amounts_overall_course_total', true),
		'notes' => get_post_meta($post_id, 'overall_activity_fee_amounts_notes', true),
	];

	$location_count = (int) get_post_meta($post_id, 'location_specific_fees', true);
	$locations = [];
	for ($i = 0; $i < $location_count; $i++) {
		$location_value = get_post_meta($post_id, "location_specific_fees_{$i}_location", true);
		$breakdown_count = (int) get_post_meta($post_id, "location_specific_fees_{$i}_breakdown_of_fees", true);
		$breakdown = [];
		for ($j = 0; $j < $breakdown_count; $j++) {
			$prefix = "location_specific_fees_{$i}_breakdown_of_fees_{$j}";
			$breakdown[] = [
				'fee_description' => get_post_meta($post_id, "{$prefix}_fee_description", true),
				'fee' => get_post_meta($post_id, "{$prefix}_fee", true),
				'fundraising' => get_post_meta($post_id, "{$prefix}_fundraising", true),
				'total' => get_post_meta($post_id, "{$prefix}_total", true),
				'notes' => get_post_meta($post_id, "{$prefix}_notes", true),
				'prior_year_approved_fee' => get_post_meta($post_id, "{$prefix}_prior_year_approved_fee", true),
				'bold_line' => get_post_meta($post_id, "{$prefix}_bold_line", true),
			];
		}
		$locations[] = [
			'location' => $location_value,
			'breakdown_of_fees' => $breakdown,
		];
	}

	return [
		'overall_activity_fee_amounts' => $overall,
		'location_specific_fees' => $locations,
	];
}

function pcsd_school_fees_migration_build_yearly_data_rows($legacy_posts)
{
	$rows = [];
	foreach ($legacy_posts as $entry) {
		$term_id = pcsd_school_fees_migration_ensure_year_term($entry['year_slug']);
		if (!$term_id) {
			continue;
		}
		$data = pcsd_school_fees_migration_read_legacy_data($entry['legacy_id']);
		$rows[] = [
			'year' => $term_id,
			'overall_activity_fee_amounts' => $data['overall_activity_fee_amounts'],
			'location_specific_fees' => $data['location_specific_fees'],
		];
	}
	usort($rows, function ($a, $b) {
		$term_a = get_term($a['year']);
		$term_b = get_term($b['year']);
		return strcmp($term_a ? $term_a->slug : '', $term_b ? $term_b->slug : '');
	});
	return $rows;
}

function pcsd_school_fees_migration_create_program($title, $target_cpt, $rows, $any_published)
{
	$post_id = wp_insert_post([
		'post_type' => $target_cpt,
		'post_status' => $any_published ? 'publish' : 'draft',
		'post_title' => $title,
	], true);

	if (is_wp_error($post_id)) {
		return $post_id;
	}
	update_field('yearly_data', $rows, $post_id);
	// update_field doesn't fire acf/save_post, so the year-taxonomy sync hook in sync.php
	// would never run. Trigger it explicitly.
	pcsd_school_fees_sync_year_taxonomy($post_id);
	return $post_id;
}

function pcsd_school_fees_migration_run($execute)
{
	$collected = pcsd_school_fees_migration_collect_legacy_posts();
	$by_target = $collected['by_target'];
	$skipped_empty = $collected['skipped_empty_titles'];
	$report = ['empty_titles' => $skipped_empty];

	foreach ($by_target as $target_cpt => $posts) {
		$groups = pcsd_school_fees_migration_group_by_name($posts);
		$created = [];
		$skipped = [];
		$errors = [];

		foreach ($groups as $group) {
			$check = pcsd_school_fees_migration_check_target($group['title'], $target_cpt);

			if ($check['state'] === 'invalid') {
				continue;
			}

			if ($check['state'] === 'complete') {
				$skipped[] = ['title' => $group['title'], 'count' => count($group['legacy_posts'])];
				continue;
			}

			if (!$execute) {
				$created[] = [
					'title' => $group['title'],
					'count' => count($group['legacy_posts']),
					'legacy_posts' => $group['legacy_posts'],
					'state' => $check['state'],
					'existing_id' => $check['post_id'],
				];
				continue;
			}

			$rows = pcsd_school_fees_migration_build_yearly_data_rows($group['legacy_posts']);
			$any_published = false;
			foreach ($group['legacy_posts'] as $lp) {
				if ($lp['post_status'] === 'publish') {
					$any_published = true;
					break;
				}
			}

			if ($check['state'] === 'needs_fill') {
				// Post already exists with no yearly_data — fill it in place rather than creating a duplicate.
				update_field('yearly_data', $rows, $check['post_id']);
				pcsd_school_fees_sync_year_taxonomy($check['post_id']);
				$result = $check['post_id'];
			} else {
				$result = pcsd_school_fees_migration_create_program($group['title'], $target_cpt, $rows, $any_published);
			}
			if (is_wp_error($result)) {
				$errors[] = ['title' => $group['title'], 'error' => $result->get_error_message()];
			} else {
				$created[] = [
					'title' => $group['title'],
					'new_id' => (int) $result,
					'count' => count($group['legacy_posts']),
					'legacy_posts' => $group['legacy_posts'],
					'state' => $check['state'],
				];
			}
		}

		$report[$target_cpt] = ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
	}

	return ['executed' => $execute, 'report' => $report];
}

function pcsd_school_fees_migration_sync_categories($execute)
{
	global $wpdb;

	// English: school_fees_categories_YYYY (year-specific)
	$en_legacy_taxonomies = $wpdb->get_col(
		"SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy}
		 WHERE taxonomy LIKE 'school\_fees\_categories\_%'
		   AND taxonomy != 'school_fees_categories_spanish'"
	);

	// Spanish: school_fees_cate_spanish_YYYY (abbreviated slug — WP had a 32-char limit)
	$es_legacy_taxonomies = $wpdb->get_col(
		"SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy}
		 WHERE taxonomy LIKE 'school\_fees\_cate\_spanish\_%'"
	);

	if (empty($en_legacy_taxonomies) && empty($es_legacy_taxonomies)) {
		return ['executed' => $execute, 'message' => 'No legacy category taxonomies found.', 'rows_en' => [], 'rows_es' => []];
	}

	$rows_en = [];
	$rows_es = [];

	if (!empty($en_legacy_taxonomies)) {
		$posts = get_posts([
			'post_type'      => 'school_fees',
			'post_status'    => ['publish', 'draft'],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);
		foreach ($posts as $post) {
			$legacy_ids = $wpdb->get_col($wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_title = %s
				   AND post_type LIKE 'school\_fees\_%'
				   AND post_type != 'school_fees'
				   AND post_status NOT IN ('auto-draft', 'trash', 'inherit')",
				$post->post_title
			));
			if (empty($legacy_ids)) {
				continue;
			}
			$row = pcsd_school_fees_migration_build_cat_row($post, $legacy_ids, $en_legacy_taxonomies, $execute);
			if ($row) {
				$rows_en[] = $row;
			}
		}
	}

	if (!empty($es_legacy_taxonomies)) {
		$posts = get_posts([
			'post_type'      => 'pagos_escolares',
			'post_status'    => ['publish', 'draft'],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		]);
		foreach ($posts as $post) {
			$legacy_ids = $wpdb->get_col($wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_title = %s
				   AND post_type LIKE 'pagos\_escolares\_%'
				   AND post_type != 'pagos_escolares'
				   AND post_status NOT IN ('auto-draft', 'trash', 'inherit')",
				$post->post_title
			));
			if (empty($legacy_ids)) {
				continue;
			}
			$row = pcsd_school_fees_migration_build_cat_row($post, $legacy_ids, $es_legacy_taxonomies, $execute);
			if ($row) {
				$rows_es[] = $row;
			}
		}
	}

	return ['executed' => $execute, 'message' => '', 'rows_en' => $rows_en, 'rows_es' => $rows_es];
}

function pcsd_school_fees_migration_build_cat_row($post, $legacy_ids, $legacy_taxonomies, $execute)
{
	global $wpdb;

	$id_in  = implode(',', array_map('intval', $legacy_ids));
	$tax_in = implode(',', array_fill(0, count($legacy_taxonomies), '%s'));

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$legacy_slugs = $wpdb->get_col($wpdb->prepare(
		"SELECT DISTINCT t.slug
		 FROM {$wpdb->terms} t
		 JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
		 JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
		 WHERE tt.taxonomy IN ($tax_in)
		   AND tr.object_id IN ($id_in)",
		$legacy_taxonomies
	));

	if (empty($legacy_slugs)) {
		return null;
	}

	$new_term_ids = [];
	foreach ($legacy_slugs as $slug) {
		$term = get_term_by('slug', $slug, 'school_fees_categories');
		if ($term) {
			$new_term_ids[] = $term->term_id;
		}
	}

	if (empty($new_term_ids)) {
		return null;
	}

	$current = wp_get_object_terms($post->ID, 'school_fees_categories', ['fields' => 'ids']);
	$current = is_wp_error($current) ? [] : $current;
	$to_add  = array_diff($new_term_ids, $current);

	if (empty($to_add)) {
		return null;
	}

	if ($execute) {
		wp_set_object_terms($post->ID, array_merge($current, $new_term_ids), 'school_fees_categories');
	}

	return [
		'post_id' => $post->ID,
		'title'   => $post->post_title,
		'slugs'   => $legacy_slugs,
		'to_add'  => $to_add,
	];
}

function pcsd_school_fees_migration_render_page($results, $cat_results = null)
{
	$legacy_cpts = pcsd_school_fees_migration_legacy_cpts();
?>
	<div class="wrap">
		<h1><?php esc_html_e('Migrate Legacy School Fees', 'pcsd-school-fees'); ?></h1>
		<p><?php esc_html_e('Reads orphaned data from the legacy school_fees_* and pagos_escolares_* post types and merges them into unified programs. Programs are matched across years by normalized title (trimmed, case-insensitive). Anything that does not match across years lands in its own single-year program.', 'pcsd-school-fees'); ?></p>
		<p><strong><?php esc_html_e('Legacy postmeta is read but never modified.', 'pcsd-school-fees'); ?></strong> <?php esc_html_e('Dry-run is fully safe to re-run. Existing programs in the new CPT are never touched on execute either — duplicates are skipped.', 'pcsd-school-fees'); ?></p>

		<h2><?php esc_html_e('Detected legacy CPTs', 'pcsd-school-fees'); ?></h2>
		<?php if (empty($legacy_cpts)) : ?>
			<p><em><?php esc_html_e('No legacy data found in the database.', 'pcsd-school-fees'); ?></em></p>
		<?php else : ?>
			<ul>
				<?php foreach ($legacy_cpts as $cpt) : ?>
					<li>
						<code><?php echo esc_html($cpt); ?></code>
						&rarr; year <code><?php echo esc_html(pcsd_school_fees_migration_year_slug_from_post_type($cpt) ?? '?'); ?></code>,
						target <code><?php echo esc_html(pcsd_school_fees_migration_target_cpt($cpt)); ?></code>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="post">
				<?php wp_nonce_field('pcsd_school_fees_migration'); ?>
				<p>
					<button type="submit" name="pcsd_action" value="dry_run" class="button"><?php esc_html_e('Dry-run preview', 'pcsd-school-fees'); ?></button>
					<button type="submit" name="pcsd_action" value="execute" class="button button-primary" onclick="return confirm('This will create new program posts. Continue?');"><?php esc_html_e('Execute migration', 'pcsd-school-fees'); ?></button>
				</p>
			</form>
		<?php endif; ?>

		<?php if ($results) : pcsd_school_fees_migration_render_results($results);
		endif; ?>

		<hr>
		<h2><?php esc_html_e('Sync Categories from Legacy', 'pcsd-school-fees'); ?></h2>
		<p><?php esc_html_e('Reads legacy school_fees_categories_YYYY taxonomies and assigns matching school_fees_categories terms to unified programs. Only adds — never removes existing assignments. Safe to re-run.', 'pcsd-school-fees'); ?></p>
		<form method="post">
			<?php wp_nonce_field('pcsd_school_fees_migration'); ?>
			<p>
				<button type="submit" name="pcsd_action" value="cat_dry_run" class="button"><?php esc_html_e('Preview category sync', 'pcsd-school-fees'); ?></button>
				<button type="submit" name="pcsd_action" value="cat_execute" class="button button-primary" onclick="return confirm('This will assign category terms to programs. Continue?');"><?php esc_html_e('Execute category sync', 'pcsd-school-fees'); ?></button>
			</p>
		</form>

		<?php if ($cat_results !== null) : pcsd_school_fees_migration_render_cat_results($cat_results);
		endif; ?>
	</div>
<?php
}

function pcsd_school_fees_migration_render_cat_results($cat_results)
{
	$executed = $cat_results['executed'];
	$rows_en  = $cat_results['rows_en'] ?? [];
	$rows_es  = $cat_results['rows_es'] ?? [];
?>
	<hr>
	<h2><?php echo $executed ? esc_html__('Category sync executed', 'pcsd-school-fees') : esc_html__('Category sync preview', 'pcsd-school-fees'); ?></h2>
	<?php if (!empty($cat_results['message'])) : ?>
		<p><em><?php echo esc_html($cat_results['message']); ?></em></p>
	<?php elseif (empty($rows_en) && empty($rows_es)) : ?>
		<p><em><?php esc_html_e('All programs already have category assignments — nothing to sync.', 'pcsd-school-fees'); ?></em></p>
	<?php else :
		foreach ([
			'English (school_fees)' => $rows_en,
			'Spanish (pagos_escolares)' => $rows_es,
		] as $section_label => $rows) :
			if (empty($rows)) {
				continue;
			}
	?>
		<h3><?php echo esc_html($section_label); ?></h3>
		<p>
			<?php
			printf(
				$executed
					? esc_html__('Assigned categories to %d programs.', 'pcsd-school-fees')
					: esc_html__('%d programs will receive category assignments.', 'pcsd-school-fees'),
				count($rows)
			);
			?>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e('Program', 'pcsd-school-fees'); ?></th>
					<th><?php esc_html_e('Categories to assign', 'pcsd-school-fees'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $row) : ?>
					<tr>
						<td>
							<?php echo esc_html($row['title']); ?>
							— <a href="<?php echo esc_url(get_edit_post_link($row['post_id'])); ?>"><?php esc_html_e('edit', 'pcsd-school-fees'); ?></a>
						</td>
						<td><?php echo esc_html(implode(', ', $row['slugs'])); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endforeach;
	endif; ?>
<?php
}

function pcsd_school_fees_migration_render_results($results)
{
	$executed = $results['executed'];
	$empty = $results['report']['empty_titles'] ?? ['school_fees' => 0, 'pagos_escolares' => 0];
?>
	<hr>
	<h2><?php echo $executed ? esc_html__('Migration executed', 'pcsd-school-fees') : esc_html__('Dry-run results', 'pcsd-school-fees'); ?></h2>
	<?php if (($empty['school_fees'] + $empty['pagos_escolares']) > 0) : ?>
		<p><em><?php
			printf(
				esc_html__('%d legacy posts with empty titles were ignored (%d English, %d Spanish). Likely orphan autosaves or aborted drafts; these are never legitimate programs.', 'pcsd-school-fees'),
				(int) ($empty['school_fees'] + $empty['pagos_escolares']),
				(int) $empty['school_fees'],
				(int) $empty['pagos_escolares']
			);
		?></em></p>
	<?php endif; ?>
	<?php
	foreach ($results['report'] as $target_cpt => $bucket) {
		if ($target_cpt === 'empty_titles') {
			continue;
		}
		$label = $target_cpt === 'school_fees'
			? __('English (school_fees)', 'pcsd-school-fees')
			: __('Spanish (pagos_escolares)', 'pcsd-school-fees');
	?>
		<h3><?php echo esc_html($label); ?></h3>
		<?php
		if (!empty($bucket['created'])) {
		?>
			<h4>
				<?php echo $executed ? esc_html__('Created', 'pcsd-school-fees') : esc_html__('Will create', 'pcsd-school-fees'); ?>
				(<?php echo (int) count($bucket['created']); ?>)
			</h4>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Program title', 'pcsd-school-fees'); ?></th>
						<th><?php esc_html_e('Action', 'pcsd-school-fees'); ?></th>
						<th><?php esc_html_e('Year rows', 'pcsd-school-fees'); ?></th>
						<th><?php esc_html_e('Legacy contributors', 'pcsd-school-fees'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($bucket['created'] as $entry) :
						$state = $entry['state'] ?? 'missing';
						$action_label = $state === 'needs_fill'
							? ($executed ? __('Filled existing post', 'pcsd-school-fees') : __('Will fill existing post', 'pcsd-school-fees'))
							: ($executed ? __('Created new post', 'pcsd-school-fees') : __('Will create new post', 'pcsd-school-fees'));
						$linked_id = $entry['new_id'] ?? ($entry['existing_id'] ?? 0);
					?>
						<tr>
							<td>
								<?php echo esc_html($entry['title']); ?>
								<?php if ($linked_id) : ?>
									— <a href="<?php echo esc_url(get_edit_post_link($linked_id)); ?>"><?php esc_html_e('edit', 'pcsd-school-fees'); ?></a>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html($action_label); ?></td>
							<td><?php echo (int) $entry['count']; ?></td>
							<td>
								<?php foreach ($entry['legacy_posts'] as $lp) : ?>
									<code><?php echo esc_html($lp['year_slug']); ?></code>:
									"<?php echo esc_html($lp['title']); ?>"
									(#<?php echo (int) $lp['legacy_id']; ?>, <?php echo esc_html($lp['post_status']); ?>)<br>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php
		}

		if (!empty($bucket['skipped'])) {
		?>
			<h4><?php esc_html_e('Skipped — already exist in target CPT', 'pcsd-school-fees'); ?> (<?php echo (int) count($bucket['skipped']); ?>)</h4>
			<ul>
				<?php foreach ($bucket['skipped'] as $entry) : ?>
					<li><?php echo esc_html($entry['title']); ?> (<?php echo (int) $entry['count']; ?> legacy posts ignored)</li>
				<?php endforeach; ?>
			</ul>
		<?php
		}

		if (!empty($bucket['errors'])) {
		?>
			<h4 style="color:#d63638;"><?php esc_html_e('Errors', 'pcsd-school-fees'); ?></h4>
			<ul>
				<?php foreach ($bucket['errors'] as $entry) : ?>
					<li><?php echo esc_html($entry['title']); ?>: <?php echo esc_html($entry['error']); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php
		}

		if (empty($bucket['created']) && empty($bucket['skipped']) && empty($bucket['errors'])) {
			echo '<p><em>' . esc_html__('No legacy posts found.', 'pcsd-school-fees') . '</em></p>';
		}
	}
	?>
<?php
}
