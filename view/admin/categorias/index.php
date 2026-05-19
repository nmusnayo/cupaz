<?php
require_once ROOT_DIR . '/model/CategoriaModel.php';

$categoriaModel = new CategoriaModel();
$mensajeError = '';
$mensajeExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        if ($nombre === '') {
            $mensajeError = 'Ingresa el nombre de la categoría.';
        } else {
            $resultado = $categoriaModel->crearCategoria($nombre, $descripcion !== '' ? $descripcion : null);
            $mensajeExito = !empty($resultado['ESTADO']) ? 'Categoría creada correctamente.' : '';
            $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo crear la categoría.') : '';
        }
    } elseif ($accion === 'editar') {
        $resultado = $categoriaModel->actualizarCategoria(
            (int) ($_POST['id_categoria'] ?? 0),
            trim($_POST['nombre'] ?? ''),
            trim($_POST['descripcion'] ?? '') !== '' ? trim($_POST['descripcion']) : null,
            strtoupper(trim($_POST['estado'] ?? 'ACTIVO'))
        );
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Categoría actualizada correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo actualizar la categoría.') : '';
    } elseif ($accion === 'eliminar') {
        $resultado = $categoriaModel->eliminarCategoria((int) ($_POST['id_categoria'] ?? 0));
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Categoría eliminada correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo eliminar la categoría.') : '';
    }
}

$categorias = $categoriaModel->listarCategorias()['DATA'] ?? [];
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-tags"></i> Catálogo</span>
                    <h1 class="page-title">Categorías</h1>
                    <p class="page-subtitle">Organiza el catálogo principal del marketplace con una estructura clara,
                        más colorida y más fácil de administrar.</p>
                </div>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Categorías activas</div>
                        <div class="metric-value"><?php echo count($categorias); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Estado</div>
                        <div class="metric-value" style="font-size:20px;">Organizado</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Vista</div>
                        <div class="metric-value" style="font-size:20px;">Administrable</div>
                    </div>
                </div>
                <div class="module-full">
                    <div class="surface-card">
                        <div class="card-header d-flex justify-content-between">
                            <h3 class="card-title">Categorias registrados</h3>

                            <div class="d-flex align-items-end">
                                <button type="button" class="btn btn-cupaz-primary" data-toggle="modal"
                                    data-target="#modalCrearCategoria">
                                    <i class="fas fa-plus mr-2"></i>Nueva categoria
                                </button>
                            </div>
                        </div>

                        <div class="card-body table-responsive">
                            <table class="table table-cupaz table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($categoria['nombre']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?></td>
                                            <td><span
                                                    class="badge-cupaz badge-soft"><?php echo htmlspecialchars($categoria['estado']); ?></span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-cupaz-outline"
                                                    data-toggle="modal"
                                                    data-target="#modalEditarCategoria<?php echo (int) $categoria['id_categoria']; ?>">
                                                    <i class="fas fa-pen mr-1"></i>Editar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger-soft" data-toggle="modal"
                                                    data-target="#modalEliminarCategoria<?php echo (int) $categoria['id_categoria']; ?>">
                                                    <i class="fas fa-trash mr-1"></i>Eliminar
                                                </button>
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
</div>

<div class="modal fade" id="modalCrearCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" class="cupaz-form">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header">
                    <h5 class="modal-title">Añadir categoría</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Nombre</label><input type="text" name="nombre" class="form-control"
                            required></div>
                    <div class="form-group"><label>Descripción</label><textarea name="descripcion"
                            class="form-control"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cupaz-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($categorias as $categoria): ?>
    <div class="modal fade" id="modalEditarCategoria<?php echo (int) $categoria['id_categoria']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" class="cupaz-form">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_categoria" value="<?php echo (int) $categoria['id_categoria']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Modificar categoría</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group"><label>Nombre</label><input type="text" name="nombre" class="form-control"
                                value="<?php echo htmlspecialchars($categoria['nombre']); ?>" required></div>
                        <div class="form-group"><label>Descripción</label><textarea name="descripcion"
                                class="form-control"><?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="estado" class="custom-select">
                                <?php foreach (['ACTIVO', 'INACTIVO'] as $estado): ?>
                                    <option value="<?php echo $estado; ?>" <?php echo (($categoria['estado'] ?? '') === $estado) ? 'selected' : ''; ?>><?php echo $estado; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-cupaz-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEliminarCategoria<?php echo (int) $categoria['id_categoria']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_categoria" value="<?php echo (int) $categoria['id_categoria']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Eliminar categoría</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">¿Seguro que deseas eliminar
                        <strong><?php echo htmlspecialchars($categoria['nombre']); ?></strong>?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger-soft">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require ROOT_VIEW . '/template/footer.php'; ?>