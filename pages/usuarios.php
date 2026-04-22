<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/usuarios.php';

requireRol([ROL_ADMIN]);
$pageTitle = 'Usuarios — ' . APP_NAME;

// ── Procesar acciones ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        echo json_encode(crearUsuario($_POST));
    } elseif ($accion === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        echo json_encode($id ? actualizarUsuario($id, $_POST) : ['ok' => false, 'msg' => 'ID inválido.']);
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        echo json_encode($id ? eliminarUsuario($id) : ['ok' => false, 'msg' => 'ID inválido.']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Acción desconocida.']);
    }
    exit;
}

$usuarios = getUsuarios();
$roles    = getRoles();
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Gestión de Usuarios</h1>
  <p>Administra cuentas y asigna roles del sistema.</p>
</div>

<div style="display:flex;justify-content:flex-end;margin-bottom:1rem">
  <button class="btn btn-primary" onclick="abrirModal()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Nuevo usuario
  </button>
</div>

<div class="table-wrapper">
  <div class="table-toolbar">
    <span class="table-title"><?= count($usuarios) ?> usuario<?= count($usuarios) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Email</th>
          <th>Rol</th>
          <th>Telegram ID</th>
          <th>Estado</th>
          <th>Creado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr id="fila-<?= $u['id'] ?>">
          <td class="td-mono" style="color:var(--text-muted)"><?= $u['id'] ?></td>
          <td><strong><?= htmlspecialchars($u['nombre']) ?></strong></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?php
            $rolClase = ['admin' => 'red', 'vendedor' => 'blue', 'contabilidad' => 'green'][$u['rol_nombre']] ?? 'gray';
            ?>
            <span class="badge badge-<?= $rolClase ?>"><?= htmlspecialchars(ucfirst($u['rol_nombre'])) ?></span>
          </td>
          <td class="td-mono"><?= htmlspecialchars($u['telegram_user_id'] ?? '—') ?></td>
          <td><span class="badge <?= $u['activo'] ? 'badge-green' : 'badge-gray' ?>"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
          <td style="color:var(--text-muted);font-size:.82rem"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:.35rem">
              <button class="btn btn-secondary btn-sm"
                      onclick="editarUsuario(<?= htmlspecialchars(json_encode($u)) ?>)">
                Editar
              </button>
              <?php if ($u['id'] !== 1): ?>
              <button class="btn btn-danger btn-sm"
                      onclick="eliminarUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nombre']) ?>')">
                Eliminar
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Modal crear/editar ──────────────────────────────── -->
<div class="modal-overlay" id="modal-usuario">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h2 id="modal-titulo">Nuevo usuario</h2>
      <button class="modal-close" onclick="closeModal('modal-usuario')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="form-usuario">
      <div class="modal-body">
        <input type="hidden" name="accion" id="f-accion" value="crear">
        <input type="hidden" name="id"     id="f-id"     value="">
        <div class="edit-grid">
          <div class="form-group span-2">
            <label>Nombre completo *</label>
            <input type="text" name="nombre" id="f-nombre" required placeholder="Ej. Juan Pérez">
          </div>
          <div class="form-group span-2">
            <label>Email *</label>
            <input type="email" name="email" id="f-email" required placeholder="usuario@empresa.com">
          </div>
          <div class="form-group">
            <label>Contraseña <span id="pass-hint" style="font-weight:400;text-transform:none">(requerida)</span></label>
            <input type="password" name="password" id="f-password" placeholder="Mínimo 8 caracteres">
          </div>
          <div class="form-group">
            <label>Rol *</label>
            <select name="rol_id" id="f-rol">
              <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>"><?= htmlspecialchars(ucfirst($r['nombre'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group span-2">
            <label>Telegram User ID</label>
            <input type="text" name="telegram_user_id" id="f-telegram"
                   placeholder="ID de Telegram del vendedor">
            <span class="field-hint">Vincula al usuario con sus facturas OCR.</span>
          </div>
          <div class="form-group" id="activo-group" style="display:none">
            <label style="flex-direction:row;align-items:center;gap:.5rem;cursor:pointer">
              <input type="checkbox" name="activo" id="f-activo" value="1"> Cuenta activa
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-usuario')">Cancelar</button>
        <button type="submit" class="btn btn-primary" data-label="Guardar">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirModal() {
  document.getElementById('modal-titulo').textContent = 'Nuevo usuario';
  document.getElementById('f-accion').value   = 'crear';
  document.getElementById('f-id').value       = '';
  document.getElementById('f-nombre').value   = '';
  document.getElementById('f-email').value    = '';
  document.getElementById('f-password').value = '';
  document.getElementById('f-rol').value      = '2';
  document.getElementById('f-telegram').value = '';
  document.getElementById('pass-hint').textContent = '(requerida)';
  document.getElementById('activo-group').style.display = 'none';
  openModal('modal-usuario');
}

function editarUsuario(u) {
  document.getElementById('modal-titulo').textContent = 'Editar usuario';
  document.getElementById('f-accion').value   = 'editar';
  document.getElementById('f-id').value       = u.id;
  document.getElementById('f-nombre').value   = u.nombre;
  document.getElementById('f-email').value    = u.email;
  document.getElementById('f-password').value = '';
  document.getElementById('f-rol').value      = u.rol_id;
  document.getElementById('f-telegram').value = u.telegram_user_id || '';
  document.getElementById('f-activo').checked = u.activo == 1;
  document.getElementById('pass-hint').textContent = '(dejar vacío para no cambiar)';
  document.getElementById('activo-group').style.display = 'block';
  openModal('modal-usuario');
}

async function eliminarUsuario(id, nombre) {
  if (!confirm(`¿Desactivar al usuario "${nombre}"? No podrá iniciar sesión.`)) return;
  const fd = new FormData();
  fd.append('accion', 'eliminar');
  fd.append('id', id);
  try {
    const res  = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.msg, data.ok ? 'success' : 'error');
    if (data.ok) setTimeout(() => location.reload(), 1000);
  } catch { showToast('Error de red.', 'error'); }
}

document.getElementById('form-usuario').addEventListener('submit', async e => {
  e.preventDefault();
  await submitForm(e.target, () => {
    closeModal('modal-usuario');
    setTimeout(() => location.reload(), 1000);
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
