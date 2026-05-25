<?php
require_once ROOT_DIR . '/model/DisputaModel.php';
require_once ROOT_DIR . '/model/PedidoModel.php';
require_once ROOT_DIR . '/model/PagoModel.php';

$disputaModel = null;
$pedidoModel = null;
$pagoModel = null;
$rolActual = strtoupper($_SESSION['login']['rol'] ?? '');
$idUsuario = (int)($_SESSION['login']['id_usuario'] ?? 0);
$mensajeError = '';
$mensajeExito = '';
$disputas = [];
$pedidosCliente = [];
$pagosPorPedido = [];

try {
    $disputaModel = new DisputaModel();
    $pedidoModel = new PedidoModel();
    $pagoModel = new PagoModel();
} catch (Exception $e) {
    $mensajeError = 'No se pudo iniciar el modulo de disputas: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $disputaModel !== null) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_disputa') {
        $motivo = trim($_POST['motivo'] ?? '');
        if ($motivo === '') {
            $mensajeError = 'Describe el motivo del reclamo.';
        } else {
            $resultado = $disputaModel->crearDisputa(
                (int)($_POST['id_pedido'] ?? 0),
                (int)($_POST['id_vendedor'] ?? 0),
                $idUsuario,
                $motivo
            );
            $mensajeExito = !empty($resultado['ESTADO']) ? 'Reclamo registrado correctamente.' : '';
            $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo registrar el reclamo.') : '';
        }
    } elseif ($accion === 'cancelar_disputa' && $rolActual === 'CLIENTE') {
        $resultado = $disputaModel->cancelarPorCliente(
            (int)($_POST['id_disputa'] ?? 0),
            $idUsuario,
            trim($_POST['resolucion'] ?? 'Reclamo anulado por el cliente.')
        );
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Reclamo anulado correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo anular el reclamo.') : '';
    } elseif ($accion === 'resolver_disputa' && $rolActual === 'ADMIN') {
        $resultado = $disputaModel->resolverDisputa(
            (int)($_POST['id_disputa'] ?? 0),
            $idUsuario,
            trim($_POST['estado'] ?? 'EN_REVISION'),
            trim($_POST['resolucion'] ?? '')
        );
        $estadoResultado = trim($_POST['estado'] ?? 'EN_REVISION');
        $mensajes = [
            'EN_REVISION' => 'Reclamo tomado en revisión.',
            'RESUELTA_CLIENTE' => 'Reclamo resuelto a favor del cliente. El pago fue marcado para devolución.',
            'RESUELTA_VENDEDOR' => 'Reclamo resuelto a favor del vendedor. El pago fue liberado.',
            'CERRADA' => 'Reclamo cerrado sin mover fondos.',
        ];
        $mensajeExito = !empty($resultado['ESTADO']) ? ($mensajes[$estadoResultado] ?? 'Disputa actualizada correctamente.') : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo actualizar la disputa.') : '';
    }
}

if ($disputaModel !== null) {
    if ($rolActual === 'ADMIN') {
        $resultadoDisputas = $disputaModel->listarTodas();
    } elseif ($rolActual === 'VENDEDOR') {
        $resultadoDisputas = $disputaModel->listarPorVendedor($idUsuario);
    } else {
        $resultadoDisputas = $disputaModel->listarPorCliente($idUsuario);
    }
    if (!empty($resultadoDisputas['ESTADO'])) {
        $disputas = $resultadoDisputas['DATA'] ?? [];
    } else {
        $mensajeError = $resultadoDisputas['ERROR'] ?? 'No se pudieron cargar las disputas.';
    }
}

if ($rolActual === 'CLIENTE' && $pedidoModel !== null && $pagoModel !== null) {
    $resultadoPedidosCliente = $pedidoModel->listarPorCliente($idUsuario);
    if (!empty($resultadoPedidosCliente['ESTADO'])) {
        $pedidosCliente = $resultadoPedidosCliente['DATA'] ?? [];
    }
    foreach ($pedidosCliente as $pedido) {
        $pagosPorPedido[(int)$pedido['id_pedido']] = $pagoModel->listarPorPedido((int)$pedido['id_pedido'])['DATA'] ?? [];
    }
}
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-balance-scale"></i> <?php echo $rolActual === 'ADMIN' ? 'Administración' : ($rolActual === 'VENDEDOR' ? 'Vendedor' : 'Cliente'); ?></span>
                    <h1 class="page-title"><?php echo $rolActual === 'ADMIN' ? 'Disputas y reclamos' : ($rolActual === 'VENDEDOR' ? 'Reclamos recibidos' : 'Mis reclamos'); ?></h1>
                    <p class="page-subtitle">Registra incidencias sobre pedidos y da seguimiento a la resolución del mecanismo escrow.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Disputas</div>
                        <div class="metric-value"><?php echo count($disputas); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Gestión</div>
                        <div class="metric-value" style="font-size:20px;"><?php echo $rolActual === 'ADMIN' ? 'Resolución' : 'Seguimiento'; ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Protección</div>
                        <div class="metric-value" style="font-size:20px;">Escrow</div>
                    </div>
                </div>

                <?php if ($rolActual === 'CLIENTE'): ?>
                    <div class="surface-card mb-4">
                        <div class="card-header"><h3 class="card-title">Crear reclamo</h3></div>
                        <div class="card-body">
                            <div class="actions-bar">
                                <p class="section-note">Selecciona un pedido pagado o en seguimiento y abre el reclamo contra el vendedor correspondiente.</p>
                                <button type="button" class="btn btn-cupaz-primary" data-toggle="modal" data-target="#modalCrearDisputa">
                                    <i class="fas fa-plus mr-2"></i>Nuevo reclamo
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="surface-card">
                    <div class="card-header"><h3 class="card-title"><?php echo $rolActual === 'ADMIN' ? 'Casos registrados' : 'Historial de reclamos'; ?></h3></div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Estado</th>
                                    <th>Motivo</th>
                                    <th>Resolución</th>
                                    <th>Fecha</th>
                                    <?php if (in_array($rolActual, ['ADMIN', 'CLIENTE'], true)): ?><th>Acciones</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($disputas as $disputa): ?>
                                    <tr>
                                        <td>#<?php echo (int)$disputa['id_disputa']; ?></td>
                                        <td>#<?php echo (int)$disputa['id_pedido']; ?></td>
                                        <td><?php echo htmlspecialchars($disputa['cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($disputa['vendedor'] ?? ''); ?></td>
                                        <td><span class="badge-cupaz <?php echo in_array(($disputa['estado'] ?? ''), ['RESUELTA_CLIENTE', 'RESUELTA_VENDEDOR', 'CERRADA', 'ANULADA'], true) ? 'badge-soft' : 'badge-warning-soft'; ?>"><?php echo htmlspecialchars($disputa['estado']); ?></span></td>
                                        <td><?php echo htmlspecialchars($disputa['motivo'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($disputa['resolucion'] ?? 'Pendiente'); ?></td>
                                        <td><?php echo htmlspecialchars($disputa['fecha_apertura']); ?></td>
                                        <?php if ($rolActual === 'ADMIN'): ?>
                                            <td>
                                                <?php if (in_array(($disputa['estado'] ?? ''), ['ABIERTA', 'EN_REVISION'], true)): ?>
                                                    <form method="post" class="mb-1">
                                                        <input type="hidden" name="accion" value="resolver_disputa">
                                                        <input type="hidden" name="id_disputa" value="<?php echo (int)$disputa['id_disputa']; ?>">
                                                        <input type="hidden" name="estado" value="EN_REVISION">
                                                        <input type="hidden" name="resolucion" value="El administrador revisa el reclamo y las evidencias.">
                                                        <button type="submit" class="btn btn-sm btn-cupaz-outline">
                                                            <i class="fas fa-search mr-1"></i>Revisar
                                                        </button>
                                                    </form>
                                                    <form method="post" class="mb-1">
                                                        <input type="hidden" name="accion" value="resolver_disputa">
                                                        <input type="hidden" name="id_disputa" value="<?php echo (int)$disputa['id_disputa']; ?>">
                                                        <input type="hidden" name="estado" value="RESUELTA_CLIENTE">
                                                        <input type="hidden" name="resolucion" value="Resuelto a favor del cliente. Se procesa devolución del pago retenido.">
                                                        <button type="submit" class="btn btn-sm btn-danger-soft">
                                                            <i class="fas fa-undo mr-1"></i>Devolver pago
                                                        </button>
                                                    </form>
                                                    <form method="post" class="mb-1">
                                                        <input type="hidden" name="accion" value="resolver_disputa">
                                                        <input type="hidden" name="id_disputa" value="<?php echo (int)$disputa['id_disputa']; ?>">
                                                        <input type="hidden" name="estado" value="RESUELTA_VENDEDOR">
                                                        <input type="hidden" name="resolucion" value="Resuelto a favor del vendedor. Se libera el pago retenido.">
                                                        <button type="submit" class="btn btn-sm btn-cupaz-primary">
                                                            <i class="fas fa-check mr-1"></i>Liberar vendedor
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-cupaz-outline" data-toggle="modal" data-target="#modalResolverDisputa<?php echo (int)$disputa['id_disputa']; ?>">
                                                        <i class="fas fa-gavel mr-1"></i>Resolución manual
                                                    </button>
                                                <?php else: ?>
                                                    <span class="helper-text">Caso finalizado</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php elseif ($rolActual === 'CLIENTE'): ?>
                                            <td>
                                                <?php if (($disputa['estado'] ?? '') === 'ABIERTA'): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="accion" value="cancelar_disputa">
                                                        <input type="hidden" name="id_disputa" value="<?php echo (int)$disputa['id_disputa']; ?>">
                                                        <input type="hidden" name="resolucion" value="Reclamo anulado por el cliente antes de revisión.">
                                                        <button type="submit" class="btn btn-sm btn-cupaz-outline">
                                                            <i class="fas fa-times mr-1"></i>Anular
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="helper-text">Sin acciones</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
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

<?php if ($rolActual === 'CLIENTE'): ?>
<div class="modal fade" id="modalCrearDisputa" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" class="cupaz-form">
                <input type="hidden" name="accion" value="crear_disputa">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo reclamo</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pedido</label>
                        <select name="id_pedido" id="selectPedidoDisputa" class="custom-select" required>
                            <option value="">Selecciona un pedido</option>
                            <?php foreach ($pedidosCliente as $pedido): ?>
                                <option value="<?php echo (int)$pedido['id_pedido']; ?>">#<?php echo (int)$pedido['id_pedido']; ?> - <?php echo htmlspecialchars($pedido['productos'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vendedor involucrado</label>
                        <select name="id_vendedor" id="selectVendedorDisputa" class="custom-select" required>
                            <option value="">Selecciona un vendedor</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Motivo</label>
                        <textarea name="motivo" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cupaz-primary">Registrar reclamo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
const pagosDisputa = <?php echo json_encode($pagosPorPedido, JSON_UNESCAPED_UNICODE); ?>;
document.addEventListener('DOMContentLoaded', function () {
    const pedidoSelect = document.getElementById('selectPedidoDisputa');
    const vendedorSelect = document.getElementById('selectVendedorDisputa');
    if (!pedidoSelect || !vendedorSelect) return;

    function actualizarVendedores() {
        const pedido = pedidoSelect.value;
        vendedorSelect.innerHTML = '<option value="">Selecciona un vendedor</option>';
        (pagosDisputa[pedido] || []).forEach(function (pago) {
            const option = document.createElement('option');
            option.value = pago.id_vendedor;
            option.textContent = pago.vendedor + ' - Bs ' + parseFloat(pago.monto || 0).toFixed(2);
            vendedorSelect.appendChild(option);
        });
    }

    pedidoSelect.addEventListener('change', actualizarVendedores);
});
</script>
<?php endif; ?>

<?php if ($rolActual === 'ADMIN'): ?>
    <?php foreach ($disputas as $disputa): ?>
        <div class="modal fade" id="modalResolverDisputa<?php echo (int)$disputa['id_disputa']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" class="cupaz-form">
                        <input type="hidden" name="accion" value="resolver_disputa">
                        <input type="hidden" name="id_disputa" value="<?php echo (int)$disputa['id_disputa']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Resolver disputa #<?php echo (int)$disputa['id_disputa']; ?></h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Estado de resolución</label>
                                <select name="estado" class="custom-select">
                                    <?php foreach (['EN_REVISION', 'RESUELTA_CLIENTE', 'RESUELTA_VENDEDOR', 'CERRADA'] as $estado): ?>
                                        <option value="<?php echo $estado; ?>" <?php echo (($disputa['estado'] ?? '') === $estado) ? 'selected' : ''; ?>><?php echo $estado; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Resolución</label>
                                <textarea name="resolucion" class="form-control" rows="4"><?php echo htmlspecialchars($disputa['resolucion'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-cupaz-primary">Aplicar resolución</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require ROOT_VIEW . '/template/footer.php'; ?>
