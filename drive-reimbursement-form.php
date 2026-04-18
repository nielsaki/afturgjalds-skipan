<?php
/**
 * Plugin Name: Endurgjald — skipan
 * Description: Fleksibul endurgjaldsskrá við fleiri linjum (koyring og útreiðsla), við møguleika fyri viðheftingum, próvingarhami og viðmerkingarsíðu fyri goymdar fráboðanir.
 * Version: 2.2.1
 * Author: Niels Áki Mørk (FSS)
 *
 * NOTE: Entry filnavnið er `drive-reimbursement-form.php` fyri at varðveita plugin-aktiveringina í WordPress (WP keyar plugins eftir filnavni). Sjálv skipanin er nú allýst undir `afturgjald-skipan` / AFS_*.
 */

if (!defined('ABSPATH')) { exit; }

define('AFS_VERSION',     '2.2.1');
define('AFS_PLUGIN_FILE', __FILE__);
define('AFS_PLUGIN_DIR',  plugin_dir_path(__FILE__));
define('AFS_PLUGIN_URL',  plugin_dir_url(__FILE__));

require_once AFS_PLUGIN_DIR . 'includes/class-afs-logger.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-mail.php';
require_once AFS_PLUGIN_DIR . 'includes/types/class-afs-type.php';
require_once AFS_PLUGIN_DIR . 'includes/types/class-afs-type-driving.php';
require_once AFS_PLUGIN_DIR . 'includes/types/class-afs-type-expense.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-types.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-file-stage.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-store.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-submission.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-form.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-settings.php';

if (is_admin()) {
    require_once AFS_PLUGIN_DIR . 'includes/admin/class-afs-admin-list-table.php';
    require_once AFS_PLUGIN_DIR . 'includes/admin/class-afs-admin-submissions.php';
}

require_once AFS_PLUGIN_DIR . 'includes/class-afs-plugin.php';

register_activation_hook(__FILE__, ['AFS_Plugin', 'activate']);

AFS_Plugin::init();
