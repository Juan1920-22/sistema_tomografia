<?php
session_start();
session_unset();
session_destroy();
header("Location: login.php"); // Redirige al login
exit;
?>