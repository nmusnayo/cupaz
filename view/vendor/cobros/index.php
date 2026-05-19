<?php
require_once ROOT_DIR . '/model/CobroVendedorModel.php';
require_once ROOT_DIR . '/model/PagoModel.php';
require_once ROOT_DIR . '/model/LiquidacionModel.php';

$cobroModel = new CobroVendedorModel();
$pagoModel = new PagoModel();
$liquidacionModel = new LiquidacionModel();
$idVendedor = (int)($_SESSION['login']['id_usuario'] ?? 0);
$mensajeError = '';
$mensajeExito = '';

function guardarQrCobroVendedor($file)
{
    if (!isset($file['tmp_name']) || $file['tmp_name'] === '' || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }
    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        return null;
    }
    $uploadDir = ROOT_UPLOAD . '/cobros';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $extension = $allowedMime[$mime];
    $fileName = 'qr_cobro_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }
    return 'uploads/cobros/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titular = trim($_POST['titular'] ?? '');
    $banco = trim($_POST['banco'] ?? '');
    $numeroCuenta = trim($_POST['numero_cuenta'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');
    $qrCobro = guardarQrCobroVendedor($_FILES['qr_cobro'] ?? []);

    if ($titular === '') {
        $mensajeError = 'Debes registrar al menos el titular de cobro.';
    } else {
        $resultado = $cobroModel->guardar($idVendedor, $titular, $banco, $numeroCuenta, $qrCobro, $observaciones);
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Datos de cobro guardados correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudieron guardar los datos de cobro.') : '';
    }
}

$cobro = $cobroModel->obtenerPorVendedor($idVendedor)['DATA'][0] ?? null;
$cobros = $pagoModel->listarCobrosPorVendedor($idVendedor)['DATA'] ?? [];
$liquidaciones = $liquidacionModel->listarPorVendedor($idVendedor)['DATA'] ?? [];

$retenidos = [];
$listosDeposito = [];
$depositados = [];
$totalRetenido = 0.0;
$totalDisponible = 0.0;
$totalDepositado = 0.0;

foreach ($cobros as $movimiento) {
    $monto = (float)($movimiento['monto'] ?? 0);
    $estado = strtoupper($movimiento['estado'] ?? '');
    $tieneLiquidacion = !empty($movimiento['id_liquidacion']);

    if ($estado === 'LIBERADO' && !$tieneLiquidacion) {
        $listosDeposito[] = $movimiento;
        $totalDisponible += round($monto * 0.90, 2);
    } elseif ($estado === 'LIBERADO' && $tieneLiquidacion) {
        $depositados[] = $movimiento;
        $totalDepositado += (float)($movimiento['monto_vendedor'] ?? $monto);
    } elseif (in_array($estado, ['RETENIDO', 'EN_DISPUTA'], true)) {
        $retenidos[] = $movimiento;
        $totalRetenido += $monto;
    }
}
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-wallet"></i> Espacio vendedor</span>
                    <h1 class="page-title">Mis cobros</h1>
                    <p class="page-subtitle">Consulta qué pagos siguen retenidos, cuáles ya están listos para depósito y el historial de transferencias registradas por administración.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Retenido</div>
                        <div class="metric-value">Bs <?php echo number_format($totalRetenido, 2); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Listo para depósito</div>
                        <div class="metric-value">Bs <?php echo number_format($totalDisponible, 2); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Ya depositado</div>
                        <div class="metric-value">Bs <?php echo number_format($totalDepositado, 2); ?></div>
                    </div>
                </div>

                <div class="module-grid">
                    <div class="surface-card">
                        <div class="card-header"><h3 class="card-title">Registrar cobro</h3></div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" class="cupaz-form">
                                <div class="form-group">
                                    <label>Titular</label>
                                    <input type="text" name="titular" class="form-control" value="<?php echo htmlspecialchars($cobro['titular'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Banco</label>
                                    <input type="text" name="banco" class="form-control" value="<?php echo htmlspecialchars($cobro['banco'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Número de cuenta</label>
                                    <input type="text" name="numero_cuenta" class="form-control" value="<?php echo htmlspecialchars($cobro['numero_cuenta'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>QR de cobro</label>
                                    <input type="file" name="qr_cobro" class="form-control" accept="image/*">
                                </div>
                                <div class="form-group">
                                    <label>Observaciones</label>
                                    <textarea name="observaciones" class="form-control"><?php echo htmlspecialchars($cobro['observaciones'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-cupaz-primary btn-block">
                                    <i class="fas fa-save mr-2"></i>Guardar datos de cobro
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="surface-card">
                        <div class="card-header"><h3 class="card-title">Resumen actual</h3></div>
                        <div class="card-body">
                            <p><strong>Titular:</strong> <?php echo htmlspecialchars($cobro['titular'] ?? 'No registrado'); ?></p>
                            <p><strong>Banco:</strong> <?php echo htmlspecialchars($cobro['banco'] ?? 'No registrado'); ?></p>
                            <p><strong>Cuenta:</strong> <?php echo htmlspecialchars($cobro['numero_cuenta'] ?? 'No registrada'); ?></p>
                            <p><strong>Actualizado:</strong> <?php echo htmlspecialchars($cobro['fecha_actualizacion'] ?? 'Sin datos'); ?></p>
                            <?php if (!empty($cobro['qr_cobro'])): ?>
                                <div class="mt-4">
                                    <div class="metric-label mb-2">QR registrado</div>
                                    <img src="<?php echo HTTP_BASE . '/' . ltrim($cobro['qr_cobro'], '/'); ?>" alt="QR de cobro" style="max-width:260px;border-radius:18px;border:1px solid #d7e4e5;padding:8px;background:#fff;">
                                </div>
                            <?php else: ?>
                                <p class="section-note">Aún no has subido un QR de cobro.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="surface-card mt-4">
                    <div class="card-header"><h3 class="card-title">Listo para que CUPAZ te deposite</h3></div>
                    <div class="card-body table-responsive">
                        <p class="section-note">Estos pagos ya fueron liberados del escrow. El administrador debe entrar a Liquidaciones y registrar la transferencia a tus datos de cobro.</p>
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>Pago</th>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Productos</th>
                                        <th>Monto venta</th>
                                        <th>Neto estimado</th>
                                    <th>Liberado</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listosDeposito as $movimiento): ?>
                                    <tr>
                                        <td>#<?php echo (int)$movimiento['id_pago']; ?></td>
                                        <td>#<?php echo (int)$movimiento['id_pedido']; ?></td>
                                        <td><?php echo htmlspecialchars($movimiento['cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($movimiento['productos'] ?? ''); ?></td>
                                        <td><strong>Bs <?php echo number_format((float)$movimiento['monto'], 2); ?></strong></td>
                                        <td>Bs <?php echo number_format(round((float)$movimiento['monto'] * 0.90, 2), 2); ?></td>
                                        <td><?php echo htmlspecialchars($movimiento['fecha_liberacion'] ?? 'Pendiente'); ?></td>
                                        <td><span class="badge-cupaz badge-warning-soft">Pendiente de depósito</span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($listosDeposito)): ?>
                                    <tr><td colspan="8" class="helper-text">Aún no tienes pagos liberados pendientes de depósito.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="surface-card mt-4">
                    <div class="card-header"><h3 class="card-title">Pagos retenidos o en disputa</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>Pago</th>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Productos</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Fecha pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($retenidos as $movimiento): ?>
                                    <tr>
                                        <td>#<?php echo (int)$movimiento['id_pago']; ?></td>
                                        <td>#<?php echo (int)$movimiento['id_pedido']; ?></td>
                                        <td><?php echo htmlspecialchars($movimiento['cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($movimiento['productos'] ?? ''); ?></td>
                                        <td><strong>Bs <?php echo number_format((float)$movimiento['monto'], 2); ?></strong></td>
                                        <td><span class="badge-cupaz badge-warning-soft"><?php echo htmlspecialchars($movimiento['estado']); ?></span></td>
                                        <td><?php echo htmlspecialchars($movimiento['fecha_pago'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($retenidos)): ?>
                                    <tr><td colspan="7" class="helper-text">No tienes pagos retenidos en este momento.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="surface-card mt-4">
                    <div class="card-header"><h3 class="card-title">Historial de depósitos</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>Liquidación</th>
                                    <th>Pago</th>
                                    <th>Pedido</th>
                                    <th>Monto</th>
                                    <th>Referencia</th>
                                    <th>Admin</th>
                                    <th>Fecha</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($liquidaciones as $liquidacion): ?>
                                    <tr>
                                        <td>#<?php echo (int)$liquidacion['id_liquidacion']; ?></td>
                                        <td>#<?php echo (int)$liquidacion['id_pago']; ?></td>
                                        <td>#<?php echo (int)$liquidacion['id_pedido']; ?></td>
                                        <td><strong>Bs <?php echo number_format((float)($liquidacion['monto_vendedor'] ?? $liquidacion['monto']), 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($liquidacion['referencia_liquidacion'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($liquidacion['admin'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($liquidacion['fecha_registro'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($liquidacion['observaciones'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($liquidaciones)): ?>
                                    <tr><td colspan="8" class="helper-text">Aún no tienes depósitos registrados por administración.</td></tr>
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
