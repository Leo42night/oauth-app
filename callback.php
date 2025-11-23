<?php
session_start();
require_once 'config.php';
require_once 'database.php';

// Verify state parameter to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('Invalid state parameter');
}

// Check for authorization code
if (!isset($_GET['code'])) {
    die('No authorization code received');
}

$code = $_GET['code'];

// Exchange authorization code for access token
$tokenData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init(GOOGLE_TOKEN_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$tokenResponse = json_decode($response, true);

if (!isset($tokenResponse['access_token'])) {
    die('Failed to obtain access token');
}

$accessToken = $tokenResponse['access_token'];

// Get user info from Google
$ch = curl_init(GOOGLE_USER_INFO_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userInfoResponse = curl_exec($ch);
curl_close($ch);

$userInfo = json_decode($userInfoResponse, true);

if (!isset($userInfo['id'])) {
    die('Failed to obtain user information');
}

// Save user to database
$db = new Database();
$userId = $db->saveUser(
    $userInfo['id'],
    $userInfo['email'],
    $userInfo['name'],
    $userInfo['picture'] ?? null
);

if ($userId) {
    // Set session
    $_SESSION['user_id'] = $userId;
    $_SESSION['google_id'] = $userInfo['id'];
    
    // Redirect to home page
    header('Location: index.php');
    exit;
} else {
    die('Failed to save user to database');
}
?>