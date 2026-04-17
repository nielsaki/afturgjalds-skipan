<?php
/**
 * Standalone CLI test runner. Usage: `php tests/run-tests.php`
 *
 * @package AfturgjaldSkipan\Tests
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/test-cases.php';

$tests    = afs_tests();
$pass     = 0;
$fail     = 0;
$failures = [];

fwrite(STDOUT, "Afturgjald skipan — test suite\n");
fwrite(STDOUT, str_repeat('-', 60) . "\n");

foreach ($tests as $t) {
    list($name, $fn) = $t;

    afs_reset_state();
    AFS_Types::reset();

    try {
        call_user_func($fn);
        fwrite(STDOUT, "  PASS  {$name}\n");
        $pass++;
    } catch (Throwable $e) {
        fwrite(STDOUT, "  FAIL  {$name}\n");
        fwrite(STDOUT, "        " . $e->getMessage() . "\n");
        $failures[] = [$name, $e->getMessage()];
        $fail++;
    }
}

fwrite(STDOUT, str_repeat('-', 60) . "\n");
fwrite(STDOUT, "{$pass} passed, {$fail} failed.\n");

if ($fail > 0) {
    fwrite(STDOUT, "\nLog location: " . AFS_Logger::log_path() . "\n");
    fwrite(STDOUT, "(Inspect it to see what the plugin would have emailed.)\n");
}

exit($fail > 0 ? 1 : 0);
