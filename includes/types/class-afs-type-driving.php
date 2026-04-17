<?php
/**
 * Driving reimbursement type: kilometres + tunnel passes.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Type_Driving extends AFS_Type {

    public function id() { return 'driving'; }

    public function label() { return 'Koyring'; }

    public static function tunnels() {
        return apply_filters('afs_tunnels', [
            'Vágatunnilin' => 10,
            'Norðoyatunnilin' => 10,
            'Eysturoyartunnilin (Strendur-Rókin)' => 25,
            'Eysturoyartunnilin (Eysturoy-Streymoy)' => 75,
            'Sandoyartunnilin' => 75,
        ]);
    }

    public static function rate_per_km() {
        $opt  = function_exists('get_option') ? get_option('drf_rate_per_km', '1.60') : '1.60';
        $norm = str_replace(',', '.', (string) $opt);
        return is_numeric($norm) ? (float) $norm : 1.60;
    }

    public function validate(array $raw) {
        $errors = [];
        $n = [];
        $n['type']        = $this->id();
        $n['date']        = sanitize_text_field($raw['date'] ?? '');
        $n['description'] = sanitize_text_field($raw['description'] ?? '');
        $n['occasion']    = sanitize_text_field($raw['occasion'] ?? '');
        $n['note']        = sanitize_textarea_field($raw['note'] ?? '');
        $n['km_claim']    = !empty($raw['km_claim']);
        $n['km']          = $this->parse_float($raw['km'] ?? '');

        $tunnels = [];
        if (!empty($raw['tunnels']) && is_array($raw['tunnels'])) {
            $known = self::tunnels();
            foreach ($raw['tunnels'] as $name => $count) {
                $name  = sanitize_text_field((string) $name);
                $count = is_numeric($count) ? (int) $count : 0;
                if ($count < 0) { $count = 0; }
                if ($count > 0 && isset($known[$name])) {
                    $tunnels[$name] = $count;
                }
            }
        }
        $n['tunnels']    = $tunnels;
        $n['attachment'] = $raw['_attachment'] ?? null;

        if ($n['date'] === '')        { $errors[] = 'Dagur vantar fyri koyring.'; }
        if ($n['description'] === '') { $errors[] = 'Lýsing av koyring vantar.'; }
        if ($n['occasion'] === '')    { $errors[] = 'Høvi/endamál vantar fyri koyring.'; }

        if ($n['km_claim']) {
            if ($n['km'] <= 0) {
                $errors[] = 'Kilometratal má vera størri enn 0, tá ið tú krevur afturgjald fyri kilometrar.';
            }
        } else {
            $n['km'] = 0.0;
        }

        return ['normalized' => $n, 'errors' => $errors];
    }

    public function amount(array $n) {
        $km_amount = !empty($n['km_claim'])
            ? (float) ($n['km'] ?? 0) * self::rate_per_km()
            : 0.0;
        $tunnel_total = 0.0;
        $known        = self::tunnels();
        foreach (($n['tunnels'] ?? []) as $name => $count) {
            if (isset($known[$name])) {
                $tunnel_total += $known[$name] * (int) $count;
            }
        }
        return $km_amount + $tunnel_total;
    }

    public function format_for_email(array $n) {
        $rate         = self::rate_per_km();
        $km           = (float) ($n['km'] ?? 0);
        $km_claim     = !empty($n['km_claim']);
        $km_amount    = $km_claim ? $km * $rate : 0.0;
        $tunnel_total = 0.0;
        $known        = self::tunnels();
        $tunnel_lines = [];
        foreach (($n['tunnels'] ?? []) as $name => $count) {
            if (isset($known[$name])) {
                $line_total   = $known[$name] * (int) $count;
                $tunnel_total += $line_total;
                $tunnel_lines[] = "  {$name} × {$count} = " . $this->format_kr($line_total);
            }
        }

        $out  = "Slag:            {$this->label()}\n";
        $out .= "Dagur:           {$n['date']}\n";
        $out .= "Lýsing:          {$n['description']}\n";
        $out .= "Høvi/endamál:    {$n['occasion']}\n";
        if ($km_claim) {
            $out .= 'Kilometrar:      ' . number_format($km, 1, ',', ' ') . "\n";
            $out .= 'Kilometragjald:  ' . number_format($rate, 2, ',', ' ') . ' kr/km → ' . $this->format_kr($km_amount) . "\n";
        } else {
            $out .= "Km-afturgjald:   ei krav (avtala ikki sett)\n";
        }
        if ($tunnel_lines) {
            $out .= "Tunnlar:\n" . implode("\n", $tunnel_lines) . "\n";
            $out .= 'Tunnlar íalt:    ' . $this->format_kr($tunnel_total) . "\n";
        }
        $out .= 'Íalt í linjuni:  ' . $this->format_kr($km_amount + $tunnel_total) . "\n";
        if (!empty($n['note'])) {
            $out .= "Viðmerking:      {$n['note']}\n";
        }
        return $out;
    }

    public function render_form_fields($index, array $v = []) {
        $tunnels  = self::tunnels();
        $rate     = self::rate_per_km();
        $km_val   = isset($v['km']) ? esc_attr($v['km']) : '';
        $km_claim = empty($v) ? true : !empty($v['km_claim']);

        $name_idx = esc_attr((string) $index);

        $html  = '<div class="afs-fields afs-fields--driving">';

        $html .= '<p class="afs-km-claim"><label>';
        $html .= '<input type="checkbox" name="afs_lines[' . $name_idx . '][km_claim]" value="1"' . ($km_claim ? ' checked' : '') . ' data-afs-km-claim> ';
        $html .= 'Avtala um afturgjald fyri koyrdar kilometrar ';
        $html .= '<span class="afs-km-rate">(' . esc_html(number_format($rate, 2, ',', ' ')) . ' kr/km)</span>';
        $html .= '</label></p>';

        $html .= '<p class="afs-km-input' . ($km_claim ? '' : ' afs-km-input--off') . '"><label>';
        $html .= 'Koyrdir kilometrar<span class="afs-km-req"' . ($km_claim ? '' : ' hidden') . '> *</span><br>';
        $html .= '<input type="number" step="0.1" min="0" name="afs_lines[' . $name_idx . '][km]" value="' . $km_val . '" placeholder="t.d. 12,5" data-afs-driving-km' . ($km_claim ? ' required' : '') . '>';
        $html .= '</label></p>';

        $html .= '<p><strong>Tunnlar (tal av ferðum ígjøgnum)</strong></p>';
        $html .= '<div class="afs-tunnels">';
        foreach ($tunnels as $name => $cost) {
            $count = isset($v['tunnels'][$name]) ? (int) $v['tunnels'][$name] : 0;
            $html .= '<label class="afs-tunnel">'
                  . '<span class="afs-tunnel__name">' . esc_html($name) . ' (' . esc_html((string) $cost) . ' kr)</span>'
                  . '<input type="number" min="0" step="1" name="afs_lines[' . $name_idx . '][tunnels][' . esc_attr($name) . ']" value="' . esc_attr((string) $count) . '">'
                  . '</label>';
        }
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}
