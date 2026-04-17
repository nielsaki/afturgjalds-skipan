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
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        if ($tag === 'afs_store_submission' && !empty($GLOBALS['AFS_TEST_FILTER_STORE_OFF'])) {
            return false;
        }
        return $value;
    }
}
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
        if (!empty($GLOBALS['AFS_TEST_MAIL_FORCE_FAIL'])) { return false; }
        return true;
    }
}

if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return rtrim(dirname($f), '/\\') . '/'; } }
if (!function_exists('plugin_dir_url'))  { function plugin_dir_url($f)  { return 'https://test.local/' . basename(dirname($f)) . '/'; } }

if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
if (!defined('ARRAY_N')) { define('ARRAY_N', 'ARRAY_N'); }
if (!defined('OBJECT'))  { define('OBJECT', 'OBJECT'); }

if (!function_exists('current_time')) {
    function current_time($type = 'mysql') { return date('Y-m-d H:i:s'); }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options | JSON_UNESCAPED_UNICODE, $depth);
    }
}
if (!function_exists('update_option')) {
    function update_option($name, $value) {
        $GLOBALS['AFS_TEST_OPTIONS'][$name] = $value;
        return true;
    }
}
if (!function_exists('add_option')) {
    function add_option($name, $value) {
        if (!array_key_exists($name, $GLOBALS['AFS_TEST_OPTIONS'])) {
            $GLOBALS['AFS_TEST_OPTIONS'][$name] = $value;
            return true;
        }
        return false;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($name) { unset($GLOBALS['AFS_TEST_OPTIONS'][$name]); return true; }
}

/**
 * Minimal SQLite-backed $wpdb stub. Implements only the subset of the
 * real $wpdb API that AFS_Store actually calls. It's not meant to be a
 * general replacement.
 */
if (!class_exists('AFS_Test_Wpdb')) {
    class AFS_Test_Wpdb {
        public $prefix    = 'wptest_';
        public $insert_id = 0;
        /** @var PDO */
        private $pdo;

        public function __construct($path = ':memory:') {
            $this->pdo = new PDO('sqlite:' . $path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        public function get_charset_collate() { return ''; }

        public function esc_like($s) { return addcslashes((string) $s, '_%\\'); }

        public function prepare($query, ...$args) {
            if (count($args) === 1 && is_array($args[0])) { $args = $args[0]; }
            $i = 0;
            return preg_replace_callback('/%[sdf]/', function ($m) use (&$args, &$i) {
                $v = array_key_exists($i, $args) ? $args[$i] : '';
                $i++;
                if ($m[0] === '%d') { return (string) (int) $v; }
                if ($m[0] === '%f') { return (string) (float) $v; }
                return $this->pdo->quote((string) $v);
            }, $query);
        }

        public function insert($table, $data, $formats = null) {
            $cols = array_map([$this, 'ident'], array_keys($data));
            $placeholders = array_fill(0, count($data), '?');
            $sql = 'INSERT INTO ' . $this->ident($table) . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_values($data));
                $this->insert_id = (int) $this->pdo->lastInsertId();
                return $stmt->rowCount();
            } catch (Exception $e) {
                return false;
            }
        }

        public function update($table, $data, $where, $formats = null, $where_formats = null) {
            $set    = [];
            $params = [];
            foreach ($data as $k => $v) { $set[] = $this->ident($k) . ' = ?'; $params[] = $v; }
            $clause = [];
            foreach ($where as $k => $v) { $clause[] = $this->ident($k) . ' = ?'; $params[] = $v; }
            $sql = 'UPDATE ' . $this->ident($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $clause);
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->rowCount();
            } catch (Exception $e) {
                return false;
            }
        }

        public function delete($table, $where, $formats = null) {
            $clause = [];
            $params = [];
            foreach ($where as $k => $v) { $clause[] = $this->ident($k) . ' = ?'; $params[] = $v; }
            $sql = 'DELETE FROM ' . $this->ident($table) . ' WHERE ' . implode(' AND ', $clause);
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->rowCount();
            } catch (Exception $e) {
                return false;
            }
        }

        public function query($sql) {
            $sql = $this->translate($sql);
            try {
                return $this->pdo->exec($sql);
            } catch (Exception $e) {
                return false;
            }
        }

        public function get_var($sql) {
            try {
                $stmt = $this->pdo->query($this->translate($sql));
                $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : null;
                return $row ? $row[0] : null;
            } catch (Exception $e) {
                return null;
            }
        }

        public function get_row($sql, $output = OBJECT) {
            try {
                $stmt = $this->pdo->query($this->translate($sql));
                $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if (!$row) { return null; }
                return $output === ARRAY_A ? $row : (object) $row;
            } catch (Exception $e) {
                return null;
            }
        }

        public function get_results($sql, $output = OBJECT) {
            try {
                $stmt = $this->pdo->query($this->translate($sql));
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                if ($output === ARRAY_A) { return $rows; }
                return array_map(function ($r) { return (object) $r; }, $rows);
            } catch (Exception $e) {
                return [];
            }
        }

        /** Translate the few MySQL-isms used by AFS_Store into SQLite syntax. */
        private function translate($sql) {
            if (preg_match('/^\s*CREATE\s+TABLE/i', $sql)) {
                // Rewrite the auto-increment column inline so SQLite
                // accepts AUTOINCREMENT (only allowed with INTEGER PRIMARY
                // KEY column definitions, not table-level constraints).
                $sql = preg_replace(
                    '/\bBIGINT\s+UNSIGNED\s+NOT\s+NULL\s+AUTO_INCREMENT\b/i',
                    'INTEGER PRIMARY KEY AUTOINCREMENT',
                    $sql
                );
                $sql = preg_replace('/\bINT\s+UNSIGNED\b/i',           'INTEGER', $sql);
                $sql = preg_replace('/\bTINYINT\(\d+\)/i',             'INTEGER', $sql);
                $sql = preg_replace('/\bDECIMAL\(\d+\s*,\s*\d+\)/i',   'REAL',    $sql);
                $sql = preg_replace('/\bDATETIME\b/i',                 'TEXT',    $sql);
                $sql = preg_replace('/\bLONGTEXT\b/i',                 'TEXT',    $sql);
                $sql = preg_replace('/\bVARCHAR\(\d+\)/i',             'TEXT',    $sql);
                // Drop the separate PRIMARY KEY and KEY constraints —
                // their job is already done by the INTEGER PRIMARY KEY
                // AUTOINCREMENT above, and SQLite doesn't support the
                // inline KEY syntax anyway.
                $sql = preg_replace('/,\s*PRIMARY\s+KEY\s*\(\s*id\s*\)/i', '', $sql);
                $sql = preg_replace('/,\s*KEY\s+\w+\s*\(\s*[^)]*\)/i',     '', $sql);
                // Collapse trailing commas and strip MySQL table options
                // (charset/collate) after the closing paren.
                $sql = preg_replace('/,\s*\)/', ')', $sql);
                $sql = preg_replace('/\)\s*[A-Za-z][^;]*;?/', ')', $sql);
            }
            // Order matters: replace DATE_SUB before NOW() so the inner
            // NOW() reference is still recognizable to the regex.
            $sql = preg_replace_callback(
                '/DATE_SUB\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i',
                function ($m) { return "datetime('now', '-" . (int) $m[1] . " days')"; },
                $sql
            );
            $sql = preg_replace('/\bNOW\(\)/i', "datetime('now')", $sql);
            return $sql;
        }

        private function ident($name) { return '"' . str_replace('"', '""', (string) $name) . '"'; }
    }
}

if (!isset($GLOBALS['wpdb'])) {
    // Allow callers (e.g. tests/serve.php) to pick a file-backed SQLite
    // path via $GLOBALS['AFS_WPDB_PATH'] so state survives across
    // HTTP requests. Defaults to an in-memory DB for unit tests.
    $db_path = isset($GLOBALS['AFS_WPDB_PATH']) ? (string) $GLOBALS['AFS_WPDB_PATH'] : ':memory:';
    $GLOBALS['wpdb'] = new AFS_Test_Wpdb($db_path);
}

if (!function_exists('dbDelta')) {
    function dbDelta($sql) {
        global $wpdb;
        // Extract the CREATE TABLE statement and run it directly. SQLite
        // ignores IF NOT EXISTS-style re-creation failures gracefully via
        // the translator's try/catch, so we prepend it for safety.
        $sql = preg_replace('/^\s*CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $sql);
        $wpdb->query($sql);
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $ts) { return date($format, (int) $ts); }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'http://test.local/wp-admin/' . ltrim($path, '/'); }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg() {
        $args = func_get_args();
        if (is_array($args[0])) {
            $params = $args[0];
            $url    = $args[1] ?? '';
        } else {
            $params = [$args[0] => $args[1]];
            $url    = $args[2] ?? '';
        }
        $parts = parse_url((string) $url);
        $query = [];
        if (!empty($parts['query'])) { parse_str($parts['query'], $query); }
        $query = array_merge($query, $params);
        $base  = ($parts['scheme'] ?? '') . (isset($parts['scheme']) ? '://' : '')
               . ($parts['host'] ?? '') . ($parts['path'] ?? '');
        return $base . ($query ? '?' . http_build_query($query) : '');
    }
}
if (!function_exists('wp_safe_redirect')) { function wp_safe_redirect($url) { return true; } }
if (!function_exists('check_admin_referer')) { function check_admin_referer($action) { return 1; } }
if (!function_exists('is_admin')) { function is_admin() { return false; } }
if (!function_exists('wp_kses_post')) { function wp_kses_post($s) { return is_scalar($s) ? (string) $s : ''; } }
if (!function_exists('sanitize_key')) { function sanitize_key($s) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $s)); } }
