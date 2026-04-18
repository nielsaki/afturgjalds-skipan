<?php
/**
 * Expense/receipt reimbursement type: fixed amount + required purpose,
 * with optional attachment (reikningur / kvittan).
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Type_Expense extends AFS_Type {

    public function id() { return 'expense'; }

    public function label() { return 'Útreisðla'; }

    public function validate(array $raw) {
        $errors = [];
        $n = [];
        $n['type']        = $this->id();
        $n['date']        = sanitize_text_field($raw['date'] ?? '');
        $n['description']   = sanitize_text_field($raw['description'] ?? '');
        $n['note']          = sanitize_textarea_field($raw['note'] ?? '');
        $n['authorized_by'] = sanitize_text_field($raw['authorized_by'] ?? '');
        $n['amount']      = $this->parse_float($raw['amount'] ?? '');
        $n['attachment']  = $raw['_attachment'] ?? null;

        if ($n['date'] === '')        { $errors[] = 'Dagur vantar fyri útreiðslu.'; }
        if ($n['description'] === '') { $errors[] = 'Lýsing av útreiðslu vantar.'; }
        if ($n['amount'] <= 0)        { $errors[] = 'Upphædd á útreiðslulinju má vera størri enn 0.'; }

        return ['normalized' => $n, 'errors' => $errors];
    }

    public function amount(array $n) {
        return (float) ($n['amount'] ?? 0);
    }

    public function format_for_email(array $n) {
        $out  = "Slag:            {$this->label()}\n";
        $out .= "Dagur:           {$n['date']}\n";
        $out .= "Lýsing:          {$n['description']}\n";
        $out .= 'Upphædd:         ' . $this->format_kr($n['amount']) . "\n";
        if (!empty($n['attachment']['name'])) {
            $out .= "Hjálagt:         {$n['attachment']['name']}\n";
        } else {
            $out .= "Hjálagt:         (eingin viðhefting)\n";
        }
        if (!empty($n['note'])) {
            $out .= "Viðmerking:      {$n['note']}\n";
        }
        if (!empty($n['authorized_by'])) {
            $out .= "Loyvi frá:       {$n['authorized_by']}\n";
        }
        return $out;
    }

    public function render_form_fields($index, array $v = []) {
        $amt = isset($v['amount']) ? esc_attr($v['amount']) : '';
        $idx = esc_attr((string) $index);
        $html  = '<div class="afs-fields afs-fields--expense">';
        $html .= '<p><label>Upphædd (kr) *<br>';
        $html .= '<input type="number" step="0.01" min="0" name="afs_lines[' . $idx . '][amount]" value="' . $amt . '" data-afs-amount>';
        $html .= '</label></p>';
        $html .= '<p class="afs-file-row"><label>Viðheft skjal (valfrítt)<br>';
        $html .= '<input type="file" name="afs_files_' . $idx . '" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.heic,.heif">';
        $html .= '</label></p>';
        $st_tok = isset($v['_staged_token']) ? (string) $v['_staged_token'] : '';
        $st_nm  = isset($v['_staged_name']) ? (string) $v['_staged_name'] : '';
        if ($st_tok !== '') {
            $html .= '<input type="hidden" name="afs_staged[' . $idx . ']" value="' . esc_attr($st_tok) . '">';
            if ($st_nm !== '') {
                $html .= '<p class="afs-staged-hint" role="status"><strong>Útvalt:</strong> ' . esc_html($st_nm) . '</p>';
            }
        }
        $html .= '</div>';
        return $html;
    }
}
