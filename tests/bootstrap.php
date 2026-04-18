<?php
/**
 * Test-suite bootstrap. Loads WordPress stubs and plugin classes.
 *
 * @package AfturgjaldSkipan\Tests
 */

require_once __DIR__ . '/wp-stubs.php';

if (!defined('DRF_EMAIL_TEST_MODE')) { define('DRF_EMAIL_TEST_MODE', true); }
if (!defined('DRF_EMAIL_TEST_TO'))   { define('DRF_EMAIL_TEST_TO', 'tester@example.com'); }
if (!defined('DRF_EMAIL_DRY_RUN'))   { define('DRF_EMAIL_DRY_RUN', true); }

$log_file = sys_get_temp_dir() . '/afs-test-email.log';
if (file_exists($log_file)) { @unlink($log_file); }
if (!defined('DRF_EMAIL_LOG_FILE')) { define('DRF_EMAIL_LOG_FILE', $log_file); }

$root = dirname(__DIR__);

require_once $root . '/includes/class-afs-logger.php';
require_once $root . '/includes/class-afs-mail.php';
require_once $root . '/includes/types/class-afs-type.php';
require_once $root . '/includes/types/class-afs-type-driving.php';
require_once $root . '/includes/types/class-afs-type-expense.php';
require_once $root . '/includes/class-afs-types.php';
require_once $root . '/includes/class-afs-file-stage.php';
require_once $root . '/includes/class-afs-store.php';
require_once $root . '/includes/class-afs-submission.php';

// Ensure the submissions table exists in the in-memory SQLite stub so
// AFS_Store queries work end-to-end.
AFS_Store::install_table();
