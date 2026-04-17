<?php
/**
 * Abstract base class for reimbursement types.
 *
 * Types are responsible for validating their own fields, rendering their form
 * fragment, computing their amount and formatting their email fragment.
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

abstract class AFS_Type {

    abstract public function id();

    abstract public function label();

    /**
     * Validate raw input for a single line.
     *
     * @param array $raw Raw POST data for the line (already trimmed/sanitized at top-level).
     * @return array ['normalized' => array, 'errors' => string[]]
     */
    abstract public function validate(array $raw);

    /**
     * Computed amount in kroner for the given normalized line.
     *
     * @param array $normalized
     * @return float
     */
    abstract public function amount(array $normalized);

    /**
     * Text fragment for this line, used in the email body.
     *
     * @param array $normalized
     * @return string
     */
    abstract public function format_for_email(array $normalized);

    /**
     * HTML fragment for this type's type-specific inputs.
     *
     * @param int|string $index  Line index (may be the string "__INDEX__" for JS template).
     * @param array      $values Previously-submitted values for this line.
     * @return string
     */
    abstract public function render_form_fields($index, array $values = []);

    protected function parse_float($raw) {
        if (is_array($raw)) { return 0.0; }
        $norm = str_replace(',', '.', trim((string) $raw));
        return is_numeric($norm) ? (float) $norm : 0.0;
    }

    protected function format_kr($amount) {
        return number_format((float) $amount, 2, ',', ' ') . ' kr';
    }
}
