<?php
session_start();

// Destroy all session data
session_destroy();

// Redirect to main index page
header('Location: ../index.php');
exit();
?>