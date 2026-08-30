<?php
require_once __DIR__ . '/includes/auth.php';
logoutUser();
header('Location: login.php');
exit;
