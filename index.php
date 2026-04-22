<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

iniciarSesionSegura();

// Si ya está autenticado, redirigir al dashboard
if (estaAutenticado()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$error   = '';
$expired = isset($_GET['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Por favor ingresa tu email y contraseña.';
    } else {
        $resultado = login($email, $password);
        if ($resultado['ok']) {
            header('Location: ' . BASE_URL . '/pages/dashboard.php');
            exit;
        }
        $error = $resultado['msg'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar Sesión — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --blue-dark:  #0f3460;
    --blue-mid:   #1a56a0;
    --blue-light: #e8f0fb;
    --accent:     #f97316;
    --text-main:  #0f172a;
    --text-muted: #64748b;
    --border:     #cbd5e1;
    --surface:    #ffffff;
    --bg:         #f1f5f9;
    --error-bg:   #fef2f2;
    --error-text: #b91c1c;
    --error-border:#fca5a5;
    --warn-bg:    #fffbeb;
    --warn-text:  #92400e;
    --warn-border:#fcd34d;
  }

  body {
    font-family: 'IBM Plex Sans', sans-serif;
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
  }

  /* Fondo geométrico */
  body::before {
    content: '';
    position: fixed; inset: 0; z-index: 0;
    background:
      linear-gradient(135deg, #0f3460 0%, #1a56a0 50%, #2563eb 100%);
    opacity: .07;
  }

  .login-wrapper {
    position: relative; z-index: 1;
    width: 100%; max-width: 420px;
  }

  .brand {
    text-align: center;
    margin-bottom: 2rem;
  }
  .brand-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 56px; height: 56px;
    background: var(--blue-dark);
    border-radius: 14px;
    margin-bottom: .75rem;
  }
  .brand-icon svg { width: 28px; height: 28px; stroke: white; }
  .brand h1 {
    font-size: 1.1rem; font-weight: 600;
    color: var(--blue-dark); letter-spacing: -.01em;
    line-height: 1.3;
  }
  .brand p { font-size: .8rem; color: var(--text-muted); margin-top: 2px; }

  .card {
    background: var(--surface);
    border-radius: 16px;
    border: 1px solid var(--border);
    padding: 2rem;
    box-shadow: 0 4px 32px rgba(15,52,96,.08), 0 1px 4px rgba(0,0,0,.04);
  }

  .card h2 {
    font-size: 1.05rem; font-weight: 600;
    color: var(--text-main); margin-bottom: 1.5rem;
  }

  .alert {
    padding: .75rem 1rem; border-radius: 8px;
    font-size: .85rem; margin-bottom: 1.25rem;
  }
  .alert-error  { background: var(--error-bg);  color: var(--error-text); border: 1px solid var(--error-border); }
  .alert-warning{ background: var(--warn-bg);   color: var(--warn-text);  border: 1px solid var(--warn-border); }

  .form-group { margin-bottom: 1.1rem; }
  .form-group label {
    display: block; font-size: .8rem; font-weight: 500;
    color: var(--text-muted); margin-bottom: .4rem;
    text-transform: uppercase; letter-spacing: .04em;
  }
  .form-group input {
    width: 100%; padding: .7rem .9rem;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-size: .95rem; font-family: inherit;
    color: var(--text-main); background: var(--surface);
    transition: border-color .15s;
  }
  .form-group input:focus { outline: none; border-color: var(--blue-mid); }

  .btn-login {
    width: 100%; padding: .8rem;
    background: var(--blue-dark); color: white;
    border: none; border-radius: 8px;
    font-size: .95rem; font-weight: 600; font-family: inherit;
    cursor: pointer; letter-spacing: .01em;
    transition: background .15s, transform .1s;
    margin-top: .5rem;
  }
  .btn-login:hover  { background: var(--blue-mid); }
  .btn-login:active { transform: scale(.99); }

  .footer-note {
    text-align: center; margin-top: 1.5rem;
    font-size: .78rem; color: var(--text-muted);
  }
  .footer-note code {
    font-family: 'IBM Plex Mono', monospace;
    background: var(--blue-light); color: var(--blue-dark);
    padding: 1px 5px; border-radius: 4px; font-size: .78rem;
  }
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="brand">
    <div class="brand-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="3" width="20" height="14" rx="2"/>
        <path d="M8 21h8M12 17v4"/>
        <path d="M7 8h2m2 0h6M7 11h4m2 0h3"/>
      </svg>
    </div>
    <h1><?= APP_NAME ?></h1>
    <p>Gestión de facturas OCR</p>
  </div>

  <div class="card">
    <h2>Iniciar sesión</h2>

    <?php if ($expired): ?>
    <div class="alert alert-warning">Tu sesión expiró. Por favor ingresa nuevamente.</div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="usuario@empresa.com" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password"
               placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-login">Ingresar al sistema</button>
    </form>
  </div>

  <p class="footer-note">
    Admin inicial: <code></code> / <code></code>
  </p>
</div>
</body>
</html>
