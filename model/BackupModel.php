<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class BackupModel extends ModeloBasePDO
{
    private $backupDir;

    public function __construct()
    {
        parent::__construct();
        $this->backupDir = ROOT_DIR . '/backups';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function listarBackups()
    {
        $archivos = glob($this->backupDir . '/*.sql') ?: [];
        $backups = [];
        foreach ($archivos as $archivo) {
            $backups[] = [
                'nombre' => basename($archivo),
                'tamano' => filesize($archivo),
                'fecha' => date('Y-m-d H:i:s', filemtime($archivo)),
            ];
        }
        usort($backups, function ($a, $b) {
            return strcmp($b['fecha'], $a['fecha']);
        });
        return $backups;
    }

    public function generarBackup()
    {
        $nombre = 'backup_cupaz_' . date('Ymd_His') . '.sql';
        $ruta = $this->backupDir . '/' . $nombre;
        $sql = $this->crearDumpSql();

        if (file_put_contents($ruta, $sql) === false) {
            return ['ESTADO' => false, 'ERROR' => 'No se pudo escribir el archivo de respaldo.'];
        }

        $this->protegerDirectorio();
        return ['ESTADO' => true, 'DATA' => ['nombre' => $nombre]];
    }

    public function rutaBackup($nombre)
    {
        $nombreSeguro = basename($nombre);
        $ruta = $this->backupDir . '/' . $nombreSeguro;
        if (!preg_match('/^backup_cupaz_\d{8}_\d{6}\.sql$/', $nombreSeguro) || !is_file($ruta)) {
            return null;
        }
        return $ruta;
    }

    public function eliminarBackup($nombre)
    {
        $ruta = $this->rutaBackup($nombre);
        if ($ruta === null) {
            return ['ESTADO' => false, 'ERROR' => 'El respaldo no existe.'];
        }
        return ['ESTADO' => unlink($ruta), 'ERROR' => 'No se pudo eliminar el respaldo.'];
    }

    private function crearDumpSql()
    {
        $lineas = [];
        $lineas[] = '-- Respaldo CUPAZ';
        $lineas[] = '-- Generado: ' . date('Y-m-d H:i:s');
        $lineas[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
        $lineas[] = 'START TRANSACTION;';
        $lineas[] = 'SET time_zone = "+00:00";';
        $lineas[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $lineas[] = '';

        $tablas = $this->_db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($tablas as $tablaInfo) {
            $tabla = $tablaInfo[0];
            $tipo = strtoupper($tablaInfo[1] ?? 'BASE TABLE');
            if ($tipo !== 'BASE TABLE') {
                continue;
            }

            $tablaSql = str_replace('`', '``', $tabla);
            $crear = $this->_db->query('SHOW CREATE TABLE `' . $tablaSql . '`')->fetch(PDO::FETCH_ASSOC);
            $createTable = $crear['Create Table'] ?? array_values($crear)[1] ?? '';

            $lineas[] = '-- Estructura de tabla `' . $tabla . '`';
            $lineas[] = 'DROP TABLE IF EXISTS `' . $tablaSql . '`;';
            $lineas[] = $createTable . ';';
            $lineas[] = '';

            $stmt = $this->_db->query('SELECT * FROM `' . $tablaSql . '`');
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($filas)) {
                continue;
            }

            $columnas = array_map(function ($columna) {
                return '`' . str_replace('`', '``', $columna) . '`';
            }, array_keys($filas[0]));

            $lineas[] = '-- Datos de tabla `' . $tabla . '`';
            foreach ($filas as $fila) {
                $valores = array_map(function ($valor) {
                    return $valor === null ? 'NULL' : $this->_db->quote((string)$valor);
                }, array_values($fila));
                $lineas[] = 'INSERT INTO `' . $tablaSql . '` (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ');';
            }
            $lineas[] = '';
        }

        $lineas[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $lineas[] = 'COMMIT;';
        $lineas[] = '';

        return implode(PHP_EOL, $lineas);
    }

    private function protegerDirectorio()
    {
        $htaccess = $this->backupDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        $index = $this->backupDir . '/index.html';
        if (!file_exists($index)) {
            file_put_contents($index, '');
        }
    }
}
