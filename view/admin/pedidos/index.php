<?php
require_once ROOT_DIR . '/model/PedidoModel.php';

$pedidos = [];
$mensajeError = '';
try {
    $pedidoModel = new PedidoModel();
    $resultadoPedidos = $pedidoModel->listarTodos();
    if (!empty($resultadoPedidos['ESTADO'])) {
        $pedidos = $resultadoPedidos['DATA'] ?? [];
    } else {
        $mensajeError = $resultadoPedidos['ERROR'] ?? 'No se pudieron cargar los pedidos.';
    }
} catch (Exception $e) {
    $mensajeError = $e->getMessage();
}
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-clipboard-list"></i> Administración</span>
                    <h1 class="page-title">Pedidos del sistema</h1>
                    <p class="page-subtitle">Vista global para supervisar pedidos, clientes, vendedores y el estado del flujo multivendedor.</p>
                </div>

                <?php if ($mensajeError !== ''): ?>
                    <div class="alert alert-danger">
                        No se pudieron cargar los pedidos: <?php echo htmlspecialchars($mensajeError); ?>
                    </div>
                <?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Pedidos registrados</div>
                        <div class="metric-value"><?php echo count($pedidos); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Cobertura</div>
                        <div class="metric-value" style="font-size:20px;">General</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Supervisión</div>
                        <div class="metric-value" style="font-size:20px;">Activa</div>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="card-header"><h3 class="card-title">Pedidos</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Productos</th>
                                    <th>Vendedores</th>
                                    <th>Estado</th>
                                    <th>Verificacion QR</th>
                                    <th>Total</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php $estado = strtoupper($pedido['estado'] ?? ''); ?>
                                    <?php
                                    $qrConfirmado = (int)($pedido['qr_confirmado'] ?? 0) === 1;
                                    $estadoQrEntrega = strtoupper($pedido['estado_qr_entrega'] ?? '');
                                    $evidenciaRecepcion = trim($pedido['evidencia_recepcion'] ?? '');
                                    $evidenciaRecepcionQr = trim($pedido['evidencia_recepcion_qr'] ?? '');
                                    $evidenciaVisible = $evidenciaRecepcionQr !== '' ? $evidenciaRecepcionQr : $evidenciaRecepcion;
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo (int)$pedido['id_pedido']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($pedido['cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($pedido['productos'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($pedido['vendedores'] ?? ''); ?></td>
                                        <td><span class="badge-cupaz <?php echo $estado === 'COMPLETADO' ? 'badge-soft' : 'badge-warning-soft'; ?>"><?php echo htmlspecialchars($pedido['estado']); ?></span></td>
                                        <td>
                                            <?php if ($qrConfirmado): ?>
                                                <span class="badge-cupaz badge-soft">
                                                    <i class="fas fa-check-circle mr-1"></i>QR verificado
                                                </span>
                                                <div class="helper-text mt-1">
                                                    <?php echo htmlspecialchars($pedido['fecha_verificacion_qr'] ?? ''); ?>
                                                </div>
                                                <?php if ($evidenciaVisible !== ''): ?>
                                                    <a href="<?php echo HTTP_BASE . '/' . ltrim($evidenciaVisible, '/'); ?>" target="_blank" class="btn btn-sm btn-cupaz-outline mt-2">
                                                        <i class="fas fa-image mr-1"></i>Ver evidencia
                                                    </a>
                                                <?php endif; ?>
                                            <?php elseif ($estado === 'COMPLETADO' && $evidenciaRecepcion !== ''): ?>
                                                <span class="badge-cupaz badge-warning-soft">
                                                    <i class="fas fa-exclamation-circle mr-1"></i>Recibido sin QR
                                                </span>
                                                <div class="helper-text mt-1">
                                                    El pedido fue completado por confirmacion manual.
                                                </div>
                                                <a href="<?php echo HTTP_BASE . '/' . ltrim($evidenciaRecepcion, '/'); ?>" target="_blank" class="btn btn-sm btn-cupaz-outline mt-2">
                                                    <i class="fas fa-image mr-1"></i>Ver evidencia
                                                </a>
                                            <?php elseif ($estadoQrEntrega !== ''): ?>
                                                <span class="badge-cupaz badge-warning-soft"><?php echo htmlspecialchars($estadoQrEntrega); ?></span>
                                            <?php else: ?>
                                                <span class="badge-cupaz badge-warning-soft">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>Bs <?php echo number_format((float)$pedido['monto_total'], 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($pedido['fecha_pedido']); ?></td>
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
