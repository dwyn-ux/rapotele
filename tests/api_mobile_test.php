<?php

declare(strict_types=1);

define('TEST_SUITE_RUNNING', true);

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

// 5. Test Session fallback in mobile_api_authenticated_user
unset($_SERVER['HTTP_AUTHORIZATION']);
$_SESSION['user_id'] = (int)$user['id'];
$sessionAuthUser = mobile_api_authenticated_user();
assert($sessionAuthUser !== null && (int)$sessionAuthUser['id'] === (int)$user['id'], 'Session fallback authentication failed');
echo "✔ Session fallback auth passed.\n";

// 6. Test Web Push VAPID keys and encryption
require_once dirname(__DIR__) . '/app/Services/webpush.php';
$vapidPublic = webpush_vapid_public_key();
assert(!empty($vapidPublic), 'VAPID public key should be auto-generated and non-empty');
assert(webpush_is_configured() === true, 'Webpush should be configured');

// Test payload encryption with a valid client EC key
$cnf = webpush_openssl_config_path();
$clientKeyArgs = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
if ($cnf) $clientKeyArgs['config'] = $cnf;
$clientKey = openssl_pkey_new($clientKeyArgs);
$clientDetails = openssl_pkey_get_details($clientKey);
$cx = str_pad((string)$clientDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$cy = str_pad((string)$clientDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
$validP256dh = webpush_base64url_encode("\x04" . $cx . $cy);
$validAuth   = webpush_base64url_encode(random_bytes(16));

$encrypted   = webpush_encrypt_payload('{"title":"Test"}', $validP256dh, $validAuth);
assert(!empty($encrypted['body']), 'Encrypted body must not be empty');
assert(!empty($encrypted['salt']) && strlen($encrypted['salt']) === 16, 'Salt must be 16 bytes');
echo "✔ VAPID key auto-generation and payload encryption passed.\n";

// 7. Test VAPID public key API dispatch
$_GET['action'] = 'vapid_public_key';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
mobile_api_handle_vapid_public_key();
$output = ob_get_clean();
$decoded = json_decode((string)$output, true);
assert(is_array($decoded) && ($decoded['ok'] ?? false) === true, 'VAPID public key API endpoint should return ok: true');
assert(!empty($decoded['vapid_public_key']), 'VAPID endpoint should return non-empty vapid_public_key');
echo "✔ VAPID endpoint JSON dispatch passed.\n";

echo "All Mobile API tests passed successfully!\n";

