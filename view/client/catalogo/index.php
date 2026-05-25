<?php
require_once ROOT_DIR . '/model/ProductoModel.php';
require_once ROOT_DIR . '/model/PedidoModel.php';

$productoModel = new ProductoModel();
$pedidoModel   = new PedidoModel();
$mensajeError  = '';
$mensajeExito  = '';
$idCliente     = (int)($_SESSION['login']['id_usuario'] ?? 0);

// ── Inicializar carrito en sesión ────────────────────────────────────────────
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// ── Acciones AJAX / POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 1. Agregar al carrito
    if ($accion === 'agregar_carrito') {
        $idProducto = (int)($_POST['id_producto'] ?? 0);
        $cantidad   = max(1, (int)($_POST['cantidad'] ?? 1));

        if ($idProducto > 0) {
            if (isset($_SESSION['carrito'][$idProducto])) {
                $_SESSION['carrito'][$idProducto]['cantidad'] += $cantidad;
            } else {
                $prodData = $productoModel->obtenerPorId($idProducto);
                $prod = $prodData['DATA'][0] ?? null;
                if ($prod !== null) {
                    $_SESSION['carrito'][$idProducto] = [
                        'id_producto' => $idProducto,
                        'nombre'      => $prod['nombre'],
                        'precio'      => (float)$prod['precio'],
                        'stock'       => (int)$prod['stock'],
                        'imagen'      => $prod['imagen_principal'] ?? '',
                        'vendedor'    => $prod['vendedor'] ?? '',
                        'cantidad'    => $cantidad,
                    ];
                }
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['total_items' => array_sum(array_column($_SESSION['carrito'], 'cantidad'))]);
        exit;
    }

    // 2. Eliminar ítem del carrito
    if ($accion === 'eliminar_carrito') {
        $idProducto = (int)($_POST['id_producto'] ?? 0);
        unset($_SESSION['carrito'][$idProducto]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // 3. Actualizar cantidad
    if ($accion === 'actualizar_cantidad') {
        $idProducto = (int)($_POST['id_producto'] ?? 0);
        $cantidad   = max(1, (int)($_POST['cantidad'] ?? 1));
        if (isset($_SESSION['carrito'][$idProducto])) {
            $_SESSION['carrito'][$idProducto]['cantidad'] = $cantidad;
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // 4. Confirmar pedido
    if ($accion === 'confirmar_pedido') {
        $direccionEntrega = trim($_POST['direccion_entrega'] ?? '');
        $notas            = trim($_POST['notas'] ?? '');

        if (empty($_SESSION['carrito'])) {
            $mensajeError = 'El carrito está vacío.';
        } elseif ($direccionEntrega === '') {
            $mensajeError = 'Ingresa la dirección de entrega.';
        } else {
            $items = [];
            foreach ($_SESSION['carrito'] as $item) {
                $items[] = [
                    'id_producto' => $item['id_producto'],
                    'cantidad'    => $item['cantidad'],
                    'precio'      => $item['precio'],
                ];
            }
            $resultado = $pedidoModel->crearDesdeCarrito($idCliente, $items, $direccionEntrega, $notas);
            if (!empty($resultado['ESTADO'])) {
                $_SESSION['carrito'] = [];
                $mensajeExito = 'Pedido #' . (int)($resultado['ID_PEDIDO'] ?? 0) . ' generado correctamente. Ahora debes abrir "Mis pedidos" para pagar con QR y activar la retención en escrow.';
            } else {
                $mensajeError = $resultado['ERROR'] ?? 'No se pudo generar el pedido.';
            }
        }
    }
}

$productos = $productoModel->listarCatalogo()['DATA'] ?? [];
$categoriasCatalogo = [];
foreach ($productos as $productoCategoria) {
    $idCategoria = (int)($productoCategoria['id_categoria'] ?? 0);
    $claveCategoria = $idCategoria > 0 ? 'cat-' . $idCategoria : 'sin-categoria';
    if (!isset($categoriasCatalogo[$claveCategoria])) {
        $categoriasCatalogo[$claveCategoria] = [
            'clave' => $claveCategoria,
            'nombre' => trim($productoCategoria['categoria'] ?? '') !== '' ? $productoCategoria['categoria'] : 'Sin categoria',
            'total' => 0,
        ];
    }
    $categoriasCatalogo[$claveCategoria]['total']++;
}
uasort($categoriasCatalogo, function ($a, $b) {
    return strcasecmp($a['nombre'], $b['nombre']);
});

function urlImagenCatalogo($ruta)
{
    $ruta = trim((string)$ruta);
    if ($ruta === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $ruta)) {
        return $ruta;
    }
    return HTTP_BASE . '/' . ltrim($ruta, '/');
}

// Serializar carrito para JS — forzar tipos numéricos para evitar el error toFixed
$carritoJs = array_values(array_map(function($item) {
    return [
        'id_producto' => (int)$item['id_producto'],
        'nombre'      => (string)$item['nombre'],
        'precio'      => (float)$item['precio'],
        'stock'       => (int)$item['stock'],
        'imagen'      => urlImagenCatalogo($item['imagen'] ?? ''),
        'vendedor'    => (string)($item['vendedor'] ?? ''),
        'cantidad'    => (int)$item['cantidad'],
    ];
}, $_SESSION['carrito'] ?? []));

// Serializar productos para JS
$productosJs = [];
foreach ($productos as $p) {
    $id = (int)$p['id_producto'];
    $productosJs[$id] = [
        'id'          => $id,
        'nombre'      => (string)$p['nombre'],
        'descripcion' => (string)($p['descripcion'] ?? ''),
        'precio'      => (float)$p['precio'],
        'stock'       => (int)$p['stock'],
        'imagen'      => urlImagenCatalogo($p['imagen_principal'] ?? ''),
        'vendedor'    => (string)($p['vendedor'] ?? '—'),
        'categoria'   => trim(($p['categoria'] ?? '') . (!empty($p['subcategoria']) ? ' / ' . $p['subcategoria'] : ''), ' /'),
        'categoria_id'=> (int)($p['id_categoria'] ?? 0),
    ];
}
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>

<style>
.catalog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.catalog-tabs-wrap {
    margin-top: 4px;
    padding: 14px 16px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid var(--cupaz-border);
    box-shadow: 0 12px 28px rgba(28, 62, 67, 0.05);
}
.catalog-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.catalog-tab {
    border: 1px solid rgba(31, 111, 120, 0.18);
    background: #fff;
    color: var(--cupaz-primary-dark);
    border-radius: 999px;
    min-height: 40px;
    padding: 8px 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    transition: background .16s, color .16s, border-color .16s, box-shadow .16s;
}
.catalog-tab:hover {
    background: var(--cupaz-primary-soft);
    color: var(--cupaz-primary-dark);
}
.catalog-tab.active {
    background: linear-gradient(135deg, var(--cupaz-primary) 0%, #2d8e98 100%);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 10px 22px rgba(31, 111, 120, 0.16);
}
.catalog-tab-count {
    min-width: 24px;
    height: 24px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 8px;
    background: rgba(31, 111, 120, 0.1);
    color: var(--cupaz-primary-dark);
    font-size: 12px;
}
.catalog-tab.active .catalog-tab-count {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
.catalog-empty-filter {
    display: none;
    margin-top: 20px;
    padding: 28px;
    border: 1px dashed var(--cupaz-border);
    border-radius: 18px;
    background: #fff;
    color: var(--cupaz-muted);
    text-align: center;
}
.catalog-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform .18s, box-shadow .18s;
    cursor: pointer;
}
.catalog-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,.12);
}
.catalog-card-img {
    width: 100%;
    height: 170px;
    object-fit: cover;
}
.catalog-placeholder {
    width: 100%;
    height: 170px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f6f9;
    color: #c0c7d0;
    font-size: 36px;
}
.catalog-card-footer {
    padding: 12px 14px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.catalog-card-name {
    font-weight: 600;
    font-size: 13.5px;
    color: #2d3748;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.catalog-card-price {
    font-weight: 700;
    font-size: 14px;
    color: #1a7f37;
    white-space: nowrap;
}
.btn-ver-detalle {
    width: 100%;
    background: #3490dc;
    color: #fff;
    border: none;
    padding: 8px 0;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}
.btn-ver-detalle:hover { background: #2779bd; }

/* Modal detalle */
.modal-detalle .modal-dialog { max-width: 680px; }
.modal-detalle .modal-img-wrap {
    width: 100%;
    height: 260px;
    overflow: hidden;
    border-radius: 8px;
    background: #f4f6f9;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-detalle .modal-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.modal-detalle .modal-img-wrap .placeholder-icon { font-size: 56px; color: #c0c7d0; }
.detalle-meta { margin: 14px 0 0; }
.detalle-meta p { margin: 4px 0; font-size: 14px; color: #555; }
.detalle-meta strong { color: #2d3748; }
.detalle-precio-grande { font-size: 26px; font-weight: 700; color: #1a7f37; margin: 10px 0 4px; }
.stock-badge {
    display: inline-block;
    background: #e3f7ea;
    color: #1a7f37;
    border-radius: 20px;
    padding: 2px 12px;
    font-size: 12px;
    font-weight: 600;
}
.stock-badge.agotado { background: #fee2e2; color: #c53030; }

/* Carrito flotante */
.cart-fab {
    position: fixed;
    bottom: 32px;
    right: 32px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #3490dc;
    color: #fff;
    border: none;
    font-size: 22px;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(52,144,220,.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
    transition: background .15s, transform .15s;
}
.cart-fab:hover { background: #2779bd; transform: scale(1.08); }
.cart-fab .cart-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #e53e3e;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

/* Modal carrito */
.modal-carrito .modal-dialog { max-width: 620px; }
.cart-item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.cart-item-row:last-child { border-bottom: none; }
.cart-item-img {
    width: 52px;
    height: 52px;
    object-fit: cover;
    border-radius: 6px;
    flex-shrink: 0;
    background: #f4f6f9;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cart-item-info { flex: 1; min-width: 0; }
.cart-item-name {
    font-weight: 600;
    font-size: 13.5px;
    color: #2d3748;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cart-item-price { font-size: 12.5px; color: #718096; }
.cart-item-qty {
    width: 60px;
    text-align: center;
    border: 1px solid #cbd5e0;
    border-radius: 6px;
    padding: 4px 6px;
    font-size: 13px;
}
.cart-item-remove {
    background: none;
    border: none;
    color: #e53e3e;
    cursor: pointer;
    font-size: 16px;
    padding: 4px;
    line-height: 1;
}
.cart-total-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0 4px;
    font-weight: 700;
    font-size: 16px;
    border-top: 2px solid #e2e8f0;
    margin-top: 6px;
    color: #2d3748;
}
.cart-total-bar span.monto { color: #1a7f37; font-size: 20px; }
.cart-empty-msg {
    text-align: center;
    padding: 36px 0;
    color: #a0aec0;
    font-size: 15px;
}
.cart-empty-msg i { font-size: 42px; display: block; margin-bottom: 10px; }
.confirm-section {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}
.confirm-section .form-group { margin-bottom: 12px; }
.confirm-section label { font-size: 13px; font-weight: 600; color: #4a5568; }

@keyframes pop { 0%{transform:scale(1)} 50%{transform:scale(1.45)} 100%{transform:scale(1)} }
.pop { animation: pop .28s ease; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">

                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-shopping-basket"></i> Cliente</span>
                    <h1 class="page-title">Catálogo disponible</h1>
                    <p class="page-subtitle">Explora productos de los vendedores registrados. Agrega al carrito, crea tu pedido y luego págalo con QR para activar la retención en escrow.</p>
                </div>

                <?php if ($mensajeError !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div>
                <?php endif; ?>
                <?php if ($mensajeExito !== ''): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div>
                <?php endif; ?>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Productos disponibles</div>
                        <div class="metric-value"><?php echo count($productos); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Mecanismo de pago</div>
                        <div class="metric-value" style="font-size:20px;">QR + Escrow</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Experiencia</div>
                        <div class="metric-value" style="font-size:20px;">Multivendedor</div>
                    </div>
                </div>

                <div class="catalog-tabs-wrap">
                    <div class="catalog-tabs" role="tablist" aria-label="Categorias del catalogo">
                        <button type="button" class="catalog-tab active" data-category-filter="todos" onclick="filtrarCatalogo('todos', this)">
                            <i class="fas fa-border-all"></i>
                            <span>Todos</span>
                            <span class="catalog-tab-count"><?php echo count($productos); ?></span>
                        </button>
                        <?php foreach ($categoriasCatalogo as $categoriaTab): ?>
                            <button type="button" class="catalog-tab" data-category-filter="<?php echo htmlspecialchars($categoriaTab['clave']); ?>" onclick="filtrarCatalogo('<?php echo htmlspecialchars($categoriaTab['clave']); ?>', this)">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($categoriaTab['nombre']); ?></span>
                                <span class="catalog-tab-count"><?php echo (int)$categoriaTab['total']; ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="catalog-grid">
                    <?php foreach ($productos as $p): ?>
                        <?php
                        $idCategoriaCard = (int)($p['id_categoria'] ?? 0);
                        $claveCategoriaCard = $idCategoriaCard > 0 ? 'cat-' . $idCategoriaCard : 'sin-categoria';
                        ?>
                        <div class="catalog-card" data-category="<?php echo htmlspecialchars($claveCategoriaCard); ?>" onclick="abrirDetalle(<?php echo (int)$p['id_producto']; ?>)">
                            <?php if (!empty($p['imagen_principal'])): ?>
                                <img class="catalog-card-img"
                                     src="<?php echo HTTP_BASE . '/' . ltrim($p['imagen_principal'], '/'); ?>"
                                     alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                            <?php else: ?>
                                <div class="catalog-placeholder"><i class="fas fa-image"></i></div>
                            <?php endif; ?>
                            <div class="catalog-card-footer">
                                <span class="catalog-card-name"><?php echo htmlspecialchars($p['nombre']); ?></span>
                                <span class="catalog-card-price">Bs <?php echo number_format((float)$p['precio'], 2); ?></span>
                            </div>
                            <button class="btn-ver-detalle"
                                    onclick="event.stopPropagation(); abrirDetalle(<?php echo (int)$p['id_producto']; ?>)">
                                <i class="fas fa-eye"></i> Ver detalle
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="catalog-empty-filter" id="catalogEmptyFilter">
                    <i class="fas fa-filter mr-2"></i>No hay productos disponibles en esta categoria.
                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL: DETALLE DEL PRODUCTO -->
<div class="modal fade modal-detalle" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mdTitulo">Detalle del producto</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="modal-img-wrap">
                    <img id="mdImagen" src="" alt="" style="display:none;">
                    <i class="fas fa-image placeholder-icon" id="mdPlaceholder"></i>
                </div>
                <div class="detalle-precio-grande" id="mdPrecio">Bs 0.00</div>
                <span class="stock-badge" id="mdStock">Stock: 0</span>
                <div class="detalle-meta">
                    <p><strong>Descripción:</strong> <span id="mdDescripcion">—</span></p>
                    <p><strong>Vendedor:</strong> <span id="mdVendedor">—</span></p>
                    <p><strong>Categoría:</strong> <span id="mdCategoria">—</span></p>
                </div>
                <hr>
                <div class="form-group">
                    <label style="font-weight:600;">Cantidad</label>
                    <input type="number" id="mdCantidad" class="form-control" value="1" min="1" max="99" style="width:110px;">
                </div>
                <input type="hidden" id="mdIdProducto" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-cupaz-primary" onclick="agregarAlCarrito()">
                    <i class="fas fa-cart-plus"></i> Agregar al carrito
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: CARRITO -->
<div class="modal fade modal-carrito" id="modalCarrito" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shopping-cart"></i> Mi carrito</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="carritoLista"></div>
                <div class="cart-total-bar" id="carritoTotal" style="display:none;">
                    <span>Total estimado</span>
                    <span class="monto" id="carritoMontoTotal">Bs 0.00</span>
                </div>
                <div class="confirm-section" id="confirmSection" style="display:none;">
                    <p style="font-size:13px;color:#718096;margin-bottom:12px;">
                        <i class="fas fa-info-circle"></i>
                        Al confirmar, se crea el pedido y luego podrás pagar con <strong>QR escaneable</strong> para activar el escrow.
                    </p>
                    <form id="formPedido" method="post">
                        <input type="hidden" name="accion" value="confirmar_pedido">
                        <div class="form-group">
                            <label>Dirección de entrega <span style="color:#e53e3e;">*</span></label>
                            <input type="text" name="direccion_entrega" class="form-control"
                                   placeholder="Ej: Av. Montes 123, La Paz" required>
                        </div>
                        <div class="form-group">
                            <label>Notas adicionales</label>
                            <textarea name="notas" class="form-control" rows="2"
                                      placeholder="Instrucciones especiales..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-cupaz-primary btn-block">
                            <i class="fas fa-file-invoice"></i> Crear pedido y pasar a pago
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BOTÓN FLOTANTE -->
<button class="cart-fab" onclick="abrirCarrito()" title="Ver carrito">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-badge" id="cartBadge" style="display:none;">0</span>
</button>

<script>
// ── Datos desde PHP (tipos ya forzados en PHP) ────────────────────────────────
const PRODUCTOS = <?php echo json_encode($productosJs, JSON_UNESCAPED_UNICODE); ?>;
let carrito     = <?php echo json_encode($carritoJs,   JSON_UNESCAPED_UNICODE); ?>;

// ── Helpers numéricos (evita el error toFixed sobre undefined) ────────────────
function numPrecio(v)   { return parseFloat(v)   || 0; }
function numCantidad(v) { return parseInt(v, 10) || 0; }
function agregarCsrf(fd) {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const token = csrfMeta ? csrfMeta.getAttribute('content') : '';
    if (token) {
        fd.append('_csrf', token);
    }
    return fd;
}

// ── Badge ─────────────────────────────────────────────────────────────────────
function totalItems() {
    return carrito.reduce((s, i) => s + numCantidad(i.cantidad), 0);
}
function actualizarBadge() {
    const badge = document.getElementById('cartBadge');
    if (!badge) return;
    const n = totalItems();
    badge.textContent = n;
    badge.style.display = n > 0 ? 'flex' : 'none';
    badge.classList.remove('pop');
    void badge.offsetWidth;
    badge.classList.add('pop');
}

// ── Modal detalle ─────────────────────────────────────────────────────────────
function abrirDetalle(id) {
    const p = PRODUCTOS[id];
    if (!p) return;

    document.getElementById('mdTitulo').textContent      = p.nombre;
    document.getElementById('mdDescripcion').textContent = p.descripcion || '—';
    document.getElementById('mdVendedor').textContent    = p.vendedor;
    document.getElementById('mdCategoria').textContent   = p.categoria || '—';
    document.getElementById('mdPrecio').textContent      = 'Bs ' + numPrecio(p.precio).toFixed(2);
    document.getElementById('mdIdProducto').value        = p.id;

    const stockEl = document.getElementById('mdStock');
    stockEl.textContent = 'Stock: ' + p.stock;
    stockEl.className   = 'stock-badge' + (p.stock === 0 ? ' agotado' : '');

    const img   = document.getElementById('mdImagen');
    const ph    = document.getElementById('mdPlaceholder');
    const input = document.getElementById('mdCantidad');
    input.max   = p.stock;
    input.value = 1;

    if (p.imagen) {
        img.src           = p.imagen;
        img.style.display = 'block';
        ph.style.display  = 'none';
    } else {
        img.style.display = 'none';
        ph.style.display  = 'block';
    }

    $('#modalDetalle').modal('show');
}

// ── Agregar al carrito ────────────────────────────────────────────────────────
function agregarAlCarrito() {
    const id       = parseInt(document.getElementById('mdIdProducto').value, 10);
    const cantidad = numCantidad(document.getElementById('mdCantidad').value) || 1;
    const p        = PRODUCTOS[id];
    if (!p || p.stock === 0) return;

    const existe = carrito.find(i => i.id_producto === id);
    if (existe) {
        existe.cantidad += cantidad;
    } else {
        carrito.push({
            id_producto: id,
            nombre:      p.nombre,
            precio:      numPrecio(p.precio),   // ← siempre float
            stock:       p.stock,
            imagen:      p.imagen,
            vendedor:    p.vendedor,
            cantidad:    cantidad,
        });
    }

    const fd = new FormData();
    fd.append('accion',      'agregar_carrito');
    fd.append('id_producto', id);
    fd.append('cantidad',    cantidad);
    fetch(window.location.href, { method: 'POST', body: agregarCsrf(fd) });

    actualizarBadge();
    $('#modalDetalle').modal('hide');
    mostrarToast(p.nombre + ' agregado al carrito');
}

// ── Renderizar carrito ────────────────────────────────────────────────────────
function renderCarrito() {
    const lista    = document.getElementById('carritoLista');
    const totalBar = document.getElementById('carritoTotal');
    const confirmS = document.getElementById('confirmSection');
    const montoEl  = document.getElementById('carritoMontoTotal');

    if (!lista) return;

    if (carrito.length === 0) {
        lista.innerHTML = `<div class="cart-empty-msg">
            <i class="fas fa-shopping-basket"></i>
            Tu carrito está vacío.<br>Agrega productos desde el catálogo.
        </div>`;
        if (totalBar) totalBar.style.display = 'none';
        if (confirmS) confirmS.style.display = 'none';
        return;
    }

    let html  = '';
    let total = 0;

    carrito.forEach(item => {
        const precio   = numPrecio(item.precio);       // ← fix principal
        const cantidad = numCantidad(item.cantidad);
        const subtotal = precio * cantidad;
        total += subtotal;

        const imgHtml = item.imagen
            ? `<img class="cart-item-img" src="${item.imagen}" alt="">`
            : `<div class="cart-item-img"><i class="fas fa-image" style="color:#c0c7d0;font-size:20px;"></i></div>`;

        html += `
        <div class="cart-item-row" id="cart-row-${item.id_producto}">
            ${imgHtml}
            <div class="cart-item-info">
                <div class="cart-item-name">${escapeHtml(item.nombre)}</div>
                <div class="cart-item-price">
                    Bs ${precio.toFixed(2)} c/u &nbsp;·&nbsp;
                    Subtotal: <strong>Bs ${subtotal.toFixed(2)}</strong>
                </div>
                <div class="cart-item-price">${escapeHtml(item.vendedor)}</div>
            </div>
            <input type="number" class="cart-item-qty"
                   value="${cantidad}" min="1" max="${item.stock}"
                   onchange="cambiarCantidad(${item.id_producto}, this.value)">
            <button class="cart-item-remove"
                    onclick="quitarDelCarrito(${item.id_producto})" title="Eliminar">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>`;
    });

    lista.innerHTML = html;
    if (montoEl)  montoEl.textContent      = 'Bs ' + total.toFixed(2);
    if (totalBar) totalBar.style.display   = 'flex';
    if (confirmS) confirmS.style.display   = 'block';
}

// ── Quitar ítem ───────────────────────────────────────────────────────────────
function quitarDelCarrito(id) {
    carrito = carrito.filter(i => i.id_producto !== id);
    actualizarBadge();
    renderCarrito();
    const fd = new FormData();
    fd.append('accion',      'eliminar_carrito');
    fd.append('id_producto', id);
    fetch(window.location.href, { method: 'POST', body: agregarCsrf(fd) });
}

// ── Cambiar cantidad ──────────────────────────────────────────────────────────
function cambiarCantidad(id, val) {
    const item = carrito.find(i => i.id_producto === id);
    if (!item) return;
    item.cantidad = Math.max(1, Math.min(numCantidad(val), item.stock));
    actualizarBadge();
    renderCarrito();
    const fd = new FormData();
    fd.append('accion',      'actualizar_cantidad');
    fd.append('id_producto', id);
    fd.append('cantidad',    item.cantidad);
    fetch(window.location.href, { method: 'POST', body: agregarCsrf(fd) });
}

// ── Abrir modal carrito ───────────────────────────────────────────────────────
function abrirCarrito() {
    renderCarrito();
    $('#modalCarrito').modal('show');
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function mostrarToast(msg) {
    let t = document.getElementById('cupazToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'cupazToast';
        t.style.cssText = 'position:fixed;bottom:100px;right:32px;background:#2d3748;color:#fff;' +
            'padding:10px 18px;border-radius:8px;font-size:13.5px;z-index:2000;' +
            'opacity:0;transition:opacity .25s;pointer-events:none;max-width:280px;';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = '1';
    clearTimeout(t._timer);
    t._timer = setTimeout(function() { t.style.opacity = '0'; }, 2400);
}

// ── Escape HTML ───────────────────────────────────────────────────────────────
function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ──────────────────────────────────────────────────────────────────────
function filtrarCatalogo(categoria, boton) {
    const cards = document.querySelectorAll('.catalog-card[data-category]');
    const empty = document.getElementById('catalogEmptyFilter');
    let visibles = 0;

    document.querySelectorAll('.catalog-tab').forEach(tab => tab.classList.remove('active'));
    if (boton) {
        boton.classList.add('active');
    }

    cards.forEach(card => {
        const mostrar = categoria === 'todos' || card.dataset.category === categoria;
        card.style.display = mostrar ? '' : 'none';
        if (mostrar) {
            visibles++;
        }
    });

    if (empty) {
        empty.style.display = visibles === 0 ? 'block' : 'none';
    }
}

actualizarBadge();
</script>

<?php require ROOT_VIEW . '/template/footer.php'; ?>
