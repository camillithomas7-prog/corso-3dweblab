<?php
require_once dirname(__DIR__).'/inc/auth.php';
boot_session(); unset($_SESSION['admin_id']); session_destroy();
header('Location: index.php');
