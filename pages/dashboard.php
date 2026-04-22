<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/facturas.php';

requireAuth();
$usuario  = usuarioActual();
$pageTitle = 'Dashboard — ' . APP_NAME;
$db = getDB();

// ── Estadísticas según rol ───────────────────────────────
if ($usuario['rol_id'] === ROL_VENDEDOR) {
    $telegramId = $usuario['telegram_user_id'];
    $stmt = $db->prepare("SELECT COUNT(*) AS total, SUM(total) AS suma_total,
        SUM(CASE WHEN MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS este_mes
        FROM facturas_ocr WHERE telegram_user_id = ?");
    $stmt->execute([$telegramId]);
} else {
    $stmt = $db->query("SELECT COUNT(*) AS total, SUM(total) AS suma_total,
        SUM(CASE WHEN MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS este_mes
        FROM facturas_ocr");
}
$stats = $stmt->fetch();

// Últimas 5 facturas
$filtros   = [];
$recientes = getFacturas($filtros, 1, 6);

// Conteo usuarios (solo admin)
$totalUsuarios = 0;
if ($usuario['rol_id'] === ROL_ADMIN) {
    $totalUsuarios = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE activo=1")->fetchColumn();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Bienvenido, <?= htmlspecialchars($usuario['nombre']) ?></h1>
  <p>Panel principal del sistema de facturas de viáticos</p>
</div>

<!-- Estadísticas -->
<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-label">Total facturas</div>
    <div class="stat-value"><?= number_format($stats['total']) ?></div>
    <div class="stat-sub"><?= $usuario['rol_id'] === ROL_VENDEDOR ? 'Tus facturas' : 'En el sistema' ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Monto total</div>
    <div class="stat-value" style="font-size:1.15rem">Q <?= number_format($stats['suma_total'] ?? 0, 2) ?></div>
    <div class="stat-sub">Suma de todos los totales</div>
  </div>
  <div class="stat-card amber">
    <div class="stat-label">Este mes</div>
    <div class="stat-value"><?= number_format($stats['este_mes']) ?></div>
    <div class="stat-sub">Facturas del mes actual</div>
  </div>
  <?php if ($usuario['rol_id'] === ROL_ADMIN): ?>
  <div class="stat-card">
    <div class="stat-label">Usuarios activos</div>
    <div class="stat-value"><?= $totalUsuarios ?></div>
    <div class="stat-sub"><a href="<?= BASE_URL ?>/pages/usuarios.php" class="link">Administrar</a></div>
  </div>
  <?php else: ?>
  <div class="stat-card">
    <div class="stat-label">Tu rol</div>
    <div class="stat-value" style="font-size:1rem;text-transform:capitalize"><?= htmlspecialchars($usuario['rol']) ?></div>
    <div class="stat-sub"><?= htmlspecialchars($usuario['email']) ?></div>
  </div>
  <?php endif; ?>
</div>

<!-- Accesos rápidos -->
<div style="display:flex;gap:.75rem;margin-bottom:1.75rem;flex-wrap:wrap">
  <a href="<?= BASE_URL ?>/pages/facturas.php" class="btn btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
    Ver facturas
  </a>
  <?php if ($usuario['rol_id'] !== ROL_VENDEDOR): ?>
  <a href="<?= BASE_URL ?>/pages/facturas.php?exportar=1" class="btn btn-secondary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Exportar Excel
  </a>
  <?php endif; ?>
  <?php if ($usuario['rol_id'] === ROL_ADMIN): ?>
  <a href="<?= BASE_URL ?>/pages/usuarios.php?nuevo=1" class="btn btn-secondary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
    Nuevo usuario
  </a>
  <?php endif; ?>
</div>

<!-- Facturas recientes -->
<div class="table-wrapper">
  <div class="table-toolbar">
    <span class="table-title">Facturas recientes</span>
    <a href="<?= BASE_URL ?>/pages/facturas.php" class="btn btn-secondary btn-sm">Ver todas →</a>
  </div>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>N° Factura</th>
          <th>Fecha</th>
          <th>Proveedor</th>
          <th>NIT</th>
          <th>Total</th>
          <?php if ($usuario['rol_id'] !== ROL_VENDEDOR): ?><th>Vendedor</th><?php endif; ?>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recientes['data'])): ?>
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">Sin facturas registradas.</td></tr>
        <?php else: ?>
        <?php foreach ($recientes['data'] as $f): ?>
        <tr>
          <td class="td-mono"><?= htmlspecialchars($f['numero_factura'] ?? '—') ?></td>
          <td><?= htmlspecialchars($f['fecha'] ?? '—') ?></td>
          <td class="td-truncate"><?= htmlspecialchars($f['proveedor'] ?? '—') ?></td>
          <td class="td-mono"><?= htmlspecialchars($f['nit_proveedor'] ?? '—') ?></td>
          <td class="td-mono"><strong>Q <?= number_format($f['total'] ?? 0, 2) ?></strong></td>
          <?php if ($usuario['rol_id'] !== ROL_VENDEDOR): ?>
          <td><?= htmlspecialchars($f['vendedor_nombre'] ?? $f['telegram_user_id'] ?? '—') ?></td>
          <?php endif; ?>
          <td>
            <a href="<?= BASE_URL ?>/pages/editar_factura.php?id=<?= $f['id'] ?>" class="btn btn-secondary btn-sm">Editar</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
