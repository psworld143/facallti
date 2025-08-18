<?php
session_start();

// Destroy the session
session_destroy();

// Redirect to main index page
header('Location: ../index.php');
exit();
?>