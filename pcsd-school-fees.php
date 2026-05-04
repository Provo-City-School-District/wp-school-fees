<?php
/*
 * Plugin Name: PCSD School Fees
 * Plugin URI: https://employee.provo.edu/technology
 * Description: Unified plugin for managing school fees across years. One post per program (e.g. "Culinary"); each program holds yearly fee data inside a repeater. Spanish lives in a parallel CPT (pagos_escolares) that can be stripped if TranslatePress takes over.
 * Author: Josh Espinoza
 * Version: 2.0.0
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pcsd-school-fees
 */

defined('ABSPATH') or die('No script kiddies please!');

define('PCSD_SCHOOL_FEES_DIR', plugin_dir_path(__FILE__));
define('PCSD_SCHOOL_FEES_URL', plugin_dir_url(__FILE__));
define('PCSD_SCHOOL_FEES_FILE', __FILE__);

require_once PCSD_SCHOOL_FEES_DIR . 'includes/post-types.php';
require_once PCSD_SCHOOL_FEES_DIR . 'includes/taxonomy.php';
require_once PCSD_SCHOOL_FEES_DIR . 'includes/locations.php';
require_once PCSD_SCHOOL_FEES_DIR . 'includes/acf-fields.php';
require_once PCSD_SCHOOL_FEES_DIR . 'includes/sync.php';
require_once PCSD_SCHOOL_FEES_DIR . 'includes/shortcodes.php';
require_once PCSD_SCHOOL_FEES_DIR . 'includes/templates.php';
if (is_admin()) {
	require_once PCSD_SCHOOL_FEES_DIR . 'includes/admin.php';
	require_once PCSD_SCHOOL_FEES_DIR . 'includes/migration.php';
	require_once PCSD_SCHOOL_FEES_DIR . 'includes/year-generator.php';
	require_once PCSD_SCHOOL_FEES_DIR . 'includes/help.php';
}

register_activation_hook(__FILE__, 'pcsd_school_fees_activate');
register_deactivation_hook(__FILE__, 'pcsd_school_fees_deactivate');

function pcsd_school_fees_activate()
{
	pcsd_school_fees_register_post_types();
	pcsd_school_fees_register_taxonomy();
	flush_rewrite_rules();
}

function pcsd_school_fees_deactivate()
{
	flush_rewrite_rules();
}
