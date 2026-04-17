<?php
/**
 * Renders the reimbursement form (shortcode handler).
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Form {

    public static function render() {
        $result = null;
        $values = [];

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['afs_form_submitted'])) {
            $result = AFS_Submission::process($_POST, $_FILES);
            $values = $_POST;
        }

        ob_start();
        ?>
        <div class="afs-wrap">
            <?php if (AFS_Mail::is_test_mode()): ?>
                <div class="afs-test-banner" role="status">
                    <strong>Próving:</strong> teldupostur verður ikki sendur til bókhald ella avsendara — hann fer til
                    <code><?php echo esc_html(AFS_Mail::test_recipient() ?: '(ikki settur)'); ?></code>.
                    <?php if (AFS_Mail::is_dry_run()): ?>
                        <em>(Dry run — einki verður í roynd og veru sent.)</em>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($result && $result['success'] && $result['sent']): ?>
                <div class="afs-success">Takk! Fráboðanin er móttikin.</div>
            <?php elseif ($result && !empty($result['errors'])): ?>
                <div class="afs-error">
                    <ul><?php foreach ($result['errors'] as $e) echo '<li>' . esc_html($e) . '</li>'; ?></ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="afs-form" id="afs-form">
                <?php wp_nonce_field('afs_submit', 'afs_nonce'); ?>
                <input type="hidden" name="afs_form_submitted" value="1">

                <fieldset class="afs-person">
                    <legend>Persónligar upplýsingar</legend>

                    <p><label>Navn *<br>
                        <input type="text" name="afs_name" required value="<?php echo esc_attr($values['afs_name'] ?? ''); ?>">
                    </label></p>

                    <p><label>Teldupostur *<br>
                        <input type="email" name="afs_email" required value="<?php echo esc_attr($values['afs_email'] ?? ''); ?>" placeholder="fss@fss.fo">
                    </label></p>

                    <p><label>Kontonummar (valfrítt)<br>
                        <input type="text" name="afs_account" value="<?php echo esc_attr($values['afs_account'] ?? ''); ?>" placeholder="1234 1234567 ella 1234 0001234567" pattern="[0-9]{4} ([0-9]{7}|000[0-9]{7})">
                    </label></p>

                    <p class="afs-consent"><label>
                        <input type="checkbox" name="afs_consent" value="1" <?php checked(!empty($values['afs_consent'])); ?> required>
                        Eg góðtaki, at hesar upplýsingarnar verða nýttar til at útreiða og avgreiða afturgjaldið.
                    </label></p>
                </fieldset>

                <fieldset class="afs-lines">
                    <legend>Afturgjaldslinjur</legend>

                    <div class="afs-lines__list" id="afs-lines-list">
                        <?php
                        $raw_lines = (!empty($values['afs_lines']) && is_array($values['afs_lines']))
                            ? array_values($values['afs_lines'])
                            : [];
                        if (empty($raw_lines)) {
                            echo self::render_line(0, []);
                        } else {
                            foreach ($raw_lines as $i => $line) {
                                if (!is_array($line)) { $line = []; }
                                echo self::render_line($i, $line);
                            }
                        }
                        ?>
                    </div>

                    <p class="afs-add-row">
                        <button type="button" class="afs-add-line" id="afs-add-line">+ Legg afturgjaldslinju afturat</button>
                    </p>
                </fieldset>

                <div class="afs-total-wrap">
                    <label>Útroknað afturgjald íalt<br>
                        <input type="text" id="afs-total" readonly>
                    </label>
                    <p><small>Hetta er ein fyribils útrokning. Upphæddin verður endaliga uppgjørd av bókhaldinum.</small></p>
                </div>

                <p class="afs-hp" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
                    <label>Ikki fyll hetta út<br>
                        <input type="text" name="afs_hp" tabindex="-1" autocomplete="off">
                    </label>
                </p>

                <p class="afs-submit">
                    <button type="submit">Send inn</button>
                </p>
            </form>

            <template id="afs-line-template"><?php echo self::render_line('__INDEX__', []); ?></template>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single reimbursement line.
     *
     * @param int|string $index  Line index, or "__INDEX__" placeholder for the JS template.
     * @param array      $values Previously-submitted values for this line.
     * @return string
     */
    public static function render_line($index, array $values = []) {
        $types    = AFS_Types::all();
        $selected = isset($values['type']) ? (string) $values['type'] : 'driving';
        $name_idx = (string) $index;

        $defaults = [
            'date'        => $values['date']        ?? date('Y-m-d'),
            'description' => $values['description'] ?? '',
            'occasion'    => $values['occasion']    ?? '',
            'note'        => $values['note']        ?? '',
        ];

        ob_start();
        ?>
        <div class="afs-line" data-index="<?php echo esc_attr($name_idx); ?>">
            <div class="afs-line__header">
                <strong class="afs-line__title">Linja <span class="afs-line__num"><?php echo is_numeric($index) ? ((int) $index + 1) : '#'; ?></span></strong>
                <button type="button" class="afs-remove-line" aria-label="Strika linju" title="Strika linju">×</button>
            </div>

            <p><label>Slag *<br>
                <select name="afs_lines[<?php echo esc_attr($name_idx); ?>][type]" class="afs-line__type">
                    <?php foreach ($types as $t): ?>
                        <option value="<?php echo esc_attr($t->id()); ?>" <?php selected($selected === $t->id()); ?>><?php echo esc_html($t->label()); ?></option>
                    <?php endforeach; ?>
                </select>
            </label></p>

            <p><label>Dagur *<br>
                <input type="date" name="afs_lines[<?php echo esc_attr($name_idx); ?>][date]" value="<?php echo esc_attr($defaults['date']); ?>" required>
            </label></p>

            <p><label>Lýsing *<br>
                <input type="text" name="afs_lines[<?php echo esc_attr($name_idx); ?>][description]" value="<?php echo esc_attr($defaults['description']); ?>" placeholder="t.d. Tórshavn → Klaksvík / innkeyp av bandi" required>
            </label></p>

            <p><label>Høvi / endamál<br>
                <input type="text" name="afs_lines[<?php echo esc_attr($name_idx); ?>][occasion]" value="<?php echo esc_attr($defaults['occasion']); ?>">
            </label></p>

            <div class="afs-line__type-fields">
                <?php foreach ($types as $t): ?>
                    <div class="afs-line__type-section" data-type="<?php echo esc_attr($t->id()); ?>" <?php echo $t->id() === $selected ? '' : 'style="display:none;"'; ?>>
                        <?php echo $t->render_form_fields($name_idx, $t->id() === $selected ? $values : []); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <p><label>Viðmerking (valfrítt)<br>
                <textarea name="afs_lines[<?php echo esc_attr($name_idx); ?>][note]" rows="2"><?php echo esc_textarea($defaults['note']); ?></textarea>
            </label></p>
        </div>
        <?php
        return ob_get_clean();
    }
}
