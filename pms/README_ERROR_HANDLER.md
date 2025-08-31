# PMS Unified Error Handler

## Overview
The PMS (Property Management System) folder now has a unified error handling system that redirects all errors to the main `404.php` and `505.php` error pages in the root directory. **The error handler only redirects when actual errors occur, not on normal page loads.**

## Files Created

### 1. `pms/error_handler.php`
The main error handler file that contains all error handling functions and logic.

### 2. `pms/includes/error_handler.php`
A simple include file that loads the main error handler. This is what gets included in all PMS PHP files.

## How It Works

### Error Types and Redirects
- **404 Errors**: Redirects to `/seait/404.php`
  - Page not found
  - Invalid URLs
  - Session validation failures (when explicitly called)
  - Input validation errors (when explicitly called)
  - CSRF token failures (when explicitly called)

- **505 Errors**: Redirects to `/seait/505.php`
  - Server errors (fatal errors only)
  - Database connection failures (when explicitly checked)
  - Fatal PHP errors
  - File inclusion errors (when explicitly checked)
  - Maintenance mode (when explicitly checked)

### Important Notes
- **No Automatic Redirects**: The error handler does NOT automatically redirect on page load
- **Manual Validation**: Functions like `pmsValidateSession()` must be called explicitly to trigger redirects
- **Fatal Errors Only**: Only fatal PHP errors trigger automatic redirects
- **Warnings/Notices**: Non-fatal errors are logged but don't cause redirects

### Functions Available

#### Core Error Handling
- `pmsErrorHandler()` - Handles PHP errors (fatal errors only)
- `pmsExceptionHandler()` - Handles uncaught exceptions
- `pmsHandle404Error()` - Manually trigger 404 redirect
- `pmsHandle505Error()` - Manually trigger 505 redirect

#### Database Functions
- `pmsCheckDatabaseConnection($conn)` - Check database connection (redirects on failure)
- `pmsCheckDatabaseQuery($result, $query_name)` - Check query results (redirects on failure)
- `pmsCheckDatabaseStatement($stmt, $query_name)` - Check statement execution (redirects on failure)

#### Validation Functions (Manual - Only redirect when called)
- `pmsValidateSession()` - Check if user is logged in (redirects to 404 if not)
- `pmsValidateRole($required_role)` - Check user role (redirects to 404 if invalid)
- `pmsSanitizeAndValidate($input, $type)` - Sanitize and validate input (redirects to 404 if invalid)
- `pmsValidateCSRFToken($token)` - Validate CSRF tokens (redirects to 404 if invalid)

#### Utility Functions (No Redirects)
- `pmsIsLoggedIn()` - Check login status
- `pmsGetCurrentUserId()` - Get current user ID
- `pmsGetCurrentUserRole()` - Get current user role
- `pmsHasPermission($permission)` - Check user permissions
- `pmsIsAjaxRequest()` - Check if request is AJAX
- `pmsHandleAjaxError($message, $error_code)` - Handle AJAX errors

#### Logging Functions (No Redirects)
- `pmsLogUserActivity($action, $details)` - Log user activities
- `pmsLogErrorWithContext($error_message, $context)` - Log errors with context

#### Security Functions (No Redirects)
- `pmsGenerateCSRFToken()` - Generate CSRF tokens
- `pmsCheckRateLimit($action, $max_attempts, $time_window)` - Rate limiting

## Usage

### Including the Error Handler
All PHP files in the PMS folder should include the error handler at the top:

```php
<?php
session_start();
require_once '../includes/error_handler.php'; // Adjust path as needed
// ... rest of your code
```

### Example Usage

#### Normal Page Load (No Redirects)
```php
<?php
session_start();
require_once '../includes/error_handler.php';

// These functions work normally without causing redirects
$is_logged_in = pmsIsLoggedIn();
$user_id = pmsGetCurrentUserId();
$token = pmsGenerateCSRFToken();

// Page continues to load normally
echo "Welcome to the dashboard!";
?>
```

#### Manual Validation (Redirects Only When Called)
```php
<?php
session_start();
require_once '../includes/error_handler.php';

// Only redirects if user is not logged in
pmsValidateSession();

// Only redirects if role doesn't match
pmsValidateRole('admin');

// Only redirects if email is invalid
$email = pmsSanitizeAndValidate($_POST['email'], 'email');
if (!$email) {
    pmsHandle404Error(); // Manual redirect
}

// Check database connection (only redirects on failure)
pmsCheckDatabaseConnection($conn);
?>
```

#### Error Handling (Automatic Redirects)
```php
<?php
session_start();
require_once '../includes/error_handler.php';

// This will automatically redirect to 505.php if there's a fatal error
$result = someFunctionThatMightFail();

// This will automatically redirect to 505.php if there's an exception
try {
    $data = riskyOperation();
} catch (Exception $e) {
    // Exception handler will redirect to 505.php
}
?>
```

## Error Logging

All errors are logged to the PHP error log with the prefix "PMS Error" for easy identification. The error handler logs:

- Error type and message
- File and line number
- User ID (if logged in)
- IP address
- User agent
- Request URI
- Additional context

## Maintenance Mode

The error handler includes maintenance mode functionality (manual check):

```php
// Check if maintenance mode is enabled (only redirects if enabled and user is not admin)
if (pmsIsMaintenanceMode() && !pmsIsAdmin()) {
    pmsHandle505Error(); // Manual redirect to 505 page
}
```

## Rate Limiting

The error handler includes rate limiting functionality (no redirects):

```php
// Check rate limit for login attempts
if (!pmsCheckRateLimit('login', 5, 300)) {
    pmsHandleAjaxError("Too many login attempts", 429);
}
```

## Security Features

- **CSRF Protection**: Automatic CSRF token generation and validation
- **Input Sanitization**: Built-in input sanitization and validation
- **Session Validation**: Manual session checks (redirects when called)
- **Role-based Access**: Role validation functions (redirects when called)
- **Rate Limiting**: Built-in rate limiting for actions

## Integration with Main Error Pages

The PMS error handler seamlessly integrates with the main error pages:

- **404.php**: Handles page not found and client-side errors
- **505.php**: Handles server errors and technical issues

Both pages provide:
- User-friendly error messages
- Technical details for debugging
- Navigation options
- Contact information
- Search functionality

## Files Updated

The error handler has been automatically integrated into 75 PHP files in the PMS folder, including:

- All main booking pages
- API endpoints
- Module pages
- Front-desk pages
- Training pages
- Management pages

## Testing

To test the error handler:

1. **Normal Operation**: Pages should load normally without redirects
2. **Manual Validation**: Call validation functions to test redirects
3. **Error Simulation**: Trigger actual errors to test automatic redirects
4. **Database Errors**: Test database connection failures
5. **Session Validation**: Test session validation redirects

## Troubleshooting

### If pages are redirecting unexpectedly:
1. Check if validation functions are being called unnecessarily
2. Verify that only fatal errors trigger automatic redirects
3. Ensure maintenance mode is not enabled
4. Check error logs for specific error messages

### If errors are not being handled:
1. Verify the error handler is included correctly
2. Check that fatal errors are occurring
3. Ensure headers haven't been sent before redirects
4. Review error logs for details

## Notes

- The error handler is designed to work with the existing PMS structure
- All redirects go to the main error pages in the root directory
- Error logging includes PMS-specific context
- The handler is backward compatible with existing code
- No changes needed to existing functionality
- **Pages load normally by default - redirects only occur on actual errors**
