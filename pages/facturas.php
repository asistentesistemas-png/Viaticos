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
    exportarExcel([
        'fecha_desde'      => $_GET['fecha_desde']      ?? '',
        'fecha_hasta'      => $_GET['fecha_hasta']      ?? '',
        'proveedor'        => $_GET['proveedor']        ?? '',
        'numero_factura'   => $_GET['numero_factura']   ?? '',
        'nit_proveedor'    => $_GET['nit_proveedor']    ?? '',
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

$vendedores = [];
if ($usuario['rol_id'] !== ROL_VENDEDOR) {
    $vendedores = getVendedores();
}

// ── Flash message — DEBE ir después de requireAuth() ─────
$msg     = $_SESSION['flash_msg']  ?? '';
$msgType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

function paginaUrl(int $p, array $f): string {
    $params = array_filter(array_merge($f, ['p' => $p]));
    return '?' . http_build_query($params);
}

// ── Obtener datos de la vista ─────────────────────────────
$db          = getDB();
$idsFacturas = array_column($filas, 'id');
$vistaData   = [];
if (!empty($idsFacturas)) {
    $placeholders = implode(',', array_fill(0, count($idsFacturas), '?'));
    $stmtVista = $db->prepare(
        "SELECT v.*, f.id AS factura_id
         FROM vista_facturas_viaticos v
         JOIN facturas_ocr f ON f.serie_factura  = v.serie_factura
                            AND f.numero_factura = v.numero_factura
                            AND f.nit_proveedor  = v.nit_proveedor
                            AND f.fecha          = v.fecha
         WHERE f.id IN ($placeholders)"
    );
    $stmtVista->execute($idsFacturas);
    foreach ($stmtVista->fetchAll(PDO::FETCH_ASSOC) as $vRow) {
        $vistaData[$vRow['factura_id']] = $vRow;
    }
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
<div class="alert alert-<?= $msgType ?>" data-autodismiss><?= htmlspecialchars($msg) ?></div>
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
      <button class="btn btn-primary btn-sm" onclick="openModal('modal-nueva-factura')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva Factura
      </button>
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
          <th>Fecha</th>
          <th>Departamento</th>
          <th>Municipio</th>
          <th>Serie</th>
          <th>N° Factura</th>
          <th>NIT Proveedor</th>
          <th>Proveedor</th>
          <th>Combustible</th>
          <th>Alimentación</th>
          <th>Hospedaje</th>
          <th>Otros</th>
          <th>Descripción Otros</th>
          <th>Forma de Pago</th>
          <th>Estado</th>
          <?php if ($usuario['rol_id'] !== ROL_VENDEDOR): ?><th>Vendedor</th><?php endif; ?>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($filas)): ?>
        <tr>
          <td colspan="16">
            <div class="empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
              <p>No se encontraron facturas con los filtros aplicados.</p>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($filas as $f): ?>
        <?php $v = $vistaData[$f['id']] ?? []; ?>
        <tr>
          <td><?= htmlspecialchars($f['fecha'] ?? '—') ?></td>
          <td><?= htmlspecialchars($f['departamento'] ?? '—') ?></td>
          <td><?= htmlspecialchars($f['municipio'] ?? '—') ?></td>
          <td class="td-mono"><?= htmlspecialchars($f['serie_factura'] ?? '—') ?></td>
          <td class="td-mono"><?= htmlspecialchars($f['numero_factura'] ?? '—') ?></td>
          <td class="td-mono"><?= htmlspecialchars($f['nit_proveedor'] ?? '—') ?></td>
          <td class="td-truncate" title="<?= htmlspecialchars($f['proveedor'] ?? '') ?>"><?= htmlspecialchars($f['proveedor'] ?? '—') ?></td>
          <td class="td-mono text-right">Q <?= number_format($v['combustible']  ?? 0, 2) ?></td>
          <td class="td-mono text-right">Q <?= number_format($v['alimentacion'] ?? 0, 2) ?></td>
          <td class="td-mono text-right">Q <?= number_format($v['hospedaje']    ?? 0, 2) ?></td>
          <td class="td-mono text-right">Q <?= number_format($v['otros']        ?? 0, 2) ?></td>
          <td><?= htmlspecialchars($v['descripcion_otros'] ?? '—') ?></td>
          <td><?= htmlspecialchars($v['forma_pago'] ?? '—') ?></td>
          <td>
            <?php if (!empty($f['veces_exportada']) && $f['veces_exportada'] > 0): ?>
              <span class="badge badge-green"
                    title="Exportado <?= $f['veces_exportada'] ?> vez. Última: <?= $f['ultima_exportacion'] ?>">
                ✓ Exportado
              </span>
            <?php else: ?>
              <span class="badge badge-gray">Pendiente</span>
            <?php endif; ?>
          </td>
          <?php if ($usuario['rol_id'] !== ROL_VENDEDOR): ?>
          <td><?= htmlspecialchars($f['vendedor_nombre'] ?? $f['telegram_user_id'] ?? '—') ?></td>
          <?php endif; ?>
          <td>
            <div style="display:flex;gap:.35rem">
              <a href="<?= BASE_URL ?>/pages/editar_factura.php?id=<?= $f['id'] ?>"
                 class="btn btn-secondary btn-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4z"/></svg>
                Editar
              </a>
              <?php if (!empty($f['url_google_drive'])): ?>
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

<!-- ── Modal Nueva Factura ──────────────────────────── -->
<div class="modal-overlay" id="modal-nueva-factura">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <h2>Nueva Factura</h2>
      <button class="modal-close" onclick="closeModal('modal-nueva-factura')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-nueva-factura" method="POST" action="<?= BASE_URL ?>/pages/nueva_factura.php">
      <div class="modal-body">
        <div class="edit-grid">
          <div class="form-group">
            <label>Fecha *</label>
            <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label>N° Factura</label>
            <input type="text" name="numero_factura" placeholder="Ej. 00123">
          </div>
          <div class="form-group">
            <label>Serie</label>
            <input type="text" name="serie_factura" placeholder="Ej. A">
          </div>
          <div class="form-group">
            <label>NIT Proveedor</label>
            <input type="text" name="nit_proveedor" placeholder="NIT">
          </div>
          <div class="form-group span-2">
            <label>Proveedor *</label>
            <input type="text" name="proveedor" required placeholder="Nombre del proveedor">
          </div>
          <div class="form-group">
            <label>Total *</label>
            <input type="number" name="total" id="nueva-total" step="0.01"
                   placeholder="0.00" oninput="calcDesdeTotal()" required>
          </div>
          <div class="form-group">
            <label>Subtotal (auto)</label>
            <input type="number" name="subtotal" id="nueva-subtotal" step="0.01"
                   placeholder="0.00" style="background:var(--bg-alt)" readonly>
          </div>
          <div class="form-group">
            <label>IVA 12% (auto)</label>
            <input type="number" name="iva" id="nueva-iva" step="0.01"
                   placeholder="0.00" style="background:var(--bg-alt)" readonly>
          </div>
          <div class="form-group">
            <label>Moneda</label>
            <select name="moneda">
              <option value="GTQ">GTQ</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </div>
          <div class="form-group">
            <label>Departamento</label>
            <select name="departamento" id="nueva-depto" onchange="actualizarMunicipiosNueva(this.value)">
              <option value="">-- Seleccionar --</option>
              <?php foreach (array_keys(DEPARTAMENTOS_MUNICIPIOS) as $d): ?>
              <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Municipio</label>
            <select name="municipio" id="nueva-municipio">
              <option value="">-- Seleccionar departamento --</option>
            </select>
          </div>
          <div class="form-group">
            <label>Tipo de Gasto</label>
            <select name="texto_manuscrito">
              <option value="">-- Seleccionar --</option>
              <option value="comb">Combustible</option>
              <option value="alim">Alimentación</option>
              <option value="hosp">Hospedaje</option>
              <option value="">Otros</option>
            </select>
          </div>
          <div class="form-group">
            <label>Descripción Otros</label>
            <select name="descripcion_otros">
              <option value="">-- Seleccionar --</option>
              <option value="Peajes o Ferri">Peajes o Ferri</option>
              <option value="Parqueos">Parqueos</option>
              <option value="Mant. Vehículos">Mant. Vehículos</option>
              <option value="Recargos TC">Recargos TC</option>
              <option value="Otros">Otros</option>
              <option value="Atención a Clientes">Atención a Clientes</option>
            </select>
          </div>
          <div class="form-group">
            <label>Forma de Pago</label>
            <select name="forma_pago">
              <option value="Tarjeta de Crédito">Tarjeta de Crédito</option>
              <option value="Efectivo">Efectivo</option>
              <option value="Transferencia Bancaria">Transferencia Bancaria</option>
              <option value="Tarjeta de Débito">Tarjeta de Débito</option>
            </select>
          </div>
          <div class="form-group span-2">
            <label>Descripción / Items</label>
            <textarea name="items_texto" rows="3" placeholder="Descripción de los items..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-nueva-factura')">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar Factura</button>
      </div>
    </form>
  </div>
</div>

<script>
const deptosMunicipios = <?= json_encode(DEPARTAMENTOS_MUNICIPIOS) ?>;

function calcDesdeTotal() {
    const total    = parseFloat(document.getElementById('nueva-total').value) || 0;
    const iva      = Math.round((total * 12 / 112) * 100) / 100;
    const subtotal = Math.round((total - iva) * 100) / 100;
    document.getElementById('nueva-subtotal').value = subtotal.toFixed(2);
    document.getElementById('nueva-iva').value      = iva.toFixed(2);
}

function actualizarMunicipiosNueva(depto) {
    const sel = document.getElementById('nueva-municipio');
    sel.innerHTML = '<option value="">-- Seleccionar --</option>';
    if (!depto || !deptosMunicipios[depto]) return;
    deptosMunicipios[depto].forEach(m => {
        const opt       = document.createElement('option');
        opt.value       = m;
        opt.textContent = m;
        sel.appendChild(opt);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>