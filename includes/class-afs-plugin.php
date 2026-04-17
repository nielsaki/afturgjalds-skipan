<?php
/**
 * Plugin bootstrap: hooks, shortcodes, asset loading, settings menu.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Plugin {

    public static function init() {
        add_action('init',              [__CLASS__, 'register_shortcode']);
        add_action('wp_enqueue_scripts',[__CLASS__, 'enqueue_assets']);
        add_action('admin_init',        [__CLASS__, 'register_settings']);
        add_action('admin_init',        ['AFS_Settings', 'maybe_clear_log']);
        add_action('admin_menu',        [__CLASS__, 'settings_menu']);
    }

    public static function register_shortcode() {
        add_shortcode('afturgjald_form',            ['AFS_Form', 'render']);
        add_shortcode('drive_reimbursement_form',   ['AFS_Form', 'render']);
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

    public static function register_settings() {
        register_setting('drf_settings', 'drf_rate_per_km');
    }

    public static function settings_menu() {
        add_options_page(
            'Afturgjald — stillingar',
            'Afturgjald',
            'manage_options',
            'afs-settings',
            ['AFS_Settings', 'render']
        );
    }
}
