<?php
/**
 * Plugin bootstrap: hooks, shortcodes, asset loading, admin menu.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Plugin {

    public static function init() {
        add_action('init',               [__CLASS__, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('admin_init',         [__CLASS__, 'register_settings']);
        add_action('admin_init',         [__CLASS__, 'maybe_upgrade_db']);
        add_action('admin_init',         ['AFS_Settings', 'maybe_clear_log']);
        add_action('admin_menu',         [__CLASS__, 'register_menu']);

        if (class_exists('AFS_Admin_Submissions')) {
            AFS_Admin_Submissions::register();
        }
    }

    public static function register_shortcode() {
        add_shortcode('afturgjald_form',          ['AFS_Form', 'render']);
        add_shortcode('drive_reimbursement_form', ['AFS_Form', 'render']);
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'afs-form',
            AFS_PLUGIN_URL . 'assets/css/drf.css',
            [],
            AFS_VERSION
        );
        wp_enqueue_script(
            'afs-form',
            AFS_PLUGIN_URL . 'assets/js/drf.js',
            [],
            AFS_VERSION,
            true
        );
        wp_localize_script('afs-form', 'AFS_DATA', [
            'ratePerKm' => AFS_Type_Driving::rate_per_km(),
            'tunnels'   => AFS_Type_Driving::tunnels(),
        ]);
    }

    public static function enqueue_admin_assets($hook) {
        // Only load the tiny admin stylesheet on our own pages.
        if (strpos((string) $hook, 'afs-') === false) { return; }
        wp_enqueue_style(
            'afs-admin',
            AFS_PLUGIN_URL . 'assets/css/drf-admin.css',
            [],
            AFS_VERSION
        );
    }

    public static function register_settings() {
        register_setting('drf_settings', 'drf_rate_per_km');
    }

    public static function maybe_upgrade_db() {
        if (class_exists('AFS_Store')) {
            AFS_Store::maybe_upgrade();
        }
    }

    /**
     * Top-level "Afturgjald" menu with submenus for submissions and settings.
     */
    public static function register_menu() {
        add_menu_page(
            'Afturgjald',
            'Afturgjald',
            'manage_options',
            'afs-submissions',
            ['AFS_Admin_Submissions', 'render'],
            'dashicons-clipboard',
            30
        );
        add_submenu_page(
            'afs-submissions',
            'Fráboðanir',
            'Fráboðanir',
            'manage_options',
            'afs-submissions',
            ['AFS_Admin_Submissions', 'render']
        );
        add_submenu_page(
            'afs-submissions',
            'Stillingar',
            'Stillingar',
            'manage_options',
            'afs-settings',
            ['AFS_Settings', 'render']
        );
    }

    /**
     * Run on plugin activation: create the submissions table.
     */
    public static function activate() {
        if (class_exists('AFS_Store')) {
            AFS_Store::install_table();
        }
    }
}
