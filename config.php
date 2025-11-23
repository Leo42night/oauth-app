<?php

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', trim(getenv('GOOGLE_CLIENT_ID')));
define('GOOGLE_CLIENT_SECRET', trim(getenv('GOOGLE_CLIENT_SECRET')));
define('GOOGLE_REDIRECT_URI', trim(getenv('GOOGLE_REDIRECT_URI')));

// Database Configuration for Cloud SQL
define('DB_HOST', trim(getenv('DB_HOST')) ?: '127.0.0.1'); // Use Cloud SQL Proxy locally
define('DB_USER', trim(getenv('DB_USER')));
define('DB_PASS', trim(getenv('DB_PASS')));
define('DB_NAME', trim(getenv('DB_NAME')));

// For Cloud SQL Unix Socket (used in Cloud Run)
define('DB_UNIX_SOCKET', trim(getenv('DB_UNIX_SOCKET')));

// Google OAuth endpoints
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USER_INFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');
?>