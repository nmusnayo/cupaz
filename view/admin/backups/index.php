<?php
require_once ROOT_DIR . '/model/BackupModel.php';

$backupModel = new BackupModel();
$mensajeError = '';
$mensajeExito = '';

if (isset($_GET['download'])) {
    $ruta = $backupModel->rutaBackup($_GET['download']);
    if ($ruta === null) {
        http_response_code(404);
        echo 'Backup no encontrado.';
        exit;
    }

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'generar') {
        $resultado = $backupModel->generarBackup();
        if (!empty($resultado['ESTADO'])) {
            $mensajeExito = 'Backup generado correctamente: ' . $resultado['DATA']['nombre'];
        } else {
            $mensajeError = $resultado['ERROR'] ?? 'No se pudo generar el backup.';
        }
    } elseif ($accion === 'eliminar') {
        $resultado = $backupModel->eliminarBackup($_POST['nombre'] ?? '');
        if (!empty($resultado['ESTADO'])) {
            $mensajeExito = 'Backup eliminado correctamente.';
        } else {
            $mensajeError = $resultado['ERROR'] ?? 'No se pudo eliminar el backup.';
        }
    }
}

$backups = $backupModel->listarBackups();
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-database"></i> Administracion</span>
                    <h1 class="page-title">Backups del sistema</h1>
                    <p class="page-subtitle">Genera respaldos SQL de la base de datos para restaurar la informacion si fuera necesario.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="surface-card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">Respaldos disponibles</h3>
                        <form method="post" class="mb-0">
                            <input type="hidden" name="accion" value="generar">
                            <button type="submit" class="btn btn-cupaz-primary">
                                <i class="fas fa-plus mr-2"></i>Generar backup
                            </button>
                        </form>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Fecha</th>
                                    <th>Tamano</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backups)): ?>
                                    <tr><td colspan="4">Todavia no hay backups generados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($backup['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($backup['fecha']); ?></td>
                                            <td><?php echo number_format($backup['tamano'] / 1024, 2); ?> KB</td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a class="btn btn-outline-success" href="<?php echo HTTP_BASE; ?>/admin/backups?download=<?php echo urlencode($backup['nombre']); ?>" title="Descargar">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Eliminar este backup?');">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="nombre" value="<?php echo htmlspecialchars($backup['nombre']); ?>">
                                                        <button type="submit" class="btn btn-outline-danger" title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require ROOT_VIEW . '/template/footer.php'; ?>

