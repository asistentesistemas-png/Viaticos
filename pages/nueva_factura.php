<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
$usuario = usuarioActual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    try {
        $db->prepare("INSERT INTO facturas_ocr 
            (fecha, numero_factura, serie_factura, nit_proveedor, proveedor,
             subtotal, iva, total, moneda, departamento, municipio,
             texto_manuscrito, descripcion_otros, forma_pago, items_texto,
             telegram_user_id, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
        ->execute([
            $_POST['fecha']             ?? null,
            $_POST['numero_factura']    ?? null,
            $_POST['serie_factura']     ?? null,
            $_POST['nit_proveedor']     ?? null,
            $_POST['proveedor']         ?? null,
            $_POST['subtotal']          ?? 0,
            $_POST['iva']               ?? 0,
            $_POST['total']             ?? 0,
            $_POST['moneda']            ?? 'GTQ',
            $_POST['departamento']      ?? null,
            $_POST['municipio']         ?? null,
            $_POST['texto_manuscrito']  ?? null,
            $_POST['descripcion_otros'] ?? null,
            $_POST['forma_pago']        ?? null,
            $_POST['items_texto']       ?? null,
            $usuario['telegram_user_id'] ?? null,
        ]);

        $_SESSION['flash_msg']  = 'Factura creada correctamente.';
        $_SESSION['flash_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['flash_msg']  = 'Error al crear la factura: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
}

header('Location: ' . BASE_URL . '/pages/facturas.php');
exit;