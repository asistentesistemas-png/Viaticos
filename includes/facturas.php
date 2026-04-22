<?php
// includes/facturas.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

/**
 * Obtiene facturas según rol del usuario con filtros y paginación.
 */
function getFacturas(array $filtros = [], int $pagina = 1, int $porPagina = 20): array {
    $db      = getDB();
    $usuario = usuarioActual();
    $where   = ['1=1'];
    $params  = [];

    // VENDEDOR: solo sus facturas
    if ($usuario['rol_id'] === ROL_VENDEDOR) {
        $where[] = 'f.telegram_user_id = ?';
        $params[] = $usuario['telegram_user_id'];
    }

    // Filtro por telegram_user_id específico (para Admin/Contabilidad)
    if (!empty($filtros['telegram_user_id']) && $usuario['rol_id'] !== ROL_VENDEDOR) {
        $where[] = 'f.telegram_user_id = ?';
        $params[] = $filtros['telegram_user_id'];
    }

    // Filtros comunes
    if (!empty($filtros['fecha_desde'])) {
        $where[] = 'f.fecha >= ?';
        $params[] = $filtros['fecha_desde'];
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where[] = 'f.fecha <= ?';
        $params[] = $filtros['fecha_hasta'];
    }
    if (!empty($filtros['proveedor'])) {
        $where[] = 'f.proveedor LIKE ?';
        $params[] = '%' . $filtros['proveedor'] . '%';
    }
    if (!empty($filtros['numero_factura'])) {
        $where[] = 'f.numero_factura LIKE ?';
        $params[] = '%' . $filtros['numero_factura'] . '%';
    }
    if (!empty($filtros['nit_proveedor'])) {
        $where[] = 'f.nit_proveedor LIKE ?';
        $params[] = '%' . $filtros['nit_proveedor'] . '%';
    }

    $whereStr = implode(' AND ', $where);

    // Total para paginación
    $countSql = "SELECT COUNT(*) FROM facturas_ocr f WHERE $whereStr";
    $total = (int)$db->prepare($countSql)->execute($params) ? 
             $db->prepare($countSql)->execute($params) : 0;
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $offset = ($pagina - 1) * $porPagina;
    $sql = "SELECT f.id, f.fecha, f.nit_proveedor, f.proveedor, f.numero_factura,
                   f.serie_factura, f.nit_cliente, f.nombre_cliente,
                   f.subtotal, f.iva, f.total, f.moneda,
                   f.cuenta_contable, f.descripcion_cuenta,
                   f.dimension_1, f.dimension_2, f.dimension_3,
                   f.nombre_responsable, f.telegram_user_id,
                   f.tipo_documento, f.numero_autorizacion,f.url_google_drive,f.departamento,f.municipio,
                   f.created_at, f.fecha_procesamiento,
                   u.nombre AS vendedor_nombre
            FROM facturas_ocr f
            LEFT JOIN usuarios u ON u.telegram_user_id = f.telegram_user_id
            WHERE $whereStr
            ORDER BY f.fecha DESC, f.id DESC
            LIMIT ? OFFSET ?";

    $stmtData = $db->prepare($sql);
    $allParams = array_merge($params, [$porPagina, $offset]);
    $stmtData->execute($allParams);
    $filas = $stmtData->fetchAll();

    return [
        'data'        => $filas,
        'total'       => $total,
        'pagina'      => $pagina,
        'por_pagina'  => $porPagina,
        'total_paginas' => (int)ceil($total / $porPagina),
    ];
}



function getFacturaById(int $id): ?array {
    $db      = getDB();
    $usuario = usuarioActual();

    $sql = "SELECT 
                f.id,
                f.fecha,
                f.nit_proveedor,
                f.proveedor,
                f.numero_factura,
                f.serie_factura,
                f.nit_cliente,
                f.nombre_cliente,
                f.subtotal,
                f.iva,
                f.total,
                f.uuid,
                f.moneda,
                f.regimen_isr,
                f.tipo_contribuyente,
                f.fecha_procesamiento,
                f.items_texto,
                f.cuenta_contable,
                f.descripcion_cuenta,
                f.texto_manuscrito,
                f.dimension_1,
                f.dimension_2,
                f.dimension_3,
                f.nombre_responsable,
                f.departamento,
                f.municipio,
                f.url_google_drive,
                f.telegram_user_id,
                f.created_at,
                f.tipo_documento,
                f.numero_autorizacion,
                u.nombre AS vendedor_nombre
            FROM facturas_ocr f
            LEFT JOIN usuarios u 
                ON u.telegram_user_id = f.telegram_user_id
            WHERE f.id = ?";

    $params = [$id];

    if ($usuario['rol_id'] === ROL_VENDEDOR) {
        $sql .= " AND f.telegram_user_id = ?";
        $params[] = $usuario['telegram_user_id'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}


/**
 * Actualiza campos de una factura con auditoría por campo.
 */
function actualizarFactura(int $id, array $datos): array {
    $db      = getDB();
    $usuario = usuarioActual();

    // Verificar acceso
    $factura = getFacturaById($id);
    if (!$factura) {
        return ['ok' => false, 'msg' => 'Factura no encontrada o sin permisos.'];
    }

    // Solo campos permitidos
    $camposPermitidos = array_keys(CAMPOS_FACTURA);
    $setClauses = [];
    $params     = [];
    $auditoria  = [];

    foreach ($datos as $campo => $valor) {
        if (!in_array($campo, $camposPermitidos, true)) continue;

        $valorAnterior = $factura[$campo] ?? null;
        $valorNuevo    = trim($valor) === '' ? null : trim($valor);

        // Validación básica por tipo
        $tipo = CAMPOS_FACTURA[$campo]['tipo'];
        if ($tipo === 'decimal' && $valorNuevo !== null && !is_numeric($valorNuevo)) {
            return ['ok' => false, 'msg' => "El campo '{$campo}' debe ser numérico."];
        }
        if ($tipo === 'date' && $valorNuevo !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valorNuevo)) {
            return ['ok' => false, 'msg' => "El campo 'fecha' tiene formato inválido."];
        }

        if ((string)$valorAnterior !== (string)$valorNuevo) {
            $setClauses[] = "`$campo` = ?";
            $params[]     = $valorNuevo;
            $auditoria[]  = [
                'campo'    => $campo,
                'anterior' => $valorAnterior,
                'nuevo'    => $valorNuevo,
            ];
        }
    }

    if (empty($setClauses)) {
        return ['ok' => true, 'msg' => 'Sin cambios que guardar.'];
    }

    try {
        $db->beginTransaction();

        $params[] = $id;
        $sql = "UPDATE facturas_ocr SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $db->prepare($sql)->execute($params);

        // Registrar auditoría por cada campo modificado
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $stmtAud = $db->prepare(
            "INSERT INTO auditoria_facturas
             (factura_id, usuario_id, accion, campo_editado, valor_anterior, valor_nuevo, ip, user_agent)
             VALUES (?, ?, 'EDITAR', ?, ?, ?, ?, ?)"
        );
        foreach ($auditoria as $a) {
            $stmtAud->execute([$id, $usuario['id'], $a['campo'], $a['anterior'], $a['nuevo'], $ip, $ua]);
        }

        $db->commit();
        return ['ok' => true, 'msg' => 'Factura actualizada correctamente.'];
    /*} catch (PDOException $e) {
        $db->rollBack();
        error_log('actualizarFactura error: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'Error al guardar. Intenta de nuevo.'];
    }*/
        } catch (PDOException $e) {
    $db->rollBack();
    return ['ok' => false, 'msg' => 'Error: ' . $e->getMessage()];
}
}

/**
 * Retorna todos los vendedores únicos (para filtros de Admin/Contabilidad).
 */
function getVendedores(): array {
    $db = getDB();
    return $db->query(
        "SELECT u.telegram_user_id, u.nombre, u.email
         FROM usuarios u
         WHERE u.rol_id = " . ROL_VENDEDOR . " AND u.activo = 1
         ORDER BY u.nombre"
    )->fetchAll();
}

/**
 * Registrar acción de auditoría genérica (VER, EXPORTAR).
 */
function registrarAuditoria(int $facturaId, string $accion): void {
    $usuario = usuarioActual();
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua      = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    try {
        getDB()->prepare(
            "INSERT INTO auditoria_facturas (factura_id, usuario_id, accion, ip, user_agent)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$facturaId, $usuario['id'], $accion, $ip, $ua]);
    } catch (PDOException $e) {
        error_log('auditoria error: ' . $e->getMessage());
    }
}
