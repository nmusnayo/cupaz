<?php
require_once ROOT_DIR . '/model/PedidoModel.php';

$pedidoModel = new PedidoModel();
$pedidos = $pedidoModel->listarTodos()['DATA'] ?? [];
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
                                    <th>Total</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $pedido): ?>
                                    <?php $estado = strtoupper($pedido['estado'] ?? ''); ?>
                                    <tr>
                                        <td><strong>#<?php echo (int)$pedido['id_pedido']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($pedido['cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($pedido['productos'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($pedido['vendedores'] ?? ''); ?></td>
                                        <td><span class="badge-cupaz <?php echo $estado === 'COMPLETADO' ? 'badge-soft' : 'badge-warning-soft'; ?>"><?php echo htmlspecialchars($pedido['estado']); ?></span></td>
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
