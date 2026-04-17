<?php
/**
 * Persistence layer for reimbursement submissions.
 *
 * Uses a dedicated `{prefix}afs_submissions` table so rows are easy to
 * count, purge, and keep isolated from the rest of WordPress. All
 * methods gracefully no-op if $wpdb isn't available (e.g. in the
 * headless preview server).
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Store {

    /**
     * Schema version. Bump when the table shape changes so the
     * admin_init upgrade check re-runs dbDelta.
     */
    const VERSION     = '1';
    const OPT_VERSION = 'afs_db_version';

    public static function table() {
        global $wpdb;
        return (isset($wpdb) && is_object($wpdb) ? $wpdb->prefix : 'wp_') . 'afs_submissions';
    }

    public static function is_available() {
        global $wpdb;
        return isset($wpdb) && is_object($wpdb);
    }

    public static function statuses() {
        return [
            'mottikin'  => 'Móttikin',
            'i_arbeidi' => 'Í arbeiði',
            'utgoldin'  => 'Útgoldin',
            'avvist'    => 'Avvíst',
        ];
    }

    /**
     * Create or upgrade the table. Safe to call repeatedly.
     */
    public static function install_table() {
        if (!self::is_available()) { return; }
        global $wpdb;
        $table   = self::table();
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL,
            account VARCHAR(32) NOT NULL DEFAULT '',
            line_count INT UNSIGNED NOT NULL DEFAULT 0,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'mottikin',
            subject VARCHAR(255) NOT NULL DEFAULT '',
            lines_json LONGTEXT NOT NULL,
            email_body LONGTEXT NOT NULL,
            sent_ok TINYINT(1) NOT NULL DEFAULT 1,
            admin_note LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY status (status),
            KEY email (email)
        ) {$charset};";

        if (!function_exists('dbDelta')) {
            $upgrade_file = defined('ABSPATH') ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';
            if ($upgrade_file && file_exists($upgrade_file)) {
                require_once $upgrade_file;
            }
        }
        if (function_exists('dbDelta')) {
            dbDelta($sql);
        } else {
            $wpdb->query($sql);
        }

        if (function_exists('update_option')) {
            update_option(self::OPT_VERSION, self::VERSION);
        }
    }

    public static function maybe_upgrade() {
        if (!self::is_available()) { return; }
        $current = function_exists('get_option') ? get_option(self::OPT_VERSION) : null;
        if ($current !== self::VERSION) {
            self::install_table();
        }
    }

    /**
     * Insert a new submission row. Returns the new id (or 0 on failure).
     *
     * @param array $data {
     *   @type string $name
     *   @type string $email
     *   @type string $account
     *   @type array  $lines       normalized line data (will be JSON-encoded)
     *   @type float  $total
     *   @type string $subject
     *   @type string $email_body
     *   @type bool   $sent
     * }
     * @return int
     */
    public static function save(array $data) {
        if (!self::is_available()) { return 0; }
        global $wpdb;

        $now = function_exists('current_time')
            ? current_time('mysql')
            : date('Y-m-d H:i:s');

        $lines = $data['lines'] ?? [];
        $json  = function_exists('wp_json_encode')
            ? wp_json_encode($lines)
            : json_encode($lines, JSON_UNESCAPED_UNICODE);

        $row = [
            'created_at'   => $now,
            'name'         => self::clip((string) ($data['name']    ?? ''), 191),
            'email'        => self::clip((string) ($data['email']   ?? ''), 191),
            'account'      => self::clip((string) ($data['account'] ?? ''), 32),
            'line_count'   => (int) (isset($data['line_count']) ? $data['line_count'] : count((array) $lines)),
            'total_amount' => (float) ($data['total'] ?? 0),
            'status'       => 'mottikin',
            'subject'      => self::clip((string) ($data['subject']    ?? ''), 255),
            'lines_json'   => (string) $json,
            'email_body'   => (string) ($data['email_body'] ?? ''),
            'sent_ok'      => !empty($data['sent']) ? 1 : 0,
            'admin_note'   => null,
        ];

        $formats = ['%s','%s','%s','%s','%d','%f','%s','%s','%s','%s','%d','%s'];
        $ok = $wpdb->insert(self::table(), $row, $formats);
        if ($ok === false || $ok === 0) { return 0; }
        return (int) $wpdb->insert_id;
    }

    public static function get($id) {
        if (!self::is_available()) { return null; }
        global $wpdb;
        $sql = $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id);
        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }

    public static function delete($id) {
        if (!self::is_available()) { return 0; }
        global $wpdb;
        return (int) $wpdb->delete(self::table(), ['id' => (int) $id], ['%d']);
    }

    public static function delete_many(array $ids) {
        if (!self::is_available() || !$ids) { return 0; }
        global $wpdb;
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) { return 0; }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = $wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE id IN (' . $placeholders . ')',
            $ids
        );
        return (int) $wpdb->query($sql);
    }

    public static function set_status($id, $status) {
        if (!self::is_available()) { return 0; }
        $allowed = array_keys(self::statuses());
        if (!in_array($status, $allowed, true)) { return 0; }
        global $wpdb;
        return (int) $wpdb->update(
            self::table(),
            ['status' => $status],
            ['id'     => (int) $id],
            ['%s'],
            ['%d']
        );
    }

    public static function set_note($id, $note) {
        if (!self::is_available()) { return 0; }
        global $wpdb;
        return (int) $wpdb->update(
            self::table(),
            ['admin_note' => (string) $note],
            ['id'         => (int) $id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Delete rows older than $days.
     * @return int number of rows removed
     */
    public static function purge_older_than($days) {
        if (!self::is_available()) { return 0; }
        $days = max(1, (int) $days);
        global $wpdb;
        $sql = $wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
            $days
        );
        return (int) $wpdb->query($sql);
    }

    /**
     * Paginated, filterable query.
     *
     * @param array $args {
     *   @type string $status
     *   @type string $search
     *   @type string $date_from 'YYYY-MM-DD'
     *   @type string $date_to   'YYYY-MM-DD'
     *   @type string $orderby
     *   @type string $order     'ASC' | 'DESC'
     *   @type int    $per_page
     *   @type int    $page
     * }
     * @return array{items: array[], total: int}
     */
    public static function query(array $args = []) {
        if (!self::is_available()) { return ['items' => [], 'total' => 0]; }
        global $wpdb;

        $defaults = [
            'status'    => '',
            'search'    => '',
            'date_from' => '',
            'date_to'   => '',
            'orderby'   => 'created_at',
            'order'     => 'DESC',
            'per_page'  => 20,
            'page'      => 1,
        ];
        $args = array_merge($defaults, $args);

        $where  = [];
        $params = [];

        if ($args['status'] !== '') {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        if ($args['search'] !== '') {
            $where[]  = '(name LIKE %s OR email LIKE %s)';
            $like     = '%' . (method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($args['search']) : $args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($args['date_from'] !== '') {
            $where[]  = 'created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }
        if ($args['date_to'] !== '') {
            $where[]  = 'created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $allowed_orderby = ['id', 'created_at', 'name', 'email', 'total_amount', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order   = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $per_page = max(1, (int) $args['per_page']);
        $page     = max(1, (int) $args['page']);
        $offset   = ($page - 1) * $per_page;

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $table     = self::table();

        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $total = (int) ($params
            ? $wpdb->get_var($wpdb->prepare($count_sql, $params))
            : $wpdb->get_var($count_sql));

        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $qp  = array_merge($params, [$per_page, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($sql, $qp), ARRAY_A);

        return ['items' => $items ?: [], 'total' => $total];
    }

    /**
     * Count of rows per status (plus 'all').
     * @return array<string,int>
     */
    public static function status_counts() {
        if (!self::is_available()) { return []; }
        global $wpdb;
        $rows = $wpdb->get_results('SELECT status, COUNT(*) AS n FROM ' . self::table() . ' GROUP BY status', ARRAY_A);
        $out  = ['all' => 0];
        foreach ((array) $rows as $r) {
            $out[$r['status']] = (int) $r['n'];
            $out['all']       += (int) $r['n'];
        }
        return $out;
    }

    private static function clip($s, $max) {
        if (function_exists('mb_substr')) { return mb_substr($s, 0, $max); }
        return substr($s, 0, $max);
    }
}
