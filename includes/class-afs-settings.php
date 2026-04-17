<?php
/**
 * WordPress admin settings page (Settings → Afturgjald).
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Settings {

    public static function render() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $log_path   = AFS_Logger::log_path();
        $log_exists = file_exists($log_path);
        $log_size   = $log_exists ? (int) @filesize($log_path) : 0;
        ?>
        <div class="wrap">
            <h1>Afturgjald — stillingar</h1>

            <form method="post" action="options.php">
                <?php settings_fields('drf_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Gjald pr. km</th>
                        <td>
                            <input type="text" name="drf_rate_per_km" value="<?php echo esc_attr(get_option('drf_rate_per_km', '1.60')); ?>">
                            <p class="description">Fyribils 1,60 kr/km. Skriva sum <code>1.60</code>.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>

            <h2>Próvingarham (fyri staging / lokal royning)</h2>
            <p>Fyri at royna skipanina uttan at senda nakran teldupost beinleiðis til bókhaldið, legg hetta í <code>wp-config.php</code>:</p>
            <pre><code>define('DRF_EMAIL_TEST_MODE', true);
define('DRF_EMAIL_TEST_TO', 'tín@epost.fo');   // valfrítt — annars vert admin-teldupostur brúktur
define('DRF_EMAIL_DRY_RUN', true);             // valfrítt — um true, verður ongin teldupostur sendur; bert skrivað í loggin
define('DRF_EMAIL_LOG_FILE', '/absolut/path/til/afs-email.log'); // valfrítt</code></pre>

            <p><strong>Loggur:</strong> <code><?php echo esc_html($log_path); ?></code>
                <?php if ($log_exists): ?>(<?php echo esc_html(size_format($log_size)); ?>)<?php else: ?>(ikki enn stovnaður)<?php endif; ?>
            </p>

            <?php if ($log_exists && $log_size > 0): ?>
                <h3>Seinasti partur av logginum</h3>
                <pre style="background:#f6f7f7;padding:1rem;overflow:auto;max-height:420px;border:1px solid #dcdcde;"><?php echo esc_html(self::log_tail($log_path, 6000)); ?></pre>
                <form method="post" style="margin-top:0.5rem;">
                    <?php wp_nonce_field('afs_clear_log', 'afs_clear_nonce'); ?>
                    <input type="hidden" name="afs_action" value="clear_log">
                    <?php submit_button('Tøm log', 'delete', 'submit', false); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function maybe_clear_log() {
        if (
            !empty($_POST['afs_action']) && $_POST['afs_action'] === 'clear_log'
            && !empty($_POST['afs_clear_nonce'])
            && wp_verify_nonce($_POST['afs_clear_nonce'], 'afs_clear_log')
            && current_user_can('manage_options')
        ) {
            $path = AFS_Logger::log_path();
            if (file_exists($path)) { @unlink($path); }
            if (function_exists('wp_safe_redirect')) {
                wp_safe_redirect(add_query_arg('afs_cleared', '1'));
                exit;
            }
        }
    }

    private static function log_tail($path, $chars) {
        $size = (int) @filesize($path);
        if ($size <= 0) { return ''; }
        $fp = @fopen($path, 'r');
        if (!$fp) { return ''; }
        $start = max(0, $size - $chars);
        fseek($fp, $start);
        $content = stream_get_contents($fp);
        fclose($fp);
        return $content === false ? '' : $content;
    }
}
