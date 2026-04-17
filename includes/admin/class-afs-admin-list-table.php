<?php
/**
 * WP_List_Table subclass for the reimbursement submissions admin page.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AFS_Admin_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'afs_submission',
            'plural'   => 'afs_submissions',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox">',
            'id'           => 'ID',
            'created_at'   => 'Dagur',
            'name'         => 'Navn',
            'email'        => 'Teldupostur',
            'line_count'   => 'Linjur',
            'total_amount' => 'Upphædd',
            'status'       => 'Støða',
            'sent_ok'      => 'Sendur',
            'row_actions'  => 'Aksjónir',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'id'           => ['id', true],
            'created_at'   => ['created_at', true],
            'name'         => ['name', false],
            'email'        => ['email', false],
            'total_amount' => ['total_amount', false],
            'status'       => ['status', false],
        ];
    }

    protected function get_bulk_actions() {
        return [
            'delete' => 'Strika',
        ];
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id']);
    }

    protected function column_default($item, $column) {
        return isset($item[$column]) ? esc_html($item[$column]) : '';
    }

    protected function column_id($item) {
        return '<strong>#' . (int) $item['id'] . '</strong>';
    }

    protected function column_created_at($item) {
        $ts = strtotime($item['created_at']);
        if (!$ts) { return esc_html($item['created_at']); }
        return esc_html(date_i18n('Y-m-d H:i', $ts));
    }

    protected function column_name($item) {
        $view = add_query_arg(
            ['page' => 'afs-submissions', 'action' => 'view', 'id' => (int) $item['id']],
            admin_url('admin.php')
        );
        return '<a href="' . esc_url($view) . '"><strong>' . esc_html($item['name']) . '</strong></a>';
    }

    protected function column_email($item) {
        return '<a href="mailto:' . esc_attr($item['email']) . '">' . esc_html($item['email']) . '</a>';
    }

    protected function column_total_amount($item) {
        return esc_html(number_format((float) $item['total_amount'], 2, ',', ' ') . ' kr');
    }

    protected function column_status($item) {
        $labels = AFS_Store::statuses();
        $st = isset($labels[$item['status']]) ? $labels[$item['status']] : $item['status'];
        return '<span class="afs-status afs-status--' . esc_attr($item['status']) . '">' . esc_html($st) . '</span>';
    }

    protected function column_sent_ok($item) {
        return !empty($item['sent_ok']) ? '✓' : '<span style="color:#b32d2e">✗</span>';
    }

    protected function column_row_actions($item) {
        $base = admin_url('admin.php?page=afs-submissions');
        $view = add_query_arg(['action' => 'view', 'id' => (int) $item['id']], $base);
        $del  = wp_nonce_url(
            add_query_arg(['action' => 'delete', 'id' => (int) $item['id']], $base),
            'afs_delete_' . (int) $item['id']
        );
        return '<a href="' . esc_url($view) . '">Síggj</a>'
             . ' | <a href="' . esc_url($del) . '" style="color:#b32d2e" onclick="return confirm(\'Strika hesa fráboðan?\')">Strika</a>';
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') { return; }
        $statuses = AFS_Store::statuses();
        $current  = isset($_GET['status']) ? (string) $_GET['status'] : '';
        $from     = isset($_GET['date_from']) ? (string) $_GET['date_from'] : '';
        $to       = isset($_GET['date_to'])   ? (string) $_GET['date_to']   : '';

        echo '<div class="alignleft actions">';
        echo '<select name="status"><option value="">— Allar støður —</option>';
        foreach ($statuses as $k => $lbl) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($k),
                selected($current, $k, false),
                esc_html($lbl)
            );
        }
        echo '</select> ';
        echo 'Frá <input type="date" name="date_from" value="' . esc_attr($from) . '"> ';
        echo 'til <input type="date" name="date_to" value="' . esc_attr($to) . '"> ';
        echo '<button class="button">Filtrera</button>';
        echo '</div>';
    }

    public function prepare_items() {
        $per_page = $this->get_items_per_page('afs_per_page', 20);
        $page     = $this->get_pagenum();

        $orderby = isset($_GET['orderby']) ? sanitize_key((string) $_GET['orderby']) : 'created_at';
        $order   = isset($_GET['order'])   ? strtoupper(sanitize_key((string) $_GET['order'])) : 'DESC';

        $args = [
            'status'    => isset($_GET['status'])    ? sanitize_text_field((string) $_GET['status'])    : '',
            'search'    => isset($_REQUEST['s'])     ? sanitize_text_field((string) $_REQUEST['s'])     : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field((string) $_GET['date_from']) : '',
            'date_to'   => isset($_GET['date_to'])   ? sanitize_text_field((string) $_GET['date_to'])   : '',
            'orderby'   => $orderby,
            'order'     => $order,
            'per_page'  => $per_page,
            'page'      => $page,
        ];

        $res = AFS_Store::query($args);
        $this->items = $res['items'];

        $this->set_pagination_args([
            'total_items' => $res['total'],
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($res['total'] / max(1, $per_page)),
        ]);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function no_items() {
        echo 'Ongar fráboðanir.';
    }
}
