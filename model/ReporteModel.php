<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class ReporteModel extends ModeloBasePDO
{
    public function __construct()
    {
        parent::__construct();
    }

    public function resumenGeneral()
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
                    (SELECT COUNT(*) FROM productos) AS total_productos,
                    (SELECT COUNT(*) FROM pedidos) AS total_pedidos,
                    (SELECT COUNT(*) FROM pagos WHERE estado = 'RETENIDO') AS pagos_retenidos,
                    (SELECT COUNT(*) FROM disputas WHERE estado IN ('ABIERTA', 'EN_REVISION')) AS disputas_activas,
                    (SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE estado = 'LIBERADO') AS total_liberado";
        return parent::gselect($sql, []);
    }

    public function listarReportes()
    {
        $sql = "SELECT r.id_reporte, r.tipo, r.periodo_inicio, r.periodo_fin, r.fecha_generacion,
                       u.nombre AS generado_por
                FROM reportes r
                INNER JOIN usuarios u ON u.id_usuario = r.generado_por
                ORDER BY r.fecha_generacion DESC, r.id_reporte DESC";
        return parent::gselect($sql, []);
    }

    public function obtenerReporte($idReporte)
    {
        $sql = "SELECT r.id_reporte, r.tipo, r.periodo_inicio, r.periodo_fin, r.datos_json, r.fecha_generacion,
                       u.nombre AS generado_por
                FROM reportes r
                INNER JOIN usuarios u ON u.id_usuario = r.generado_por
                WHERE r.id_reporte = :id_reporte
                LIMIT 1";
        $param = [];
        array_push($param, [':id_reporte', $idReporte, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function generarReporte($tipo, $inicio, $fin, $generadoPor)
    {
        $array = [];
        try {
            $datos = [];

            if ($tipo === 'VENTAS') {
                $stmt = $this->_db->prepare("SELECT u.nombre AS vendedor,
                                                    COUNT(pa.id_pago) AS pagos,
                                                    COALESCE(SUM(pa.monto), 0) AS total
                                             FROM pagos pa
                                             INNER JOIN usuarios u ON u.id_usuario = pa.id_vendedor
                                             WHERE DATE(pa.fecha_pago) BETWEEN :inicio AND :fin
                                             GROUP BY u.id_usuario, u.nombre
                                             ORDER BY total DESC");
            } elseif ($tipo === 'PEDIDOS') {
                $stmt = $this->_db->prepare("SELECT estado, COUNT(*) AS cantidad, COALESCE(SUM(monto_total), 0) AS total
                                             FROM pedidos
                                             WHERE DATE(fecha_pedido) BETWEEN :inicio AND :fin
                                             GROUP BY estado
                                             ORDER BY cantidad DESC");
            } elseif ($tipo === 'PAGOS') {
                $stmt = $this->_db->prepare("SELECT estado, COUNT(*) AS cantidad, COALESCE(SUM(monto), 0) AS total
                                             FROM pagos
                                             WHERE DATE(fecha_pago) BETWEEN :inicio AND :fin
                                             GROUP BY estado
                                             ORDER BY cantidad DESC");
            } else {
                $stmt = $this->_db->prepare("SELECT estado, COUNT(*) AS cantidad
                                             FROM disputas
                                             WHERE DATE(fecha_apertura) BETWEEN :inicio AND :fin
                                             GROUP BY estado
                                             ORDER BY cantidad DESC");
            }

            $stmt->bindValue(':inicio', $inicio, PDO::PARAM_STR);
            $stmt->bindValue(':fin', $fin, PDO::PARAM_STR);
            $stmt->execute();
            $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql = "INSERT INTO reportes (tipo, periodo_inicio, periodo_fin, generado_por, datos_json, fecha_generacion)
                    VALUES (:tipo, :inicio, :fin, :generado_por, :datos_json, NOW())";
            $param = [];
            array_push($param, [':tipo', $tipo, PDO::PARAM_STR]);
            array_push($param, [':inicio', $inicio, PDO::PARAM_STR]);
            array_push($param, [':fin', $fin, PDO::PARAM_STR]);
            array_push($param, [':generado_por', $generadoPor, PDO::PARAM_INT]);
            array_push($param, [':datos_json', json_encode($datos, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR]);

            $insert = parent::ginsert($sql, $param);
            if (empty($insert['ESTADO'])) {
                return $insert;
            }

            return [
                'ESTADO' => true,
                'DATA' => $datos,
            ];
        } catch (PDOException $e) {
            $array['ESTADO'] = false;
            $array['ERROR'] = $e->getMessage();
            return $array;
        }
    }
}
