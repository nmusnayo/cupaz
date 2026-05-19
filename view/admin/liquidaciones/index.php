<?php
require_once ROOT_DIR . '/model/PagoModel.php';
require_once ROOT_DIR . '/model/LiquidacionModel.php';

$pagoModel = new PagoModel();
$liquidacionModel = new LiquidacionModel();
$idAdmin = (int)($_SESSION['login']['id_usuario'] ?? 0);
$mensajeError = '';
$mensajeExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar_liquidacion') {
    $resultado = $liquidacionModel->registrar(
        (int)($_POST['id_pago'] ?? 0),
        (int)($_POST['id_vendedor'] ?? 0),
        $idAdmin,
        (float)($_POST['monto'] ?? 0),
        trim($_POST['referencia_liquidacion'] ?? ''),
        trim($_POST['observaciones'] ?? '')
    );
    $mensajeExito = !empty($resultado['ESTADO']) ? 'Liquidación registrada correctamente.' : '';
    $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo registrar la liquidación.') : '';
}

$pagos = $pagoModel->listarParaLiquidacion()['DATA'] ?? [];
$liquidaciones = $liquidacionModel->listarTodas()['DATA'] ?? [];
$pendientes = array_values(array_filter($pagos, function ($pago) {
    return empty($pago['id_liquidacion']);
}));
$comisionPorcentaje = LiquidacionModel::COMISION_PORCENTAJE;
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-wallet"></i> Administración</span>
                    <h1 class="page-title">Liquidaciones a vendedores</h1>
                    <p class="page-subtitle">Aquí CUPAZ gestiona el pago real al vendedor una vez que el pedido ya fue liberado dentro del escrow.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Pagos liberados</div>
                        <div class="metric-value"><?php echo count($pagos); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Pendientes de liquidar</div>
                        <div class="metric-value"><?php echo count($pendientes); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Liquidados</div>
                        <div class="metric-value"><?php echo count($liquidaciones); ?></div>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="card-header"><h3 class="card-title">Pendientes de pago al vendedor</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>Pago</th>
                                    <th>Pedido</th>
                                    <th>Vendedor</th>
                                    <th>Monto</th>
                                    <th>Comisión CUPAZ</th>
                                    <th>Neto vendedor</th>
                                    <th>Destino</th>
                                    <th>QR</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pagos as $pago): ?>
                                    <tr>
                                        <td>#<?php echo (int)$pago['id_pago']; ?></td>
                                        <td>#<?php echo (int)$pago['id_pedido']; ?></td>
                                        <td><?php echo htmlspecialchars($pago['vendedor'] ?? ''); ?></td>
                                        <?php
                                        $montoBruto = (float)$pago['monto'];
                                        $montoComision = round($montoBruto * ($comisionPorcentaje / 100), 2);
                                        $montoVendedor = round($montoBruto - $montoComision, 2);
                                        ?>
                                        <td><strong>Bs <?php echo number_format($montoBruto, 2); ?></strong></td>
                                        <td>Bs <?php echo number_format($montoComision, 2); ?> <span class="helper-text">(<?php echo number_format($comisionPorcentaje, 2); ?>%)</span></td>
                                        <td><strong>Bs <?php echo number_format($montoVendedor, 2); ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($pago['titular'] ?? 'Sin titular'); ?></strong><br>
                                            <span class="helper-text"><?php echo htmlspecialchars($pago['banco'] ?? 'Sin banco'); ?><?php echo !empty($pago['numero_cuenta']) ? ' / ' . htmlspecialchars($pago['numero_cuenta']) : ''; ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($pago['qr_cobro'])): ?>
                                                <a href="<?php echo HTTP_BASE . '/' . ltrim($pago['qr_cobro'], '/'); ?>" target="_blank" class="btn btn-sm btn-cupaz-outline">
                                                    <i class="fas fa-image mr-1"></i>Ver QR
                                                </a>
                                            <?php else: ?>
                                                <span class="helper-text">Sin QR</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($pago['id_liquidacion'])): ?>
                                                <span class="badge-cupaz badge-soft">Pagado</span>
                                            <?php elseif (!empty($pago['qr_cobro'])): ?>
                                                <button type="button" class="btn btn-sm btn-cupaz-primary" data-toggle="modal" data-target="#modalLiquidar<?php echo (int)$pago['id_pago']; ?>">
                                                    <i class="fas fa-hand-holding-usd mr-1"></i>Liquidar
                                                </button>
                                            <?php else: ?>
                                                <span class="helper-text">El vendedor debe registrar su QR</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="surface-card mt-4">
                    <div class="card-header"><h3 class="card-title">Historial de liquidaciones</h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pago</th>
                                    <th>Vendedor</th>
                                    <th>Monto</th>
                                    <th>Comisión</th>
                                    <th>Neto vendedor</th>
                                    <th>Referencia</th>
                                    <th>Admin</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($liquidaciones as $liquidacion): ?>
                                    <tr>
                                        <td>#<?php echo (int)$liquidacion['id_liquidacion']; ?></td>
                                        <td>#<?php echo (int)$liquidacion['id_pago']; ?></td>
                                        <td><?php echo htmlspecialchars($liquidacion['vendedor'] ?? ''); ?></td>
                                        <td><strong>Bs <?php echo number_format((float)($liquidacion['monto_bruto'] ?? $liquidacion['monto']), 2); ?></strong></td>
                                        <td>Bs <?php echo number_format((float)($liquidacion['monto_comision'] ?? 0), 2); ?></td>
                                        <td><strong>Bs <?php echo number_format((float)($liquidacion['monto_vendedor'] ?? $liquidacion['monto']), 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($liquidacion['referencia_liquidacion'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($liquidacion['admin'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($liquidacion['fecha_registro']); ?></td>
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

<?php foreach ($pendientes as $pago): ?>
    <div class="modal fade" id="modalLiquidar<?php echo (int)$pago['id_pago']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post" class="cupaz-form">
                    <input type="hidden" name="accion" value="registrar_liquidacion">
                    <input type="hidden" name="id_pago" value="<?php echo (int)$pago['id_pago']; ?>">
                    <input type="hidden" name="id_vendedor" value="<?php echo (int)$pago['id_vendedor']; ?>">
                    <input type="hidden" name="monto" value="<?php echo htmlspecialchars($pago['monto']); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Liquidar pago #<?php echo (int)$pago['id_pago']; ?></h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($pago['vendedor'] ?? ''); ?></p>
                        <?php
                        $montoBruto = (float)$pago['monto'];
                        $montoComision = round($montoBruto * ($comisionPorcentaje / 100), 2);
                        $montoVendedor = round($montoBruto - $montoComision, 2);
                        ?>
                        <p><strong>Monto cobrado:</strong> Bs <?php echo number_format($montoBruto, 2); ?></p>
                        <p><strong>Comisión CUPAZ:</strong> Bs <?php echo number_format($montoComision, 2); ?> (<?php echo number_format($comisionPorcentaje, 2); ?>%)</p>
                        <p><strong>Monto a pagar al vendedor:</strong> Bs <?php echo number_format($montoVendedor, 2); ?></p>
                        <p><strong>Titular:</strong> <?php echo htmlspecialchars($pago['titular'] ?? 'No registrado'); ?></p>
                        <p><strong>Banco/Cuenta:</strong> <?php echo htmlspecialchars(($pago['banco'] ?? 'Sin banco') . (!empty($pago['numero_cuenta']) ? ' / ' . $pago['numero_cuenta'] : '')); ?></p>
                        <?php if (!empty($pago['qr_cobro'])): ?>
                            <div class="mb-3">
                                <div class="metric-label mb-2">QR del vendedor</div>
                                <img src="<?php echo HTTP_BASE . '/' . ltrim($pago['qr_cobro'], '/'); ?>" alt="QR vendedor" style="max-width:220px;border-radius:18px;border:1px solid #d7e4e5;padding:8px;background:#fff;">
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Referencia de liquidación</label>
                            <input type="text" name="referencia_liquidacion" class="form-control" placeholder="Ej: TRF-2026-001" required>
                        </div>
                        <div class="form-group">
                            <label>Observaciones</label>
                            <textarea name="observaciones" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-cupaz-primary">Marcar neto como pagado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require ROOT_VIEW . '/template/footer.php'; ?>
