<?php
define("CONTROLADOR_DEFECTO", "Usuarios");
define("ACCION_DEFECTO", "index");

$appFolderEnv = getenv('CUPAZ_APP_FOLDER');
define("APP_FOLDER", $appFolderEnv !== false ? trim($appFolderEnv, '/') : basename(dirname(__DIR__)));
define("RUTA_BASE", $_SERVER['DOCUMENT_ROOT']."/");
$httpBaseEnv = getenv('CUPAZ_HTTP_BASE');
if ($httpBaseEnv) {
    define("HTTP_BASE", rtrim($httpBaseEnv, '/'));
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    define("HTTP_BASE", $scheme . "://" . $host . (APP_FOLDER !== '' ? "/" . APP_FOLDER : ""));
}
define('ROOT_DIR', dirname(__DIR__));
define('ROOT_CORE', ROOT_DIR . '/core');
define('ROOT_UPLOAD', ROOT_DIR . '/uploads');
define('ROOT_DOCUMENTOS', ROOT_UPLOAD . '/documentos'); // Carpeta para documentos
define('ROOT_VIEW', ROOT_DIR . '/view');
define('ROOT_REPORT', ROOT_DIR . '/report');
define('ROOT_REPORT_DOWN', ROOT_DIR . '/report_download');
define("URL_RESOURCES", HTTP_BASE."/public/");

// JWT TOKEN
define('SECRET_KEY', 'MIEMPRESA.MBmxKMifghY7d55sghvTlB1jyAjB3uN0g6ZDdOXpz21');
define('ALGORITHM', 'HS256');

?>
