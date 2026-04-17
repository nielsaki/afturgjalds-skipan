<?php
/**
 * File-based log of mail activity (used in test mode).
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Logger {

    /**
     * In-memory queue of log entries. Useful for the automated test suite.
     *
     * @var array<int,array<string,mixed>>
     */
    public static $test_queue = [];

    public static function log_path() {
        if (defined('DRF_EMAIL_LOG_FILE') && DRF_EMAIL_LOG_FILE) {
            return (string) DRF_EMAIL_LOG_FILE;
        }
        $dir = '';
        if (function_exists('wp_upload_dir')) {
            $u = wp_upload_dir();
            if (!empty($u['basedir'])) {
                $dir = trailingslashit($u['basedir']) . 'afturgjald-skipan';
            }
        }
        if ($dir === '') {
            $dir = sys_get_temp_dir() . '/afturgjald-skipan';
        }
        if (!file_exists($dir)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($dir);
            } else {
                @mkdir($dir, 0777, true);
            }
        }
        return $dir . '/email-test.log';
    }

    /**
     * Append an email entry to the log (file + in-memory queue).
     *
     * @param array $data {
     *     @type mixed    $original_to
     *     @type string   $test_to
     *     @type string   $role_label
     *     @type string   $subject
     *     @type string   $body
     *     @type string[] $headers
     *     @type array[]  $attachments  Each: [name, path, size]
     *     @type bool     $dry_run
     * }
     */
    public static function log_mail(array $data) {
        self::$test_queue[] = $data;

        $path = self::log_path();
        $ts   = date('Y-m-d H:i:s');

        $orig_to = $data['original_to'] ?? '';
        if (is_array($orig_to)) {
            $orig_to = implode(', ', $orig_to);
        }

        $lines   = [];
        $lines[] = str_repeat('=', 70);
        $lines[] = "[{$ts}] EMAIL QUEUED (próvingarham)";
        if (!empty($data['dry_run'])) {
            $lines[] = 'Mode:             DRY RUN (einki verður sent)';
        } else {
            $lines[] = 'Mode:             TEST (verður sent til próvingar-viðtakara)';
        }
        $lines[] = 'To (original):    ' . $orig_to;
        $lines[] = 'To (test):        ' . ($data['test_to'] ?? '(ongin)');
        if (!empty($data['role_label'])) {
            $lines[] = 'Role:             ' . $data['role_label'];
        }
        $lines[] = 'Subject:          ' . ($data['subject'] ?? '');

        if (!empty($data['headers'])) {
            foreach ((array) $data['headers'] as $h) {
                $lines[] = 'Header:           ' . $h;
            }
        }

        if (!empty($data['attachments'])) {
            $lines[] = 'Attachments:';
            foreach ((array) $data['attachments'] as $a) {
                if (is_array($a)) {
                    $name = $a['name'] ?? basename($a['path'] ?? '?');
                    $size = isset($a['size']) ? self::human_size((int) $a['size']) : '?';
                    $lines[] = "  - {$name} ({$size})";
                } else {
                    $name = basename((string) $a);
                    $size = @file_exists($a) ? self::human_size((int) @filesize($a)) : '?';
                    $lines[] = "  - {$name} ({$size})";
                }
            }
        } else {
            $lines[] = 'Attachments:      (ongin)';
        }

        $lines[] = '';
        $lines[] = '--- BODY ---';
        $lines[] = (string) ($data['body'] ?? '');
        $lines[] = str_repeat('=', 70);
        $lines[] = '';

        $contents = implode("\n", $lines);
        @file_put_contents($path, $contents, FILE_APPEND | LOCK_EX);
    }

    private static function human_size($bytes) {
        if (function_exists('size_format')) {
            $s = size_format($bytes);
            if ($s) { return $s; }
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
