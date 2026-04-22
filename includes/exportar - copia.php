<?php
// includes/exportar.php
 
require_once __DIR__ . '/facturas.php';
require_once __DIR__ . '/auth.php';
 
function exportarExcel(array $filtros = []): void {
    requireAuth();
    $usuario = usuarioActual();
    $db      = getDB();
 
    // ── Construir WHERE según rol ────────────────────────
    $where  = ['1=1'];
    $params = [];
 
    if ($usuario['rol_id'] === ROL_VENDEDOR) {
        $where[]  = 'v.telegram_user_id = ?';
        $params[] = $usuario['telegram_user_id'];
    }
 
    if (!empty($filtros['telegram_user_id']) && $usuario['rol_id'] !== ROL_VENDEDOR) {
        $where[]  = 'v.telegram_user_id = ?';
        $params[] = $filtros['telegram_user_id'];
    }
 
    if (!empty($filtros['fecha_desde'])) {
        $where[]  = 'v.fecha >= ?';
        $params[] = $filtros['fecha_desde'];
    }
    if (!empty($filtros['fecha_hasta'])) {
        $where[]  = 'v.fecha <= ?';
        $params[] = $filtros['fecha_hasta'];
    }
    if (!empty($filtros['proveedor'])) {
        $where[]  = 'v.proveedor LIKE ?';
        $params[] = '%' . $filtros['proveedor'] . '%';
    }
    if (!empty($filtros['numero_factura'])) {
        $where[]  = 'v.numero_factura LIKE ?';
        $params[] = '%' . $filtros['numero_factura'] . '%';
    }
    if (!empty($filtros['nit_proveedor'])) {
        $where[]  = 'v.nit_proveedor LIKE ?';
        $params[] = '%' . $filtros['nit_proveedor'] . '%';
    }
 
    $whereStr = implode(' AND ', $where);
 
    // ── Consultar vista ──────────────────────────────────
    $sql = "SELECT
                v.fecha,
                v.departamento,
                v.municipio,
                v.serie_factura,
                v.numero_factura,
                v.nit_proveedor,
                v.proveedor,
                v.combustible,
                v.alimentacion,
                v.hospedaje,
                v.otros,
                v.descripcion_otros,
                v.forma_pago
            FROM vista_facturas_viaticos v
            WHERE $whereStr
            ORDER BY v.fecha DESC";
 
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    // ── Registrar auditoría ──────────────────────────────
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmtAud = $db->prepare(
        "INSERT INTO auditoria_facturas (factura_id, usuario_id, accion, ip, user_agent)
         VALUES (?, ?, 'EXPORTAR', ?, ?)"
    );
    foreach ($filas as $f) {
        if (!empty($f['id'])) {
            $stmtAud->execute([$f['id'], $usuario['id'], $ip, $ua]);
        }
    }
 
    $fechaArchivo = date('Y-m-d_H-i-s');
    $filename     = "facturas_viaticos_{$fechaArchivo}";
 
    // ── PhpSpreadsheet si está disponible ────────────────
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        exportarXLSX($filas, $filename . '.xlsx');
        return;
    }
 
    // ── Fallback CSV con BOM UTF-8 ───────────────────────
    exportarCSV($filas, $filename . '.csv');
}
 
function exportarXLSX(array $filas, string $filename): void {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Facturas Viáticos');
 
    // ── Estilo encabezado ────────────────────────────────
    $headerStyle = [
        'font' => [
            'bold'  => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size'  => 11,
        ],
        'fill' => [
            'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '0F3460'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color'       => ['rgb' => 'CCCCCC'],
            ],
        ],
    ];
 
    // ── Encabezados ──────────────────────────────────────
    $encabezados = [
        'A' => 'Fecha',
        'B' => 'Departamento',
        'C' => 'Municipio',
        'D' => 'Serie',
        'E' => 'N° Factura',
        'F' => 'NIT Proveedor',
        'G' => 'Proveedor',
        'H' => 'Combustible',
        'I' => 'Alimentación',
        'J' => 'Hospedaje',
        'K' => 'Otros',
        'L' => 'Descripción Otros',
        'M' => 'Forma de Pago',
    ];
 
    foreach ($encabezados as $col => $titulo) {
        $sheet->setCellValue($col . '1', $titulo);
    }
 
    // Aplicar estilo a encabezados
    $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
    $sheet->getRowDimension(1)->setRowHeight(22);
 
    // ── Datos ────────────────────────────────────────────
    $row = 2;
    foreach ($filas as $f) {
        $sheet->setCellValue('A' . $row, $f['fecha']            ?? '');
        $sheet->setCellValue('B' . $row, $f['departamento']     ?? '');
        $sheet->setCellValue('C' . $row, $f['municipio']        ?? '');
        $sheet->setCellValue('D' . $row, $f['serie_factura']    ?? '');
        $sheet->setCellValue('E' . $row, $f['numero_factura']   ?? '');
        $sheet->setCellValue('F' . $row, $f['nit_proveedor']    ?? '');
        $sheet->setCellValue('G' . $row, $f['proveedor']        ?? '');
        $sheet->setCellValue('H' . $row, $f['combustible']      ?? 0);
        $sheet->setCellValue('I' . $row, $f['alimentacion']     ?? 0);
        $sheet->setCellValue('J' . $row, $f['hospedaje']        ?? 0);
        $sheet->setCellValue('K' . $row, $f['otros']            ?? 0);
        $sheet->setCellValue('L' . $row, $f['descripcion_otros']?? '');
        $sheet->setCellValue('M' . $row, $f['forma_pago']       ?? '');
 
        // Filas alternadas
        if ($row % 2 === 0) {
            $sheet->getStyle('A' . $row . ':M' . $row)->getFill()
                  ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('F8FAFC');
        }
        $row++;
    }
 
    // ── Formato moneda columnas H-K ──────────────────────
    if ($row > 2) {
        $sheet->getStyle('H2:K' . ($row - 1))
              ->getNumberFormat()
              ->setFormatCode('#,##0.00');
    }
 
    // ── Ancho de columnas ────────────────────────────────
    $anchos = [
        'A' => 14, 'B' => 18, 'C' => 20, 'D' => 12,
        'E' => 16, 'F' => 14, 'G' => 35, 'H' => 14,
        'I' => 14, 'J' => 14, 'K' => 14, 'L' => 25, 'M' => 16,
    ];
    foreach ($anchos as $col => $ancho) {
        $sheet->getColumnDimension($col)->setWidth($ancho);
    }
 
    // ── Fila de totales ──────────────────────────────────
    if ($row > 2) {
        $totalRow = $row;
        $sheet->setCellValue('G' . $totalRow, 'TOTAL');
        $sheet->setCellValue('H' . $totalRow, '=SUM(H2:H' . ($row - 1) . ')');
        $sheet->setCellValue('I' . $totalRow, '=SUM(I2:I' . ($row - 1) . ')');
        $sheet->setCellValue('J' . $totalRow, '=SUM(J2:J' . ($row - 1) . ')');
        $sheet->setCellValue('K' . $totalRow, '=SUM(K2:K' . ($row - 1) . ')');
 
        $totalStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8F0FB'],
            ],
            'numberFormat' => ['formatCode' => '#,##0.00'],
        ];
        $sheet->getStyle('G' . $totalRow . ':K' . $totalRow)->applyFromArray($totalStyle);
    }
 
    // ── Congelar primera fila ────────────────────────────
    $sheet->freezePane('A2');
 
    // ── Autofilter ───────────────────────────────────────
    $sheet->setAutoFilter('A1:M1');
 
    // ── Output ───────────────────────────────────────────
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
 
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
 
function exportarCSV(array $filas, string $filename): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
 
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8
 
    fputcsv($out, [
        'Fecha', 'Departamento', 'Municipio', 'Serie', 'N° Factura',
        'NIT Proveedor', 'Proveedor', 'Combustible', 'Alimentación',
        'Hospedaje', 'Otros', 'Descripción Otros', 'Forma de Pago',
    ]);
 
    foreach ($filas as $f) {
        fputcsv($out, [
            $f['fecha']             ?? '',
            $f['departamento']      ?? '',
            $f['municipio']         ?? '',
            $f['serie_factura']     ?? '',
            $f['numero_factura']    ?? '',
            $f['nit_proveedor']     ?? '',
            $f['proveedor']         ?? '',
            $f['combustible']       ?? 0,
            $f['alimentacion']      ?? 0,
            $f['hospedaje']         ?? 0,
            $f['otros']             ?? 0,
            $f['descripcion_otros'] ?? '',
            $f['forma_pago']        ?? '',
        ]);
    }
    fclose($out);
    exit;
}
