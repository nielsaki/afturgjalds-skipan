<?php
/**
 * Admin page: list/view/delete/purge reimbursement submissions.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Admin_Submissions {

    public static function register() {
        add_action('admin_init', [__CLASS__, 'handle_actions']);
    }

    /**
     * Handle non-AJAX admin POST/GET actions (delete, bulk-delete, status, purge).
     * Registered on admin_init so redirects can happen before any output.
     */
    public static function handle_actions() {
        if (!is_admin() || !current_user_can('manage_options')) { return; }
        if (($_REQUEST['page'] ?? '') !== 'afs-submissions')    { return; }

        $action = $_REQUEST['action'] ?? '';

        // Single delete (GET with nonce)
        if ($action === 'delete' && !empty($_GET['id'])) {
            $id = (int) $_GET['id'];
            check_admin_referer('afs_delete_' . $id);
            AFS_Store::delete($id);
            wp_safe_redirect(add_query_arg(
                ['page' => 'afs-submissions', 'deleted' => 1],
                admin_url('admin.php')
            ));
            exit;
        }

        // Bulk delete (POST from list table)
        $bulk = $_REQUEST['action2'] ?? $_REQUEST['action'] ?? '';
        if ($bulk === 'delete' && !empty($_POST['ids']) && is_array($_POST['ids'])) {
            check_admin_referer('bulk-afs_submissions');
            $count = AFS_Store::delete_many((array) $_POST['ids']);
            wp_safe_redirect(add_query_arg(
                ['page' => 'afs-submissions', 'deleted' => (int) $count],
                admin_url('admin.php')
            ));
            exit;
        }

        // Change status (POST from detail page)
        if (!empty($_POST['afs_set_status']) && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            check_admin_referer('afs_status_' . $id);
            $status = sanitize_text_field((string) ($_POST['status'] ?? 'mottikin'));
            AFS_Store::set_status($id, $status);
            $note = isset($_POST['admin_note']) ? wp_kses_post((string) $_POST['admin_note']) : '';
            AFS_Store::set_note($id, $note);
            wp_safe_redirect(add_query_arg(
                ['page' => 'afs-submissions', 'action' => 'view', 'id' => $id, 'updated' => 1],
                admin_url('admin.php')
            ));
            exit;
        }

        // Purge older than X days (POST from list page)
        if (!empty($_POST['afs_purge'])) {
            check_admin_referer('afs_purge');
            $days  = max(1, (int) ($_POST['purge_days'] ?? 365));
            $count = AFS_Store::purge_older_than($days);
            wp_safe_redirect(add_query_arg(
                ['page' => 'afs-submissions', 'purged' => (int) $count],
                admin_url('admin.php')
            ));
            exit;
        }
    }

    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
        if ($action === 'view') {
            self::render_detail();
            return;
        }
        self::render_list();
    }

    public static function render_list() {
        $table = new AFS_Admin_List_Table();
        $table->prepare_items();

        $counts = AFS_Store::status_counts();
        $labels = AFS_Store::statuses();

        echo '<div class="wrap afs-admin">';
        echo '<h1 class="wp-heading-inline">Afturgjald — fráboðanir</h1>';
        echo '<hr class="wp-header-end">';

        if (isset($_GET['deleted'])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>Strikað %d fráboðan%s.</p></div>',
                (int) $_GET['deleted'],
                ((int) $_GET['deleted']) === 1 ? '' : 'ir'
            );
        }
        if (isset($_GET['purged'])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>Strikað %d gamla fráboðan%s.</p></div>',
                (int) $_GET['purged'],
                ((int) $_GET['purged']) === 1 ? '' : 'ir'
            );
        }

        // Status-link views above the table.
        $base      = admin_url('admin.php?page=afs-submissions');
        $current   = isset($_GET['status']) ? (string) $_GET['status'] : '';
        $view_links = [];
        $all_url = $base;
        $view_links['all'] = sprintf(
            '<a href="%s"%s>Allar <span class="count">(%d)</span></a>',
            esc_url($all_url),
            $current === '' ? ' class="current"' : '',
            (int) ($counts['all'] ?? 0)
        );
        foreach ($labels as $k => $lbl) {
            $url = add_query_arg('status', $k, $base);
            $view_links[$k] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url($url),
                $current === $k ? ' class="current"' : '',
                esc_html($lbl),
                (int) ($counts[$k] ?? 0)
            );
        }
        echo '<ul class="subsubsub">';
        $i = 0; $last = count($view_links);
        foreach ($view_links as $link) {
            echo '<li>' . $link . ($i < $last - 1 ? ' |' : '') . '</li>';
            $i++;
        }
        echo '</ul>';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="afs-submissions">';
        $table->search_box('Leita', 'afs-search');
        $table->display();
        echo '</form>';

        // Maintenance: purge
        echo '<hr>';
        echo '<h2>Viðlíkahald</h2>';
        echo '<form method="post" style="margin:0.5rem 0;">';
        echo '<input type="hidden" name="page" value="afs-submissions">';
        wp_nonce_field('afs_purge');
        echo '<p>Strika fráboðanir eldri enn ';
        echo '<input type="number" name="purge_days" value="365" min="1" style="width:5em;"> ';
        echo 'dagar. ';
        echo '<button class="button button-secondary" name="afs_purge" value="1" '
           . 'onclick="return confirm(\'Hetta kann ikki angrast. Halda fram?\');">Strika gamlar</button>';
        echo '</p>';
        echo '</form>';

        echo '</div>';
    }

    public static function render_detail() {
        $id   = (int) ($_GET['id'] ?? 0);
        $item = $id ? AFS_Store::get($id) : null;
        $back = admin_url('admin.php?page=afs-submissions');

        echo '<div class="wrap afs-admin">';
        if (!$item) {
            echo '<h1>Fráboðan ikki funnin</h1>';
            echo '<p><a class="button" href="' . esc_url($back) . '">← Aftur til listan</a></p>';
            echo '</div>';
            return;
        }

        $statuses = AFS_Store::statuses();
        $lines    = json_decode((string) $item['lines_json'], true);
        if (!is_array($lines)) { $lines = []; }

        $ts = strtotime($item['created_at']);
        $created = $ts ? date_i18n('Y-m-d H:i', $ts) : $item['created_at'];

        echo '<h1 class="wp-heading-inline">Fráboðan #' . (int) $item['id'] . '</h1>';
        echo ' <a href="' . esc_url($back) . '" class="page-title-action">← Aftur til listan</a>';
        echo '<hr class="wp-header-end">';

        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Dagført.</p></div>';
        }

        echo '<div class="afs-detail-cols" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:1.5rem;align-items:start;">';

        // Left column: details + lines
        echo '<div>';
        echo '<table class="form-table"><tbody>';
        self::row('Dagur', esc_html($created));
        self::row('Navn', esc_html($item['name']));
        self::row('Teldupostur', '<a href="mailto:' . esc_attr($item['email']) . '">' . esc_html($item['email']) . '</a>');
        if ($item['account'] !== '') { self::row('Kontonummar', esc_html($item['account'])); }
        self::row('Upphædd íalt', esc_html(number_format((float) $item['total_amount'], 2, ',', ' ')) . ' kr');
        self::row('Emni', esc_html($item['subject']));
        self::row('Sendur teldupostur', !empty($item['sent_ok']) ? 'Ja' : '<span style="color:#b32d2e">Nei</span>');
        echo '</tbody></table>';

        echo '<h2>Linjur (' . count($lines) . ')</h2>';
        if (!$lines) {
            echo '<p><em>Ongar linjur goymdar.</em></p>';
        } else {
            echo '<ol class="afs-lines-list">';
            foreach ($lines as $line) {
                $type_id = isset($line['type']) ? (string) $line['type'] : '';
                $data    = isset($line['data']) && is_array($line['data']) ? $line['data'] : [];
                echo '<li style="margin-bottom:0.75rem;">';
                echo '<strong>' . esc_html(self::type_label($type_id)) . '</strong> — ';
                echo esc_html($data['date'] ?? '') . ' · ' . esc_html($data['description'] ?? '');
                echo '<pre style="background:#f6f7f7;padding:0.75rem;border-left:3px solid #c3c4c7;margin-top:0.25rem;white-space:pre-wrap;">';
                echo esc_html(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                echo '</pre>';
                echo '</li>';
            }
            echo '</ol>';
        }

        echo '<h2>Teldupostur (innihald)</h2>';
        echo '<pre style="background:#1d2327;color:#e5e7ea;padding:1rem;border-radius:6px;max-height:420px;overflow:auto;white-space:pre-wrap;">';
        echo esc_html((string) $item['email_body']);
        echo '</pre>';
        echo '</div>';

        // Right column: status + delete
        echo '<div>';
        echo '<div class="postbox"><div class="postbox-header"><h2 class="hndle">Støða & viðmerking</h2></div><div class="inside">';
        echo '<form method="post">';
        echo '<input type="hidden" name="page" value="afs-submissions">';
        echo '<input type="hidden" name="id" value="' . (int) $item['id'] . '">';
        wp_nonce_field('afs_status_' . (int) $item['id']);
        echo '<p><label><strong>Støða</strong><br>';
        echo '<select name="status" style="min-width:14em">';
        foreach ($statuses as $k => $lbl) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($k),
                selected($item['status'], $k, false),
                esc_html($lbl)
            );
        }
        echo '</select></label></p>';
        echo '<p><label><strong>Viðmerking (kunn tiknast burtur)</strong><br>';
        echo '<textarea name="admin_note" rows="4" style="width:100%;">' . esc_textarea((string) $item['admin_note']) . '</textarea>';
        echo '</label></p>';
        echo '<p><button class="button button-primary" name="afs_set_status" value="1">Goym støðu</button></p>';
        echo '</form>';
        echo '</div></div>';

        $del = wp_nonce_url(
            add_query_arg(
                ['page' => 'afs-submissions', 'action' => 'delete', 'id' => (int) $item['id']],
                admin_url('admin.php')
            ),
            'afs_delete_' . (int) $item['id']
        );
        echo '<p><a href="' . esc_url($del) . '" class="button button-link-delete" '
           . 'onclick="return confirm(\'Strika hesa fráboðan? Hetta kann ikki angrast.\');">Strika fráboðan</a></p>';

        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    private static function row($label, $value_html) {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $value_html . '</td></tr>';
    }

    private static function type_label($id) {
        $t = class_exists('AFS_Types') ? AFS_Types::get($id) : null;
        return $t ? $t->label() : ($id ?: 'óþekt');
    }
}
