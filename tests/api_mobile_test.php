<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/Api/mobile.php';

echo "Running Mobile API Unit & Integration Tests...\n";

// 1. Test token generation & verification
$user = fetch_one('SELECT * FROM users WHERE active = 1 ORDER BY id LIMIT 1');
if (!$user) {
    throw new RuntimeException('No active user found in test DB.');
}

$token = mobile_api_generate_token((int)$user['id']);
assert(!empty($token), 'Token should not be empty');
assert(str_contains($token, '.'), 'Token must contain signature delimiter');

$verifiedUser = mobile_api_verify_token($token);
assert($verifiedUser !== null, 'Token verification failed');
assert((int)$verifiedUser['id'] === (int)$user['id'], 'Verified user ID mismatch');
echo "✔ Token generation and HMAC verification passed.\n";

// 2. Test invalid / expired token
$tamperedToken = $token . 'bad';
assert(mobile_api_verify_token($tamperedToken) === null, 'Tampered token should fail verification');
echo "✔ Tampered token rejection passed.\n";

// 3. Test School profile helper
$school = mobile_api_school_profile();
assert(!empty($school['name']), 'School name should not be empty');
assert(isset($school['academic_year'], $school['semester']), 'School academic info present');
echo "✔ School profile helper passed.\n";

// 4. Test Notification generation for user
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
$authTestUser = mobile_api_authenticated_user();
assert($authTestUser !== null, 'Authenticated user via Bearer header failed');
echo "✔ Bearer token extraction passed.\n";

echo "All Mobile API tests passed successfully!\n";

