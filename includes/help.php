<?php
defined('ABSPATH') or die('No script kiddies please!');

add_action('admin_menu', 'pcsd_school_fees_help_menu');

function pcsd_school_fees_help_menu()
{
	add_submenu_page(
		'edit.php?post_type=school_fees',
		__('School Fees — How to Use', 'pcsd-school-fees'),
		__('How to Use', 'pcsd-school-fees'),
		'edit_posts',
		'pcsd-school-fees-help',
		'pcsd_school_fees_help_page'
	);
}

function pcsd_school_fees_help_page()
{
	$programs_url = esc_url(admin_url('edit.php?post_type=school_fees'));
?>
<div class="wrap">
	<h1><?php esc_html_e('School Fees — How to Use', 'pcsd-school-fees'); ?></h1>
	<p><?php esc_html_e('This page covers the day-to-day tasks for managing fee data. If something is broken or you need a new school year set up, contact the web team.', 'pcsd-school-fees'); ?></p>

	<hr>

	<h2><?php esc_html_e('The basics', 'pcsd-school-fees'); ?></h2>
	<p><?php esc_html_e('Each program — Band, Culinary, Football, etc. — is one record. All years of fees for that program live inside that one record. You never create a new record just because the year changed; you add a new year row to the existing one.', 'pcsd-school-fees'); ?></p>

	<hr>

	<h2><?php esc_html_e('Updating fees for a new school year', 'pcsd-school-fees'); ?></h2>
	<p><?php esc_html_e('This is the most common task — the new year rows may already exist (pre-filled with last year\'s amounts) and you just need to update the numbers.', 'pcsd-school-fees'); ?></p>
	<ol>
		<li><?php printf(
			/* translators: %s: link to programs list */
			esc_html__('Go to %s and click the program you want to update.', 'pcsd-school-fees'),
			'<a href="' . $programs_url . '">' . esc_html__('School Fees → Programs', 'pcsd-school-fees') . '</a>'
		); ?></li>
		<li><?php esc_html_e('Scroll down to "Yearly Fee Data". Each collapsed row is one school year — click a row to expand it.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('If the new year row is already there (pre-filled from last year), update the amounts directly. If there is no row yet for the new year, click "Add Year" at the bottom of the list and fill it in from scratch.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Inside a year row:', 'pcsd-school-fees'); ?>
			<ul style="list-style:disc;margin:.5em 0 .5em 2em;">
				<li><?php esc_html_e('Year — the school year this row applies to (e.g., 2026-2027). Do not change this if a row was pre-filled.', 'pcsd-school-fees'); ?></li>
				<li><?php esc_html_e('Overall Activity Fee — the summary totals shown at the top of the fee table on the public page.', 'pcsd-school-fees'); ?></li>
				<li><?php esc_html_e('Location Specific Fees — one section per school. Expand a school to see and edit its individual fee line items.', 'pcsd-school-fees'); ?></li>
			</ul>
		</li>
		<li><?php esc_html_e('Click "Update" at the top right when done.', 'pcsd-school-fees'); ?></li>
	</ol>

	<hr>

	<h2><?php esc_html_e('Adding a brand new program', 'pcsd-school-fees'); ?></h2>
	<ol>
		<li><?php printf(
			/* translators: %s: link to programs list */
			esc_html__('Go to %s and click "Add New Program" at the top.', 'pcsd-school-fees'),
			'<a href="' . $programs_url . '">' . esc_html__('School Fees → Programs', 'pcsd-school-fees') . '</a>'
		); ?></li>
		<li><?php esc_html_e('Enter the program title — use the full name as it appears in the fee schedule (e.g., "Culinary Arts").', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('In the "Fee Categories" box on the right side, check the category this program belongs to (e.g., CTE, Athletics).', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Under "Yearly Fee Data", click "Add Year" and fill in the current year\'s fee information.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Click "Publish" when ready for it to appear on the public site.', 'pcsd-school-fees'); ?></li>
	</ol>
	<p><strong><?php esc_html_e('If you are not ready to publish yet:', 'pcsd-school-fees'); ?></strong>
		<?php esc_html_e('Leave it as a draft. Drafts do not appear on the public site.', 'pcsd-school-fees'); ?>
	</p>

	<hr>

	<h2><?php esc_html_e('Adding or updating a fee line item', 'pcsd-school-fees'); ?></h2>
	<p><?php esc_html_e('Inside a year row → Location Specific Fees → expand a school → Breakdown of Fees:', 'pcsd-school-fees'); ?></p>
	<ul style="list-style:disc;margin:.5em 0 .5em 2em;">
		<li><?php esc_html_e('Fee Description — the name of the fee (e.g., "Activity Fee").', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Fee — the dollar amount (e.g., 75.00). Enter numbers only; do not include the $ sign.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Fundraising — fundraising amount, if applicable.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Total — combined total, if applicable.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Notes — any clarification for this specific line item.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Prior Year Approved Fee — last year\'s approved fee, for reference.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Bold Row — toggle on to make this row bold in the table (typically used for total rows).', 'pcsd-school-fees'); ?></li>
	</ul>
	<p><?php esc_html_e('To add a new line: click "Add Fee" at the bottom of the breakdown. To remove a line: click the minus (−) icon on the right side of that row.', 'pcsd-school-fees'); ?></p>

	<hr>

	<h2><?php esc_html_e('Uploading a fee summary PDF', 'pcsd-school-fees'); ?></h2>
	<p><?php esc_html_e('Each school\'s per-year page has a PDF upload field. The PDF appears as a link at the top of that school\'s public fee page.', 'pcsd-school-fees'); ?></p>
	<ol>
		<li><?php esc_html_e('In the left sidebar, go to Pages and find the year landing page (e.g., "School Fees 26-27").', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Expand it to see the per-school child pages. Click the school you want to upload for.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Scroll to the "Fee Summary PDF" field. Click "Add File", then upload or select the PDF from the media library.', 'pcsd-school-fees'); ?></li>
		<li><?php esc_html_e('Click Update to save. The link appears on the public page immediately.', 'pcsd-school-fees'); ?></li>
	</ol>

	<hr>

	<h2><?php esc_html_e('Where fees appear on the public site', 'pcsd-school-fees'); ?></h2>
	<p><?php esc_html_e('Understanding this helps when you are checking your work after saving.', 'pcsd-school-fees'); ?></p>
	<table class="widefat" style="max-width:680px;">
		<thead>
			<tr>
				<th><?php esc_html_e('Page', 'pcsd-school-fees'); ?></th>
				<th><?php esc_html_e('What it shows', 'pcsd-school-fees'); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php esc_html_e('Program page (e.g., /school-fees/culinary-arts/)', 'pcsd-school-fees'); ?></td>
				<td><?php esc_html_e('All years for that program, newest first. Click "View" on the program in the admin to see it.', 'pcsd-school-fees'); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e('Per-school page (e.g., /school-fees-26-27/provo-high/)', 'pcsd-school-fees'); ?></td>
				<td><?php esc_html_e('All fees for that school in that year, across all programs.', 'pcsd-school-fees'); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e('Year landing page (e.g., /school-fees-26-27/)', 'pcsd-school-fees'); ?></td>
				<td><?php esc_html_e('Links to every school and every category for that year.', 'pcsd-school-fees'); ?></td>
			</tr>
		</tbody>
	</table>
	<p style="margin-top:.75em;"><?php esc_html_e('After saving a program, the quickest way to check your work is to click "View" at the top of the edit screen.', 'pcsd-school-fees'); ?></p>

</div>
<?php
}
