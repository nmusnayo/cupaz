<?php
require_once ROOT_DIR . '/model/PedidoModel.php';
require_once ROOT_DIR . '/model/PagoModel.php';
require_once ROOT_DIR . '/model/AdminQrPagoModel.php';

$pedidoModel = new PedidoModel();
$pagoModel = new PagoModel();
$adminQrModel = new AdminQrPagoModel();
$idCliente = (int)($_SESSION['login']['id_usuario'] ?? 0);
$rolActual = strtoupper($_SESSION['login']['rol'] ?? '');
$mensajeError = '';
$mensajeExito = '';
$qrAdmin = $adminQrModel->obtenerActivo()['DATA'][0] ?? null;

function guardarComprobantePagoCliente($file)
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
        'application/pdf' => 'pdf',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        return null;
    }
    $uploadDir = ROOT_UPLOAD . '/comprobantes';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = 'comprobante_pago_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMime[$mime];
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }
    return 'uploads/comprobantes/' . $fileName;
}

function guardarEvidenciaRecepcionCliente($file)
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
        'application/pdf' => 'pdf',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        return null;
    }
    $uploadDir = ROOT_UPLOAD . '/evidencias';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = 'evidencia_recepcion_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMime[$mime];
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }
    return 'uploads/evidencias/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idPedido = (int)($_POST['id_pedido'] ?? 0);

    if ($accion === 'confirmar_pago') {
        $comprobante = guardarComprobantePagoCliente($_FILES['comprobante_pago'] ?? []);
        if ($qrAdmin === null) {
            $resultado = ['ESTADO' => false, 'ERROR' => 'CUPAZ aún no tiene un QR de pago activo.'];
        } elseif ($comprobante === null) {
            $resultado = ['ESTADO' => false, 'ERROR' => 'Debes subir un comprobante válido en imagen o PDF.'];
        } else {
            $resultado = $pedidoModel->confirmarPagoPedido(
                $idPedido,
                $idCliente,
                'QR_CUPAZ_ADMIN',
                $comprobante,
                trim($_POST['referencia_cliente'] ?? ''),
                (int)$qrAdmin['id_qr']
            );
        }
        if (!empty($resultado['ESTADO'])) {
            $mensajeExito = 'Comprobante enviado correctamente. CUPAZ verificará el pago antes de habilitar el envío.';
        } else {
            $mensajeError = $resultado['ERROR'] ?? 'No se pudo registrar el pago.';
        }
    } elseif ($accion === 'confirmar_recepcion') {
        $evidencia = guardarEvidenciaRecepcionCliente($_FILES['evidencia_recepcion'] ?? []);
        if ($evidencia === null) {
            $resultado = ['ESTADO' => false, 'ERROR' => 'Debes subir una evidencia válida en imagen o PDF.'];
        } else {
            $resultado = $pedidoModel->confirmarRecepcionPedido($idPedido, $idCliente, $evidencia);
        }
        if (!empty($resultado['ESTADO'])) {
            $mensajeExito = 'Recepción confirmada. El pago retenido fue liberado a los vendedores.';
        } else {
            $mensajeError = $resultado['ERROR'] ?? 'No se pudo confirmar la recepción.';
        }
    }
}

$pedidos = $rolActual === 'ADMIN'
    ? ($pedidoModel->listarTodos()['DATA'] ?? [])
    : ($pedidoModel->listarPorCliente($idCliente)['DATA'] ?? []);
$pagosPorPedido = [];
foreach ($pedidos as $pedido) {
    $pagosPorPedido[(int)$pedido['id_pedido']] = $pagoModel->listarPorPedido((int)$pedido['id_pedido'])['DATA'] ?? [];
}
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-truck"></i> Seguimiento</span>
                    <h1 class="page-title"><?php echo $rolActual === 'ADMIN' ? 'Pedidos del sistema' : 'Mis pedidos'; ?></h1>
                    <p class="page-subtitle">Consulta el historial de compras, genera el QR de pago, registra la retención en escrow y confirma la recepción para liberar fondos.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Pedidos</div>
                        <div class="metric-value"><?php echo count($pedidos); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Pago operativo</div>
                        <div class="metric-value" style="font-size:20px;">QR escaneable</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Protección</div>
                        <div class="metric-value" style="font-size:20px;">Escrow activo</div>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="card-header"><h3 class="card-title">Historial de pedidos</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Productos</th>
                                    <th>Vendedores</th>
                                    <th>Estado</th>
                                    <th>Total</th>
                                        <th>En revisión</th>
                                        <th>Retenido</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php
                                    $estado = strtoupper($pedido['estado'] ?? '');
                                    $claseEstado = in_array($estado, ['EN_DISPUTA', 'REEMBOLSADO', 'CANCELADO'], true) ? 'badge-danger-soft' : ($estado === 'COMPLETADO' ? 'badge-soft' : 'badge-warning-soft');
                                    $payloadQr = json_encode([
                                        'sistema' => 'CUPAZ',
                                        'pedido' => (int)$pedido['id_pedido'],
                                        'cliente' => $idCliente,
                                        'monto' => number_format((float)$pedido['monto_total'], 2, '.', ''),
                                        'moneda' => 'BOB',
                                        'concepto' => 'Pago pedido CUPAZ'
                                    ], JSON_UNESCAPED_UNICODE);
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo (int)$pedido['id_pedido']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($pedido['productos'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($pedido['vendedores'] ?? ''); ?></td>
                                        <td><span class="badge-cupaz <?php echo $claseEstado; ?>"><?php echo htmlspecialchars($pedido['estado']); ?></span></td>
                                        <td><strong>Bs <?php echo number_format((float)$pedido['monto_total'], 2); ?></strong></td>
                                        <td>Bs <?php echo number_format((float)($pedido['total_por_verificar'] ?? 0), 2); ?></td>
                                        <td>Bs <?php echo number_format((float)($pedido['total_retenido'] ?? 0), 2); ?></td>
                                        <td><?php echo htmlspecialchars($pedido['fecha_pedido']); ?></td>
                                        <td>
                                            <?php if ($rolActual !== 'ADMIN' && $estado === 'PENDIENTE_PAGO'): ?>
                                                <button type="button" class="btn btn-sm btn-cupaz-primary btn-pagar-qr"
                                                    data-toggle="modal"
                                                    data-target="#modalQrPago"
                                                    data-pedido="<?php echo (int)$pedido['id_pedido']; ?>"
                                                    data-monto="<?php echo number_format((float)$pedido['monto_total'], 2, '.', ''); ?>"
                                                    data-productos="<?php echo htmlspecialchars($pedido['productos'] ?? '', ENT_QUOTES); ?>"
                                                    data-qr='<?php echo htmlspecialchars($payloadQr, ENT_QUOTES); ?>'>
                                                    <i class="fas fa-qrcode mr-1"></i>Pagar
                                                </button>
                                            <?php elseif ($rolActual !== 'ADMIN' && in_array($estado, ['PAGO_RETENIDO', 'ENVIADO', 'ENTREGADO'], true)): ?>
                                                <form method="post" enctype="multipart/form-data" style="display:inline-block;min-width:220px;">
                                                    <input type="hidden" name="accion" value="confirmar_recepcion">
                                                    <input type="hidden" name="id_pedido" value="<?php echo (int)$pedido['id_pedido']; ?>">
                                                    <input type="file" name="evidencia_recepcion" class="form-control form-control-sm mb-1" accept="image/*,.pdf" required>
                                                    <button type="submit" class="btn btn-sm btn-cupaz-outline">
                                                        <i class="fas fa-check-circle mr-1"></i>Confirmar recepción
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="helper-text">Sin acciones pendientes</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($rolActual === 'ADMIN'): ?>
                    <div class="surface-card mt-4">
                        <div class="card-header"><h3 class="card-title">Pagos asociados</h3></div>
                        <div class="card-body table-responsive">
                            <table class="table table-cupaz table-hover">
                                <thead>
                                    <tr>
                                        <th>Pedido</th>
                                        <th>Vendedor</th>
                                        <th>Referencia</th>
                                        <th>Monto</th>
                                        <th>Estado</th>
                                        <th>Método</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagosPorPedido as $idPedido => $pagos): ?>
                                        <?php foreach ($pagos as $pago): ?>
                                            <tr>
                                                <td>#<?php echo (int)$idPedido; ?></td>
                                                <td><?php echo htmlspecialchars($pago['vendedor'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($pago['referencia_pago'] ?? ''); ?></td>
                                                <td>Bs <?php echo number_format((float)$pago['monto'], 2); ?></td>
                                                <td><span class="badge-cupaz <?php echo (($pago['estado'] ?? '') === 'LIBERADO') ? 'badge-soft' : 'badge-warning-soft'; ?>"><?php echo htmlspecialchars($pago['estado']); ?></span></td>
                                                <td><?php echo htmlspecialchars($pago['metodo_pago'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalQrPago" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="confirmar_pago">
                <input type="hidden" name="id_pedido" id="qrPedidoId" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Pago con QR</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="section-note">Escanea el QR de CUPAZ, realiza el pago y adjunta tu comprobante. El administrador verificará el depósito antes de retener el monto para el vendedor.</p>
                    <div style="display:flex;justify-content:center;margin:18px 0;">
                        <?php if (!empty($qrAdmin['qr_pago'])): ?>
                            <img src="<?php echo HTTP_BASE . '/' . ltrim($qrAdmin['qr_pago'], '/'); ?>" alt="QR CUPAZ" style="max-width:260px;border-radius:18px;border:1px solid #d7e4e5;padding:8px;background:#fff;">
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">CUPAZ aún no registró un QR de pago.</div>
                        <?php endif; ?>
                    </div>
                    <div class="actions-bar">
                        <div>
                            <div class="metric-label">Pedido</div>
                            <div id="qrPedidoTexto" class="metric-value" style="font-size:22px;">#0</div>
                        </div>
                        <div>
                            <div class="metric-label">Monto</div>
                            <div id="qrMontoTexto" class="metric-value" style="font-size:22px;">Bs 0.00</div>
                        </div>
                    </div>
                    <p><strong>Concepto:</strong> <span id="qrProductosTexto">-</span></p>
                    <p><strong>Titular QR:</strong> <?php echo htmlspecialchars($qrAdmin['titular'] ?? 'No configurado'); ?></p>
                    <div class="form-group">
                        <label>Referencia de pago</label>
                        <input type="text" name="referencia_cliente" class="form-control" placeholder="Ej: número de transacción">
                    </div>
                    <div class="form-group">
                        <label>Comprobante de pago</label>
                        <input type="file" name="comprobante_pago" class="form-control" accept="image/*,.pdf" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-cupaz-primary" <?php echo empty($qrAdmin['qr_pago']) ? 'disabled' : ''; ?>>Enviar comprobante</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-pagar-qr').forEach(function (button) {
        button.addEventListener('click', function () {
            const pedido = this.getAttribute('data-pedido') || '0';
            const monto = this.getAttribute('data-monto') || '0.00';
            const productos = this.getAttribute('data-productos') || '-';

            document.getElementById('qrPedidoId').value = pedido;
            document.getElementById('qrPedidoTexto').textContent = '#' + pedido;
            document.getElementById('qrMontoTexto').textContent = 'Bs ' + monto;
            document.getElementById('qrProductosTexto').textContent = productos;
        });
    });
});
</script>
<?php require ROOT_VIEW . '/template/footer.php'; ?>
