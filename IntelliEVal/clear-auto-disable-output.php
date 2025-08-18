<?php
session_start();

// Clear the auto-disable output from session
unset($_SESSION['auto_disable_output']);

// Redirect back to evaluations page
header('Location: evaluations.php');
exit();
?>