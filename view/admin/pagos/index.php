<?php
require_once ROOT_DIR . '/model/PagoModel.php';
require_once ROOT_DIR . '/model/LiquidacionModel.php';
require_once ROOT_DIR . '/model/AdminQrPagoModel.php';

$pagoModel = new PagoModel();
$liquidacionModel = new LiquidacionModel();
$adminQrModel = new AdminQrPagoModel();
$idAdmin = (int)($_SESSION['login']['id_usuario'] ?? 0);
$mensajeError = '';
$mensajeExito = '';

function guardarQrPagoAdmin($file)
{
    if (!isset($file['tmp_name']) || $file['tmp_name'] === '' || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
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
    $uploadDir = ROOT_UPLOAD . '/admin_pagos';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = 'qr_pago_admin_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMime[$mime];
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }
    return 'uploads/admin_pagos/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idPago = (int)($_POST['id_pago'] ?? 0);
    $observacion = trim($_POST['observacion'] ?? '');

    if ($accion === 'guardar_qr_admin') {
        $qrPago = guardarQrPagoAdmin($_FILES['qr_pago'] ?? []);
        $titular = trim($_POST['titular'] ?? '');
        if ($titular === '' || $qrPago === null) {
            $mensajeError = 'Debes indicar el titular y subir una imagen QR válida.';
        } else {
            $resultado = $adminQrModel->guardar($idAdmin, $titular, $qrPago, trim($_POST['observaciones'] ?? ''));
            $mensajeExito = !empty($resultado['ESTADO']) ? 'QR de pago CUPAZ actualizado correctamente.' : '';
            $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo guardar el QR de pago.') : '';
        }
    } elseif ($accion === 'verificar_pago') {
        $resultado = $pagoModel->verificarPago($idPago, $idAdmin, $observacion);
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Pago verificado. El monto quedó retenido y el vendedor ya puede preparar el envío.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo verificar el pago.') : '';
    } elseif ($accion === 'liberar_pago') {
        $resultado = $pagoModel->liberarPago($idPago, $idAdmin, $observacion);
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Pago liberado dentro del escrow. Ahora queda pendiente de liquidación al vendedor.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo liberar el pago.') : '';
    } elseif ($accion === 'reembolsar_pago') {
        $resultado = $pagoModel->reembolsarPago($idPago, $idAdmin, $observacion);
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Pago marcado como reembolsado.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo reembolsar el pago.') : '';
    } elseif ($accion === 'cancelar_pago') {
        $resultado = $pagoModel->cancelarPago($idPago, $idAdmin, $observacion);
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Pago cancelado correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo cancelar el pago.') : '';
    }
}

$qrAdmin = $adminQrModel->obtenerActivo()['DATA'][0] ?? null;
$pagos = $pagoModel->listarTodos()['DATA'] ?? [];
$liquidados = [];
foreach ($liquidacionModel->listarTodas()['DATA'] ?? [] as $liquidacion) {
    $liquidados[(int)$liquidacion['id_pago']] = $liquidacion;
}
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-qrcode"></i> Administración</span>
                    <h1 class="page-title">Pagos y escrow</h1>
                    <p class="page-subtitle">Supervisa los pagos retenidos, su liberación dentro del escrow y si ya fueron liquidados realmente al vendedor.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Movimientos</div>
                        <div class="metric-value"><?php echo count($pagos); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Modalidad</div>
                        <div class="metric-value" style="font-size:20px;">QR operativo</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Protección</div>
                        <div class="metric-value" style="font-size:20px;">Retención</div>
                    </div>
                </div>

                <div class="module-grid mb-4">
                    <div class="surface-card">
                        <div class="card-header"><h3 class="card-title">QR para recibir pagos</h3></div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" class="cupaz-form">
                                <input type="hidden" name="accion" value="guardar_qr_admin">
                                <div class="form-group">
                                    <label>Titular</label>
                                    <input type="text" name="titular" class="form-control" value="<?php echo htmlspecialchars($qrAdmin['titular'] ?? 'CUPAZ'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>QR de pago CUPAZ</label>
                                    <input type="file" name="qr_pago" class="form-control" accept="image/*" required>
                                </div>
                                <div class="form-group">
                                    <label>Observaciones</label>
                                    <textarea name="observaciones" class="form-control"><?php echo htmlspecialchars($qrAdmin['observaciones'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-cupaz-primary btn-block">
                                    <i class="fas fa-save mr-2"></i>Guardar QR
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="surface-card">
                        <div class="card-header"><h3 class="card-title">QR activo</h3></div>
                        <div class="card-body">
                            <?php if (!empty($qrAdmin['qr_pago'])): ?>
                                <p><strong>Titular:</strong> <?php echo htmlspecialchars($qrAdmin['titular'] ?? ''); ?></p>
                                <p><strong>Actualizado:</strong> <?php echo htmlspecialchars($qrAdmin['fecha_actualizacion'] ?? ''); ?></p>
                                <img src="<?php echo HTTP_BASE . '/' . ltrim($qrAdmin['qr_pago'], '/'); ?>" alt="QR CUPAZ" style="max-width:260px;border-radius:18px;border:1px solid #d7e4e5;padding:8px;background:#fff;">
                            <?php else: ?>
                                <p class="section-note">Aún no hay QR activo para que los clientes paguen a CUPAZ.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="card-header"><h3 class="card-title">Pagos registrados</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>Pago</th>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Referencia</th>
                                    <th>Comprobante</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Liquidación</th>
                                    <th>Método</th>
                                    <th>Acciones escrow</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $pago): ?>
                                    <?php $estadoPago = strtoupper($pago['estado'] ?? ''); ?>
                                    <tr>
                                        <td>#<?php echo (int)$pago['id_pago']; ?></td>
                                        <td>#<?php echo (int)$pago['id_pedido']; ?></td>
                                        <td><?php echo htmlspecialchars($pago['cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($pago['vendedor'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($pago['referencia_pago'] ?? ''); ?></td>
                                        <td>
                                            <?php if (!empty($pago['comprobante_pago'])): ?>
                                                <a href="<?php echo HTTP_BASE . '/' . ltrim($pago['comprobante_pago'], '/'); ?>" target="_blank" class="btn btn-sm btn-cupaz-outline">
                                                    <i class="fas fa-file-alt mr-1"></i>Ver
                                                </a>
                                                <div class="helper-text"><?php echo htmlspecialchars($pago['referencia_cliente'] ?? ''); ?></div>
                                            <?php else: ?>
                                                <span class="helper-text">Sin comprobante</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>Bs <?php echo number_format((float)$pago['monto'], 2); ?></strong></td>
                                        <td><span class="badge-cupaz <?php echo $estadoPago === 'LIBERADO' ? 'badge-soft' : ($estadoPago === 'REEMBOLSADO' ? 'badge-danger-soft' : 'badge-warning-soft'); ?>"><?php echo htmlspecialchars($pago['estado']); ?></span></td>
                                        <td>
                                            <?php if (!empty($liquidados[(int)$pago['id_pago']])): ?>
                                                <span class="badge-cupaz badge-soft">Pagado al vendedor</span><br>
                                                <span class="helper-text"><?php echo htmlspecialchars($liquidados[(int)$pago['id_pago']]['referencia_liquidacion'] ?? ''); ?></span>
                                            <?php elseif ($estadoPago === 'LIBERADO'): ?>
                                                <span class="badge-cupaz badge-warning-soft">Pendiente de liquidar</span>
                                            <?php else: ?>
                                                <span class="helper-text">No aplica aún</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $metodo = $pago['metodo_pago'] ?? '';
                                            echo htmlspecialchars($metodo === 'SIMULADO' ? 'QR CUPAZ registrado' : $metodo);
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($estadoPago === 'POR_VERIFICAR'): ?>
                                                <form method="post" class="mb-1">
                                                    <input type="hidden" name="accion" value="verificar_pago">
                                                    <input type="hidden" name="id_pago" value="<?php echo (int)$pago['id_pago']; ?>">
                                                    <input type="hidden" name="observacion" value="Pago verificado contra comprobante del cliente">
                                                    <button type="submit" class="btn btn-sm btn-cupaz-primary">
                                                        <i class="fas fa-check mr-1"></i>Verificar
                                                    </button>
                                                </form>
                                                <form method="post">
                                                    <input type="hidden" name="accion" value="cancelar_pago">
                                                    <input type="hidden" name="id_pago" value="<?php echo (int)$pago['id_pago']; ?>">
                                                    <input type="hidden" name="observacion" value="Comprobante rechazado por administración">
                                                    <button type="submit" class="btn btn-sm btn-cupaz-outline">
                                                        <i class="fas fa-ban mr-1"></i>Rechazar
                                                    </button>
                                                </form>
                                            <?php elseif (in_array($estadoPago, ['RETENIDO', 'EN_DISPUTA'], true)): ?>
                                                <form method="post" class="mb-1">
                                                    <input type="hidden" name="accion" value="liberar_pago">
                                                    <input type="hidden" name="id_pago" value="<?php echo (int)$pago['id_pago']; ?>">
                                                    <input type="hidden" name="observacion" value="Liberación manual desde administración">
                                                    <button type="submit" class="btn btn-sm btn-cupaz-primary">
                                                        <i class="fas fa-unlock mr-1"></i>Liberar
                                                    </button>
                                                </form>
                                                <form method="post" class="mb-1">
                                                    <input type="hidden" name="accion" value="reembolsar_pago">
                                                    <input type="hidden" name="id_pago" value="<?php echo (int)$pago['id_pago']; ?>">
                                                    <input type="hidden" name="observacion" value="Reembolso manual desde administración">
                                                    <button type="submit" class="btn btn-sm btn-cupaz-outline">
                                                        <i class="fas fa-undo mr-1"></i>Reembolsar
                                                    </button>
                                                </form>
                                                <form method="post">
                                                    <input type="hidden" name="accion" value="cancelar_pago">
                                                    <input type="hidden" name="id_pago" value="<?php echo (int)$pago['id_pago']; ?>">
                                                    <input type="hidden" name="observacion" value="Cancelación manual desde administración">
                                                    <button type="submit" class="btn btn-sm btn-cupaz-outline">
                                                        <i class="fas fa-ban mr-1"></i>Cancelar
                                                    </button>
                                                </form>
                                            <?php elseif ($estadoPago === 'LIBERADO'): ?>
                                                <?php if (!empty($liquidados[(int)$pago['id_pago']])): ?>
                                                    <span class="helper-text">Liquidación registrada</span>
                                                <?php else: ?>
                                                    <a href="<?php echo HTTP_BASE; ?>/admin/liquidaciones" class="btn btn-sm btn-cupaz-primary">
                                                        <i class="fas fa-wallet mr-1"></i>Pagar al vendedor
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="helper-text">Sin acciones</span>
                                            <?php endif; ?>
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
