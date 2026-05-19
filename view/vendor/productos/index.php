<?php
require_once ROOT_DIR . '/model/ProductoModel.php';
require_once ROOT_DIR . '/model/CategoriaModel.php';

$productoModel = new ProductoModel();
$categoriaModel = new CategoriaModel();
$mensajeError = '';
$mensajeExito = '';
$idVendedor = (int) ($_SESSION['login']['id_usuario'] ?? 0);
$rolActual = strtoupper($_SESSION['login']['rol'] ?? '');

function guardarImagenProducto($file, $prefix)
{
    if (!isset($file['tmp_name']) || $file['tmp_name'] === '' || !is_uploaded_file($file['tmp_name'])) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return '';
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return '';
    }
    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        return '';
    }
    $uploadDir = ROOT_UPLOAD . '/productos';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $extension = $allowedMime[$mime];
    $fileName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return '';
    }
    return 'uploads/productos/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = (float) ($_POST['precio'] ?? 0);
        $stock = (int) ($_POST['stock'] ?? 0);
        $idCategoria = (int) ($_POST['id_categoria'] ?? 0);
        $idSubcategoria = (int) ($_POST['id_subcategoria'] ?? 0);

        if ($nombre === '' || $precio <= 0 || $stock < 0) {
            $mensajeError = 'Completa nombre, precio y stock válidos.';
        } else {
            $imagenPrincipal = guardarImagenProducto($_FILES['imagen_principal'] ?? [], 'principal');
            $resultado = $productoModel->crearProducto($nombre, $descripcion, $precio, $stock, $idCategoria, $idSubcategoria, $idVendedor, $imagenPrincipal !== '' ? $imagenPrincipal : null);
            if (!empty($resultado['ESTADO'])) {
                $idProducto = $productoModel->obtenerUltimoId();
                if (isset($_FILES['imagenes_adicionales']['tmp_name']) && is_array($_FILES['imagenes_adicionales']['tmp_name'])) {
                    foreach ($_FILES['imagenes_adicionales']['tmp_name'] as $index => $tmpName) {
                        $file = ['name' => $_FILES['imagenes_adicionales']['name'][$index] ?? '', 'tmp_name' => $tmpName];
                        if ($tmpName !== '') {
                            $ruta = guardarImagenProducto($file, 'extra');
                            if ($ruta !== '') {
                                $productoModel->agregarImagen($idProducto, $ruta, $index + 1);
                            }
                        }
                    }
                }
                $mensajeExito = 'Producto creado correctamente.';
            } else {
                $mensajeError = $resultado['ERROR'] ?? 'No se pudo crear el producto.';
            }
        }
    } elseif ($accion === 'editar') {
        $imagenPrincipal = guardarImagenProducto($_FILES['imagen_principal'] ?? [], 'principal_edit');
        $resultado = $productoModel->actualizarProducto(
            (int) ($_POST['id_producto'] ?? 0),
            trim($_POST['nombre'] ?? ''),
            trim($_POST['descripcion'] ?? ''),
            (float) ($_POST['precio'] ?? 0),
            (int) ($_POST['stock'] ?? 0),
            (int) ($_POST['id_categoria'] ?? 0),
            (int) ($_POST['id_subcategoria'] ?? 0),
            $imagenPrincipal !== '' ? $imagenPrincipal : null
        );
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Producto actualizado correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo actualizar el producto.') : '';
    } elseif ($accion === 'eliminar') {
        $resultado = $productoModel->eliminarProducto((int) ($_POST['id_producto'] ?? 0));
        $mensajeExito = !empty($resultado['ESTADO']) ? 'Producto eliminado correctamente.' : '';
        $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo eliminar el producto.') : '';
    }
}

$categorias = $categoriaModel->listarCategorias()['DATA'] ?? [];
$subcategorias = $categoriaModel->listarSubcategorias()['DATA'] ?? [];
$productos = $rolActual === 'ADMIN'
    ? ($productoModel->listarTodos()['DATA'] ?? [])
    : ($productoModel->listarPorVendedor($idVendedor)['DATA'] ?? []);
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-box-open"></i> Espacio vendedor</span>
                    <h1 class="page-title"><?php echo $rolActual === 'ADMIN' ? 'Productos del sistema' : 'Mis productos'; ?></h1>
                    <p class="page-subtitle">Publica, modifica y elimina productos desde modales con una vista más
                        colorida y mejor separada para tu catálogo.</p>
                </div>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Productos publicados</div>
                        <div class="metric-value"><?php echo count($productos); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Categorías disponibles</div>
                        <div class="metric-value"><?php echo count($categorias); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Catálogo</div>
                        <div class="metric-value" style="font-size:20px;">Visual</div>
                    </div>
                </div>

                <div class="module-full">
                    <div class="surface-card">
                        <div class="card-header d-flex justify-content-between">
                            <h3 class="card-title">Productos registrados</h3>

                            <div class="d-flex align-items-end">
                                <button type="button" class="btn btn-cupaz-primary" data-toggle="modal"
                                    data-target="#modalCrearProducto">
                                    <i class="fas fa-plus mr-2"></i>Nuevo Producto
                                </button>
                            </div>
                        </div>

                        <div class="card-body table-responsive">
                            <table class="table table-cupaz table-hover">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th>Descripcion</th>
                                        <?php if ($rolActual === 'ADMIN'): ?><th>Vendedor</th><?php endif; ?>
                                        <th>Precio</th>
                                        <th>Stock</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos as $producto): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong><br>
                                                <span
                                                    class="helper-text"><?php echo !empty($producto['imagen_principal']) ? 'Con imagen principal' : 'Sin imagen principal'; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars(trim(($producto['categoria'] ?? '') . ' / ' . ($producto['subcategoria'] ?? ''), ' /')); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($producto['descripcion']); ?>
                                            </td>
                                            <?php if ($rolActual === 'ADMIN'): ?><td><?php echo htmlspecialchars($producto['vendedor'] ?? ''); ?></td><?php endif; ?>
                                            <td><strong>Bs
                                                    <?php echo number_format((float) $producto['precio'], 2); ?></strong>
                                            </td>
                                            <td><?php echo (int) $producto['stock']; ?></td>
                                            <td><span
                                                    class="badge-cupaz <?php echo (($producto['estado'] ?? '') === 'AGOTADO') ? 'badge-danger-soft' : 'badge-soft'; ?>"><?php echo htmlspecialchars($producto['estado']); ?></span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-cupaz-outline"
                                                    data-toggle="modal"
                                                    data-target="#modalEditarProducto<?php echo (int) $producto['id_producto']; ?>">
                                                    <i class="fas fa-pen mr-1"></i>Editar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger-soft" data-toggle="modal"
                                                    data-target="#modalEliminarProducto<?php echo (int) $producto['id_producto']; ?>">
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

<div class="modal fade" id="modalCrearProducto" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" class="cupaz-form">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header">
                    <h5 class="modal-title">Añadir producto</h5><button type="button" class="close"
                        data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Nombre</label><input type="text" name="nombre"
                                class="form-control" required></div>
                        <div class="col-md-6 form-group"><label>Precio</label><input type="number" step="0.01"
                                min="0.01" name="precio" class="form-control" required></div>
                        <div class="col-md-12 form-group"><label>Descripción</label><textarea name="descripcion"
                                class="form-control"></textarea></div>
                        <div class="col-md-6 form-group"><label>Stock</label><input type="number" min="0" name="stock"
                                class="form-control" required></div>
                        <div class="col-md-6 form-group"><label>Categoría</label><select name="id_categoria"
                                class="custom-select">
                                <option value="0">Sin categoría</option><?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo (int) $categoria['id_categoria']; ?>">
                                        <?php echo htmlspecialchars($categoria['nombre']); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="col-md-12 form-group"><label>Subcategoría</label><select name="id_subcategoria"
                                class="custom-select">
                                <option value="0">Sin subcategoría</option>
                                <?php foreach ($subcategorias as $subcategoria): ?>
                                    <option value="<?php echo (int) $subcategoria['id_subcategoria']; ?>">
                                        <?php echo htmlspecialchars($subcategoria['categoria'] . ' / ' . $subcategoria['nombre']); ?>
                                    </option><?php endforeach; ?>
                            </select></div>
                        <div class="col-md-6 form-group"><label>Imagen principal</label><input type="file"
                                name="imagen_principal" class="form-control" accept="image/*"></div>
                        <div class="col-md-6 form-group"><label>Imágenes adicionales</label><input type="file"
                                name="imagenes_adicionales[]" class="form-control" accept="image/*" multiple></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cupaz-primary">Publicar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($productos as $producto): ?>
    <div class="modal fade" id="modalEditarProducto<?php echo (int) $producto['id_producto']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data" class="cupaz-form">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_producto" value="<?php echo (int) $producto['id_producto']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Modificar producto</h5><button type="button" class="close"
                            data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group"><label>Nombre</label><input type="text" name="nombre"
                                    class="form-control" value="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                    required></div>
                            <div class="col-md-6 form-group"><label>Precio</label><input type="number" step="0.01"
                                    min="0.01" name="precio" class="form-control"
                                    value="<?php echo htmlspecialchars($producto['precio']); ?>" required></div>
                            <div class="col-md-12 form-group"><label>Descripción</label><textarea name="descripcion"
                                    class="form-control"><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6 form-group"><label>Stock</label><input type="number" min="0" name="stock"
                                    class="form-control" value="<?php echo (int) $producto['stock']; ?>" required></div>
                            <div class="col-md-6 form-group"><label>Categoría</label><select name="id_categoria"
                                    class="custom-select">
                                    <option value="0">Sin categoría</option><?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo (int) $categoria['id_categoria']; ?>" <?php echo (($producto['categoria'] ?? '') === $categoria['nombre']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($categoria['nombre']); ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-12 form-group"><label>Subcategoría</label><select name="id_subcategoria"
                                    class="custom-select">
                                    <option value="0">Sin subcategoría</option>
                                    <?php foreach ($subcategorias as $subcategoria): ?>
                                        <option value="<?php echo (int) $subcategoria['id_subcategoria']; ?>" <?php echo (($producto['subcategoria'] ?? '') === $subcategoria['nombre']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subcategoria['categoria'] . ' / ' . $subcategoria['nombre']); ?>
                                        </option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-12 form-group"><label>Nueva imagen principal</label><input type="file"
                                    name="imagen_principal" class="form-control" accept="image/*"></div>
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

    <div class="modal fade" id="modalEliminarProducto<?php echo (int) $producto['id_producto']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_producto" value="<?php echo (int) $producto['id_producto']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Eliminar producto</h5><button type="button" class="close"
                            data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">¿Seguro que deseas eliminar
                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>?</div>
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
