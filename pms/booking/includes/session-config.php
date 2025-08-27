<?php
// Session configuration - must be included before session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Allow cross-origin requests
ini_set('session.cookie_path', '/'); // Ensure cookies are available for all paths
ini_set('session.cookie_domain', ''); // Use current domain
?>
