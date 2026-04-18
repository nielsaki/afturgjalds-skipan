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
    if (class_exists('AFS_Store') && AFS_Store::is_available()) {
        global $wpdb;
        $wpdb->query('DELETE FROM ' . AFS_Store::table());
    }
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

    $tests[] = ['Driving with km_claim=on counts km + tunnels', function () {
        $post = afs_base_post(['afs_lines' => [
            [
                'type'        => 'driving',
                'date'        => '2026-04-10',
                'description' => 'Tórshavn → Klaksvík',
                'km_claim'    => '1',
                'km'          => '45,2',
                'tunnels'     => ['Norðoyatunnilin' => '2'],
            ],
        ]]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success'], 'Expected success; errors: ' . implode('; ', $res['errors']));
        // 45.2 * 1.60 + (10 * 2) = 72.32 + 20 = 92.32
        afs_assert_close(92.32, $res['total'], 0.01, 'Unexpected total: ' . $res['total']);
        afs_assert_str_contains($res['email_body'], 'Kilometragjald');
    }];

    $tests[] = ['Driving without km_claim, tunnels only, succeeds and ignores km', function () {
        $post = afs_base_post(['afs_lines' => [[
            'type'        => 'driving',
            'date'        => '2026-04-10',
            'description' => 'Tórshavn → Klaksvík t/r',
            // no km_claim checkbox posted
            'km'          => '30',  // should be ignored
            'tunnels'     => ['Eysturoyartunnilin (Eysturoy-Streymoy)' => '2'],
        ]]]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success'], 'Expected success; errors: ' . implode('; ', $res['errors']));
        // Only tunnels: 2 * 75 = 150
        afs_assert_close(150.0, $res['total'], 0.01);
        afs_assert_str_contains($res['email_body'], 'ei krav');
    }];

    $tests[] = ['Driving with km_claim=on but km=0 fails', function () {
        $post = afs_base_post(['afs_lines' => [[
            'type'        => 'driving',
            'date'        => '2026-04-10',
            'description' => 'x',
            'km_claim'    => '1',
            'km'          => '0',
        ]]]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success'], 'Expected failure');
        afs_assert_contains($res['errors'], 'Kilometratal');
    }];

    $tests[] = ['Missing name fails', function () {
        $post = afs_base_post([
            'afs_name'  => '',
            'afs_lines' => [[
                'type' => 'driving', 'date' => '2026-04-10',
                'description' => 'x', 'km' => '10',
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
                'description' => 'x', 'km' => '10',
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
                'description' => 'x', 'km' => '10',
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
            'amount'      => '',
        ]]]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'Upphædd');
    }];

    $tests[] = ['Mixed lines compute total correctly', function () {
        $post = afs_base_post(['afs_lines' => [
            ['type' => 'driving', 'date' => '2026-04-10', 'description' => 'x', 'km_claim' => '1', 'km' => '10'],
            ['type' => 'expense', 'date' => '2026-04-11', 'description' => 'x', 'amount' => '250'],
        ]]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success'], 'Expected success; errors: ' . implode('; ', $res['errors']));
        // 10*1.60 + 250 = 16 + 250 = 266
        afs_assert_close(266.0, $res['total'], 0.01);
        afs_assert_eq(2, count($res['lines']));
    }];

    $tests[] = ['Dry-run mode logs but does not call wp_mail', function () {
        afs_reset_state();
        $post = afs_base_post(['afs_lines' => [[
            'type' => 'driving', 'date' => '2026-04-10',
            'description' => 'x',
            'km_claim' => '1', 'km' => '10',
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
            'description' => 'x',
            'km_claim' => '1', 'km' => '5',
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
                'description' => 'x', 'km' => '10',
            ]],
        ]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success']);
        afs_assert_eq(0, count(AFS_Logger::$test_queue), 'No mail should be logged for honeypot');
    }];

    $tests[] = ['Unknown type rejected', function () {
        $post = afs_base_post(['afs_lines' => [[
            'type' => 'flying-carpet', 'date' => '2026-04-10',
            'description' => 'x',
        ]]]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'ókent');
    }];

    $tests[] = ['Empty type shows "Vel slag" error', function () {
        $post = afs_base_post(['afs_lines' => [[
            'type' => '', 'date' => '2026-04-10',
            'description' => 'x',
        ]]]);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'Vel slag');
    }];

    $tests[] = ['Invalid nonce rejected', function () {
        $post = afs_base_post(['afs_nonce' => 'WRONG']);
        $res = AFS_Submission::process($post, []);
        afs_assert(!$res['success']);
        afs_assert_contains($res['errors'], 'Trygd');
    }];

    $tests[] = ['Submission is persisted to AFS_Store with correct fields', function () {
        afs_reset_state();
        $post = afs_base_post(['afs_account' => '6460 0005461304', 'afs_lines' => [
            [
                'type' => 'driving', 'date' => '2026-04-10',
                'description' => 'Tórshavn → Klaksvík',
                'km_claim' => '1', 'km' => '45,2',
                'tunnels' => ['Norðoyatunnilin' => '2'],
            ],
            ['type' => 'expense', 'date' => '2026-04-11',
             'description' => 'Reikningur',
             'amount' => '250'],
        ]]);
        $res = AFS_Submission::process($post, []);
        afs_assert($res['success'], 'Submission should succeed');
        afs_assert(!empty($res['stored_id']), 'Expected a stored_id on result');

        $row = AFS_Store::get($res['stored_id']);
        afs_assert($row !== null, 'Stored row should be retrievable');
        afs_assert_eq('Jane Doe',           $row['name']);
        afs_assert_eq('jane@example.com',   $row['email']);
        afs_assert_eq('6460 0005461304',    $row['account']);
        afs_assert_eq(2,                    (int) $row['line_count']);
        afs_assert_close(342.32, (float) $row['total_amount'], 0.01);
        afs_assert_eq('mottikin',           $row['status']);
        afs_assert_eq(1,                    (int) $row['sent_ok']);
        $lines = json_decode($row['lines_json'], true);
        afs_assert(is_array($lines) && count($lines) === 2, 'Expected 2 lines in stored JSON');
    }];

    $tests[] = ['AFS_Store records sent_ok based on sent flag', function () {
        afs_reset_state();
        $ok   = AFS_Store::save([
            'name'=>'A','email'=>'a@b.c','lines'=>[],'total'=>0,
            'subject'=>'s','email_body'=>'b','sent'=>true, 'line_count'=>0,
        ]);
        $fail = AFS_Store::save([
            'name'=>'B','email'=>'b@b.c','lines'=>[],'total'=>0,
            'subject'=>'s','email_body'=>'b','sent'=>false,'line_count'=>0,
        ]);
        afs_assert($ok   > 0, 'Expected id for successful save');
        afs_assert($fail > 0, 'Expected id for failed-send save');
        afs_assert_eq(1, (int) AFS_Store::get($ok)['sent_ok']);
        afs_assert_eq(0, (int) AFS_Store::get($fail)['sent_ok']);
    }];

    $tests[] = ['Purge older than removes old rows only', function () {
        afs_reset_state();
        $new = AFS_Store::save(['name'=>'Recent','email'=>'r@b.c','lines'=>[],'total'=>0,
            'subject'=>'s','email_body'=>'b','sent'=>true,'line_count'=>0]);
        // Manually age this row 400 days into the past.
        global $wpdb;
        $old = AFS_Store::save(['name'=>'Old','email'=>'o@b.c','lines'=>[],'total'=>0,
            'subject'=>'s','email_body'=>'b','sent'=>true,'line_count'=>0]);
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . AFS_Store::table() . " SET created_at = datetime('now', '-400 days') WHERE id = %d",
            $old
        ));

        $purged = AFS_Store::purge_older_than(365);
        afs_assert_eq(1, $purged, 'Expected exactly one row purged');
        afs_assert(AFS_Store::get($old) === null);
        afs_assert(AFS_Store::get($new) !== null);
    }];

    $tests[] = ['Store query filters by status and search', function () {
        afs_reset_state();
        // Seed 3 submissions with different names/statuses.
        $post1 = afs_base_post(['afs_name' => 'Anna', 'afs_lines' => [[
            'type' => 'expense', 'date' => '2026-01-01',
            'description' => 'a', 'amount' => '10']]]);
        $post2 = afs_base_post(['afs_name' => 'Bjarki', 'afs_lines' => [[
            'type' => 'expense', 'date' => '2026-01-02',
            'description' => 'b', 'amount' => '20']]]);
        $post3 = afs_base_post(['afs_name' => 'Katrin', 'afs_lines' => [[
            'type' => 'expense', 'date' => '2026-01-03',
            'description' => 'c', 'amount' => '30']]]);
        $r1 = AFS_Submission::process($post1, []);
        $r2 = AFS_Submission::process($post2, []);
        $r3 = AFS_Submission::process($post3, []);
        afs_assert($r1['success'] && $r2['success'] && $r3['success']);

        AFS_Store::set_status($r2['stored_id'], 'utgoldin');

        $all = AFS_Store::query(['per_page' => 100]);
        afs_assert_eq(3, $all['total']);

        $paid = AFS_Store::query(['status' => 'utgoldin']);
        afs_assert_eq(1, $paid['total']);
        afs_assert_eq('Bjarki', $paid['items'][0]['name']);

        $search = AFS_Store::query(['search' => 'kat']);
        afs_assert_eq(1, $search['total']);
        afs_assert_eq('Katrin', $search['items'][0]['name']);

        $counts = AFS_Store::status_counts();
        afs_assert_eq(3, (int) ($counts['all'] ?? 0));
        afs_assert_eq(2, (int) ($counts['mottikin'] ?? 0));
        afs_assert_eq(1, (int) ($counts['utgoldin'] ?? 0));
    }];

    $tests[] = ['Store deletes single and bulk', function () {
        afs_reset_state();
        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $r = AFS_Submission::process(afs_base_post(['afs_lines' => [[
                'type' => 'expense', 'date' => '2026-01-0' . $i,
                'description' => 'x', 'amount' => '10',
            ]]]), []);
            $ids[] = $r['stored_id'];
        }
        afs_assert_eq(3, AFS_Store::query()['total']);

        afs_assert_eq(1, AFS_Store::delete($ids[0]));
        afs_assert_eq(2, AFS_Store::query()['total']);
        afs_assert(AFS_Store::get($ids[0]) === null);

        afs_assert_eq(2, AFS_Store::delete_many([$ids[1], $ids[2]]));
        afs_assert_eq(0, AFS_Store::query()['total']);
    }];

    $tests[] = ['Store status values are whitelisted', function () {
        afs_reset_state();
        $r = AFS_Submission::process(afs_base_post(['afs_lines' => [[
            'type' => 'expense', 'date' => '2026-01-01',
            'description' => 'x', 'amount' => '10']]]), []);
        afs_assert(!empty($r['stored_id']));

        afs_assert_eq(0, AFS_Store::set_status($r['stored_id'], 'pwned'));
        $row = AFS_Store::get($r['stored_id']);
        afs_assert_eq('mottikin', $row['status']);

        afs_assert_eq(1, AFS_Store::set_status($r['stored_id'], 'utgoldin'));
        $row = AFS_Store::get($r['stored_id']);
        afs_assert_eq('utgoldin', $row['status']);
    }];

    $tests[] = ['afs_store_submission filter can disable persistence', function () {
        afs_reset_state();
        $prev = $GLOBALS['AFS_TEST_FILTER_STORE_OFF'] ?? false;
        $GLOBALS['AFS_TEST_FILTER_STORE_OFF'] = true;

        $r = AFS_Submission::process(afs_base_post(['afs_lines' => [[
            'type' => 'expense', 'date' => '2026-01-01',
            'description' => 'x', 'amount' => '10']]]), []);
        afs_assert($r['success']);
        afs_assert_eq(0, (int) ($r['stored_id'] ?? 0), 'Expected no stored_id when filter disabled storage');
        afs_assert_eq(0, AFS_Store::query()['total']);

        $GLOBALS['AFS_TEST_FILTER_STORE_OFF'] = $prev;
    }];

    $tests[] = ['Attachment is noted in email body and log', function () {
        afs_reset_state();
        $fake = tempnam(sys_get_temp_dir(), 'afs_upload_');
        file_put_contents($fake, 'pretend-pdf-bytes');

        $post = afs_base_post(['afs_lines' => [[
            'type' => 'expense', 'date' => '2026-04-10',
            'description' => 'Reikningur',
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
