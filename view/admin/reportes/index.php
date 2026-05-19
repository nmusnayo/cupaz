<?php
require_once ROOT_DIR . '/model/ReporteModel.php';

$reporteModel = new ReporteModel();
$idAdmin = (int)($_SESSION['login']['id_usuario'] ?? 0);
$mensajeError = '';
$mensajeExito = '';
$resultadoActual = [];
$reporteActual = null;

function reporteDatosDesdeFila($reporte)
{
    $datos = json_decode($reporte['datos_json'] ?? '[]', true);
    return is_array($datos) ? $datos : [];
}

function reporteColumnas($datos)
{
    if (empty($datos) || !is_array($datos[0])) {
        return [];
    }
    return array_keys($datos[0]);
}

function descargarReporteExcel($reporte)
{
    $datos = reporteDatosDesdeFila($reporte);
    $columnas = reporteColumnas($datos);
    $nombre = 'reporte_' . strtolower($reporte['tipo']) . '_' . $reporte['periodo_inicio'] . '_' . $reporte['periodo_fin'] . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    echo "\xEF\xBB\xBF";
    echo "Reporte\t" . $reporte['tipo'] . "\n";
    echo "Periodo\t" . $reporte['periodo_inicio'] . " al " . $reporte['periodo_fin'] . "\n";
    echo "Generado por\t" . $reporte['generado_por'] . "\n";
    echo "Fecha\t" . $reporte['fecha_generacion'] . "\n\n";

    if (!empty($columnas)) {
        echo implode("\t", $columnas) . "\n";
        foreach ($datos as $fila) {
            $valores = [];
            foreach ($columnas as $columna) {
                $valores[] = str_replace(["\t", "\r", "\n"], ' ', (string)($fila[$columna] ?? ''));
            }
            echo implode("\t", $valores) . "\n";
        }
    } else {
        echo "Sin datos para el periodo seleccionado.\n";
    }
    exit;
}

function imprimirReporte($reporte)
{
    $datos = reporteDatosDesdeFila($reporte);
    $columnas = reporteColumnas($datos);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Reporte <?php echo htmlspecialchars($reporte['tipo']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; color: #1f2937; margin: 28px; }
            h1 { margin: 0 0 6px; font-size: 24px; }
            .meta { margin-bottom: 22px; color: #4b5563; font-size: 13px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; font-size: 12px; }
            th { background: #f3f4f6; }
            .actions { margin-bottom: 18px; }
            .actions button { padding: 8px 12px; border: 0; background: #2563eb; color: #fff; border-radius: 4px; cursor: pointer; }
            @media print { .actions { display: none; } body { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="actions"><button type="button" onclick="window.print()">Imprimir</button></div>
        <h1>Reporte <?php echo htmlspecialchars($reporte['tipo']); ?></h1>
        <div class="meta">
            Periodo: <?php echo htmlspecialchars($reporte['periodo_inicio']); ?> al <?php echo htmlspecialchars($reporte['periodo_fin']); ?><br>
            Generado por: <?php echo htmlspecialchars($reporte['generado_por']); ?> |
            Fecha: <?php echo htmlspecialchars($reporte['fecha_generacion']); ?>
        </div>
        <table>
            <thead>
                <tr>
                    <?php foreach ($columnas as $columna): ?><th><?php echo htmlspecialchars($columna); ?></th><?php endforeach; ?>
                    <?php if (empty($columnas)): ?><th>Mensaje</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($datos)): ?>
                    <tr><td>Sin datos para el periodo seleccionado.</td></tr>
                <?php else: ?>
                    <?php foreach ($datos as $fila): ?>
                        <tr>
                            <?php foreach ($columnas as $columna): ?><td><?php echo htmlspecialchars((string)($fila[$columna] ?? '')); ?></td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_GET['id']) && (isset($_GET['export']) || isset($_GET['print']))) {
    $consulta = $reporteModel->obtenerReporte((int)$_GET['id']);
    $reporte = $consulta['DATA'][0] ?? null;
    if ($reporte) {
        if (($_GET['export'] ?? '') === 'excel') {
            descargarReporteExcel($reporte);
        }
        imprimirReporte($reporte);
    }
    http_response_code(404);
    echo 'Reporte no encontrado.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = strtoupper(trim($_POST['tipo'] ?? 'PEDIDOS'));
    $inicio = trim($_POST['periodo_inicio'] ?? date('Y-m-01'));
    $fin = trim($_POST['periodo_fin'] ?? date('Y-m-d'));

    $resultado = $reporteModel->generarReporte($tipo, $inicio, $fin, $idAdmin);
    if (!empty($resultado['ESTADO'])) {
        $resultadoActual = $resultado['DATA'] ?? [];
        $reportesTmp = $reporteModel->listarReportes()['DATA'] ?? [];
        $reporteActual = $reportesTmp[0] ?? null;
        $mensajeExito = 'Reporte generado y almacenado correctamente.';
    } else {
        $mensajeError = $resultado['ERROR'] ?? 'No se pudo generar el reporte.';
    }
}

$resumen = $reporteModel->resumenGeneral()['DATA'][0] ?? [];
$reportes = $reporteModel->listarReportes()['DATA'] ?? [];
$columnasActuales = reporteColumnas($resultadoActual);
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-chart-bar"></i> Administración</span>
                    <h1 class="page-title">Reportes del sistema</h1>
                    <p class="page-subtitle">Genera reportes básicos de ventas, pedidos, pagos y disputas, y guarda un historial en la base de datos.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Usuarios</div>
                        <div class="metric-value"><?php echo (int)($resumen['total_usuarios'] ?? 0); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Pedidos</div>
                        <div class="metric-value"><?php echo (int)($resumen['total_pedidos'] ?? 0); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Liberado</div>
                        <div class="metric-value" style="font-size:20px;">Bs <?php echo number_format((float)($resumen['total_liberado'] ?? 0), 2); ?></div>
                    </div>
                </div>

                <div class="module-grid">
                    <div class="surface-card">
                        <div class="card-header"><h3 class="card-title">Generar reporte</h3></div>
                        <div class="card-body">
                            <form method="post" class="cupaz-form">
                                <div class="form-group">
                                    <label>Tipo</label>
                                    <select name="tipo" class="custom-select">
                                        <?php foreach (['VENTAS', 'PEDIDOS', 'PAGOS', 'DISPUTAS'] as $tipo): ?>
                                            <option value="<?php echo $tipo; ?>"><?php echo $tipo; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Desde</label>
                                    <input type="date" name="periodo_inicio" class="form-control" value="<?php echo date('Y-m-01'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Hasta</label>
                                    <input type="date" name="periodo_fin" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-cupaz-primary btn-block">
                                    <i class="fas fa-file-alt mr-2"></i>Generar
                                </button>
                                <?php if ($reporteActual): ?>
                                    <div class="btn-group btn-block mt-2" role="group">
                                        <a class="btn btn-outline-secondary" target="_blank" href="<?php echo HTTP_BASE; ?>/admin/reportes?id=<?php echo (int)$reporteActual['id_reporte']; ?>&print=1">
                                            <i class="fas fa-print mr-1"></i>Imprimir
                                        </a>
                                        <a class="btn btn-outline-success" href="<?php echo HTTP_BASE; ?>/admin/reportes?id=<?php echo (int)$reporteActual['id_reporte']; ?>&export=excel">
                                            <i class="fas fa-file-excel mr-1"></i>Excel
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="surface-card">
                        <div class="card-header"><h3 class="card-title">Último resultado</h3></div>
                        <div class="card-body table-responsive">
                            <table class="table table-cupaz table-hover">
                                <thead>
                                    <tr>
                                        <?php if (empty($columnasActuales)): ?>
                                            <th>Mensaje</th>
                                        <?php else: ?>
                                            <?php foreach ($columnasActuales as $columna): ?>
                                                <th><?php echo htmlspecialchars($columna); ?></th>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($resultadoActual)): ?>
                                        <tr><td>Genera un reporte para ver el detalle.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($resultadoActual as $fila): ?>
                                            <tr>
                                                <?php foreach ($columnasActuales as $columna): ?>
                                                    <td><?php echo htmlspecialchars((string)($fila[$columna] ?? '')); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="surface-card mt-4">
                    <div class="card-header"><h3 class="card-title">Historial de reportes</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Generado por</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportes as $reporte): ?>
                                    <tr>
                                        <td>#<?php echo (int)$reporte['id_reporte']; ?></td>
                                        <td><span class="badge-cupaz badge-soft"><?php echo htmlspecialchars($reporte['tipo']); ?></span></td>
                                        <td><?php echo htmlspecialchars($reporte['periodo_inicio']); ?></td>
                                        <td><?php echo htmlspecialchars($reporte['periodo_fin']); ?></td>
                                        <td><?php echo htmlspecialchars($reporte['generado_por']); ?></td>
                                        <td><?php echo htmlspecialchars($reporte['fecha_generacion']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a class="btn btn-outline-secondary" target="_blank" href="<?php echo HTTP_BASE; ?>/admin/reportes?id=<?php echo (int)$reporte['id_reporte']; ?>&print=1" title="Imprimir">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <a class="btn btn-outline-success" href="<?php echo HTTP_BASE; ?>/admin/reportes?id=<?php echo (int)$reporte['id_reporte']; ?>&export=excel" title="Descargar Excel">
                                                    <i class="fas fa-file-excel"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require ROOT_VIEW . '/template/footer.php'; ?>
