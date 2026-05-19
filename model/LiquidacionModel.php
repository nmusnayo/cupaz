<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class LiquidacionModel extends ModeloBasePDO
{
    const COMISION_PORCENTAJE = 10.00;

    public function __construct()
    {
        parent::__construct();
        $this->asegurarTabla();
    }

    private function asegurarTabla()
    {
        $sql = "CREATE TABLE IF NOT EXISTS liquidaciones_vendedor (
                    id_liquidacion INT AUTO_INCREMENT PRIMARY KEY,
                    id_pago INT NOT NULL UNIQUE,
                    id_vendedor INT NOT NULL,
                    id_admin INT NOT NULL,
                    monto DECIMAL(10,2) NOT NULL,
                    estado ENUM('PENDIENTE','PAGADA') DEFAULT 'PAGADA',
                    referencia_liquidacion VARCHAR(150),
                    observaciones TEXT,
                    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
                )";
        $this->_db->exec($sql);
        $this->asegurarColumna('monto_bruto', 'DECIMAL(10,2) NULL');
        $this->asegurarColumna('comision_porcentaje', 'DECIMAL(5,2) NOT NULL DEFAULT 10.00');
        $this->asegurarColumna('monto_comision', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        $this->asegurarColumna('monto_vendedor', 'DECIMAL(10,2) NULL');
        $this->_db->exec("UPDATE liquidaciones_vendedor
                          SET monto_bruto = COALESCE(monto_bruto, monto),
                              monto_vendedor = COALESCE(monto_vendedor, monto)
                          WHERE monto_bruto IS NULL OR monto_vendedor IS NULL");
    }

    private function asegurarColumna($columna, $definicion)
    {
        $stmt = $this->_db->prepare("SELECT COUNT(*)
                                     FROM information_schema.COLUMNS
                                     WHERE TABLE_SCHEMA = DATABASE()
                                       AND TABLE_NAME = 'liquidaciones_vendedor'
                                       AND COLUMN_NAME = :columna");
        $stmt->bindValue(':columna', $columna, PDO::PARAM_STR);
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            $this->_db->exec("ALTER TABLE liquidaciones_vendedor ADD {$columna} {$definicion}");
        }
    }

    public function listarTodas()
    {
        $sql = "SELECT l.id_liquidacion, l.id_pago, l.id_vendedor, l.id_admin, l.monto,
                       l.monto_bruto, l.comision_porcentaje, l.monto_comision, l.monto_vendedor,
                       l.estado,
                       l.referencia_liquidacion, l.observaciones, l.fecha_registro,
                       ven.nombre AS vendedor, adm.nombre AS admin
                FROM liquidaciones_vendedor l
                INNER JOIN usuarios ven ON ven.id_usuario = l.id_vendedor
                INNER JOIN usuarios adm ON adm.id_usuario = l.id_admin
                ORDER BY l.fecha_registro DESC, l.id_liquidacion DESC";
        return parent::gselect($sql, []);
    }

    public function listarPorVendedor($idVendedor)
    {
        $sql = "SELECT l.id_liquidacion, l.id_pago, l.id_vendedor, l.id_admin, l.monto,
                       l.monto_bruto, l.comision_porcentaje, l.monto_comision, l.monto_vendedor,
                       l.estado,
                       l.referencia_liquidacion, l.observaciones, l.fecha_registro,
                       ven.nombre AS vendedor, adm.nombre AS admin,
                       pa.id_pedido, pa.referencia_pago
                FROM liquidaciones_vendedor l
                INNER JOIN usuarios ven ON ven.id_usuario = l.id_vendedor
                INNER JOIN usuarios adm ON adm.id_usuario = l.id_admin
                INNER JOIN pagos pa ON pa.id_pago = l.id_pago
                WHERE l.id_vendedor = :id_vendedor
                ORDER BY l.fecha_registro DESC, l.id_liquidacion DESC";
        $param = [];
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function registrar($idPago, $idVendedor, $idAdmin, $monto, $referencia, $observaciones)
    {
        $montoBruto = round((float)$monto, 2);
        $porcentaje = self::COMISION_PORCENTAJE;
        $montoComision = round($montoBruto * ($porcentaje / 100), 2);
        $montoVendedor = round($montoBruto - $montoComision, 2);

        $sql = "INSERT INTO liquidaciones_vendedor
                (id_pago, id_vendedor, id_admin, monto, monto_bruto, comision_porcentaje, monto_comision, monto_vendedor,
                 estado, referencia_liquidacion, observaciones, fecha_registro)
                VALUES
                (:id_pago, :id_vendedor, :id_admin, :monto, :monto_bruto, :comision_porcentaje, :monto_comision,
                 :monto_vendedor, 'PAGADA', :referencia, :observaciones, NOW())";
        $param = [];
        array_push($param, [':id_pago', $idPago, PDO::PARAM_INT]);
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        array_push($param, [':id_admin', $idAdmin, PDO::PARAM_INT]);
        array_push($param, [':monto', $montoVendedor]);
        array_push($param, [':monto_bruto', $montoBruto]);
        array_push($param, [':comision_porcentaje', $porcentaje]);
        array_push($param, [':monto_comision', $montoComision]);
        array_push($param, [':monto_vendedor', $montoVendedor]);
        array_push($param, [':referencia', $referencia, PDO::PARAM_STR]);
        array_push($param, [':observaciones', $observaciones, PDO::PARAM_STR]);
        return parent::ginsert($sql, $param);
    }
}
