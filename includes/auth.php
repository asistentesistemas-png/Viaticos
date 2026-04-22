<?php
// includes/auth.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

function iniciarSesionSegura(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,   // cambiar a true en HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function login(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT u.*, r.nombre AS rol_nombre
         FROM usuarios u
         JOIN roles r ON r.id = u.rol_id
         WHERE u.email = ? AND u.activo = 1
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        return ['ok' => false, 'msg' => 'Credenciales incorrectas.'];
    }

    // Regenerar ID de sesión para prevenir fixation
    session_regenerate_id(true);

    $_SESSION['usuario_id']       = $usuario['id'];
    $_SESSION['usuario_nombre']   = $usuario['nombre'];
    $_SESSION['usuario_email']    = $usuario['email'];
    $_SESSION['usuario_rol_id']   = (int)$usuario['rol_id'];
    $_SESSION['usuario_rol']      = $usuario['rol_nombre'];
    $_SESSION['telegram_user_id'] = $usuario['telegram_user_id'];
    $_SESSION['login_time']       = time();

    return ['ok' => true];
}

function logout(): void {
    iniciarSesionSegura();
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function estaAutenticado(): bool {
    iniciarSesionSegura();
    if (empty($_SESSION['usuario_id'])) return false;
    // Expirar sesión por inactividad
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
        logout();
    }
    return true;
}

function requireAuth(): void {
    if (!estaAutenticado()) {
        header('Location: ' . BASE_URL . '/index.php?expired=1');
        exit;
    }
}

function requireRol(array $roles): void {
    requireAuth();
    if (!in_array($_SESSION['usuario_rol_id'], $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

function esAdmin(): bool        { return estaAutenticado() && $_SESSION['usuario_rol_id'] === ROL_ADMIN; }
function esVendedor(): bool     { return estaAutenticado() && $_SESSION['usuario_rol_id'] === ROL_VENDEDOR; }
function esContabilidad(): bool { return estaAutenticado() && $_SESSION['usuario_rol_id'] === ROL_CONTABILIDAD; }

function usuarioActual(): array {
    return [
        'id'              => $_SESSION['usuario_id']       ?? 0,
        'nombre'          => $_SESSION['usuario_nombre']   ?? '',
        'email'           => $_SESSION['usuario_email']    ?? '',
        'rol_id'          => $_SESSION['usuario_rol_id']   ?? 0,
        'rol'             => $_SESSION['usuario_rol']      ?? '',
        'telegram_user_id'=> $_SESSION['telegram_user_id'] ?? null,
    ];
}
