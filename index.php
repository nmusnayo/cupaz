<?php
session_start();
require_once './config/global.php';

$request = $_SERVER['REQUEST_URI'];
$request = parse_url($request, PHP_URL_PATH);
$segments = explode('/', trim($request, '/'));
$appFolder = APP_FOLDER;
$routeOffset = $appFolder === '' ? 0 : 1;
$route = $segments[$routeOffset] ?? '';
$subroute = $segments[$routeOffset + 1] ?? '';
function home()
{
    require ROOT_DIR . '/view/home.php';
}
function error404()
{
    require ROOT_DIR . '/view/home.php';
}
function verificarlogin()
{
    if (!isset($_SESSION['login']['nombre'])) {
        echo '<script>window.location.href="' . HTTP_BASE . '/login"</script>';
        exit;
    }
}
function csrf_token()
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}
function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
function csrf_verify()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $token = $_POST['_csrf'] ?? '';
    return is_string($token) && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}
function obtenerRolActual()
{
    return strtoupper($_SESSION['login']['rol'] ?? '');
}
function accesoDenegado($mensaje = 'No tienes permisos para acceder a este mÃ³dulo.')
{
    $_SESSION['flash_error'] = $mensaje;
    echo '<script>window.location.href="' . HTTP_BASE . '/home/"</script>';
    exit;
}
function requiereRoles($rolesPermitidos, $mensaje = 'No tienes permisos para acceder a este mÃ³dulo.')
{
    verificarlogin();
    $rolActual = obtenerRolActual();
    $rolesPermitidos = array_map('strtoupper', $rolesPermitidos);
    if (!in_array($rolActual, $rolesPermitidos, true)) {
        accesoDenegado($mensaje);
    }
}
function registrarAuditoria($accion, $modulo, $detalle = null)
{
    try {
        require_once ROOT_DIR . '/model/AuditoriaModel.php';
        $auditoria = new AuditoriaModel();
        $auditoria->registrar($accion, $modulo, $detalle);
    } catch (Throwable $e) {
    }
}
function auditoriaModuloRuta($route, $subroute)
{
    if ($route === '') {
        return 'home';
    }
    return $subroute !== '' ? $route . '/' . $subroute : $route;
}
function prepararDetalleAuditoria($route, $subroute, $detalle)
{
    $accion = $detalle['accion'] ?? '';
    if ($route === 'client' && $subroute === 'catalogo' && $accion === 'confirmar_pedido') {
        $detalle['id_cliente'] = $_SESSION['login']['id_usuario'] ?? '';
        $detalle['metodo'] = $detalle['metodo'] ?? 'QR_CUPAZ';
        $detalle['items'] = count($_SESSION['carrito'] ?? []);
    }
    return $detalle;
}

if ($appFolder === '' || (($segments[0] ?? '') === $appFolder)) {
    $rutaPublicaPost = in_array($route, ['login', 'register'], true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rutaPublicaPost && !csrf_verify()) {
        http_response_code(403);
        echo 'Solicitud no vÃ¡lida. Recarga la pÃ¡gina e intÃ©ntalo nuevamente.';
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rutaPublicaPost && isset($_SESSION['login'])) {
        registrarAuditoria($_POST['accion'] ?? 'POST', auditoriaModuloRuta($route, $subroute), prepararDetalleAuditoria($route, $subroute, $_POST));
    }

    switch ($route) {
        case 'login':
            require ROOT_VIEW . '/seguridad/login.php';
            break;
        case 'register':
            require ROOT_VIEW . '/seguridad/register.php';
            break;
        case 'logout':
            registrarAuditoria('logout', 'seguridad', ['usuario' => $_SESSION['login']['correo'] ?? '']);
            session_destroy();
            $data = [
                'ope' => 'logout',

            ];
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode($data),
                ]
            ]);
            $url = HTTP_BASE . "/controller/LoginController.php";
            $response = file_get_contents($url, false, $context);
            echo '<script>window.location.href="' . HTTP_BASE . '/login"</script>';
            break;
        case 'admin':
            requiereRoles(['ADMIN'], 'Solo el administrador puede acceder a este mÃ³dulo.');
            switch ($subroute) {
                case 'usuarios':
                    require ROOT_VIEW . '/admin/usuarios/index.php';
                    break;
                case 'categorias':
                    require ROOT_VIEW . '/admin/categorias/index.php';
                    break;
                case 'subcategorias':
                    require ROOT_VIEW . '/admin/subcategorias/index.php';
                    break;
                case 'pedidos':
                    require ROOT_VIEW . '/admin/pedidos/index.php';
                    break;
                case 'pagos':
                    require ROOT_VIEW . '/admin/pagos/index.php';
                    break;
                case 'liquidaciones':
                    require ROOT_VIEW . '/admin/liquidaciones/index.php';
                    break;
                case 'disputas':
                    require ROOT_VIEW . '/admin/disputas/index.php';
                    break;
                case 'reportes':
                    require ROOT_VIEW . '/admin/reportes/index.php';
                    break;
                case 'backups':
                    require ROOT_VIEW . '/admin/backups/index.php';
                    break;
                case 'auditoria':
                    require ROOT_VIEW . '/admin/auditoria/index.php';
                    break;
                case 'productos':
                    require ROOT_VIEW . '/vendor/productos/index.php';
                    break;
                case 'catalogo':
                    require ROOT_VIEW . '/client/catalogo/index.php';
                    break;
                default:
                    home();
                    break;
            }
            break;
        case 'vendor':
            requiereRoles(['VENDEDOR', 'ADMIN'], 'Solo el vendedor o el administrador pueden acceder a este mÃ³dulo.');
            switch ($subroute) {
                case 'productos':
                    require ROOT_VIEW . '/vendor/productos/index.php';
                    break;
                case 'catalogo':
                    require ROOT_VIEW . '/client/catalogo/index.php';
                    break;
                case 'ventas':
                    require ROOT_VIEW . '/vendor/ventas/index.php';
                    break;
                case 'cobros':
                    require ROOT_VIEW . '/vendor/cobros/index.php';
                    break;
                case 'disputas':
                    require ROOT_VIEW . '/admin/disputas/index.php';
                    break;
                default:
                    home();
                    break;
            }
            break;
        case 'entrega':
            $token = $_GET['token'] ?? '';
            require ROOT_VIEW . '/client/entrega_confirmar.php';
            break;
        case 'client':
            requiereRoles(['CLIENTE', 'ADMIN', 'VENDEDOR'], 'Solo usuarios autorizados pueden acceder a este mÃ³dulo.');
            switch ($subroute) {
                case 'catalogo':
                    require ROOT_VIEW . '/client/catalogo/index.php';
                    break;
                case 'pedidos':
                    require ROOT_VIEW . '/client/pedidos/index.php';
                    break;
                case 'disputas':
                    require ROOT_VIEW . '/admin/disputas/index.php';
                    break;
                default:
                    home();
                    break;
            }
            break;

        default:
            verificarlogin();
            home();
            break;
    }
}

