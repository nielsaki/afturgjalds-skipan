<?php
/**
 * Minimal stubs for WordPress functions so the plugin can be exercised
 * headlessly by the test runner.
 *
 * @package AfturgjaldSkipan\Tests
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['AFS_TEST_OPTIONS'] = [
    'drf_rate_per_km' => '1.60',
    'admin_email'     => 'admin@example.com',
];
$GLOBALS['AFS_TEST_MAILS'] = [];

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s) {
        if (!is_scalar($s)) { return ''; }
        $s = (string) $s;
        $s = strip_tags($s);
        $s = preg_replace('/[\r\n\t]+/', ' ', $s);
        return trim((string) $s);
    }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($s) {
        if (!is_scalar($s)) { return ''; }
        $e = filter_var(trim((string) $s), FILTER_VALIDATE_EMAIL);
        return $e ? $e : '';
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($s) {
        return is_scalar($s) ? trim(strip_tags((string) $s)) : '';
    }
}
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($s) {
        $s = is_scalar($s) ? (string) $s : 'file';
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $s);
    }
}
if (!function_exists('esc_attr'))    { function esc_attr($s)    { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_html'))    { function esc_html($s)    { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_textarea')){ function esc_textarea($s){ return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } }

if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($n, $a) { return $n === 'TEST_NONCE' ? 1 : false; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a)     { return 'TEST_NONCE'; } }
if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($a, $n = '_wpnonce', $referer = true, $echo = true) {
        $out = '<input type="hidden" name="' . $n . '" value="TEST_NONCE">';
        if ($echo) { echo $out; }
        return $out;
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return array_key_exists($name, $GLOBALS['AFS_TEST_OPTIONS'])
            ? $GLOBALS['AFS_TEST_OPTIONS'][$name]
            : $default;
    }
}
if (!function_exists('get_site_url')) { function get_site_url() { return 'https://test.local'; } }
if (!function_exists('apply_filters')) { function apply_filters($tag, $value) { $a = func_get_args(); return $value; } }
if (!function_exists('add_action'))    { function add_action() {} }
if (!function_exists('add_shortcode')) { function add_shortcode() {} }
if (!function_exists('add_filter'))    { function add_filter() {} }
if (!function_exists('current_user_can')) { function current_user_can() { return true; } }
if (!function_exists('checked')) {
    function checked($v, $check = true, $echo = true) {
        $r = ($v == $check) ? ' checked' : '';
        if ($echo) { echo $r; }
        return $r;
    }
}
if (!function_exists('selected')) {
    function selected($v, $check = true, $echo = true) {
        $r = ($v == $check) ? ' selected' : '';
        if ($echo) { echo $r; }
        return $r;
    }
}
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        $base = sys_get_temp_dir() . '/afs-test-uploads';
        if (!is_dir($base)) { @mkdir($base, 0777, true); }
        return ['basedir' => $base, 'baseurl' => 'http://test.local/uploads'];
    }
}
if (!function_exists('wp_mkdir_p'))    { function wp_mkdir_p($p) { return is_dir($p) || @mkdir($p, 0777, true); } }
if (!function_exists('trailingslashit')){ function trailingslashit($s){ return rtrim((string)$s, '/\\') . '/'; } }
if (!function_exists('size_format'))   {
    function size_format($bytes) {
        $bytes = (int) $bytes;
        if ($bytes < 1024) { return $bytes . ' B'; }
        if ($bytes < 1024 * 1024) { return round($bytes / 1024, 1) . ' KB'; }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
if (!function_exists('get_temp_dir'))  { function get_temp_dir() { return sys_get_temp_dir() . '/'; } }

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $body, $headers = [], $attachments = []) {
        $GLOBALS['AFS_TEST_MAILS'][] = compact('to', 'subject', 'body', 'headers', 'attachments');
        return true;
    }
}

if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return rtrim(dirname($f), '/\\') . '/'; } }
if (!function_exists('plugin_dir_url'))  { function plugin_dir_url($f)  { return 'https://test.local/' . basename(dirname($f)) . '/'; } }
