<?php
require_once __DIR__ . '/../admin/config.php';
customerLogout();
header('Location: login.php');
exit;
