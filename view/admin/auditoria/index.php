<?php
require_once ROOT_DIR . '/model/AuditoriaModel.php';

$auditoriaModel = new AuditoriaModel();
$filtros = [
    'texto' => trim($_GET['texto'] ?? ''),
    'modulo' => trim($_GET['modulo'] ?? ''),
    'desde' => trim($_GET['desde'] ?? ''),
    'hasta' => trim($_GET['hasta'] ?? ''),
];

$eventos = $auditoriaModel->listar($filtros)['DATA'] ?? [];
$resumen = $auditoriaModel->resumen();
$modulos = $auditoriaModel->modulos();
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-user-shield"></i> Seguridad</span>
                    <h1 class="page-title">Auditoria del sistema</h1>
                    <p class="page-subtitle">Revisa accesos, acciones administrativas y movimientos importantes con usuario, modulo, ruta, IP y fecha.</p>
                </div>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Eventos registrados</div>
                        <div class="metric-value"><?php echo number_format((int)$resumen['total']); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Eventos de hoy</div>
                        <div class="metric-value"><?php echo number_format((int)$resumen['hoy']); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Usuarios con actividad</div>
                        <div class="metric-value"><?php echo number_format((int)$resumen['usuarios']); ?></div>
                    </div>
                </div>

                <div class="surface-card mb-3">
                    <div class="card-header">
                        <h3 class="card-title">Filtros</h3>
                    </div>
                    <div class="card-body">
                        <form method="get" class="cupaz-form">
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label>Buscar</label>
                                    <input type="text" name="texto" class="form-control" value="<?php echo htmlspecialchars($filtros['texto']); ?>" placeholder="Usuario, accion o detalle">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label>Modulo</label>
                                    <select name="modulo" class="custom-select">
                                        <option value="">Todos</option>
                                        <?php foreach ($modulos as $modulo): ?>
                                            <option value="<?php echo htmlspecialchars($modulo); ?>" <?php echo $filtros['modulo'] === $modulo ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($modulo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <label>Desde</label>
                                    <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($filtros['desde']); ?>">
                                </div>
                                <div class="col-md-2 form-group">
                                    <label>Hasta</label>
                                    <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($filtros['hasta']); ?>">
                                </div>
                                <div class="col-md-1 form-group d-flex align-items-end">
                                    <button type="submit" class="btn btn-cupaz-primary btn-block" title="Filtrar">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Ultimos eventos</h3>
                        <span class="helper-text">Se muestran hasta 300 registros</span>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-cupaz table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Modulo</th>
                                    <th>Accion</th>
                                    <th>Ruta / IP</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($eventos)): ?>
                                    <tr>
                                        <td colspan="6">Todavia no hay eventos con esos filtros.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($eventos as $evento): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($evento['fecha_evento']); ?></strong><br>
                                                <span class="helper-text"><?php echo htmlspecialchars($evento['metodo'] ?? ''); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($evento['usuario']); ?></strong><br>
                                                <span class="helper-text"><?php echo htmlspecialchars($evento['correo'] ?? 'Sin correo'); ?></span>
                                            </td>
                                            <td><span class="badge-cupaz badge-soft"><?php echo htmlspecialchars($evento['modulo']); ?></span></td>
                                            <td><?php echo htmlspecialchars($evento['accion']); ?></td>
                                            <td>
                                                <span class="helper-text"><?php echo htmlspecialchars($evento['ruta'] ?? ''); ?></span><br>
                                                <span class="badge-cupaz badge-warning-soft"><?php echo htmlspecialchars($evento['ip'] ?? ''); ?></span>
                                            </td>
                                            <td style="max-width: 360px;">
                                                <code style="white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($evento['detalle'] ?? ''); ?></code>
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
