<?php
/**
 * Mail facade. Routes through wp_mail() in production, and through the
 * logger + a single test recipient in test mode.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Mail {

    public static function is_test_mode() {
        return defined('DRF_EMAIL_TEST_MODE') && DRF_EMAIL_TEST_MODE;
    }

    public static function is_dry_run() {
        return defined('DRF_EMAIL_DRY_RUN') && DRF_EMAIL_DRY_RUN;
    }

    public static function test_recipient() {
        if (defined('DRF_EMAIL_TEST_TO') && DRF_EMAIL_TEST_TO) {
            $t = function_exists('sanitize_email')
                ? sanitize_email(DRF_EMAIL_TEST_TO)
                : (string) DRF_EMAIL_TEST_TO;
            if ($t) { return $t; }
        }
        if (function_exists('get_option')) {
            return (string) get_option('admin_email', '');
        }
        return '';
    }

    /**
     * Send (or simulate) an email.
     *
     * @param string|string[] $to          Intended recipient(s).
     * @param string          $subject     Subject.
     * @param string          $body        Body.
     * @param string[]        $headers     Extra headers.
     * @param array           $attachments Either paths (string) or [name, path, size] entries.
     * @param string          $role_label  Label used in the test-mode prefix/log.
     *
     * @return bool
     */
    public static function send($to, $subject, $body, $headers = [], $attachments = [], $role_label = '') {
        $attach_paths = self::normalize_attachments($attachments);
        $attach_meta  = self::attachment_meta($attachments);

        if (!self::is_test_mode()) {
            return (bool) wp_mail($to, $subject, $body, $headers, $attach_paths);
        }

        $test_to = self::test_recipient();
        $orig    = is_array($to) ? implode(', ', $to) : (string) $to;

        $prefix  = "[AFS — próving av telduposti]\n";
        $prefix .= "Hetta brúkti at fara til: {$orig}\n";
        if ($role_label !== '') {
            $prefix .= "Slag: {$role_label}\n";
        }
        if (self::is_dry_run()) {
            $prefix .= "DRY RUN: ongin teldupostur verður í roynd og veru sendur.\n";
        }
        $prefix .= str_repeat('-', 48) . "\n\n";

        $test_body    = $prefix . $body;
        $test_subject = '[AFS próving] ' . $subject;

        AFS_Logger::log_mail([
            'original_to' => $to,
            'test_to'     => $test_to,
            'role_label'  => $role_label,
            'subject'     => $test_subject,
            'body'        => $test_body,
            'headers'     => $headers,
            'attachments' => $attach_meta,
            'dry_run'     => self::is_dry_run(),
        ]);

        if (self::is_dry_run() || !$test_to) {
            return true;
        }

        return (bool) wp_mail($test_to, $test_subject, $test_body, $headers, $attach_paths);
    }

    /**
     * Accepts either plain paths or [name, path, size] arrays and returns paths.
     *
     * @param array $items
     * @return string[]
     */
    private static function normalize_attachments(array $items) {
        $paths = [];
        foreach ($items as $a) {
            if (is_array($a)) {
                if (!empty($a['path'])) { $paths[] = (string) $a['path']; }
            } elseif (is_string($a) && $a !== '') {
                $paths[] = $a;
            }
        }
        return $paths;
    }

    private static function attachment_meta(array $items) {
        $out = [];
        foreach ($items as $a) {
            if (is_array($a)) {
                $out[] = [
                    'name' => $a['name'] ?? (isset($a['path']) ? basename((string) $a['path']) : '?'),
                    'path' => $a['path'] ?? '',
                    'size' => (int) ($a['size'] ?? 0),
                ];
            } elseif (is_string($a) && $a !== '') {
                $out[] = [
                    'name' => basename($a),
                    'path' => $a,
                    'size' => @file_exists($a) ? (int) @filesize($a) : 0,
                ];
            }
        }
        return $out;
    }
}
