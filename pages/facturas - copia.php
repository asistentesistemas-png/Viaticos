<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/facturas.php';
require_once __DIR__ . '/../includes/exportar.php';

requireAuth();
$usuario   = usuarioActual();
$pageTitle = 'Facturas — ' . APP_NAME;

// ── Exportar ─────────────────────────────────────────────
if (isset($_GET['exportar'])) {
    requireRol([ROL_ADMIN, ROL_VENDEDOR, ROL_CONTABILIDAD]);
    exportarExcel([
        'fecha_desde'      => $_GET['fecha_desde']      ?? '',
        'fecha_hasta'      => $_GET['fecha_hasta']      ?? '',
        'proveedor'        => $_GET['proveedor']        ?? '',
        'numero_factura'   => $_GET['numero_factura']   ?? '',
        'telegram_user_id' => $_GET['telegram_user_id'] ?? '',
    ]);
}

// ── Filtros ──────────────────────────────────────────────
$filtros = [
    'fecha_desde'      => $_GET['fecha_desde']      ?? '',
    'fecha_hasta'      => $_GET['fecha_hasta']      ?? '',
    'proveedor'        => $_GET['proveedor']        ?? '',
    'numero_factura'   => $_GET['numero_factura']   ?? '',
    'nit_proveedor'    => $_GET['nit_proveedor']    ?? '',
    'telegram_user_id' => $_GET['telegram_user_id'] ?? '',
];
$pagina    = max(1, (int)($_GET['p'] ?? 1));
$resultado = getFacturas($filtros, $pagina, 25);
$filas     = $resultado['data'];
$total     = $resultado['total'];
$totalPags = $resultado['total_paginas'];

// Vendedores para filtro (Admin/Contabilidad)
$vendedores = [];
if ($usuario['rol_id'] !== ROL_VENDEDOR) {
    $vendedores = getVendedores();
}

// Mensaje flash
$msg     = $_SESSION['flash_msg']  ?? '';
$msgType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// URL base para paginación con filtros
function paginaUrl(int $p, array $f): string {
    $params = array_filter(array_merge($f, ['p' => $p]));
    return '?' . http_build_query($params);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Facturas de Viáticos</h1>
  <p>
    <?php if ($usuario['rol_id'] === ROL_VENDEDOR): ?>
      Tus facturas registradas por OCR. Edita cualquier error de captura.
    <?php else: ?>
      Todas las facturas del sistema.
    <?php endif; ?>
  </p>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>" data-autodismiss>
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- ── Filtros ─────────────────────────────────────────── -->
<div class="filter-bar">
  <form method="GET" action="">
    <div class="filter-grid">
      <div class="filter-group">
        <label>Desde</label>
        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtros['fecha_desde']) ?>">
      </div>
      <div class="filter-group">
        <label>Hasta</label>
        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtros['fecha_hasta']) ?>">
      </div>
      <div class="filter-group">
        <label>Proveedor</label>
        <input type="text" name="proveedor" placeholder="Nombre proveedor…" value="<?= htmlspecialchars($filtros['proveedor']) ?>">
      </div>
      <div class="filter-group">
        <label>N° Factura</label>
        <input type="text" name="numero_factura" placeholder="Ej. A-00123" value="<?= htmlspecialchars($filtros['numero_factura']) ?>">
      </div>
      <div class="filter-group">
        <label>NIT Proveedor</label>
        <input type="text" name="nit_proveedor" placeholder="NIT…" value="<?= htmlspecialchars($filtros['nit_proveedor']) ?>">
      </div>
      <?php if ($usuario['rol_id'] !== ROL_VENDEDOR): ?>
      <div class="filter-group">
        <label>Vendedor</label>
        <select name="telegram_user_id">
          <option value="">Todos los vendedores</option>
          <?php foreach ($vendedores as $v): ?>
          <option value="<?= htmlspecialchars($v['telegram_user_id']) ?>"
            <?= $filtros['telegram_user_id'] === $v['telegram_user_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($v['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="filter-group" style="flex-direction:row;align-items:flex-end;gap:.5rem">
        <button type="submit" class="btn btn-primary">Buscar</button>
        <a href="<?= BASE_URL ?>/pages/facturas.php" class="btn btn-secondary">Limpiar</a>
      </div>
    </div>
  </form>
</div>

<!-- ── Tabla ───────────────────────────────────────────── -->
<div class="table-wrapper">
  <div class="table-toolbar">
    <span class="table-title"><?= number_format($total) ?> factura<?= $total !== 1 ? 's' : '' ?> encontrada<?= $total !== 1 ? 's' : '' ?></span>
    <div class="table-actions">
      <a href="<?= BASE_URL ?>/pages/facturas.php?<?= http_build_query(array_filter($filtros)) ?>&exportar=1"
         class="btn btn-success btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exportar Excel
      </a>
    </div>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Proveedor</th>
          <th>NIT Prov.</th>
          <th>N° Factura</th>
          <th>Serie</th>
          <th>Subtotal</th>
          <th>IVA</th>
          <th>Total</th>
          <th>Moneda</th>
          <?php if ($usuario['rol_id'] !== ROL_VENDEDOR): ?><th>Vendedor</th><?php endif; ?>
          <th>Cuenta Contable</th>
          <th>Dim. 1</th>
          <th>Dim. 2</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($filas)): ?>
        <tr>
          <td colspan="15">
            <div class="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
              <p>No se encontraron facturas con los filtros aplicados.</p>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($filas as $f): ?>
        <tr>
          <td class="td-mono" style="color:var(--text-muted)"><?= $f['id'] ?></td>
          <td><?= htmlspecialchars($f['fecha'] ?? '—') ?></td>
          <td class="td-truncate" title="<?= htmlspecialchars($f['proveedor'] ?? '') ?>"><?= htmlspecialchars($f['proveedor'] ?? '—') ?></td>
          <td class="td-mono"><?= htmlspecialchars($f['nit_proveedor'] ?? '—') ?></td>
          <td class="td-mono"><?= htmlspecialchars($f['numero_factura'] ?? '—') ?></td>
          <td class="td-mono"><?= htmlspecialchars($f['serie_factura'] ?? '—') ?></td>
          <td class="td-mono text-right">Q <?= number_format($f['subtotal'] ?? 0, 2) ?></td>
          <td class="td-mono text-right">Q <?= number_format($f['iva'] ?? 0, 2) ?></td>
          <td class="td-mono text-right"><strong>Q <?= number_format($f['total'] ?? 0, 2) ?></strong></td>
          <td><span class="badge badge-gray"><?= htmlspecialchars($f['moneda'] ?? 'GTQ') ?></span></td>
          <?php if ($usuario['rol_id'] !== ROL_VENDEDOR): ?>
          <td><?= htmlspecialchars($f['vendedor_nombre'] ?? $f['telegram_user_id'] ?? '—') ?></td>
          <?php endif; ?>
          <td class="td-truncate" title="<?= htmlspecialchars($f['cuenta_contable'] ?? '') ?>"><?= htmlspecialchars($f['cuenta_contable'] ?? '—') ?></td>
          <td><?= htmlspecialchars($f['dimension_1'] ?? '—') ?></td>
          <td><?= htmlspecialchars($f['dimension_2'] ?? '—') ?></td>
          <td>
            <div style="display:flex;gap:.35rem">
              <a href="<?= BASE_URL ?>/pages/editar_factura.php?id=<?= $f['id'] ?>"
                 class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4z"/></svg>
                Editar
              </a>
              <?php if ($f['url_google_drive']): ?>
              <a href="<?= htmlspecialchars($f['url_google_drive']) ?>" target="_blank"
                 class="btn btn-secondary btn-sm" title="Ver en Drive">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <?php if ($totalPags > 1): ?>
  <div class="pagination">
    <span>Página <?= $pagina ?> de <?= $totalPags ?> — <?= number_format($total) ?> registros</span>
    <div class="page-nums">
      <a href="<?= paginaUrl($pagina-1, $filtros) ?>"
         class="page-btn <?= $pagina <= 1 ? 'disabled' : '' ?>">‹</a>
      <?php
        $inicio = max(1, $pagina - 2);
        $fin    = min($totalPags, $pagina + 2);
        for ($i = $inicio; $i <= $fin; $i++):
      ?>
      <a href="<?= paginaUrl($i, $filtros) ?>"
         class="page-btn <?= $i === $pagina ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a href="<?= paginaUrl($pagina+1, $filtros) ?>"
         class="page-btn <?= $pagina >= $totalPags ? 'disabled' : '' ?>">›</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
