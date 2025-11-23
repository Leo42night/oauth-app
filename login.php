<?php
session_start();
require_once 'config.php';

// Generate and store state parameter for CSRF protection
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

// Build Google OAuth URL
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'state' => $_SESSION['oauth_state'],
    'access_type' => 'online',
    'prompt' => 'select_account'
];

$authUrl = GOOGLE_AUTH_URL . '?' . http_build_query($params);

// Redirect to Google OAuth
header('Location: ' . $authUrl);
exit;
?>