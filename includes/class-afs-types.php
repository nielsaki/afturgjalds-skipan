<?php
/**
 * Registry of reimbursement types.
 *
 * Add custom types via the `afs_types` filter, e.g.:
 *
 *     add_filter('afs_types', function ($types) {
 *         $types['per_diem'] = new My_Per_Diem_Type();
 *         return $types;
 *     });
 *
 * @package AfturgjaldSkipan
 */

if (!defined('ABSPATH')) { exit; }

class AFS_Types {

    /**
     * @var AFS_Type[]|null
     */
    private static $types = null;

    /**
     * Reset the cache (used by the test suite).
     */
    public static function reset() {
        self::$types = null;
    }

    /**
     * @return AFS_Type[]
     */
    public static function all() {
        if (self::$types === null) {
            $types = [
                'driving' => new AFS_Type_Driving(),
                'expense' => new AFS_Type_Expense(),
            ];
            self::$types = function_exists('apply_filters')
                ? apply_filters('afs_types', $types)
                : $types;
        }
        return self::$types;
    }

    /**
     * @param string $id
     * @return AFS_Type|null
     */
    public static function get($id) {
        $all = self::all();
        return isset($all[$id]) ? $all[$id] : null;
    }
}
