<?php
/**
 * Processes a posted reimbursement form. Returns a structured result.
 *
 * This class is deliberately framework-light: it only uses functions that are
 * stubbed in `tests/wp-stubs.php`, so it can be exercised headlessly.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Submission {

    /**
     * Process a submission.
     *
     * @param array $post  $_POST-like data.
     * @param array $files $_FILES-like data.
     * @return array {
     *     @type bool     $success      Overall success.
     *     @type bool     $sent         Whether the main email was queued/sent.
     *     @type string[] $errors       Validation / send errors.
     *     @type array    $values       Echoed-back values (for redisplay).
     *     @type float    $total        Total amount calculated.
     *     @type array    $lines        Normalized line data.
     *     @type string   $email_body   Final email body composed.
     *     @type string   $subject      Subject used for the main email.
     * }
     */
    public static function process(array $post, array $files = []) {
        $result = [
            'success'    => false,
            'sent'       => false,
            'errors'     => [],
            'values'     => $post,
            'total'      => 0.0,
            'lines'      => [],
            'email_body' => '',
            'subject'    => '',
        ];

        if (!isset($post['afs_nonce']) || !wp_verify_nonce($post['afs_nonce'], 'afs_submit')) {
            $result['errors'][] = 'Trygdarkanning miseydnaðist. Royn aftur.';
            return $result;
        }

        if (!empty($post['afs_hp'])) {
            $result['success'] = true;
            return $result;
        }

        $name    = sanitize_text_field($post['afs_name']    ?? '');
        $email   = sanitize_email($post['afs_email']        ?? '');
        $account = sanitize_text_field($post['afs_account'] ?? '');
        $consent = !empty($post['afs_consent']);

        if ($name === '')  { $result['errors'][] = 'Navn vantar.'; }
        if ($email === '') { $result['errors'][] = 'Teldupostur vantar ella er ikki gildigur.'; }
        if (!$consent)     { $result['errors'][] = 'Vinaliga vátta, at tú góðtekur, at vit nýta upplýsingarnar.'; }
        if ($account !== '' && !preg_match('/^[0-9]{4}\s([0-9]{7}|000[0-9]{7})$/', $account)) {
            $result['errors'][] = 'Kontonummar er ikki í rættum sniði (xxxx xxxxxxx ella xxxx 000xxxxxxx).';
        }

        $raw_lines = [];
        if (!empty($post['afs_lines']) && is_array($post['afs_lines'])) {
            $raw_lines = array_values($post['afs_lines']);
        }
        if (empty($raw_lines)) {
            $result['errors'][] = 'Tú mást velja í minsta lagi eina linju.';
        }

        $lines              = [];
        $attachments_to_send = [];
        $tmp_copies          = [];

        foreach ($raw_lines as $i => $raw) {
            $type_id = sanitize_text_field($raw['type'] ?? '');
            $type    = AFS_Types::get($type_id);
            if (!$type) {
                if ($type_id === '') {
                    $result['errors'][] = 'Vel slag fyri linju ' . ($i + 1) . '.';
                } else {
                    $result['errors'][] = 'Slagið fyri linju ' . ($i + 1) . ' er ókent.';
                }
                continue;
            }

            $file_key = 'afs_files_' . $i;
            if (
                !empty($files[$file_key]) &&
                !empty($files[$file_key]['tmp_name']) &&
                isset($files[$file_key]['error']) &&
                (int) $files[$file_key]['error'] === UPLOAD_ERR_OK
            ) {
                $orig_name = sanitize_file_name($files[$file_key]['name']);
                $tmp_path  = self::relocate_upload(
                    $files[$file_key]['tmp_name'],
                    $orig_name
                );
                if ($tmp_path) {
                    $att = [
                        'name' => $orig_name,
                        'path' => $tmp_path,
                        'size' => (int) $files[$file_key]['size'],
                        'type' => sanitize_text_field($files[$file_key]['type']),
                    ];
                    $raw['_attachment']    = $att;
                    $attachments_to_send[] = $att;
                    $tmp_copies[]          = $tmp_path;
                }
            }

            $validated = $type->validate($raw);
            foreach ($validated['errors'] as $e) {
                $result['errors'][] = 'Linja ' . ($i + 1) . ': ' . $e;
            }
            if (empty($validated['errors'])) {
                $lines[] = ['type' => $type, 'data' => $validated['normalized']];
            }
        }

        if (!empty($result['errors'])) {
            self::cleanup_tmp($tmp_copies);
            return $result;
        }

        $total = 0.0;
        $body  = "Nýggj fráboðan um afturgjald er móttikin:\n\n";
        $body .= "Navn:            {$name}\n";
        $body .= "Teldupostur:     {$email}\n";
        if ($account !== '') {
            $body .= "Kontonummar:     {$account}\n";
        }
        $body .= "\n";

        foreach ($lines as $idx => $line) {
            $body .= '--- Linja ' . ($idx + 1) . ' ---' . "\n";
            $body .= $line['type']->format_for_email($line['data']);
            $total += $line['type']->amount($line['data']);
            $body .= "\n";
        }

        $body .= 'Íalt tilsamans:  ' . number_format($total, 2, ',', ' ') . ' kr' . "\n\n";
        $body .= 'Sent frá: ' . (function_exists('get_site_url') ? get_site_url() : '(ókent)') . "\n";

        $subject_parts = [];
        $first         = $lines[0]['data'] ?? null;
        if ($first && !empty($first['date'])) { $subject_parts[] = $first['date']; }
        $subject_parts[] = $name;
        $subject_parts[] = '(' . count($lines) . ' ' . (count($lines) === 1 ? 'linja' : 'linjur') . ', ' . number_format($total, 2, ',', ' ') . ' kr)';
        $subject = 'Afturgjald: ' . implode(' ', array_filter($subject_parts));

        $recipient = function_exists('apply_filters')
            ? apply_filters('afs_recipient', 'bokhald@fss.fo')
            : 'bokhald@fss.fo';

        $headers = [];
        if ($email !== '') {
            $headers[] = 'Reply-To: ' . $email;
        }

        $sent = AFS_Mail::send($recipient, $subject, $body, $headers, $attachments_to_send, 'Bókhald');

        if ($sent && $email !== '') {
            $copy_body  = "Hetta er kvittan fyri, at tú hevur sent inn fráboðan um afturgjald:\n\n";
            $copy_body .= $body;
            AFS_Mail::send($email, 'Kvittan: ' . $subject, $copy_body, [], $attachments_to_send, 'Kvittan til avsendara');
        }

        self::cleanup_tmp($tmp_copies);

        $result['success']    = (bool) $sent;
        $result['sent']       = (bool) $sent;
        $result['total']      = $total;
        $result['lines']      = array_map(function ($l) {
            return ['type' => $l['type']->id(), 'data' => $l['data']];
        }, $lines);
        $result['email_body'] = $body;
        $result['subject']    = $subject;

        // Persist a record of the submission so bookkeeping can review it in
        // wp-admin. Saved even when sending failed, so nothing is lost.
        $should_store = function_exists('apply_filters')
            ? apply_filters('afs_store_submission', true, $result)
            : true;
        if ($should_store && class_exists('AFS_Store')) {
            $stored_id = AFS_Store::save([
                'name'       => $name,
                'email'      => $email,
                'account'    => $account,
                'lines'      => $result['lines'],
                'total'      => $total,
                'subject'    => $subject,
                'email_body' => $body,
                'sent'       => (bool) $sent,
                'line_count' => count($lines),
            ]);
            $result['stored_id'] = $stored_id;
        }

        if (!$sent) {
            $result['errors'][] = 'Teldupostur kundi ikki sendast. Vinaliga royn aftur ella tak samband við bókhaldið.';
        }

        return $result;
    }

    /**
     * Copy or move an uploaded file to a temp location that preserves the
     * original filename (so email attachments are named sensibly).
     *
     * @param string $tmp_name
     * @param string $orig_name
     * @return string|null Absolute path to the new temp copy, or null on failure.
     */
    private static function relocate_upload($tmp_name, $orig_name) {
        $tmp_dir = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
        $tmp_dir = rtrim($tmp_dir, '/\\');
        if (!is_dir($tmp_dir) || !is_writable($tmp_dir)) {
            return null;
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $orig_name) ?: 'file.bin';
        $new  = $tmp_dir . '/afs_' . uniqid('', true) . '_' . $safe;

        if (function_exists('is_uploaded_file') && is_uploaded_file($tmp_name) && function_exists('move_uploaded_file')) {
            if (@move_uploaded_file($tmp_name, $new)) {
                return $new;
            }
        }
        if (@copy($tmp_name, $new)) {
            return $new;
        }
        return null;
    }

    private static function cleanup_tmp(array $paths) {
        foreach ($paths as $p) {
            if ($p && file_exists($p)) {
                @unlink($p);
            }
        }
    }
}
