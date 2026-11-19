<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . (usuario_actual() ? 'dashboard.php' : 'login.php'));
exit;
