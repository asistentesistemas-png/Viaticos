<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/facturas.php';

requireAuth();
$usuario = usuarioActual();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/pages/facturas.php'); exit; }

$factura = getFacturaById($id);

if (!$factura) {
    $_SESSION['flash_msg']  = 'Factura no encontrada o sin permisos de acceso.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/pages/facturas.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(actualizarFactura($id, $_POST));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = actualizarFactura($id, $_POST);
    $_SESSION['flash_msg']  = $resultado['msg'];
    $_SESSION['flash_type'] = $resultado['ok'] ? 'success' : 'error';
    header('Location: ' . BASE_URL . '/pages/editar_factura.php?id=' . $id);
    exit;
}

registrarAuditoria($id, 'VER');

$pageTitle = 'Editar Factura #' . $id . ' — ' . APP_NAME;
$msg     = $_SESSION['flash_msg']  ?? '';
$msgType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Variables panel lateral
$p_id       = htmlspecialchars((string)($factura['id']                  ?? '—'));
$p_uuid     = htmlspecialchars((string)($factura['uuid']                ?? '—'));
$p_creado   = htmlspecialchars((string)($factura['created_at']          ?? '—'));
$p_proc     = htmlspecialchars((string)($factura['fecha_procesamiento'] ?? '—'));
$p_telegram = htmlspecialchars((string)($factura['telegram_user_id']    ?? '—'));
$p_vendedor = htmlspecialchars((string)($factura['vendedor_nombre']     ?? '—'));
$p_drive    = (string)($factura['url_google_drive'] ?? '');



function campoForm(string $campo, $valor, string $label, string $tipo = 'text', bool $span2 = false): string {
    $fid = 'f_' . $campo;
    $val = htmlspecialchars((string)($valor ?? ''));
    $cls = $span2 ? ' span-2' : '';

    if ($tipo === 'textarea') {
        $inp = "<textarea id=\"$fid\" name=\"$campo\" rows=\"3\">$val</textarea>";
    } elseif ($tipo === 'decimal') {
        $inp = "<input type=\"number\" id=\"$fid\" name=\"$campo\" step=\"0.01\" value=\"$val\">";
    } elseif ($tipo === 'date') {
        $inp = "<input type=\"date\" id=\"$fid\" name=\"$campo\" value=\"$val\">";
    } elseif ($tipo === 'depto') {
        $deptos = array_keys(DEPARTAMENTOS_MUNICIPIOS);
        $opts   = "<option value=''>-- Seleccionar --</option>";
        foreach ($deptos as $d) {
            $sel   = ($val === htmlspecialchars($d)) ? 'selected' : '';
            $opts .= "<option value=\"" . htmlspecialchars($d) . "\" $sel>" . htmlspecialchars($d) . "</option>";
        }
        $inp = "<select id=\"$fid\" name=\"$campo\" onchange=\"actualizarMunicipios(this.value)\">$opts</select>";
    } elseif ($tipo === 'munic') {
        $inp = "<select id=\"f_municipio\" name=\"$campo\">
                  <option value=''>-- Seleccionar departamento primero --</option>
                </select>";
                } elseif ($tipo === 'select_pago') {
    $opciones = [
        'Tarjeta de Crédito',
        'Efectivo',
        'Transferencia Bancaria',
        'Tarjeta de Débito',
    ];
    $opts = "<option value=''>-- Seleccionar --</option>";
    foreach ($opciones as $op) {
        $sel   = ($val === $op) ? 'selected' : '';
        $opts .= "<option value=\"" . htmlspecialchars($op) . "\" $sel>" . htmlspecialchars($op) . "</option>";
    }
    $inp = "<select id=\"$fid\" name=\"$campo\">$opts</select>";
                } elseif ($tipo === 'select_otros') {
    $opciones = [
        '',
        'Peajes o Ferri',
        'Parqueos',
        'Mant. Vehículos',
        'Recargos TC',
        'Otros',
        'Atención a Clientes',
    ];
    $opts = '';
    foreach ($opciones as $op) {
        $sel   = ($val === $op) ? 'selected' : '';
        $opts .= "<option value=\"" . htmlspecialchars($op) . "\" $sel>" . ($op ?: '-- Seleccionar --') . "</option>";
    }
    $inp = "<select id=\"$fid\" name=\"$campo\">$opts</select>";


    } else {
        $inp = "<input type=\"text\" id=\"$fid\" name=\"$campo\" value=\"$val\">";
    }
    return "<div class=\"form-group{$cls}\"><label for=\"$fid\">$label</label>$inp</div>";
}

// ── ESTA LÍNEA ES LA QUE FALTABA ─────────────────────────
include __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem;font-size:.85rem;color:var(--text-muted)">
  <a href="<?= BASE_URL ?>/pages/facturas.php" class="link">Facturas</a>
  <span>›</span>
  <span>Editar #<?= $p_id ?></span>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>" data-autodismiss><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">

  <!-- ── PANEL PRINCIPAL ──────────────────────────────── -->
  <div>
    <div class="card">
      <div class="card-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
        </svg>
        Datos de la Factura
      </div>

      <form method="POST" id="form-factura">
        <input type="hidden" name="ajax" value="1">

        <div class="edit-grid">
          <?php //<?= campoForm('uuid',                $factura['uuid'],                'UUID',             'text', true) ?>
          <?= campoForm('fecha',               $factura['fecha'],               'Fecha',            'date') ?>
          <?= campoForm('serie_factura',       $factura['serie_factura'],       'Serie') ?>
          <?= campoForm('numero_factura',      $factura['numero_factura'],      'N° Factura') ?>
          <?php //<?= campoForm('tipo_documento',      $factura['tipo_documento'],      'Tipo Documento') ?>
          <?php //<?= campoForm('numero_autorizacion', $factura['numero_autorizacion'], 'N° Autorización') ?>
          <?php //<?= campoForm('moneda',              $factura['moneda'],              'Moneda') ?>
          <?= campoForm('departamento',       $factura['departamento'] ?? '', 'Departamento', 'depto') ?>
          <?= campoForm('municipio',          $factura['municipio']    ?? '', 'Municipio',    'munic') ?>
        </div>

        <hr class="divider">
        <p style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:.9rem">Proveedor</p>
        <div class="edit-grid">
          <?= campoForm('nit_proveedor',      $factura['nit_proveedor'],      'NIT Proveedor') ?>
          <?= campoForm('proveedor',          $factura['proveedor'],          'Nombre Proveedor',   'text', true) ?>
          <?php //<?= campoForm('regimen_isr',        $factura['regimen_isr'],        'Régimen ISR') ?>
          <?php //<?= campoForm('tipo_contribuyente', $factura['tipo_contribuyente'], 'Tipo Contribuyente', 'text', true) ?>
        </div>

        <hr class="divider">
        <p style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:.9rem">Cliente</p>
        <div class="edit-grid">
          <?= campoForm('nit_cliente',    $factura['nit_cliente'],    'NIT Cliente') ?>
          <?= campoForm('nombre_cliente', $factura['nombre_cliente'], 'Nombre Cliente') ?>
        </div>

        <hr class="divider">
        <p style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:.9rem">Montos</p>
        <div class="edit-grid">
          <?php //<?= campoForm('subtotal', $factura['subtotal'], 'Subtotal', 'decimal') ?>
          <?php //<?= campoForm('iva',      $factura['iva'],      'IVA',      'decimal') ?>
          <div class="form-group">
            <label for="f_total">Total (auto)</label>
            <input type="number" id="f_total" name="total" step="0.01"
                   value="<?= htmlspecialchars((string)($factura['total'] ?? '')) ?>"
                   style="background:var(--bg-alt)">
            <span class="field-hint">Se recalcula al cambiar subtotal + IVA</span>
          </div>
        </div>

        <hr class="divider">
        <p style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:.9rem">Contabilidad</p>
        <div class="edit-grid">
          <?php //<?= campoForm('cuenta_contable',    $factura['cuenta_contable'],    'Cuenta Contable') ?>
          <?php // <?= campoForm('descripcion_cuenta', $factura['descripcion_cuenta'], 'Descripción Cuenta') ?>
          <?php // <?= campoForm('dimension_1',        $factura['dimension_1'],        'Dimensión 1') ?>
          <?php // <?= campoForm('dimension_2',        $factura['dimension_2'],        'Dimensión 2') ?>
          <?php //<?= campoForm('dimension_3',        $factura['dimension_3'],        'Dimensión 3') ?>
          <?php //<?= campoForm('nombre_responsable', $factura['nombre_responsable'], 'Responsable') ?>
          <?= campoForm('forma_pago', $factura['forma_pago'] ?? 'Tarjeta de Crédito', 'Forma de Pago', 'select_pago') ?>
          
        </div>

        <hr class="divider">
        <p style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:.9rem"></p>
        <div class="edit-grid">
         <?php // <?= campoForm('items_texto',      $factura['items_texto'],      'Descripción', 'textarea', true) ?>
          <?php //<?= campoForm('texto_manuscrito', $factura['texto_manuscrito'], 'Texto Manuscrito',    'textarea', true) ?>
          <?= campoForm('descripcion_otros', $factura['descripcion_otros'] ?? '', 'Descripción Otros', 'select_otros', true) ?>
          <?= campoForm('observaciones', $factura['observaciones'] ?? '', 'Descripción / Observaciones', 'textarea', true) ?>
           
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.5rem;justify-content:flex-end">
          <a href="<?= BASE_URL ?>/pages/facturas.php" class="btn btn-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary" data-label="Guardar cambios">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
              <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
              <polyline points="17 21 17 13 7 13 7 21"/>
              <polyline points="7 3 7 8 15 8"/>
            </svg>
            Guardar cambios
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── PANEL LATERAL ────────────────────────────────── -->
  <div style="display:flex;flex-direction:column;gap:1rem">
<div style="display:flex;flex-direction:column">
  <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #e2e8f0">
    <span style="color:#64748b;font-size:.82rem">ID</span>
    <span style="color:#0f172a;font-size:.82rem;font-family:'IBM Plex Mono',monospace"><?= $p_id ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #e2e8f0">
    <span style="color:#64748b;font-size:.82rem">UUID</span>
    <span style="color:#0f172a;font-size:.70rem;font-family:'IBM Plex Mono',monospace;word-break:break-all;text-align:right;max-width:60%"><?= $p_uuid ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #e2e8f0">
    <span style="color:#64748b;font-size:.82rem">Creado</span>
    <span style="color:#0f172a;font-size:.82rem"><?= $p_creado ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #e2e8f0">
    <span style="color:#64748b;font-size:.82rem">Procesado</span>
    <span style="color:#0f172a;font-size:.82rem"><?= $p_proc ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #e2e8f0">
    <span style="color:#64748b;font-size:.82rem">Telegram ID</span>
    <span style="color:#0f172a;font-size:.82rem;font-family:'IBM Plex Mono',monospace"><?= $p_telegram ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;padding:7px 0">
    <span style="color:#64748b;font-size:.82rem">Vendedor</span>
    <span style="color:#0f172a;font-size:.82rem"><?= $p_vendedor ?></span>
  </div>
    </div>

    <!-- Link Drive -->
    <?php if (!empty($p_drive)): ?>
    <div class="card">
      <div class="card-title" style="font-size:.85rem">Documento original</div>
      <a href="<?= htmlspecialchars($p_drive) ?>" target="_blank"
         class="btn btn-secondary" style="width:100%;justify-content:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
          <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
          <polyline points="15 3 21 3 21 9"/>
          <line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
        Ver en Google Drive
      </a>
    </div>
    <?php endif; ?>

    <!-- Historial de cambios -->
    <?php
    $stmtH = getDB()->prepare(
        "SELECT a.created_at, a.campo_editado, a.valor_anterior, a.valor_nuevo, u.nombre AS usuario_nombre
         FROM auditoria_facturas a
         JOIN usuarios u ON u.id = a.usuario_id
         WHERE a.factura_id = ? AND a.accion = 'EDITAR'
         ORDER BY a.created_at DESC LIMIT 10"
    );
    $stmtH->execute([$id]);
    $cambios = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <?php if (!empty($cambios)): ?>
    <div class="card">
      <div class="card-title" style="font-size:.85rem">Últimos cambios</div>
      <div style="display:flex;flex-direction:column;gap:.6rem">
        <?php foreach ($cambios as $c): ?>
        <div style="font-size:.78rem;border-left:2px solid var(--blue-mid);padding-left:.6rem">
          <div style="color:var(--text-muted)"><?= htmlspecialchars((string)($c['created_at'] ?? '')) ?></div>
          <div style="font-weight:500"><?= htmlspecialchars((string)($c['usuario_nombre'] ?? '')) ?></div>
          <div>
            <strong><?= htmlspecialchars((string)($c['campo_editado'] ?? '')) ?></strong>:
            <span style="color:var(--red);text-decoration:line-through"><?= htmlspecialchars(substr((string)($c['valor_anterior'] ?? '(vacío)'), 0, 30)) ?></span>
            → <span style="color:var(--green)"><?= htmlspecialchars(substr((string)($c['valor_nuevo'] ?? '(vacío)'), 0, 30)) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
// ── Departamentos y municipios ───────────────────────────
const deptosMunicipios = <?= json_encode(DEPARTAMENTOS_MUNICIPIOS) ?>;
const municipioActual  = <?= json_encode($factura['municipio'] ?? '') ?>;

function actualizarMunicipios(depto, seleccionado = '') {
    const sel = document.getElementById('f_municipio');
    sel.innerHTML = '<option value="">-- Seleccionar --</option>';
    if (!depto || !deptosMunicipios[depto]) return;
    deptosMunicipios[depto].forEach(m => {
        const opt       = document.createElement('option');
        opt.value       = m;
        opt.textContent = m;
        if (m === seleccionado) opt.selected = true;
        sel.appendChild(opt);
    });
}

// ── Cargar municipios al iniciar si hay depto guardado ───
/*document.addEventListener('DOMContentLoaded', () => {
    const deptoSel = document.getElementById('f_departamento');
    if (deptoSel && deptoSel.value) {
        actualizarMunicipios(deptoSel.value, municipioActual);
    }
});*/

document.addEventListener('DOMContentLoaded', () => {
    const deptoSel = document.getElementById('f_departamento');
    const deptoVal = <?= json_encode($factura['departamento'] ?? '') ?>;
    const municVal = <?= json_encode($factura['municipio'] ?? '') ?>;

    if (deptoSel && deptoVal) {
        // Seleccionar el departamento guardado
        deptoSel.value = deptoVal;
        // Cargar municipios de ese departamento
        actualizarMunicipios(deptoVal, municVal);
    }
});

// ── Guardar formulario via AJAX ──────────────────────────
document.getElementById('form-factura').addEventListener('submit', async e => {
    e.preventDefault();
    await submitForm(e.target, () => {
        setTimeout(() => location.reload(), 1500);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>