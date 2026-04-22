<?php
// includes/header.php
// Incluir DESPUÉS de requireAuth() en cada página
$usuario = usuarioActual();
$paginaActual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?? APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-inner">
    <a href="<?= BASE_URL ?>/pages/dashboard.php" class="nav-brand">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="3" width="20" height="14" rx="2"/>
        <path d="M8 21h8M12 17v4"/>
      </svg>
      <span>Viáticos</span>
    </a>

    <div class="nav-links">
      <a href="<?= BASE_URL ?>/pages/dashboard.php"
         class="nav-link <?= $paginaActual === 'dashboard.php' ? 'active' : '' ?>">
        Inicio
      </a>
      <a href="<?= BASE_URL ?>/pages/facturas.php"
         class="nav-link <?= $paginaActual === 'facturas.php' ? 'active' : '' ?>">
        Facturas
      </a>
      <?php if ($usuario['rol_id'] === ROL_ADMIN): ?>
      <a href="<?= BASE_URL ?>/pages/usuarios.php"
         class="nav-link <?= $paginaActual === 'usuarios.php' ? 'active' : '' ?>">
        Usuarios
      </a>
      <a href="<?= BASE_URL ?>/pages/auditoria.php"
         class="nav-link <?= $paginaActual === 'auditoria.php' ? 'active' : '' ?>">
        Auditoría
      </a>
      <?php endif; ?>
    </div>

    <div class="nav-user">
      <div class="nav-user-info">
        <span class="nav-user-name"><?= htmlspecialchars($usuario['nombre']) ?></span>
        <span class="badge-rol badge-rol-<?= strtolower($usuario['rol']) ?>">
          <?= htmlspecialchars(ucfirst($usuario['rol'])) ?>
        </span>
      </div>
      <a href="<?= BASE_URL ?>/pages/logout.php" class="btn-logout" title="Cerrar sesión">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>
  </div>
</nav>

<main class="main-content">
