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

        $lines               = [];
        $attachments_to_send = [];
        $line_attach_tokens    = [];
        if (!empty($post['afs_staged']) && is_array($post['afs_staged'])) {
            foreach ($post['afs_staged'] as $k => $tok) {
                $line_attach_tokens[$k] = sanitize_text_field((string) $tok);
            }
        }

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

            $file_key    = 'afs_files_' . $i;
            $prev_token  = isset($line_attach_tokens[$i]) ? (string) $line_attach_tokens[$i] : '';
            $new_upload  = !empty($files[$file_key]['tmp_name'])
                && isset($files[$file_key]['error'])
                && (int) $files[$file_key]['error'] === UPLOAD_ERR_OK;

            if ($type_id !== 'expense') {
                if ($prev_token !== '') {
                    AFS_File_Stage::delete_token($prev_token);
                }
                unset($line_attach_tokens[$i]);
            } else {
                if ($new_upload) {
                    if ($prev_token !== '') {
                        AFS_File_Stage::delete_token($prev_token);
                    }
                    $orig_name = sanitize_file_name($files[$file_key]['name']);
                    $token     = AFS_File_Stage::persist_from_tmp(
                        $files[$file_key]['tmp_name'],
                        $orig_name,
                        (int) $files[$file_key]['size'],
                        sanitize_text_field($files[$file_key]['type'] ?? '')
                    );
                    if ($token) {
                        $att = AFS_File_Stage::to_attachment_array($token);
                        if ($att) {
                            $raw['_attachment']    = $att;
                            $attachments_to_send[] = $att;
                            $line_attach_tokens[$i] = $token;
                        }
                    }
                } elseif ($prev_token !== '') {
                    $att = AFS_File_Stage::to_attachment_array($prev_token);
                    if ($att) {
                        $raw['_attachment']    = $att;
                        $attachments_to_send[] = $att;
                        $line_attach_tokens[$i] = $prev_token;
                    } else {
                        unset($line_attach_tokens[$i]);
                    }
                } else {
                    unset($line_attach_tokens[$i]);
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
            $result['values'] = self::merge_attach_values($post, $line_attach_tokens);
            return $result;
        }

        $total = 0.0;
        $body  = "Nýggj fráboðan um endurgjald er móttikin:\n\n";
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
        $subject = 'Endurgjald: ' . implode(' ', array_filter($subject_parts));

        $recipient = function_exists('apply_filters')
            ? apply_filters('afs_recipient', 'bokhald@fss.fo')
            : 'bokhald@fss.fo';

        $headers = [];
        if ($email !== '') {
            $headers[] = 'Reply-To: ' . $email;
        }

        $sent_main = (bool) AFS_Mail::send($recipient, $subject, $body, $headers, $attachments_to_send, 'Bókhald');
        $sent_copy = true;
        if ($sent_main && $email !== '') {
            $copy_body  = "Hetta er kvittan fyri, at tú hevur sent inn fráboðan um endurgjald:\n\n";
            $copy_body .= $body;
            $sent_copy = (bool) AFS_Mail::send($email, 'Kvittan: ' . $subject, $copy_body, [], $attachments_to_send, 'Kvittan til avsendara');
        }
        $sent = $sent_main && $sent_copy;

        if ($sent) {
            $done_tokens = array_unique(array_filter(array_values($line_attach_tokens)));
            AFS_File_Stage::delete_tokens($done_tokens);
        }

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
            if (!$sent_main) {
                $result['errors'][] = 'Teldupostur kundi ikki sendast. Vinaliga royn aftur ella tak samband við bókhaldið.';
            } elseif (!$sent_copy) {
                $result['errors'][] = 'Kvittan til tín kundi ikki sendast. Fráboðanin er móttikin; royn aftur ella tak samband við bókhaldið.';
            }
            $result['values'] = self::merge_attach_values($post, $line_attach_tokens);
        }

        return $result;
    }

    /**
     * Merge hidden `afs_staged` tokens into POST values for redisplay after errors or failed mail.
     *
     * @param array $post
     * @param array $line_tokens  Map line index => opaque token
     * @return array
     */
    private static function merge_attach_values(array $post, array $line_tokens) {
        $out = $post;
        $clean = [];
        foreach ($line_tokens as $idx => $tok) {
            if ((string) $tok !== '') {
                $clean[$idx] = (string) $tok;
            }
        }
        if (!empty($clean)) {
            $out['afs_staged'] = $clean;
        } else {
            unset($out['afs_staged']);
        }
        return $out;
    }
}
