<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class CobroVendedorModel extends ModeloBasePDO
{
    public function __construct()
    {
        parent::__construct();
        $this->asegurarTabla();
    }

    private function asegurarTabla()
    {
        $sql = "CREATE TABLE IF NOT EXISTS vendedor_cobros (
                    id_cobro INT AUTO_INCREMENT PRIMARY KEY,
                    id_vendedor INT NOT NULL UNIQUE,
                    titular VARCHAR(150) NOT NULL,
                    banco VARCHAR(120),
                    numero_cuenta VARCHAR(80),
                    qr_cobro VARCHAR(255),
                    observaciones TEXT,
                    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP
                )";
        $this->_db->exec($sql);
    }

    public function obtenerPorVendedor($idVendedor)
    {
        $sql = "SELECT id_cobro, id_vendedor, titular, banco, numero_cuenta, qr_cobro, observaciones, fecha_actualizacion
                FROM vendedor_cobros
                WHERE id_vendedor = :id_vendedor
                LIMIT 1";
        $param = [];
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function guardar($idVendedor, $titular, $banco, $numeroCuenta, $qrCobro, $observaciones)
    {
        $existente = $this->obtenerPorVendedor($idVendedor);
        if (!empty($existente['DATA'][0])) {
            $sql = "UPDATE vendedor_cobros
                    SET titular = :titular,
                        banco = :banco,
                        numero_cuenta = :numero_cuenta,
                        qr_cobro = COALESCE(:qr_cobro, qr_cobro),
                        observaciones = :observaciones,
                        fecha_actualizacion = NOW()
                    WHERE id_vendedor = :id_vendedor";
            $param = [];
            array_push($param, [':titular', $titular, PDO::PARAM_STR]);
            array_push($param, [':banco', $banco, PDO::PARAM_STR]);
            array_push($param, [':numero_cuenta', $numeroCuenta, PDO::PARAM_STR]);
            array_push($param, [':qr_cobro', $qrCobro, PDO::PARAM_STR]);
            array_push($param, [':observaciones', $observaciones, PDO::PARAM_STR]);
            array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
            return parent::gupdate($sql, $param);
        }

        $sql = "INSERT INTO vendedor_cobros
                (id_vendedor, titular, banco, numero_cuenta, qr_cobro, observaciones, fecha_actualizacion)
                VALUES
                (:id_vendedor, :titular, :banco, :numero_cuenta, :qr_cobro, :observaciones, NOW())";
        $param = [];
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        array_push($param, [':titular', $titular, PDO::PARAM_STR]);
        array_push($param, [':banco', $banco, PDO::PARAM_STR]);
        array_push($param, [':numero_cuenta', $numeroCuenta, PDO::PARAM_STR]);
        array_push($param, [':qr_cobro', $qrCobro, PDO::PARAM_STR]);
        array_push($param, [':observaciones', $observaciones, PDO::PARAM_STR]);
        return parent::ginsert($sql, $param);
    }
}
