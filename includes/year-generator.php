<?php
defined('ABSPATH') or die('No script kiddies please!');

// =====================================================================================
// Generate School Fees Year
// =====================================================================================
// One-click setup for next school year:
//   - Ensure the school_fee_year taxonomy term exists
//   - Create or find the parent menu pages (English: school-fees-YY-YY,
//     Spanish: pagos-escolares-YY-YY) with the right template assigned
//   - Create or find one child by-location page per school under each parent,
//     with location_of_fees_to_display ACF field set
//   - Optionally clone yearly_data rows from the most recent prior year so admins
//     don't have to copy fee data into every program manually
//
// Idempotent — every step checks if its target already exists. Safe to re-run.

add_action('admin_menu', 'pcsd_year_generator_menu');

function pcsd_year_generator_menu()
{
	add_management_page(
		__('Generate School Fees Year', 'pcsd-school-fees'),
		__('Generate School Fees Year', 'pcsd-school-fees'),
		'manage_options',
		'pcsd-school-fees-year-generator',
		'pcsd_year_generator_admin_page'
	);
}

function pcsd_year_generator_admin_page()
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('Insufficient permissions.', 'pcsd-school-fees'));
	}

	$results         = null;
	$year_slug       = '';
	$clone_fees      = false;
	$cleanup_results = null;
	$cleanup_slug    = '';

	if (!empty($_POST['pcsd_year_action']) && check_admin_referer('pcsd_year_generator')) {
		$action = sanitize_text_field(wp_unslash($_POST['pcsd_year_action']));

		if ($action === 'generate') {
			$year_slug  = isset($_POST['year_slug']) ? sanitize_text_field(wp_unslash($_POST['year_slug'])) : '';
			$clone_fees = !empty($_POST['clone_fees']);
			if (preg_match('/^\d{2}-\d{2}$/', $year_slug)) {
				$results = pcsd_year_generator_run($year_slug, $clone_fees);
			} else {
				$results = ['errors' => [__('Year slug must be in format YY-YY (e.g., 26-27).', 'pcsd-school-fees')]];
			}
		} elseif ($action === 'cleanup_pages') {
			$cleanup_slug = isset($_POST['cleanup_slug']) ? sanitize_text_field(wp_unslash($_POST['cleanup_slug'])) : '';
			if (preg_match('/^\d{2}-\d{2}$/', $cleanup_slug)) {
				$cleanup_results = pcsd_year_generator_cleanup_pages($cleanup_slug);
			} else {
				$cleanup_results = ['errors' => [__('Year slug must be in format YY-YY (e.g., 24-25).', 'pcsd-school-fees')], 'unpublished' => []];
			}
		}
	}

	pcsd_year_generator_render_page($results, $year_slug, $clone_fees, $cleanup_results, $cleanup_slug);
}

function pcsd_year_generator_page_slug_for_location($value)
{
	$overrides = [
		'global' => 'district-wide',
		'district' => 'district-office',
		'ebph' => 'provo-transition-services-ebph',
	];
	return $overrides[$value] ?? str_replace('_', '-', $value);
}

function pcsd_year_generator_ensure_term($year_slug)
{
	$term = get_term_by('slug', $year_slug, 'school_fee_year');
	if ($term) {
		return ['term_id' => (int) $term->term_id, 'created' => false];
	}
	$parts = explode('-', $year_slug);
	$label = (count($parts) === 2) ? '20' . $parts[0] . '-20' . $parts[1] : $year_slug;
	$created = wp_insert_term($label, 'school_fee_year', ['slug' => $year_slug]);
	if (is_wp_error($created)) {
		return ['term_id' => 0, 'created' => false, 'error' => $created->get_error_message()];
	}
	return ['term_id' => (int) $created['term_id'], 'created' => true];
}

function pcsd_year_generator_ensure_parent_page($year_slug, $language)
{
	$config = $language === 'es'
		? [
			'slug' => 'pagos-escolares-' . $year_slug,
			'title' => 'Pagos Escolares ' . $year_slug,
			'template' => 'template-pagos-escolares-menu.php',
		]
		: [
			'slug' => 'school-fees-' . $year_slug,
			'title' => 'School Fees ' . $year_slug,
			'template' => 'template-school-fee-menu.php',
		];

	$existing = get_page_by_path($config['slug'], OBJECT, 'page');
	if ($existing) {
		// Make sure the template assignment is current; harmless to overwrite if already correct.
		update_post_meta($existing->ID, '_wp_page_template', $config['template']);
		return ['page_id' => (int) $existing->ID, 'created' => false];
	}

	$page_id = wp_insert_post([
		'post_type' => 'page',
		'post_status' => 'publish',
		'post_title' => $config['title'],
		'post_name' => $config['slug'],
	], true);

	if (is_wp_error($page_id)) {
		return ['page_id' => 0, 'created' => false, 'error' => $page_id->get_error_message()];
	}

	update_post_meta($page_id, '_wp_page_template', $config['template']);
	return ['page_id' => (int) $page_id, 'created' => true];
}

function pcsd_year_generator_ensure_child_page($parent_id, $location_value, $location_label, $language)
{
	$page_slug = pcsd_year_generator_page_slug_for_location($location_value);
	$template = $language === 'es' ? 'template-pagos-escolares-by-location.php' : 'template-school-fees-by-location.php';

	$existing = get_posts([
		'post_type' => 'page',
		'post_status' => 'any',
		'name' => $page_slug,
		'post_parent' => $parent_id,
		'posts_per_page' => 1,
	]);

	if (!empty($existing)) {
		$page_id = (int) $existing[0]->ID;
		update_post_meta($page_id, '_wp_page_template', $template);
		// Refresh the location ACF field too in case it drifted.
		update_field('location_of_fees_to_display', $location_value, $page_id);
		return ['page_id' => $page_id, 'created' => false];
	}

	$page_id = wp_insert_post([
		'post_type' => 'page',
		'post_status' => 'publish',
		'post_title' => $location_label,
		'post_name' => $page_slug,
		'post_parent' => $parent_id,
	], true);

	if (is_wp_error($page_id)) {
		return ['page_id' => 0, 'created' => false, 'error' => $page_id->get_error_message()];
	}

	update_post_meta($page_id, '_wp_page_template', $template);
	update_field('location_of_fees_to_display', $location_value, $page_id);
	return ['page_id' => (int) $page_id, 'created' => true];
}

function pcsd_year_generator_find_source_year_term($new_year_slug)
{
	$all = get_terms([
		'taxonomy' => 'school_fee_year',
		'hide_empty' => false,
	]);
	if (empty($all) || is_wp_error($all)) {
		return null;
	}
	$candidates = [];
	foreach ($all as $term) {
		if ($term->slug !== $new_year_slug && strcmp($term->slug, $new_year_slug) < 0) {
			$candidates[] = $term;
		}
	}
	if (empty($candidates)) {
		return null;
	}
	usort($candidates, function ($a, $b) {
		return strcmp($b->slug, $a->slug);
	});
	return $candidates[0];
}

function pcsd_year_generator_clone_yearly_data($new_year_term_id, $new_year_slug, $cpt)
{
	$source_term = pcsd_year_generator_find_source_year_term($new_year_slug);
	if (!$source_term) {
		return ['cloned' => 0, 'skipped' => 0, 'error' => 'No prior year term found to clone from.'];
	}
	$source_slug = $source_term->slug;

	$programs = get_posts([
		'post_type' => $cpt,
		'post_status' => 'any',
		'posts_per_page' => -1,
		'tax_query' => [
			['taxonomy' => 'school_fee_year', 'field' => 'slug', 'terms' => $source_slug],
		],
		'fields' => 'ids',
	]);

	$cloned = 0;
	$skipped = 0;
	foreach ($programs as $program_id) {
		$yd = get_field('yearly_data', $program_id);
		if (!is_array($yd)) {
			continue;
		}
		$source_row = null;
		$already_has_new = false;
		foreach ($yd as $row) {
			if (empty($row['year']) || !is_object($row['year'])) {
				continue;
			}
			if ($row['year']->slug === $new_year_slug) {
				$already_has_new = true;
				break;
			}
			if ($row['year']->slug === $source_slug) {
				$source_row = $row;
			}
		}
		if ($already_has_new) {
			$skipped++;
			continue;
		}
		if (!$source_row) {
			$skipped++;
			continue;
		}
		$new_row = [
			'year' => $new_year_term_id,
			'overall_activity_fee_amounts' => $source_row['overall_activity_fee_amounts'] ?? [],
			'location_specific_fees' => $source_row['location_specific_fees'] ?? [],
		];
		$yd[] = $new_row;
		update_field('yearly_data', $yd, $program_id);
		// update_field doesn't fire acf/save_post, so re-run sync explicitly to keep the
		// school_fee_year taxonomy in step with what's now in yearly_data.
		pcsd_school_fees_sync_year_taxonomy($program_id);
		$cloned++;
	}

	return ['cloned' => $cloned, 'skipped' => $skipped, 'source_year' => $source_slug];
}

function pcsd_year_generator_resync_all_programs()
{
	// Backfill: walk every program and run the year-taxonomy sync. Necessary because
	// migration wrote yearly_data via update_field, which doesn't fire acf/save_post —
	// so the sync hook never ran for migrated data, leaving school_fee_year terms unset.
	// Idempotent and cheap; safe to run on every Generate Year invocation.
	$total = 0;
	foreach (['school_fees', 'pagos_escolares'] as $cpt) {
		$ids = get_posts([
			'post_type' => $cpt,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
		]);
		foreach ($ids as $id) {
			pcsd_school_fees_sync_year_taxonomy($id);
			$total++;
		}
	}
	return $total;
}

function pcsd_year_generator_run($year_slug, $clone_fees)
{
	$report = [
		'year_slug' => $year_slug,
		'term' => null,
		'resynced' => 0,
		'parents' => [],
		'children' => ['school_fees' => [], 'pagos_escolares' => []],
		'clone' => null,
		'errors' => [],
	];

	// Backfill the year taxonomy before doing anything year-aware. Migration-era
	// update_field calls didn't fire acf/save_post so taxonomy may be untagged for
	// many existing programs; the clone step's tax_query depends on it.
	$report['resynced'] = pcsd_year_generator_resync_all_programs();

	$term_result = pcsd_year_generator_ensure_term($year_slug);
	$report['term'] = $term_result;
	if (empty($term_result['term_id'])) {
		$report['errors'][] = 'Could not create or find year term.';
		return $report;
	}

	$choices = pcsd_school_fees_location_choices();

	foreach (['en', 'es'] as $language) {
		$parent = pcsd_year_generator_ensure_parent_page($year_slug, $language);
		$report['parents'][$language] = $parent;
		if (empty($parent['page_id'])) {
			$report['errors'][] = "Failed to create parent page for $language.";
			continue;
		}
		$cpt = $language === 'es' ? 'pagos_escolares' : 'school_fees';
		foreach ($choices as $value => $label) {
			$child = pcsd_year_generator_ensure_child_page($parent['page_id'], $value, $label, $language);
			$child['location_value'] = $value;
			$child['location_label'] = $label;
			$report['children'][$cpt][] = $child;
		}
	}

	if ($clone_fees) {
		$report['clone'] = [
			'school_fees' => pcsd_year_generator_clone_yearly_data($term_result['term_id'], $year_slug, 'school_fees'),
			'pagos_escolares' => pcsd_year_generator_clone_yearly_data($term_result['term_id'], $year_slug, 'pagos_escolares'),
		];
	}

	return $report;
}

function pcsd_year_generator_cleanup_pages($year_slug)
{
	$report = ['year_slug' => $year_slug, 'unpublished' => [], 'errors' => []];

	$parents = array_filter([
		get_page_by_path('school-fees-' . $year_slug),
		get_page_by_path('pagos-escolares-' . $year_slug),
	]);

	if (empty($parents)) {
		$report['errors'][] = sprintf(
			__('No year menu pages found for %s. Nothing to clean up.', 'pcsd-school-fees'),
			$year_slug
		);
		return $report;
	}

	foreach ($parents as $parent) {
		$pages = array_merge([$parent], get_pages(['parent' => $parent->ID, 'post_status' => ['publish', 'draft']]));
		foreach ($pages as $page) {
			if ($page->post_status === 'draft') {
				$report['unpublished'][] = ['id' => $page->ID, 'title' => $page->post_title, 'note' => 'already draft'];
				continue;
			}
			$result = wp_update_post(['ID' => $page->ID, 'post_status' => 'draft'], true);
			if (is_wp_error($result)) {
				$report['errors'][] = $page->post_title . ': ' . $result->get_error_message();
			} else {
				$report['unpublished'][] = ['id' => $page->ID, 'title' => $page->post_title, 'note' => 'set to draft'];
			}
		}
	}

	return $report;
}

function pcsd_year_generator_render_page($results, $year_slug, $clone_fees, $cleanup_results = null, $cleanup_slug = '')
{
?>
	<div class="wrap">
		<h1><?php esc_html_e('Generate School Fees Year', 'pcsd-school-fees'); ?></h1>
		<p><?php esc_html_e('Sets up everything a new school year needs: the year term, the English and Spanish landing pages, the per-school by-location pages under each, and (optionally) seeds each program with a yearly_data row cloned from the most recent prior year.', 'pcsd-school-fees'); ?></p>
		<p><strong><?php esc_html_e('Idempotent.', 'pcsd-school-fees'); ?></strong> <?php esc_html_e('Already-existing terms, pages, and yearly_data rows are detected and skipped — re-running on the same year just refreshes any drifted template assignments.', 'pcsd-school-fees'); ?></p>

		<form method="post">
			<?php wp_nonce_field('pcsd_year_generator'); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="year_slug"><?php esc_html_e('Year slug', 'pcsd-school-fees'); ?></label></th>
					<td>
						<input type="text" name="year_slug" id="year_slug" value="<?php echo esc_attr($year_slug); ?>" placeholder="26-27" pattern="\d{2}-\d{2}" required>
						<p class="description"><?php esc_html_e('Two-digit year range, e.g., 26-27 for 2026-2027. The label "2026-2027" is generated automatically.', 'pcsd-school-fees'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Clone fees', 'pcsd-school-fees'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="clone_fees" value="1" <?php checked($clone_fees, true); ?>>
							<?php esc_html_e('Copy fee data forward from the most recent prior year', 'pcsd-school-fees'); ?>
						</label>
						<p class="description"><?php esc_html_e('Each program that has a row for the prior year gets a duplicate row added for the new year, with the same dollar amounts and notes. Skipped if the program already has a row for the new year. Recommended for the annual rollover.', 'pcsd-school-fees'); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" name="pcsd_year_action" value="generate" class="button button-primary"><?php esc_html_e('Generate', 'pcsd-school-fees'); ?></button>
			</p>
		</form>

		<?php if ($results) : pcsd_year_generator_render_results($results);
		endif; ?>

		<hr>
		<h2><?php esc_html_e('Clean Up Year Pages', 'pcsd-school-fees'); ?></h2>
		<p><?php esc_html_e('Sets the year menu page and all per-school child pages for a given year to Draft, so they return 404 publicly. Fee data in programs is not touched. Use this after archiving a year term under School Fees → Years.', 'pcsd-school-fees'); ?></p>
		<form method="post">
			<?php wp_nonce_field('pcsd_year_generator'); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="cleanup_slug"><?php esc_html_e('Year slug to clean up', 'pcsd-school-fees'); ?></label></th>
					<td>
						<input type="text" name="cleanup_slug" id="cleanup_slug" value="<?php echo esc_attr($cleanup_slug); ?>" placeholder="24-25" pattern="\d{2}-\d{2}" required>
						<p class="description"><?php esc_html_e('e.g., 24-25. Sets the English and Spanish year pages to Draft. Reversible — restore from Pages list if needed.', 'pcsd-school-fees'); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" name="pcsd_year_action" value="cleanup_pages" class="button" onclick="return confirm('This will unpublish all pages for this year. Continue?');"><?php esc_html_e('Clean Up Year Pages', 'pcsd-school-fees'); ?></button>
			</p>
		</form>

		<?php if ($cleanup_results !== null) : pcsd_year_generator_render_cleanup_results($cleanup_results);
		endif; ?>
	</div>
<?php
}

function pcsd_year_generator_render_results($results)
{
?>
	<hr>
	<h2><?php esc_html_e('Results', 'pcsd-school-fees'); ?></h2>

	<?php if (!empty($results['errors'])) : ?>
		<div class="notice notice-error">
			<?php foreach ($results['errors'] as $error) : ?>
				<p><?php echo esc_html($error); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if (!empty($results['resynced'])) : ?>
		<p>
			<em><?php
				printf(
					esc_html__('Resynced school_fee_year taxonomy on %d existing programs.', 'pcsd-school-fees'),
					(int) $results['resynced']
				);
			?></em>
		</p>
	<?php endif; ?>

	<?php if (!empty($results['term'])) : ?>
		<h3><?php esc_html_e('Year term', 'pcsd-school-fees'); ?></h3>
		<p>
			<?php if (!empty($results['term']['created'])) : ?>
				<?php esc_html_e('Created new term:', 'pcsd-school-fees'); ?>
			<?php else : ?>
				<?php esc_html_e('Existing term reused:', 'pcsd-school-fees'); ?>
			<?php endif; ?>
			<code><?php echo esc_html($results['year_slug']); ?></code>
			(term ID <?php echo (int) $results['term']['term_id']; ?>)
		</p>
	<?php endif; ?>

	<?php if (!empty($results['parents'])) : ?>
		<h3><?php esc_html_e('Parent menu pages', 'pcsd-school-fees'); ?></h3>
		<ul>
			<?php foreach ($results['parents'] as $language => $parent) :
				$label = $language === 'es' ? 'Spanish' : 'English';
			?>
				<li>
					<?php echo esc_html($label); ?> —
					<?php echo !empty($parent['created']) ? esc_html__('created', 'pcsd-school-fees') : esc_html__('reused', 'pcsd-school-fees'); ?>
					<?php if (!empty($parent['page_id'])) : ?>
						<a href="<?php echo esc_url(get_edit_post_link($parent['page_id'])); ?>">edit</a>
						/ <a href="<?php echo esc_url(get_permalink($parent['page_id'])); ?>" target="_blank">view</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php foreach ($results['children'] ?? [] as $cpt => $children) :
		if (empty($children)) {
			continue;
		}
		$label = $cpt === 'pagos_escolares' ? 'Spanish' : 'English';
		$created_count = count(array_filter($children, fn($c) => !empty($c['created'])));
		$reused_count = count($children) - $created_count;
	?>
		<h3>
			<?php echo esc_html($label); ?>
			<?php esc_html_e('child (by-location) pages', 'pcsd-school-fees'); ?>
			— <?php echo (int) $created_count; ?> <?php esc_html_e('created', 'pcsd-school-fees'); ?>,
			<?php echo (int) $reused_count; ?> <?php esc_html_e('reused', 'pcsd-school-fees'); ?>
		</h3>
		<ul>
			<?php foreach ($children as $child) : ?>
				<li>
					<?php echo esc_html($child['location_label']); ?>
					(<code><?php echo esc_html($child['location_value']); ?></code>)
					— <?php echo !empty($child['created']) ? esc_html__('created', 'pcsd-school-fees') : esc_html__('reused', 'pcsd-school-fees'); ?>
					<?php if (!empty($child['page_id'])) : ?>
						<a href="<?php echo esc_url(get_edit_post_link($child['page_id'])); ?>">edit</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>

	<?php if (!empty($results['clone'])) : ?>
		<h3><?php esc_html_e('Fee data clone', 'pcsd-school-fees'); ?></h3>
		<?php foreach ($results['clone'] as $cpt => $clone) :
			$label = $cpt === 'pagos_escolares' ? 'Spanish' : 'English';
		?>
			<p>
				<strong><?php echo esc_html($label); ?>:</strong>
				<?php if (!empty($clone['error'])) : ?>
					<em><?php echo esc_html($clone['error']); ?></em>
				<?php else : ?>
					<?php echo (int) $clone['cloned']; ?> <?php esc_html_e('programs cloned forward from', 'pcsd-school-fees'); ?>
					<code><?php echo esc_html($clone['source_year'] ?? '?'); ?></code>,
					<?php echo (int) $clone['skipped']; ?> <?php esc_html_e('skipped (already had data for the new year, or no source row to clone from)', 'pcsd-school-fees'); ?>
				<?php endif; ?>
			</p>
		<?php endforeach; ?>
	<?php endif; ?>
<?php
}

function pcsd_year_generator_render_cleanup_results($results)
{
?>
	<hr>
	<h2><?php esc_html_e('Cleanup Results', 'pcsd-school-fees'); ?></h2>
	<?php if (!empty($results['errors'])) : ?>
		<div class="notice notice-error">
			<?php foreach ($results['errors'] as $error) : ?>
				<p><?php echo esc_html($error); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if (!empty($results['unpublished'])) : ?>
		<p><?php printf(esc_html__('%d pages updated for year %s.', 'pcsd-school-fees'), count($results['unpublished']), esc_html($results['year_slug'] ?? '')); ?></p>
		<ul>
			<?php foreach ($results['unpublished'] as $item) : ?>
				<li>
					<?php echo esc_html($item['title']); ?>
					— <em><?php echo esc_html($item['note']); ?></em>
					<?php if (!empty($item['id'])) : ?>
						<a href="<?php echo esc_url(get_edit_post_link($item['id'])); ?>"><?php esc_html_e('edit', 'pcsd-school-fees'); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php elseif (empty($results['errors'])) : ?>
		<p><em><?php esc_html_e('No pages found to clean up.', 'pcsd-school-fees'); ?></em></p>
	<?php endif; ?>
<?php
}
