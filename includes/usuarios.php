<?php
// includes/usuarios.php

require_once __DIR__ . '/../config/database.php';

function getUsuarios(): array {
    return getDB()->query(
        "SELECT u.id, u.nombre, u.email, u.rol_id, r.nombre AS rol_nombre,
                u.telegram_user_id, u.activo, u.created_at
         FROM usuarios u JOIN roles r ON r.id = u.rol_id
         ORDER BY u.nombre"
    )->fetchAll();
}

function getUsuarioById(int $id): ?array {
    $stmt = getDB()->prepare(
        "SELECT u.*, r.nombre AS rol_nombre
         FROM usuarios u JOIN roles r ON r.id = u.rol_id
         WHERE u.id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function crearUsuario(array $d): array {
    $db = getDB();
    if (empty($d['nombre']) || empty($d['email']) || empty($d['password'])) {
        return ['ok' => false, 'msg' => 'Nombre, email y contraseña son requeridos.'];
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'Email inválido.'];
    }
    if (strlen($d['password']) < 8) {
        return ['ok' => false, 'msg' => 'La contraseña debe tener al menos 8 caracteres.'];
    }
    // Email único
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$d['email']]);
    if ($stmt->fetch()) return ['ok' => false, 'msg' => 'El email ya está registrado.'];

    $hash = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare(
        "INSERT INTO usuarios (nombre, email, password_hash, rol_id, telegram_user_id, activo, created_by)
         VALUES (?, ?, ?, ?, ?, 1, ?)"
    )->execute([
        trim($d['nombre']),
        strtolower(trim($d['email'])),
        $hash,
        (int)$d['rol_id'],
        !empty($d['telegram_user_id']) ? trim($d['telegram_user_id']) : null,
        $_SESSION['usuario_id'] ?? null,
    ]);
    return ['ok' => true, 'msg' => 'Usuario creado correctamente.'];
}

function actualizarUsuario(int $id, array $d): array {
    $db = getDB();
    if (empty($d['nombre']) || empty($d['email'])) {
        return ['ok' => false, 'msg' => 'Nombre y email son requeridos.'];
    }
    // Email único (excluir el propio)
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$d['email'], $id]);
    if ($stmt->fetch()) return ['ok' => false, 'msg' => 'El email ya está en uso.'];

    $sql    = "UPDATE usuarios SET nombre=?, email=?, rol_id=?, telegram_user_id=?, activo=?";
    $params = [
        trim($d['nombre']),
        strtolower(trim($d['email'])),
        (int)$d['rol_id'],
        !empty($d['telegram_user_id']) ? trim($d['telegram_user_id']) : null,
        isset($d['activo']) ? 1 : 0,
    ];
    if (!empty($d['password'])) {
        if (strlen($d['password']) < 8) return ['ok' => false, 'msg' => 'Contraseña mínimo 8 caracteres.'];
        $sql .= ", password_hash=?";
        $params[] = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }
    $sql .= " WHERE id=?";
    $params[] = $id;
    $db->prepare($sql)->execute($params);
    return ['ok' => true, 'msg' => 'Usuario actualizado.'];
}

function eliminarUsuario(int $id): array {
    if ($id === 1) return ['ok' => false, 'msg' => 'No se puede eliminar el admin principal.'];
    getDB()->prepare("UPDATE usuarios SET activo=0 WHERE id=?")->execute([$id]);
    return ['ok' => true, 'msg' => 'Usuario desactivado.'];
}

function getRoles(): array {
    return getDB()->query("SELECT * FROM roles ORDER BY id")->fetchAll();
}
