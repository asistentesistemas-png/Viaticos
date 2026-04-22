<?php
require_once __DIR__ . '/../config/app.php';
$pageTitle = 'Acceso denegado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Acceso denegado — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
body { font-family:'IBM Plex Sans',sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f8fafc; margin:0; }
.box { text-align:center; max-width:380px; padding:2rem; }
.code { font-size:5rem; font-weight:600; color:#0f3460; line-height:1; }
h1 { font-size:1.2rem; margin:.5rem 0 .75rem; }
p  { color:#64748b; font-size:.9rem; }
a  { display:inline-block; margin-top:1.5rem; padding:.6rem 1.25rem; background:#0f3460; color:white; border-radius:8px; text-decoration:none; font-size:.9rem; }
</style>
</head>
<body>
<div class="box">
  <div class="code">403</div>
  <h1>Acceso denegado</h1>
  <p>No tienes permisos para ver esta página.</p>
  <a href="<?= BASE_URL ?>/pages/dashboard.php">← Ir al inicio</a>
</div>
</body>
</html>
