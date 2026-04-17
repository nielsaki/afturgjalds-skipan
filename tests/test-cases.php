<?php
/**
 * Lightweight assertion helpers + test cases.
 *
 * @package AfturgjaldSkipan\Tests
 */

function afs_assert($cond, $msg = 'Assertion failed') {
    if (!$cond) {
        throw new Exception($msg);
    }
}

function afs_assert_eq($expected, $actual, $msg = '') {
    if ($expected !== $actual) {
        $msg = $msg ?: 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
        throw new Exception($msg);
    }
}

function afs_assert_close($expected, $actual, $tol = 0.001, $msg = '') {
    if (abs($expected - $actual) > $tol) {
        $msg = $msg ?: 'Expected ~' . $expected . ', got ' . $actual;
        throw new Exception($msg);
    }
}

function afs_assert_contains(array $haystack, $needle, $msg = '') {
    foreach ($haystack as $h) {
        if (is_string($h) && stripos($h, $needle) !== false) { return; }
    }
    throw new Exception($msg ?: 'Expected list to contain "' . $needle . '"; got: ' . implode(' | ', $haystack));
}

function afs_assert_str_contains($haystack, $needle, $msg = '') {
    if (strpos((string) $haystack, (string) $needle) === false) {
        throw new Exception($msg ?: 'Expected string to contain "' . $needle . '"');
    }
}

function afs_reset_state() {
    $GLOBALS['AFS_TEST_MAILS'] = [];
    AFS_Logger::$test_queue = [];
    $path = AFS_Logger::log_path();
    if (file_exists($path)) { @unlink($path); }
}

function afs_base_post(array $overrides = []) {
    $base = [
        'afs_nonce'          => 'TEST_NONCE',
        'afs_form_submitted' => '1',
        'afs_name'           => 'Jane Doe',
        'afs_email'          => 'jane@example.com',
        'afs_account'        => '',
        'afs_consent'        => '1',
        'afs_lines'          => [],
    ];
    return array_replace_recursive($base, $overrides);
}

function afs_tests() {
    $tests = [];

    $tests[] = ['Valid driving submission returns success', function () {
        $post = afs_base_post(['afs_lines' => [
            [
                'type'        => 'driving',
                'date'        => '2026-04-10',
                'description' => 'Tórshavn → Klaksvík',
                'occasion'    => 'Venjing',
                'km'          => '45,2',
                'tunnels'     => ['Norðoyatunnilin' => '2'],
            ],
        ]]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success'], 'Expected success; errors: ' . implode('; ', $res['errors']));
        // 45.2 * 1.60 + (10 * 2) = 72.32 + 20 = 92.32
        afs_assert_close(92.32, $res['total'], 0.01, 'Unexpected total: ' . $res['total']);
    }];

    $tests[] = ['Missing name fails', function () {
        $post = afs_base_post([
            'afs_name'  => '',
            'afs_lines' => [[
                'type' => 'driving', 'date' => '2026-04-10',
                'description' => 'x', 'occasion' => 'x', 'km' => '10',
            ]],
        ]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success'], 'Expected failure');
        afs_assert_contains($res['errors'], 'Navn');
    }];

    $tests[] = ['Consent required', function () {
        $post = afs_base_post([
            'afs_consent' => '',
            'afs_lines'   => [[
                'type' => 'driving', 'date' => '2026-04-10',
                'description' => 'x', 'occasion' => 'x', 'km' => '10',
            ]],
        ]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'vátta');
    }];

    $tests[] = ['Bad account number format fails', function () {
        $post = afs_base_post([
            'afs_account' => '1234-1234567',
            'afs_lines'   => [[
                'type' => 'driving', 'date' => '2026-04-10',
                'description' => 'x', 'occasion' => 'x', 'km' => '10',
            ]],
        ]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'Kontonummar');
    }];

    $tests[] = ['Expense without amount fails', function () {
        $post = afs_base_post(['afs_lines' => [[
            'type'        => 'expense',
            'date'        => '2026-04-10',
            'description' => 'Reikningur',
            'occasion'    => 'Útbúnaður',
            'amount'      => '',
        ]]]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'Upphædd');
    }];

    $tests[] = ['Mixed lines compute total correctly', function () {
        $post = afs_base_post(['afs_lines' => [
            ['type' => 'driving', 'date' => '2026-04-10', 'description' => 'x', 'occasion' => 'x', 'km' => '10'],
            ['type' => 'expense', 'date' => '2026-04-11', 'description' => 'x', 'occasion' => 'x', 'amount' => '250'],
            ['type' => 'other',   'date' => '2026-04-12', 'description' => 'Gáva', 'amount' => '75,50'],
        ]]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success'], 'Expected success; errors: ' . implode('; ', $res['errors']));
        // 10*1.60 + 250 + 75.50 = 16 + 250 + 75.50 = 341.50
        afs_assert_close(341.50, $res['total'], 0.01);
        afs_assert_eq(3, count($res['lines']));
    }];

    $tests[] = ['Dry-run mode logs but does not call wp_mail', function () {
        afs_reset_state();
        $post = afs_base_post(['afs_lines' => [[
            'type' => 'driving', 'date' => '2026-04-10',
            'description' => 'x', 'occasion' => 'x', 'km' => '10',
        ]]]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success']);
        afs_assert_eq(0, count($GLOBALS['AFS_TEST_MAILS']), 'wp_mail should not be called in dry-run mode');
        // Two queued: main + user receipt
        afs_assert_eq(2, count(AFS_Logger::$test_queue), 'Expected 2 logged entries (main + kvittan)');
    }];

    $tests[] = ['Log file includes original recipient and labels', function () {
        afs_reset_state();
        $post = afs_base_post(['afs_lines' => [[
            'type' => 'driving', 'date' => '2026-04-10',
            'description' => 'x', 'occasion' => 'x', 'km' => '5',
        ]]]);
        AFS_Submission::process($post, []);
        $log = file_get_contents(AFS_Logger::log_path());
        afs_assert_str_contains($log, 'bokhald@fss.fo');
        afs_assert_str_contains($log, 'Bókhald');
        afs_assert_str_contains($log, 'Kvittan til avsendara');
        afs_assert_str_contains($log, 'jane@example.com');
    }];

    $tests[] = ['Empty line list fails', function () {
        $post = afs_base_post(['afs_lines' => []]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'linju');
    }];

    $tests[] = ['Honeypot silently "succeeds" without sending', function () {
        afs_reset_state();
        $post = afs_base_post([
            'afs_hp' => 'spam',
            'afs_lines' => [[
                'type' => 'driving', 'date' => '2026-04-10',
                'description' => 'x', 'occasion' => 'x', 'km' => '10',
            ]],
        ]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success']);
        afs_assert_eq(0, count(AFS_Logger::$test_queue), 'No mail should be logged for honeypot');
    }];

    $tests[] = ['Unknown type rejected', function () {
        $post = afs_base_post(['afs_lines' => [[
            'type' => 'flying-carpet', 'date' => '2026-04-10',
            'description' => 'x', 'occasion' => 'x',
        ]]]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'ókent');
    }];

    $tests[] = ['Invalid nonce rejected', function () {
        $post = afs_base_post(['afs_nonce' => 'WRONG']);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'Trygd');
    }];

    $tests[] = ['Attachment is noted in email body and log', function () {
        afs_reset_state();
        $fake = tempnam(sys_get_temp_dir(), 'afs_upload_');
        file_put_contents($fake, 'pretend-pdf-bytes');

        $post = afs_base_post(['afs_lines' => [[
            'type' => 'expense', 'date' => '2026-04-10',
            'description' => 'Reikningur', 'occasion' => 'Útbúnaður',
            'amount' => '250',
        ]]]);
        $files = ['afs_files_0' => [
            'name'     => 'reikningur.pdf',
            'tmp_name' => $fake,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($fake),
            'type'     => 'application/pdf',
        ]];
        $res = AFS_Submission::process($post, $files);
        afs_assert($res['success'], 'Expected success; errors: ' . implode('; ', $res['errors']));
        afs_assert_str_contains($res['email_body'], 'reikningur.pdf');

        $logged = AFS_Logger::$test_queue[0] ?? null;
        afs_assert($logged !== null, 'Expected a log entry');
        afs_assert(!empty($logged['attachments']), 'Expected attachment in log');
        afs_assert_eq('reikningur.pdf', $logged['attachments'][0]['name']);

        // Temp file should have been cleaned up
        afs_assert(!file_exists($fake) || !file_exists($logged['attachments'][0]['path']), 'Upload should be cleaned up');
    }];

    return $tests;
}
