<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRol([ROL_ADMIN]);
$pageTitle = 'Auditoría — ' . APP_NAME;
$db = getDB();

$pagina    = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 30;
$offset    = ($pagina - 1) * $porPagina;

// Filtros
$usuarioFiltro = (int)($_GET['usuario_id'] ?? 0);
$accionFiltro  = $_GET['accion'] ?? '';
$fechaDesde    = $_GET['fecha_desde'] ?? '';
$fechaHasta    = $_GET['fecha_hasta'] ?? '';

$where  = ['1=1'];
$params = [];
if ($usuarioFiltro) { $where[] = 'a.usuario_id = ?'; $params[] = $usuarioFiltro; }
if ($accionFiltro)  { $where[] = 'a.accion = ?';     $params[] = $accionFiltro; }
if ($fechaDesde)    { $where[] = 'DATE(a.created_at) >= ?'; $params[] = $fechaDesde; }
if ($fechaHasta)    { $where[] = 'DATE(a.created_at) <= ?'; $params[] = $fechaHasta; }
$whereStr = implode(' AND ', $where);

$stmtTotal = $db->prepare("SELECT COUNT(*) FROM auditoria_facturas a WHERE $whereStr");
$stmtTotal->execute($params);
$total = (int)$stmtTotal->fetchColumn();
$totalPags = (int)ceil($total / $porPagina);

$stmtData = $db->prepare(
    "SELECT a.id, a.factura_id, a.accion, a.campo_editado,
            a.valor_anterior, a.valor_nuevo, a.ip, a.created_at,
            u.nombre AS usuario_nombre, u.email AS usuario_email
     FROM auditoria_facturas a
     JOIN usuarios u ON u.id = a.usuario_id
     WHERE $whereStr
     ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmtData->execute(array_merge($params, [$porPagina, $offset]));
$filas = $stmtData->fetchAll();

$usuariosList = $db->query("SELECT id, nombre FROM usuarios ORDER BY nombre")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Registro de Auditoría</h1>
  <p>Historial de acciones realizadas en el sistema.</p>
</div>

<!-- Filtros -->
<div class="filter-bar">
  <form method="GET" action="">
    <div class="filter-grid">
      <div class="filter-group">
        <label>Usuario</label>
        <select name="usuario_id">
          <option value="">Todos</option>
          <?php foreach ($usuariosList as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $usuarioFiltro === (int)$u['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label>Acción</label>
        <select name="accion">
          <option value="">Todas</option>
          <?php foreach (['VER','EDITAR','EXPORTAR','CREAR','ELIMINAR'] as $a): ?>
          <option value="<?= $a ?>" <?= $accionFiltro === $a ? 'selected' : '' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label>Desde</label>
        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>">
      </div>
      <div class="filter-group">
        <label>Hasta</label>
        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
      </div>
      <div class="filter-group" style="flex-direction:row;align-items:flex-end;gap:.5rem">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="<?= BASE_URL ?>/pages/auditoria.php" class="btn btn-secondary">Limpiar</a>
      </div>
    </div>
  </form>
</div>

<div class="table-wrapper">
  <div class="table-toolbar">
    <span class="table-title"><?= number_format($total) ?> registros</span>
  </div>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Fecha/Hora</th>
          <th>Usuario</th>
          <th>Acción</th>
          <th>Factura ID</th>
          <th>Campo</th>
          <th>Valor anterior</th>
          <th>Valor nuevo</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($filas)): ?>
        <tr><td colspan="8"><div class="empty-state"><p>Sin registros.</p></div></td></tr>
        <?php else: ?>
        <?php foreach ($filas as $r): ?>
        <?php
        $accionClase = [
            'EDITAR'   => 'badge-blue',
            'VER'      => 'badge-gray',
            'EXPORTAR' => 'badge-green',
            'CREAR'    => 'badge-amber',
            'ELIMINAR' => 'badge-red',
        ][$r['accion']] ?? 'badge-gray';
        ?>
        <tr>
          <td style="font-size:.8rem;font-family:'IBM Plex Mono',monospace"><?= htmlspecialchars($r['created_at']) ?></td>
          <td>
            <div style="font-size:.85rem"><strong><?= htmlspecialchars($r['usuario_nombre']) ?></strong></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($r['usuario_email']) ?></div>
          </td>
          <td><span class="badge <?= $accionClase ?>"><?= $r['accion'] ?></span></td>
          <td class="td-mono">
            <a href="<?= BASE_URL ?>/pages/editar_factura.php?id=<?= $r['factura_id'] ?>" class="link">#<?= $r['factura_id'] ?></a>
          </td>
          <td class="td-mono" style="font-size:.8rem"><?= htmlspecialchars($r['campo_editado'] ?? '—') ?></td>
          <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem;color:var(--red)" title="<?= htmlspecialchars($r['valor_anterior'] ?? '') ?>">
            <?= htmlspecialchars(substr($r['valor_anterior'] ?? '—', 0, 40)) ?>
          </td>
          <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem;color:var(--green)" title="<?= htmlspecialchars($r['valor_nuevo'] ?? '') ?>">
            <?= htmlspecialchars(substr($r['valor_nuevo'] ?? '—', 0, 40)) ?>
          </td>
          <td style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars($r['ip'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPags > 1): ?>
  <div class="pagination">
    <span>Página <?= $pagina ?> de <?= $totalPags ?></span>
    <div class="page-nums">
      <?php for ($i = max(1,$pagina-2); $i <= min($totalPags,$pagina+2); $i++): ?>
      <a href="?p=<?= $i ?>&usuario_id=<?= $usuarioFiltro ?>&accion=<?= urlencode($accionFiltro) ?>&fecha_desde=<?= urlencode($fechaDesde) ?>&fecha_hasta=<?= urlencode($fechaHasta) ?>"
         class="page-btn <?= $i===$pagina?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
