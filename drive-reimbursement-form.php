<?php
/**
 * Plugin Name: Afturgjald — skipan
 * Description: Fleksibul afturgjaldsskrá við fleiri linjum og ymsum sløgum (koyring, útreiðslur, annað), við møguleika fyri viðheftingum og próvingarhami.
 * Version: 2.0
 * Author: Niels Áki Mørk (FSS)
 *
 * NOTE: Entry filnavnið er `drive-reimbursement-form.php` fyri at varðveita plugin-aktiveringina í WordPress (WP keyar plugins eftir filnavni). Sjálv skipanin er nú allýst undir `afturgjald-skipan` / AFS_*.
 */

if (!defined('ABSPATH')) { exit; }

define('AFS_VERSION',     '2.0');
define('AFS_PLUGIN_FILE', __FILE__);
define('AFS_PLUGIN_DIR',  plugin_dir_path(__FILE__));
define('AFS_PLUGIN_URL',  plugin_dir_url(__FILE__));

require_once AFS_PLUGIN_DIR . 'includes/class-afs-logger.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-mail.php';
require_once AFS_PLUGIN_DIR . 'includes/types/class-afs-type.php';
require_once AFS_PLUGIN_DIR . 'includes/types/class-afs-type-driving.php';
require_once AFS_PLUGIN_DIR . 'includes/types/class-afs-type-expense.php';
require_once AFS_PLUGIN_DIR . 'includes/types/class-afs-type-other.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-types.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-submission.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-form.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-settings.php';
require_once AFS_PLUGIN_DIR . 'includes/class-afs-plugin.php';

AFS_Plugin::init();
