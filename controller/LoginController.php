<?php
require_once(dirname(__DIR__) . "/config/global.php");

header("Access-Control-Allow-Origin: " . HTTP_BASE);
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once(ROOT_DIR . "/model/UsuarioModel.php");
require_once(ROOT_DIR . "/model/AuditoriaModel.php");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $Path_Info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : '');
    $request = explode('/', trim($Path_Info, '/'));
} catch (Exception $e) {
    echo $e->getMessage();
}

switch ($method) {
    case 'POST':
        $p_ope = !empty($input['ope']) ? $input['ope'] : $_POST['ope'] ?? '';
        if ($p_ope == 'login') {
            login($input);
        } else if ($p_ope == 'register') {
            register($input);
        } else if ($p_ope == 'filterall') {
            filterAllUsers();
        } else if ($p_ope == 'logout') {
            session_destroy();
        }
        break;

    case 'GET':
        $p_ope = $_GET['ope'] ?? '';
        if ($p_ope == 'filterall') {
            filterAllUsers();
        }
        break;
}

// FUNCIONES
function login($input)
{
    $p_correo_electronico = $input['correo_electronico'] ?? $_POST['correo_electronico'] ?? '';
    $p_password = $input['contrasena'] ?? $_POST['contrasena'] ?? '';
    $su = new UsuarioModel();
    $su->bootstrapAcceso();
    $var = $su->verificarlogin($p_correo_electronico, $p_password);
    if (!empty($var['DATA'])) {
        $_SESSION['login'] = $var['DATA'][0];
        (new AuditoriaModel())->registrar('login_exitoso', 'seguridad', ['correo' => $p_correo_electronico], (int)$_SESSION['login']['id_usuario']);
        echo json_encode($var);
        exit();
    } else {
        (new AuditoriaModel())->registrar('login_fallido', 'seguridad', ['correo' => $p_correo_electronico], null);
        echo json_encode([
            'ESTADO' => false,
            'ERROR' => "Usuario o Contraseña no válida."
        ]);
        exit();
    }
}

function register($input)
{
    $p_correo_electronico = $input['correo_electronico'] ?? $_POST['correo_electronico'] ?? '';
    $p_nombre = $input['nombre'] ?? $_POST['nombre'] ?? '';
    $p_telefono = $input['telefono'] ?? $_POST['telefono'] ?? null;
    $p_ci = $input['ci'] ?? $_POST['ci'] ?? null;
    $p_id_rol = 2;
    if (strtoupper($_SESSION['login']['rol'] ?? '') === 'ADMIN') {
        $p_id_rol = (int)($input['id_rol'] ?? $_POST['id_rol'] ?? 2);
    }
    $p_contrasena = $input['contrasena'] ?? $_POST['contrasena'] ?? '';
    $usuario = new UsuarioModel();
    $usuario->bootstrapAcceso();
    $p_contrasena = password_hash($p_contrasena, PASSWORD_BCRYPT);
    $var = $usuario->register($p_nombre, $p_correo_electronico, $p_contrasena, $p_telefono, $p_ci, $p_id_rol);
    (new AuditoriaModel())->registrar('registro_usuario', 'seguridad', ['correo' => $p_correo_electronico, 'nombre' => $p_nombre]);
    echo json_encode($var);
}

// NUEVA FUNCIÓN PARA OBTENER TODOS LOS USUARIOS
function filterAllUsers()
{
    if (strtoupper($_SESSION['login']['rol'] ?? '') !== 'ADMIN') {
        http_response_code(403);
        echo json_encode([
            'ESTADO' => false,
            'ERROR' => 'No autorizado.',
        ]);
        return;
    }
    $usuario = new UsuarioModel();
    $var = $usuario->findall(); // Debes tener un método findall en UsuarioModel
    echo json_encode($var);
}
