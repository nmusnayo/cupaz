<?php
require_once ROOT_DIR . '/model/VentaModel.php';
require_once ROOT_DIR . '/model/EntregaQrModel.php';

$ventaModel = new VentaModel();
$entregaQrModel = new EntregaQrModel();
$idVendedor = (int)($_SESSION['login']['id_usuario'] ?? 0);
$mensajeError = '';
$mensajeExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'marcar_enviado') {
        $resultado = $ventaModel->actualizarEstadoEnvio((int)($_POST['id_envio'] ?? 0), $idVendedor, 'ENVIADO');
        if (!empty($resultado['ESTADO'])) {
            $entregaQrModel->generarParaPedido((int)($_POST['id_pedido'] ?? 0), $idVendedor);
        }
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Envío actualizado a ENVIADO y QR de entrega generado.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo actualizar el envío.') : '';
    } elseif ($accion === 'marcar_entregado') {
        $resultado = $ventaModel->actualizarEstadoEnvio((int)($_POST['id_envio'] ?? 0), $idVendedor, 'ENTREGADO');
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Envío actualizado a ENTREGADO.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo actualizar el envío.') : '';
    } elseif ($accion === 'generar_qr') {
        $resultado = $entregaQrModel->generarParaPedido((int)($_POST['id_pedido'] ?? 0), $idVendedor);
        $mensajeExito = !empty($resultado['ESTADO']) ? 'QR de entrega generado correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo generar el QR.') : '';
    }
}

$ventas = $ventaModel->listarPorVendedor($idVendedor)['DATA'] ?? [];
$qrs = $entregaQrModel->listarPorVendedor($idVendedor)['DATA'] ?? [];
$qrsPorPedido = [];
foreach ($qrs as $qr) {
    $qrsPorPedido[(int)$qr['id_pedido']] = $qr;
}
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-receipt"></i> Espacio vendedor</span>
                    <h1 class="page-title">Mis ventas y envíos</h1>
                    <p class="page-subtitle">Gestiona los pedidos donde participas como vendedor, registra el envío y deja el seguimiento listo hasta la entrega.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Ventas activas</div>
                        <div class="metric-value"><?php echo count($ventas); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Gestión</div>
                        <div class="metric-value" style="font-size:20px;">Envíos</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Cobro</div>
                        <div class="metric-value" style="font-size:20px;">Escrow</div>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="card-header"><h3 class="card-title">Pedidos por atender</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Productos</th>
                                    <th>Monto vendedor</th>
                                    <th>Estado envío</th>
                                    <th>Estado pedido</th>
                                    <th>Dirección</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas as $venta): ?>
                                    <tr>
                                        <td><strong>#<?php echo (int)$venta['id_pedido']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($venta['cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($venta['productos'] ?? ''); ?></td>
                                        <td><strong>Bs <?php echo number_format((float)$venta['monto_vendedor'], 2); ?></strong></td>
                                        <td><span class="badge-cupaz <?php echo ($venta['estado_envio'] ?? '') === 'ENTREGADO' ? 'badge-soft' : 'badge-warning-soft'; ?>"><?php echo htmlspecialchars($venta['estado_envio']); ?></span></td>
                                        <td><span class="badge-cupaz <?php echo ($venta['estado_pedido'] ?? '') === 'COMPLETADO' ? 'badge-soft' : 'badge-warning-soft'; ?>"><?php echo htmlspecialchars($venta['estado_pedido']); ?></span></td>
                                        <td><?php echo htmlspecialchars($venta['direccion_entrega'] ?? ''); ?></td>
                                        <td>
                                            <?php if (($venta['estado_envio'] ?? '') === 'PENDIENTE' && (int)$venta['id_envio'] > 0): ?>
                                                <form method="post" style="display:inline-block;">
                                                    <input type="hidden" name="accion" value="marcar_enviado">
                                                    <input type="hidden" name="id_envio" value="<?php echo (int)$venta['id_envio']; ?>">
                                                    <input type="hidden" name="id_pedido" value="<?php echo (int)$venta['id_pedido']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-cupaz-primary">
                                                        <i class="fas fa-shipping-fast mr-1"></i>Marcar enviado
                                                    </button>
                                                </form>
                                            <?php elseif (($venta['estado_envio'] ?? '') === 'ENVIADO'): ?>
                                                <form method="post" style="display:inline-block;">
                                                    <input type="hidden" name="accion" value="marcar_entregado">
                                                    <input type="hidden" name="id_envio" value="<?php echo (int)$venta['id_envio']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-cupaz-outline">
                                                        <i class="fas fa-check mr-1"></i>Marcar entregado
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="helper-text">Sin acciones pendientes</span>
                                            <?php endif; ?>
                                            <?php $qrPedido = $qrsPorPedido[(int)$venta['id_pedido']] ?? null; ?>
                                            <?php if (!in_array(($venta['estado_envio'] ?? ''), ['PENDIENTE', 'PENDIENTE_PAGO'], true)): ?>
                                                <?php if ($qrPedido === null): ?>
                                                    <form method="post" style="display:inline-block;">
                                                        <input type="hidden" name="accion" value="generar_qr">
                                                        <input type="hidden" name="id_pedido" value="<?php echo (int)$venta['id_pedido']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-cupaz-outline">
                                                            <i class="fas fa-qrcode mr-1"></i>Generar QR
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-cupaz-outline btn-ver-qr"
                                                        data-toggle="modal"
                                                        data-target="#modalEntregaQr"
                                                        data-pedido="<?php echo (int)$venta['id_pedido']; ?>"
                                                        data-estado="<?php echo htmlspecialchars($qrPedido['estado'] ?? '', ENT_QUOTES); ?>"
                                                        data-url="<?php echo htmlspecialchars(HTTP_BASE . '/entrega?token=' . $qrPedido['token'], ENT_QUOTES); ?>">
                                                        <i class="fas fa-qrcode mr-1"></i>Ver QR
                                                    </button>
                                                <?php endif; ?>
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

<div class="modal fade" id="modalEntregaQr" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR de confirmación de entrega</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="section-note">Incluye este código con el paquete o compártelo al cliente. Al escanearlo y confirmar la recepción, el sistema registrará la evidencia y liberará el pago.</p>
                <div style="display:flex;justify-content:center;margin:18px 0;">
                    <div id="qrEntregaWrap" style="background:#fff;padding:18px;border-radius:18px;border:1px solid #d7e4e5;"></div>
                </div>
                <div class="actions-bar">
                    <div>
                        <div class="metric-label">Pedido</div>
                        <div id="qrEntregaPedido" class="metric-value" style="font-size:22px;">#0</div>
                    </div>
                    <div>
                        <div class="metric-label">Estado</div>
                        <div id="qrEntregaEstado" class="metric-value" style="font-size:22px;">-</div>
                    </div>
                </div>
                <input type="text" id="qrEntregaUrl" class="form-control" readonly>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('qrEntregaWrap');
    document.querySelectorAll('.btn-ver-qr').forEach(function (button) {
        button.addEventListener('click', function () {
            const pedido = this.getAttribute('data-pedido') || '0';
            const estado = this.getAttribute('data-estado') || '-';
            const url = this.getAttribute('data-url') || '';

            document.getElementById('qrEntregaPedido').textContent = '#' + pedido;
            document.getElementById('qrEntregaEstado').textContent = estado;
            document.getElementById('qrEntregaUrl').value = url;

            wrap.innerHTML = '';
            new QRCode(wrap, {
                text: url,
                width: 220,
                height: 220,
                colorDark: '#183b40',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    });
});
</script>
<?php require ROOT_VIEW . '/template/footer.php'; ?>
