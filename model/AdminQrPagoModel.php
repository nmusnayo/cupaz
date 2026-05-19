<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class AdminQrPagoModel extends ModeloBasePDO
{
    public function __construct()
    {
        parent::__construct();
        $this->asegurarTabla();
    }

    private function asegurarTabla()
    {
        $this->_db->exec("CREATE TABLE IF NOT EXISTS admin_qr_pagos (
            id_qr INT AUTO_INCREMENT PRIMARY KEY,
            id_admin INT NOT NULL,
            titular VARCHAR(150) NOT NULL,
            qr_pago VARCHAR(255) NOT NULL,
            observaciones TEXT,
            activo TINYINT(1) DEFAULT 1,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_qr_activo (activo)
        )");
    }

    public function obtenerActivo()
    {
        $sql = "SELECT id_qr, id_admin, titular, qr_pago, observaciones, activo, fecha_actualizacion
                FROM admin_qr_pagos
                WHERE activo = 1
                ORDER BY id_qr DESC
                LIMIT 1";
        return parent::gselect($sql, []);
    }

    public function guardar($idAdmin, $titular, $qrPago, $observaciones)
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $this->_db->exec("UPDATE admin_qr_pagos SET activo = 0 WHERE activo = 1");

            $stmt = $this->_db->prepare("INSERT INTO admin_qr_pagos
                (id_admin, titular, qr_pago, observaciones, activo, fecha_actualizacion)
                VALUES (:id_admin, :titular, :qr_pago, :observaciones, 1, NOW())");
            $stmt->bindValue(':id_admin', $idAdmin, PDO::PARAM_INT);
            $stmt->bindValue(':titular', $titular, PDO::PARAM_STR);
            $stmt->bindValue(':qr_pago', $qrPago, PDO::PARAM_STR);
            $stmt->bindValue(':observaciones', $observaciones, PDO::PARAM_STR);
            $stmt->execute();

            $this->_db->commit();
            $array['ESTADO'] = true;
        } catch (PDOException $e) {
            if ($this->_db->inTransaction()) {
                $this->_db->rollBack();
            }
            $array['ESTADO'] = false;
            $array['ERROR'] = $e->getMessage();
        }
        return $array;
    }
}
