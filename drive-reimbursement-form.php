<?php
/**
 * Plugin Name: Drive Reimbursement Form
 * Description: Simple form where users submit trips and kilometers; sends an email to the site admin.
 * Version: 1.2
 */

if (!defined('ABSPATH')) {
    exit; // No direct access
}

/**
 * Local / staging email test: set in wp-config.php, e.g.:
 *   define('DRF_EMAIL_TEST_MODE', true);
 *   define('DRF_EMAIL_TEST_TO', 'you@example.com'); // optional; defaults to site admin email
 */
function drf_is_mail_test_mode()
{
    return defined('DRF_EMAIL_TEST_MODE') && DRF_EMAIL_TEST_MODE;
}

function drf_mail_test_recipient()
{
    if (defined('DRF_EMAIL_TEST_TO') && DRF_EMAIL_TEST_TO) {
        $t = sanitize_email(DRF_EMAIL_TEST_TO);
        if ($t) {
            return $t;
        }
    }
    return sanitize_email(get_option('admin_email'));
}

/**
 * Sends mail; in test mode everything goes to DRF_EMAIL_TEST_TO (or admin) with original recipients noted in the body.
 *
 * @param string|string[] $original_to
 * @param string[]        $headers
 * @param string          $role_label Short label for the email role (shown in test body)
 */
function drf_send_mail($original_to, $subject, $body, $headers = [], $role_label = '')
{
    if (!drf_is_mail_test_mode()) {
        return wp_mail($original_to, $subject, $body, $headers);
    }

    $test_to = drf_mail_test_recipient();
    $orig = is_array($original_to) ? implode(', ', $original_to) : (string) $original_to;
    $prefix  = "[DRF — próving av telduposti]\n";
    $prefix .= "Hetta brúkti at fara til: {$orig}\n";
    if ($role_label !== '') {
        $prefix .= "Slag: {$role_label}\n";
    }
    $prefix .= str_repeat('-', 48) . "\n\n";

    return wp_mail($test_to, '[DRF próving] ' . $subject, $prefix . $body, $headers);
}

function drf_render_form()
{
    $output = '';
    // configurable rate per km, default 1.60
    $rate_option = get_option('drf_rate_per_km', '1.60');
    $rate_per_km = floatval(str_replace(',', '.', $rate_option));

    // Tunnel prices in kr
    $tunnels = [
        'Vágatunnilin' => 10,
        'Norðoyatunnilin' => 10,
        'Eysturoyartunnilin (Strendur-Rókin)' => 25,
        'Eysturoyartunnilin (Eysturoy-Streymoy)' => 75,
        'Sandoyartunnilin' => 75
        // Her kanst tú leggja fleiri tunnlar afturat
    ];

    // Initialize form values so they can be reused on error
    $name = '';
    $email = '';
    $date = '';
    $trip = '';
    $occasion = '';
    $km_raw = '';
    $note = '';
    $tunnel_counts = [];
    $honeypot = '';
    $account = '';
    $consent = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drf_form_submitted'])) {

        // Security: nonce check
        if (!isset($_POST['drf_nonce']) || !wp_verify_nonce($_POST['drf_nonce'], 'drf_submit')) {
            $output .= '<div class="drf-error">Trygdarkanning miseydnaðist. Royn aftur.</div>';
        } else {
            // Sanitize input
            $honeypot = sanitize_text_field($_POST['drf_hp'] ?? '');
            $name  = sanitize_text_field($_POST['drf_name']  ?? '');
            $email = sanitize_email($_POST['drf_email']      ?? '');
            $date  = sanitize_text_field($_POST['drf_date']  ?? '');
            $trip  = sanitize_text_field($_POST['drf_trip']  ?? '');
            $occasion = sanitize_text_field($_POST['drf_occasion'] ?? '');
            $account = sanitize_text_field($_POST['drf_account'] ?? '');
            $consent = isset($_POST['drf_consent']) ? '1' : '';

            // Kilometrar koma nú beinleiðis frá brúkara
            $km_raw = sanitize_text_field($_POST['drf_km'] ?? '');
            $km_raw_norm = str_replace(',', '.', trim($km_raw));
            $km_entered = is_numeric($km_raw_norm) ? (float) $km_raw_norm : 0.0;
            $km = $km_entered;

            $note  = sanitize_textarea_field($_POST['drf_note'] ?? '');
            // Tunnlar: tal av ferðum ígjøgnum hvønn tunnil
            $tunnel_counts = [];
            if (isset($_POST['drf_tunnels']) && is_array($_POST['drf_tunnels'])) {
                foreach ($_POST['drf_tunnels'] as $tun_name => $count_raw) {
                    $tun_key = sanitize_text_field($tun_name);
                    $count = is_numeric($count_raw) ? (int)$count_raw : 0;
                    if ($count < 0) { $count = 0; }
                    if (isset($tunnels[$tun_key])) {
                        $tunnel_counts[$tun_key] = $count;
                    }
                }
            }

            // Honeypot: if this is filled, treat as spam and do not process further
            if (!empty($honeypot)) {
                // Pretend success but do nothing
                $output .= '<div class="drf-success">Takk! Koyringin er móttikin.</div>';
            } else {

                // Basic validation
                $errors = [];

                if (empty($name)) {
                    $errors[] = 'Vinaliga skriva navn.';
                }
                if (empty($date)) {
                    $errors[] = 'Vinaliga vel dag fyri koyring.';
                }
                if (empty($trip)) {
                    $errors[] = 'Vinaliga skriva hvat koyringin var (t.d. hvar tú koyrdi / hví).';
                }


                if (empty($occasion)) {
                    $errors[] = 'Vinaliga skriva høvið ella endamálið við koyringini.';
                }
                if ($km <= 0) {
                    $errors[] = 'Kilometratal má vera størri enn 0.';
                }
                if (empty($consent)) {
                    $errors[] = 'Vinaliga vátta, at tú góðtekur, at vit nýta hesar upplýsingarnar til at avgreiða koyripengarnar.';
                }
                // Kontonummar: optional, but if filled, require format "xxxx xxxxxxx" or "xxxx 000xxxxxxx"
                if (!empty($account)) {
                    if (!preg_match('/^[0-9]{4}\s([0-9]{7}|000[0-9]{7})$/', $account)) {
                        $errors[] = 'Kontonummar er ikki í rættum sniði (nýt xxxx xxxxxxx ella xxxx 000xxxxxxx).';
                    }
                }

                if (!empty($errors)) {
                    $output .= '<div class="drf-error"><ul>';
                    foreach ($errors as $e) {
                        $output .= '<li>' . esc_html($e) . '</li>';
                    }
                    $output .= '</ul></div>';
                } else {
                    // Reimbursement calculation
                    $km_amount = $rate_per_km * $km;

                    // Tunnilsgjald út frá talinum av ferðum
                    $tunnel_total = 0;
                    if (!empty($tunnel_counts)) {
                        foreach ($tunnel_counts as $tun_name => $count) {
                            if ($count > 0 && isset($tunnels[$tun_name])) {
                                $tunnel_total += ($tunnels[$tun_name] * $count);
                            }
                        }
                    }

                    $total_amount = $km_amount + $tunnel_total;

                    $km_formatted = number_format($km, 1, ',', ' ');
                    $km_amount_formatted = number_format($km_amount, 2, ',', ' ');
                    $tunnel_amount_formatted = number_format($tunnel_total, 2, ',', ' ');
                    $total_amount_formatted = number_format($total_amount, 2, ',', ' ');

                    // Prepare email – more informative subject
                    $subject_parts = [];
                    if (!empty($date)) {
                        $subject_parts[] = $date;
                    }
                    if (!empty($trip)) {
                        $subject_parts[] = $trip;
                    }
                    if (!empty($name)) {
                        $subject_parts[] = '(' . $name . ')';
                    }
                    $subject_suffix = trim(implode(' ', $subject_parts));
                    if ($subject_suffix === '') {
                        $subject = 'Koyripengar: nýggj fráboðan';
                    } else {
                        $subject = 'Koyripengar: ' . $subject_suffix;
}

                    $body  = "Nýggj fráboðan um koyring er móttikin:\n\n";
                    $body .= "Navn:   {$name}\n";
                    $body .= "Teldupostur:  {$email}\n";
                    if (!empty($account)) {
                        $body .= "Kontonummar:  {$account}\n";
                    }
                    $body .= "Dagur:   {$date}\n";
                    $body .= "Koyring (lýsing):   {$trip}\n";
                    $body .= "Høvi / endamál:  {$occasion}\n";
                    $body .= "Kilometrar:  {$km_formatted}\n";
                    $body .= "Kilometragjald:  " . number_format($rate_per_km, 2, ',', ' ') . " kr/km\n";
                    $body .= "Endurgjald fyri kilometrar:  {$km_amount_formatted} kr\n";
                    $body .= "Tunnilsgjald íalt:  {$tunnel_amount_formatted} kr\n";
                    $body .= "Endurgjald íalt:  {$total_amount_formatted} kr\n";
                    if (!empty($tunnel_counts)) {
                        $parts = [];
                        foreach ($tunnel_counts as $tun_name => $count) {
                            if ($count > 0) {
                                $parts[] = $tun_name . ' x ' . $count;
                            }
                        }
                        if (!empty($parts)) {
                            $body .= "Tunnlar: " . implode(', ', $parts) . "\n";
                        } else {
                            $body .= "Tunnlar: ongin\n";
                        }
                    } else {
                        $body .= "Tunnlar: ongin\n";
                    }
                    $body .= "\nViðmerking:\n{$note}\n\n";
                    $body .= "Sent frá: " . get_site_url() . "\n";

                    $headers = [];
                    if (!empty($email)) {
                        $headers[] = 'Reply-To: ' . $email;
                    }

                    $sent = drf_send_mail('bokhald@fss.fo', $subject, $body, $headers, 'Bókhald');

                    if ($sent) {
                        // Optional kvittan til avsendara
                        if (!empty($email)) {
                            $copy_subject = 'Kvittan: ' . $subject;
                            $copy_body  = "Hetta er kvittan fyri, at tú hevur sent inn fráboðan um koyripengar:\n\n";
                            $copy_body .= $body;
                            // Her brúka vit standard-headers (uttan Reply-To), so svar fer aftur til tykkara mailserver
                            drf_send_mail($email, $copy_subject, $copy_body, [], 'Kvittan til avsendara');
                        }

                        $output .= '<div class="drf-success">Takk! Koyringin er móttikin.</div>';
                    } else {
                        $output .= '<div class="drf-error">Eitt mistak hentist við at senda teldupostin. Vinarliga set teg í samband við bókhaldið.</div>';
                    }
                }
            }
        }
    }

    if (drf_is_mail_test_mode()) {
        $test_addr = esc_html(drf_mail_test_recipient());
        $output .= '<div class="drf-test-banner" role="status"><strong>Próving:</strong> teldupostur verður ikki sendur til bókhald ella avsendara — hann fer til <code>' . $test_addr . '</code> (við teksti um uppruna móttakara).</div>';
    }

    // Always show the form
    $output .= '<form method="post" class="drf-form">';

    // Nonce + hidden field
    $output .= wp_nonce_field('drf_submit', 'drf_nonce', true, false);
    $output .= '<input type="hidden" name="drf_form_submitted" value="1">';

    $output .= '<p>
        <label>Navn *<br>
            <input type="text" name="drf_name" required value="' . esc_attr($name) . '">
        </label>
    </p>';

    $output .= '<p>
        <label>Teldupostur *<br>
            <input type="email" name="drf_email" required value="' . esc_attr($email) . '" placeholder="fss@fss.fo">
        </label>
    </p>';
    $output .= '<p>
        <label>Kontonummar (valfrítt)<br>
            <input type="text" name="drf_account" value="' . esc_attr($account) . '" placeholder="1234 1234567 ella 1234 0001234567" pattern="[0-9]{4} ([0-9]{7}|000[0-9]{7})">
        </label>
    </p>';
    $output .= '<p>
    <label>
        <input type="checkbox" name="drf_consent" value="1"' . ($consent === '1' ? ' checked="checked"' : '') . ' required>
        Eg góðtaki, at hesar upplýsingarnar verða nýttar til at útreiða og avgreiða koyripengarnar.
    </label>
    </p>';

    $default_date = $date ?: date('Y-m-d');

    $output .= '<p>
        <label>Dato fyri koyringina *<br>
            <input type="date" name="drf_date" value="' . esc_attr($default_date) . '">
        </label>
    </p>';

    $output .= '<p>
        <label>Høvi / endamál við koyringini *<br>
            <input type="text" name="drf_occasion" required value="' . esc_attr($occasion) . '">
        </label>
    </p>';

    $output .= '<p>
        <label>Koyring (hvat varð koyrt / hvar / hví) *<br>
            <input type="text" name="drf_trip" required value="' . esc_attr($trip) . '" placeholder="t.d. Tórshavn → Klaksvík, venjing">
        </label>
    </p>';


    $output .= '<p>
        <label>Koyrdir kilometrar *<br>
            <input type="number" step="0.1" min="0" name="drf_km" required value="' . esc_attr($km_raw) . '" placeholder="t.d. 12,5">
        </label>
    </p>';

    $output .= '<p>
        <label>Gjald pr. km<br>
            <input type="text" value="' . esc_attr(number_format($rate_per_km, 2, ',', ' ') . ' kr/km') . '" readonly>
        </label>
    </p>';

    $output .= '<p>
        <label>Tunnlar (tal av ferðum ígjøgnum)<br>';
    foreach ($tunnels as $tun_name => $tun_cost) {
        $val = isset($tunnel_counts[$tun_name]) ? (int)$tunnel_counts[$tun_name] : 0;
        $output .= '<label style="display:block;">' . esc_html($tun_name) . ' (' . esc_html($tun_cost) . ' kr)
            <input type="number" min="0" step="1" name="drf_tunnels[' . esc_attr($tun_name) . ']" value="' . esc_attr($val) . '" style="max-width:120px;display:inline-block;margin-left:0.5rem;">
        </label>';
    }
    $output .= '    </label>
    </p>';

    // Honeypot field (hidden) for spam protection
    $output .= '<p class="drf-hp" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
        <label>Ikki fyll hetta út<br>
            <input type="text" name="drf_hp" tabindex="-1" autocomplete="off">
        </label>
    </p>';

    $output .= '<p>
        <label>Viðmerking (valfrítt)<br>
            <textarea name="drf_note" rows="3">' . esc_textarea($note) . '</textarea>
        </label>
    </p>';

    $output .= '<p>
        <label>Útroknað endurgjald <br>
            <input type="text" id="drf_total" readonly>
        </label>
    </p>';

    $output .= '<p><small>Hetta er ein fyribils útrokning. Upphæddin verður endaliga uppgjørd av bókhaldinum.</small></p>';

    $output .= '<p>
        <button type="submit">Lat inn</button>
    </p>';

    $output .= '</form>';

    // Simple JavaScript to calculate the total on the client side
    $output .= '<script>
document.addEventListener("DOMContentLoaded", function() {
    var form = document.querySelector(".drf-form");
    if (!form) return;

    var kmInput = form.querySelector("input[name=\"drf_km\"]");
    var totalInput = form.querySelector("#drf_total");
    var rate = ' . $rate_per_km . ';

    var tunnelInputs = form.querySelectorAll("input[name^=\"drf_tunnels[\"]");

    // Same tunnel prices as in PHP
    var tunnelRates = {
        "Vágatunnilin": 10,
        "Norðoyatunnilin": 10,
        "Eysturoyartunnilin (Strendur-Rókin)": 25,
        "Eysturoyartunnilin (Eysturoy-Streymoy)": 75,
        "Sandoyartunnilin": 75
    };

    function updateTotal() {
        if (!totalInput) return;

        var kmVal = parseFloat(kmInput && kmInput.value ? kmInput.value.replace(",", ".") : "0");
        var baseAmount = 0;
        if (!isNaN(kmVal) && kmVal > 0) {
            baseAmount = kmVal * rate;
        }

        var tunnelAmount = 0;
        if (tunnelInputs && tunnelInputs.length) {
            for (var i = 0; i < tunnelInputs.length; i++) {
                var inp = tunnelInputs[i];
                var nameAttr = inp.getAttribute("name") || "";
                var key = nameAttr.replace(/^drf_tunnels\[|\]$/g, "");
                var count = parseInt(inp.value || "0", 10);
                if (!isNaN(count) && count > 0 && Object.prototype.hasOwnProperty.call(tunnelRates, key)) {
                    tunnelAmount += tunnelRates[key] * count;
                }
            }
        }

        var total = baseAmount + tunnelAmount;

        if (total > 0) {
            totalInput.value = total.toFixed(2).replace(".", ",");
        } else {
            totalInput.value = "";
        }
    }

    if (tunnelInputs && tunnelInputs.length) {
        for (var i = 0; i < tunnelInputs.length; i++) {
            tunnelInputs[i].addEventListener("change", updateTotal);
            tunnelInputs[i].addEventListener("input", updateTotal);
        }
    }
    if (kmInput) {
        kmInput.addEventListener("input", updateTotal);
    }

    // Initial calculation
    updateTotal();
});
</script>';

    return $output;
}

/**
 * Register shortcode [drive_reimbursement_form]
 */
function drf_register_shortcode()
{
    add_shortcode('drive_reimbursement_form', 'drf_render_form');
}
add_action('init', 'drf_register_shortcode');

/**
 * Optional: basic CSS for the form (you can style it better in your theme)
 */
function drf_enqueue_styles()
{
    $css = '
    .drf-test-banner {
        max-width: 620px;
        margin: 1rem auto 0;
        padding: 0.75rem 1rem;
        border-radius: 6px;
        border: 1px solid #c9a227;
        background: #fffbea;
        color: #5c4a00;
        font-size: 14px;
        box-sizing: border-box;
    }
    .drf-test-banner code {
        font-size: 13px;
        word-break: break-all;
    }

    .drf-form {
        max-width: 620px;
        margin: 2rem auto 3rem;
        padding: 1.5rem 2rem 2rem;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        box-sizing: border-box;
    }

    .drf-form p {
        margin: 0 0 1rem;
    }

    .drf-form label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .drf-form input[type="text"],
    .drf-form input[type="email"],
    .drf-form input[type="date"],
    .drf-form input[type="number"],
    .drf-form textarea,
    .drf-form select {
        width: 100%;
        max-width: 100%;
        padding: 0.5em 0.6em;
        border-radius: 4px;
        border: 1px solid #ccd0d4;
        box-sizing: border-box;
        font-size: 14px;
        font-family: inherit;
    }

    .drf-form input[type="text"]:focus,
    .drf-form input[type="email"]:focus,
    .drf-form input[type="date"]:focus,
    .drf-form input[type="number"]:focus,
    .drf-form textarea:focus,
    .drf-form select:focus {
        outline: none;
        border-color: #007cba;
        box-shadow: 0 0 0 1px #007cba33;
    }

    .drf-form small {
        color: #666;
        font-size: 12px;
    }

    .drf-form button[type="submit"] {
        display: inline-block;
        padding: 0.6rem 1.4rem;
        border-radius: 4px;
        border: none;
        background: #007cba;
        color: #ffffff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease, transform 0.05s ease;
    }

    .drf-form button[type="submit"]:hover {
        background: #006ba1;
    }

    .drf-form button[type="submit"]:active {
        transform: translateY(1px);
    }

    .drf-form input[type="checkbox"] {
        width: auto;
        margin-right: 0.4rem;
    }

    .drf-form .drf-success {
        padding: 0.6em 0.9em;
        margin: 1rem auto;
        border-radius: 4px;
        border: 1px solid #4caf50;
        background: #e8f5e9;
        color: #256029;
    }

    .drf-form .drf-error {
        padding: 0.6em 0.9em;
        margin: 1rem auto;
        border-radius: 4px;
        border: 1px solid #f44336;
        background: #ffebee;
        color: #b71c1c;
    }

    .drf-form .drf-error ul {
        margin: 0.25rem 0 0;
        padding-left: 1.2rem;
    }

    .drf-form .drf-error li {
        margin: 0.15rem 0;
    }

    @media (max-width: 600px) {
        .drf-form {
            margin: 1.5rem 1rem 2rem;
            padding: 1.25rem 1.25rem 1.75rem;
        }
    }
    ';
    wp_add_inline_style('wp-block-library', $css);
}
add_action('wp_enqueue_scripts', 'drf_enqueue_styles');


/**
 * Register settings for drive reimbursement
 */
function drf_register_settings() {
    register_setting('drf_settings', 'drf_rate_per_km');
}
add_action('admin_init', 'drf_register_settings');

/**
 * Add settings page under Settings
 */
function drf_settings_menu() {
    add_options_page(
        'Koyripengar stillingar',
        'Koyripengar',
        'manage_options',
        'drf-settings',
        'drf_settings_page'
    );
}
add_action('admin_menu', 'drf_settings_menu');

/**
 * Render the settings page
 */
function drf_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Koyripengar stillingar</h1>
        <form method="post" action="options.php">
            <?php settings_fields('drf_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Gjald pr. km</th>
                    <td>
                        <input type="text" name="drf_rate_per_km" value="<?php echo esc_attr(get_option('drf_rate_per_km', '1.60')); ?>" />
                        <p class="description">Skriva gjald pr. kilometrara, t.d. 1.60.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}