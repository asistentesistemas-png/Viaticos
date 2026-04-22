// assets/js/main.js

/* ── TOAST ─────────────────────────────────────────────── */
function showToast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  if (!c) return;
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = msg;
  c.appendChild(t);
  requestAnimationFrame(() => t.classList.add('show'));
  setTimeout(() => {
    t.classList.remove('show');
    setTimeout(() => t.remove(), 300);
  }, 3200);
}

/* ── MODAL ─────────────────────────────────────────────── */
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
  if (e.target.closest('.modal-close')) {
    const ov = e.target.closest('.modal-overlay');
    if (ov) { ov.classList.remove('open'); document.body.style.overflow = ''; }
  }
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => {
      m.classList.remove('open'); document.body.style.overflow = '';
    });
  }
});

/* ── CONFIRMACIÓN ELIMINAR ──────────────────────────────── */
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  e.preventDefault();
  if (confirm(btn.dataset.confirm || '¿Confirmas esta acción?')) {
    const form = btn.closest('form') || document.getElementById(btn.dataset.form);
    if (form) form.submit();
    else window.location.href = btn.href || btn.dataset.href;
  }
});

/* ── AJAX FORM SUBMIT ────────────────────────────────────── */
async function submitForm(formEl, successCb) {
  const fd = new FormData(formEl);
  const btn = formEl.querySelector('[type=submit]');
  if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }
  try {
    const res  = await fetch(formEl.action || window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      showToast(data.msg || 'Guardado correctamente.', 'success');
      if (successCb) successCb(data);
    } else {
      showToast(data.msg || 'Error al guardar.', 'error');
    }
  } catch {
    showToast('Error de red. Intenta de nuevo.', 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Guardar'; }
  }
}

/* ── AUTO-DISMISS ALERTS ────────────────────────────────── */
document.querySelectorAll('.alert[data-autodismiss]').forEach(a => {
  setTimeout(() => a.remove(), 4000);
});

/* ── RECALCULAR TOTAL ────────────────────────────────────── */
function recalcTotal() {
  const sub = parseFloat(document.getElementById('f_subtotal')?.value) || 0;
  const iva = parseFloat(document.getElementById('f_iva')?.value) || 0;
  const totalEl = document.getElementById('f_total');
  if (totalEl) totalEl.value = (sub + iva).toFixed(2);
}
document.addEventListener('input', e => {
  if (e.target.id === 'f_subtotal' || e.target.id === 'f_iva') recalcTotal();
});
