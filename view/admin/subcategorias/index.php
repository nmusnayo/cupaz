<?php
require_once ROOT_DIR . '/model/CategoriaModel.php';

$categoriaModel = new CategoriaModel();
$mensajeError = '';
$mensajeExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $idCategoria = (int)($_POST['id_categoria'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($idCategoria <= 0 || $nombre === '') {
            $mensajeError = 'Selecciona categoría y nombre de subcategoría.';
        } else {
            $resultado = $categoriaModel->crearSubcategoria($idCategoria, $nombre);
            $mensajeExito = !empty($resultado['ESTADO']) ? 'Subcategoría creada correctamente.' : '';
            $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo crear la subcategoría.') : '';
        }
    } elseif ($accion === 'editar') {
        $resultado = $categoriaModel->actualizarSubcategoria(
            (int)($_POST['id_subcategoria'] ?? 0),
            (int)($_POST['id_categoria'] ?? 0),
            trim($_POST['nombre'] ?? ''),
            strtoupper(trim($_POST['estado'] ?? 'ACTIVO'))
        );
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Subcategoría actualizada correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo actualizar la subcategoría.') : '';
    } elseif ($accion === 'eliminar') {
        $resultado = $categoriaModel->eliminarSubcategoria((int)($_POST['id_subcategoria'] ?? 0));
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Subcategoría eliminada correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo eliminar la subcategoría.') : '';
    }
}

$categorias = $categoriaModel->listarCategorias()['DATA'] ?? [];
$subcategorias = $categoriaModel->listarSubcategorias()['DATA'] ?? [];
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-sitemap"></i> Estructura</span>
                    <h1 class="page-title">Subcategorías</h1>
                    <p class="page-subtitle">Refina la clasificación del catálogo con más orden visual y gestión rápida mediante modales.</p>
                </div>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Subcategorías</div>
                        <div class="metric-value"><?php echo count($subcategorias); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Categorías base</div>
                        <div class="metric-value"><?php echo count($categorias); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Vista</div>
                        <div class="metric-value" style="font-size:20px;">Jerárquica</div>
                    </div>
                </div>

                <div class="module-full">
                    <div class="surface-card">
                        <div class="card-header d-flex justify-content-between">
                            <h3 class="card-title">Subcategorias registrados</h3>

                            <div class="d-flex align-items-end">
                                <button type="button" class="btn btn-cupaz-primary" data-toggle="modal"
                                    data-target="#modalCrearSubcategoria">
                                    <i class="fas fa-plus mr-2"></i>Nueva subcategoria
                                </button>
                            </div>
                        </div>

                        <div class="card-body table-responsive">
                            <table class="table table-cupaz table-hover">
                                <thead>
                                    <tr><th>Categoría</th><th>Subcategoría</th><th>Estado</th><th>Acciones</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subcategorias as $subcategoria): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($subcategoria['categoria']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($subcategoria['nombre']); ?></td>
                                            <td><span class="badge-cupaz badge-soft"><?php echo htmlspecialchars($subcategoria['estado']); ?></span></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-cupaz-outline" data-toggle="modal" data-target="#modalEditarSubcategoria<?php echo (int)$subcategoria['id_subcategoria']; ?>">
                                                    <i class="fas fa-pen mr-1"></i>Editar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger-soft" data-toggle="modal" data-target="#modalEliminarSubcategoria<?php echo (int)$subcategoria['id_subcategoria']; ?>">
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

<div class="modal fade" id="modalCrearSubcategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" class="cupaz-form">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header"><h5 class="modal-title">Añadir subcategoría</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="id_categoria" class="custom-select" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($categorias as $categoria): ?><option value="<?php echo (int)$categoria['id_categoria']; ?>"><?php echo htmlspecialchars($categoria['nombre']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Nombre</label><input type="text" name="nombre" class="form-control" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cupaz-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($subcategorias as $subcategoria): ?>
    <div class="modal fade" id="modalEditarSubcategoria<?php echo (int)$subcategoria['id_subcategoria']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" class="cupaz-form">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_subcategoria" value="<?php echo (int)$subcategoria['id_subcategoria']; ?>">
                    <div class="modal-header"><h5 class="modal-title">Modificar subcategoría</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Categoría</label>
                            <select name="id_categoria" class="custom-select" required>
                                <?php foreach ($categorias as $categoria): ?><option value="<?php echo (int)$categoria['id_categoria']; ?>" <?php echo ((int)$subcategoria['id_categoria'] === (int)$categoria['id_categoria']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoria['nombre']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Nombre</label><input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($subcategoria['nombre']); ?>" required></div>
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="estado" class="custom-select">
                                <?php foreach (['ACTIVO','INACTIVO'] as $estado): ?><option value="<?php echo $estado; ?>" <?php echo (($subcategoria['estado'] ?? '') === $estado) ? 'selected' : ''; ?>><?php echo $estado; ?></option><?php endforeach; ?>
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

    <div class="modal fade" id="modalEliminarSubcategoria<?php echo (int)$subcategoria['id_subcategoria']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_subcategoria" value="<?php echo (int)$subcategoria['id_subcategoria']; ?>">
                    <div class="modal-header"><h5 class="modal-title">Eliminar subcategoría</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                    <div class="modal-body">¿Seguro que deseas eliminar <strong><?php echo htmlspecialchars($subcategoria['nombre']); ?></strong>?</div>
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
