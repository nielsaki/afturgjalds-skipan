<?php
/**
 * Standalone preview server. Renders the real form + submission pipeline
 * using the WordPress stubs, so you can click around and see what the
 * plugin would email — without installing WordPress.
 *
 * Usage (from the plugin folder):
 *   php -S localhost:8080 -t . tests/serve.php
 * Then open http://localhost:8080/
 *
 * @package AfturgjaldSkipan\Tests
 */

$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

if (strpos($path, '/assets/') === 0 && is_file(__DIR__ . '/..' . $path)) {
    return false;
}

// Use a file-backed SQLite DB so persisted submissions survive across
// preview requests (instead of the default in-memory test DB).
$GLOBALS['AFS_WPDB_PATH'] = sys_get_temp_dir() . '/afs-preview.sqlite';

require __DIR__ . '/wp-stubs.php';

if (!defined('DRF_EMAIL_TEST_MODE')) { define('DRF_EMAIL_TEST_MODE', true); }
if (!defined('DRF_EMAIL_TEST_TO'))   { define('DRF_EMAIL_TEST_TO',   'tester@example.com'); }
if (!defined('DRF_EMAIL_DRY_RUN'))   { define('DRF_EMAIL_DRY_RUN',   true); }
if (!defined('DRF_EMAIL_LOG_FILE')) {
    define('DRF_EMAIL_LOG_FILE', sys_get_temp_dir() . '/afs-preview.log');
}

$root = dirname(__DIR__);
require_once $root . '/includes/class-afs-logger.php';
require_once $root . '/includes/class-afs-mail.php';
require_once $root . '/includes/types/class-afs-type.php';
require_once $root . '/includes/types/class-afs-type-driving.php';
require_once $root . '/includes/types/class-afs-type-expense.php';
require_once $root . '/includes/types/class-afs-type-other.php';
require_once $root . '/includes/class-afs-types.php';
require_once $root . '/includes/class-afs-store.php';
require_once $root . '/includes/class-afs-submission.php';
require_once $root . '/includes/class-afs-form.php';

AFS_Store::install_table();

if ($path === '/clear-log') {
    $p = AFS_Logger::log_path();
    if (file_exists($p)) { @unlink($p); }
    header('Location: /');
    exit;
}

if ($path === '/clear-submissions') {
    global $wpdb;
    $wpdb->query('DELETE FROM ' . AFS_Store::table());
    header('Location: /');
    exit;
}

if ($path === '/delete-submission' && !empty($_GET['id'])) {
    AFS_Store::delete((int) $_GET['id']);
    header('Location: /');
    exit;
}

$form_html   = AFS_Form::render();
$log_path    = AFS_Logger::log_path();
$log_content = file_exists($log_path) ? (string) file_get_contents($log_path) : '';

$js_data = json_encode([
    'ratePerKm' => AFS_Type_Driving::rate_per_km(),
    'tunnels'   => AFS_Type_Driving::tunnels(),
], JSON_UNESCAPED_UNICODE);

$has_log     = trim($log_content) !== '';
$submissions = AFS_Store::query(['per_page' => 50]);
$labels      = AFS_Store::statuses();
?>
<!DOCTYPE html>
<html lang="fo">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Afturgjald — lokal próving</title>
    <link rel="stylesheet" href="/assets/css/drf.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f0f1; margin: 0; padding: 0; color: #1d2327; }
        .demo-header { background: #1d2327; color: #fff; padding: 1rem 1.5rem; }
        .demo-header h1 { margin: 0; font-size: 18px; font-weight: 600; }
        .demo-header p { margin: 0.25rem 0 0; opacity: 0.75; font-size: 13px; }
        .demo-header code { background: rgba(255,255,255,0.1); padding: 1px 6px; border-radius: 3px; font-size: 12px; }
        .demo-cols { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1rem; padding: 1rem; max-width: 1500px; margin: 0 auto; align-items: start; }
        .demo-col h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #666; margin: 0 0 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        .demo-col h2 a { color: #d63638; font-weight: 600; text-decoration: none; font-size: 11px; }
        .demo-col h2 a:hover { text-decoration: underline; }
        .demo-log { background: #1d2327; color: #d0d4d8; padding: 1rem; border-radius: 6px; font-family: "SF Mono", Menlo, Consolas, monospace; font-size: 12px; line-height: 1.5; overflow: auto; max-height: 85vh; white-space: pre-wrap; word-break: break-word; }
        .demo-log.empty { color: #8a8f94; font-style: italic; }
        .afs-wrap { margin: 0; }
        .demo-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; font-size: 13px; }
        .demo-table th, .demo-table td { padding: 8px 10px; border-bottom: 1px solid #e5e7ea; text-align: left; }
        .demo-table th { background: #f6f7f7; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: #666; }
        .demo-table tr:last-child td { border-bottom: none; }
        .demo-table .afs-status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #e5f0fb; color: #0a4b78; }
        .demo-empty { padding: 1rem; background: #fff; border-radius: 6px; color: #8a8f94; font-style: italic; }
        .demo-section { padding: 1rem; max-width: 1500px; margin: 0 auto; }
        .demo-section h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #666; margin: 0 0 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        .demo-section h2 a { color: #d63638; font-weight: 600; text-decoration: none; font-size: 11px; margin-left: 0.5rem; }
        .demo-section h2 a:hover { text-decoration: underline; }
        @media (max-width: 960px) { .demo-cols { grid-template-columns: 1fr; } .demo-log { max-height: 60vh; } }
    </style>
</head>
<body>
    <div class="demo-header">
        <h1>Afturgjald skipan — lokal próving</h1>
        <p>
            Dry-run er á: <strong>ongin</strong> teldupostur verður sendur. Alt verður skrivað í log-kolonnuna.
            Loggfíla: <code><?php echo htmlspecialchars($log_path, ENT_QUOTES, 'UTF-8'); ?></code>
        </p>
    </div>

    <div class="demo-cols">
        <div class="demo-col">
            <h2>Formurin</h2>
            <?php echo $form_html; ?>
        </div>

        <div class="demo-col">
            <h2>
                <span>Loggur</span>
                <?php if ($has_log): ?><a href="/clear-log">Tøm log</a><?php endif; ?>
            </h2>
            <pre class="demo-log<?php echo $has_log ? '' : ' empty'; ?>" id="afs-log"><?php
                if ($has_log) {
                    echo htmlspecialchars($log_content, ENT_QUOTES, 'UTF-8');
                } else {
                    echo '(Einki í logginum enn. Fyll formin og trýst á "Send inn".)';
                }
            ?></pre>
        </div>
    </div>

    <div class="demo-section">
        <h2>
            <span>Goymdar fráboðanir (<?php echo count($submissions['items']); ?>)</span>
            <?php if ($submissions['items']): ?>
                <a href="/clear-submissions" onclick="return confirm('Strika allar?')">Strika allar</a>
            <?php endif; ?>
        </h2>
        <?php if (!$submissions['items']): ?>
            <div class="demo-empty">Ongar fráboðanir eru goymdar enn. Send formin inn fyri at fáa eina í hesa tabellina.</div>
        <?php else: ?>
            <table class="demo-table">
                <thead>
                    <tr>
                        <th>#</th><th>Dagur</th><th>Navn</th><th>Teldupostur</th>
                        <th>Linjur</th><th>Upphædd</th><th>Støða</th><th>Sendur</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions['items'] as $row): ?>
                        <tr>
                            <td><strong>#<?php echo (int) $row['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $row['line_count']; ?></td>
                            <td><?php echo number_format((float) $row['total_amount'], 2, ',', ' '); ?> kr</td>
                            <td><span class="afs-status"><?php echo htmlspecialchars($labels[$row['status']] ?? $row['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo !empty($row['sent_ok']) ? '✓' : '<span style="color:#d63638">✗</span>'; ?></td>
                            <td><a href="/delete-submission?id=<?php echo (int) $row['id']; ?>" onclick="return confirm('Strika?')" style="color:#d63638">Strika</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="color:#666;font-size:12px;margin:0.5rem 0 0;">
                (Hetta er ein einfaldur listi fyri lokala próving. Í WordPress fært tú eitt fullfíggjað admin-borð við filtrum, søku og yvirskriftum.)
            </p>
        <?php endif; ?>
    </div>

    <script>window.AFS_DATA = <?php echo $js_data; ?>;</script>
    <script src="/assets/js/drf.js"></script>
    <script>
        (function () {
            var log = document.getElementById('afs-log');
            if (log && !log.classList.contains('empty')) {
                log.scrollTop = log.scrollHeight;
            }
        })();
    </script>
</body>
</html>
