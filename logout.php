<?php
require_once __DIR__ . '/inc/auth.php';
boot_session();
unset($_SESSION['code_id']);
session_destroy();
header('Location: index.php');
