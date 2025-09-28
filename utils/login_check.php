<?php
require_once __DIR__ . '/../../wp-load.php';
header('Content-Type: text/plain');

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    echo 'false';
    exit;
}

// Tenta localizar o usuário pelo login ou email
if (is_email($username)) {
    $user = get_user_by('email', $username);
} else {
    $user = get_user_by('login', $username);
}

// Se usuário não encontrado, retorna false
if (!$user) {
    echo 'false';
    exit;
}

// Verifica a senha usando o hash do WordPress
if (wp_check_password($password, $user->user_pass, $user->ID)) {
    echo 'true';
} else {
    echo 'false';
}
