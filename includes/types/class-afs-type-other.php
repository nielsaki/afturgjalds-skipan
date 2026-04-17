<?php
/**
 * Fallback "Annað" (other) reimbursement type.
 *
 * Used when a line doesn't fit the driving or expense types. Amount is optional
 * (so the bookkeeper can set it), attachment is optional.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Type_Other extends AFS_Type {

    public function id() { return 'other'; }

    public function label() { return 'Annað'; }

    public function validate(array $raw) {
        $errors = [];
        $n = [];
        $n['type']        = $this->id();
        $n['date']        = sanitize_text_field($raw['date'] ?? '');
        $n['description'] = sanitize_text_field($raw['description'] ?? '');
        $n['occasion']    = sanitize_text_field($raw['occasion'] ?? '');
        $n['note']        = sanitize_textarea_field($raw['note'] ?? '');
        $n['amount']      = $this->parse_float($raw['amount'] ?? '');
        $n['attachment']  = $raw['_attachment'] ?? null;

        if ($n['date'] === '')        { $errors[] = 'Dagur vantar.'; }
        if ($n['description'] === '') { $errors[] = 'Lýsing vantar.'; }
        if ($n['amount'] < 0)         { $errors[] = 'Upphæddin kann ikki vera negativ.'; }

        return ['normalized' => $n, 'errors' => $errors];
    }

    public function amount(array $n) {
        return (float) ($n['amount'] ?? 0);
    }

    public function format_for_email(array $n) {
        $out  = "Slag:            {$this->label()}\n";
        $out .= "Dagur:           {$n['date']}\n";
        $out .= "Lýsing:          {$n['description']}\n";
        if (!empty($n['occasion'])) {
            $out .= "Høvi/endamál:    {$n['occasion']}\n";
        }
        if ((float) $n['amount'] > 0) {
            $out .= 'Upphædd:         ' . $this->format_kr($n['amount']) . "\n";
        } else {
            $out .= "Upphædd:         (verður sett av bókhaldinum)\n";
        }
        if (!empty($n['attachment']['name'])) {
            $out .= "Hjálagt:         {$n['attachment']['name']}\n";
        }
        if (!empty($n['note'])) {
            $out .= "Viðmerking:      {$n['note']}\n";
        }
        return $out;
    }

    public function render_form_fields($index, array $v = []) {
        $amt = isset($v['amount']) ? esc_attr($v['amount']) : '';
        $html  = '<div class="afs-fields afs-fields--other">';
        $html .= '<p><label>Upphædd (kr, valfrítt)<br>';
        $html .= '<input type="number" step="0.01" min="0" name="afs_lines[' . esc_attr((string) $index) . '][amount]" value="' . $amt . '" data-afs-amount>';
        $html .= '</label></p>';
        $html .= '<p><label>Viðhefting (valfrítt)<br>';
        $html .= '<input type="file" name="afs_files_' . esc_attr((string) $index) . '">';
        $html .= '</label></p>';
        $html .= '</div>';
        return $html;
    }
}
