<?php
/**
 * Persists uploaded files between form submissions (validation errors, failed mail).
 *
 * Files live under wp-content/uploads/afs-pending/; metadata is stored in a transient
 * keyed by an opaque token. The token is echoed back in a hidden field so the user
 * does not need to re-select the file after fixing other fields.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_File_Stage {

    const TRANSIENT_PREFIX = 'afs_att_';
    const TTL_SECONDS      = 172800; // 48 hours

    /**
     * Save an uploaded tmp file into uploads/afs-pending and register a transient.
     *
     * @param string $tmp_name PHP upload tmp path
     * @param string $orig_name Original filename
     * @param int    $size      File size
     * @param string $mime      MIME type
     * @return string|null Opaque token, or null on failure
     */
    public static function persist_from_tmp($tmp_name, $orig_name, $size, $mime) {
        if (!$tmp_name || !file_exists($tmp_name)) {
            return null;
        }
        $upload = function_exists('wp_upload_dir') ? wp_upload_dir() : [];
        if (empty($upload['basedir'])) {
            return null;
        }
        $base = $upload['basedir'];
        $dir  = trailingslashit($base) . 'afs-pending';
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($dir);
        } elseif (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return null;
        }

        $token = function_exists('wp_generate_password')
            ? wp_generate_password(24, false, false)
            : bin2hex(random_bytes(12));
        $safe  = sanitize_file_name($orig_name) ?: 'file.bin';
        $dest  = $dir . '/' . $token . '_' . $safe;

        $moved = false;
        if (function_exists('is_uploaded_file') && is_uploaded_file($tmp_name) && function_exists('move_uploaded_file')) {
            $moved = @move_uploaded_file($tmp_name, $dest);
        }
        if (!$moved) {
            $moved = @copy($tmp_name, $dest);
        }
        if (!$moved || !file_exists($dest)) {
            return null;
        }

        $meta = [
            'path' => $dest,
            'name' => $safe,
            'size' => (int) $size,
            'type' => sanitize_text_field($mime),
        ];
        self::store_meta($token, $meta);
        return $token;
    }

    /**
     * @param string $token
     * @return array|null Meta with path, name, size, type
     */
    public static function get_meta($token) {
        $token = self::sanitize_token($token);
        if ($token === '') {
            return null;
        }
        $data = function_exists('get_transient') ? get_transient(self::TRANSIENT_PREFIX . $token) : false;
        return is_array($data) ? $data : null;
    }

    /**
     * @param string $token
     * @return string|null Original filename for display
     */
    public static function get_original_name($token) {
        $m = self::get_meta($token);
        return $m['name'] ?? null;
    }

    /**
     * Build attachment array for wp_mail / AFS_Mail.
     *
     * @param string $token
     * @return array|null [ name, path, size, type ] or null
     */
    public static function to_attachment_array($token) {
        $m = self::get_meta($token);
        if (!$m || empty($m['path']) || !file_exists($m['path'])) {
            return null;
        }
        if (!self::path_is_under_uploads($m['path'])) {
            return null;
        }
        return [
            'name' => $m['name'],
            'path' => $m['path'],
            'size' => (int) ($m['size'] ?? 0),
            'type' => (string) ($m['type'] ?? ''),
        ];
    }

    /**
     * Remove transient and delete the file on disk.
     *
     * @param string $token
     */
    public static function delete_token($token) {
        $token = self::sanitize_token($token);
        if ($token === '') {
            return;
        }
        $m = self::get_meta($token);
        if (function_exists('delete_transient')) {
            delete_transient(self::TRANSIENT_PREFIX . $token);
        }
        if ($m && !empty($m['path']) && file_exists($m['path']) && self::path_is_under_uploads($m['path'])) {
            @unlink($m['path']);
        }
    }

    /**
     * @param string[] $tokens
     */
    public static function delete_tokens(array $tokens) {
        foreach ($tokens as $t) {
            self::delete_token((string) $t);
        }
    }

    /**
     * @param string $path
     */
    private static function path_is_under_uploads($path) {
        $upload = function_exists('wp_upload_dir') ? wp_upload_dir() : [];
        if (empty($upload['basedir'])) {
            return false;
        }
        $base = self::norm_path($upload['basedir']);
        $p    = self::norm_path($path);
        return strpos($p, $base) === 0;
    }

    /**
     * @param string $p
     * @return string
     */
    private static function norm_path($p) {
        $p = str_replace('\\', '/', (string) $p);
        return rtrim($p, '/');
    }

    /**
     * @param string $token
     * @param array  $meta
     */
    private static function store_meta($token, array $meta) {
        if (function_exists('set_transient')) {
            set_transient(self::TRANSIENT_PREFIX . $token, $meta, self::TTL_SECONDS);
        }
    }

    /**
     * @param string $token
     * @return string
     */
    private static function sanitize_token($token) {
        if (!is_string($token) || $token === '') {
            return '';
        }
        return preg_match('/^[A-Za-z0-9_-]+$/', $token) ? $token : '';
    }
}
