<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";
require_once dirname(__DIR__) . "/model/LiquidacionModel.php";

class PagoModel extends ModeloBasePDO
{
    private $schemaCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->asegurarEsquemaOperativo();
    }

    private function asegurarEsquemaOperativo()
    {
        if (!$this->hasColumn('detalle_pedidos', 'id_vendedor')) {
            $this->_db->exec("ALTER TABLE detalle_pedidos ADD id_vendedor INT NULL AFTER id_producto");
            $this->schemaCache['detalle_pedidos:id_vendedor'] = true;
            $this->_db->exec("UPDATE detalle_pedidos dp
                              INNER JOIN productos p ON p.id_producto = dp.id_producto
                              SET dp.id_vendedor = p.id_vendedor
                              WHERE dp.id_vendedor IS NULL");
        }

        if (!$this->hasColumn('pagos', 'id_vendedor')) {
            $this->_db->exec("ALTER TABLE pagos ADD id_vendedor INT NULL AFTER id_pedido");
            $this->schemaCache['pagos:id_vendedor'] = true;
            $this->_db->exec("UPDATE pagos pa
                              INNER JOIN (
                                  SELECT id_pedido, MIN(id_vendedor) AS id_vendedor
                                  FROM detalle_pedidos
                                  WHERE id_vendedor IS NOT NULL AND id_vendedor > 0
                                  GROUP BY id_pedido
                              ) dp ON dp.id_pedido = pa.id_pedido
                              SET pa.id_vendedor = dp.id_vendedor
                              WHERE pa.id_vendedor IS NULL");
        }

        $this->asegurarEstadosPago();
        $this->asegurarEstadosPedido();
        $this->asegurarComprobantesPago();
        $this->asegurarEvidencias();

        $this->_db->exec("UPDATE detalle_pedidos dp
                          INNER JOIN productos p ON p.id_producto = dp.id_producto
                          SET dp.id_vendedor = p.id_vendedor
                          WHERE dp.id_vendedor IS NULL OR dp.id_vendedor = 0");

        $this->_db->exec("UPDATE pagos pa
                          INNER JOIN (
                              SELECT id_pedido, MIN(id_vendedor) AS id_vendedor
                              FROM detalle_pedidos
                              WHERE id_vendedor IS NOT NULL AND id_vendedor > 0
                              GROUP BY id_pedido
                          ) dp ON dp.id_pedido = pa.id_pedido
                          SET pa.id_vendedor = dp.id_vendedor
                          WHERE pa.id_vendedor IS NULL OR pa.id_vendedor = 0");

        $this->_db->exec("UPDATE pagos
                          SET metodo_pago = 'QR_CUPAZ_REGISTRADO'
                          WHERE metodo_pago IS NULL
                             OR metodo_pago = ''
                             OR metodo_pago = 'SIMULADO'");

        $this->quitarUnicoPagoPorPedido();
        $this->crearEnviosFaltantes();
    }

    private function asegurarEstadosPago()
    {
        if (!$this->enumTieneValor('pagos', 'estado', 'POR_VERIFICAR')) {
            $this->_db->exec("ALTER TABLE pagos MODIFY estado ENUM('POR_VERIFICAR','RETENIDO','LIBERADO','EN_DISPUTA','REEMBOLSADO','CANCELADO') DEFAULT 'POR_VERIFICAR'");
        }
    }

    private function asegurarEstadosPedido()
    {
        if (!$this->enumTieneValor('pedidos', 'estado', 'PAGO_EN_VERIFICACION')) {
            $this->_db->exec("ALTER TABLE pedidos MODIFY estado ENUM('PENDIENTE_PAGO','PAGO_EN_VERIFICACION','PAGO_RETENIDO','ENVIADO','ENTREGADO','COMPLETADO','EN_DISPUTA','REEMBOLSADO','CANCELADO') DEFAULT 'PENDIENTE_PAGO'");
        }
    }

    private function asegurarComprobantesPago()
    {
        $this->_db->exec("CREATE TABLE IF NOT EXISTS comprobantes_pago (
            id_comprobante INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido INT NOT NULL,
            id_cliente INT NOT NULL,
            id_qr_admin INT NULL,
            url_archivo VARCHAR(255) NOT NULL,
            referencia_cliente VARCHAR(150),
            estado ENUM('PENDIENTE','VERIFICADO','RECHAZADO') DEFAULT 'PENDIENTE',
            fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_verificacion DATETIME NULL,
            INDEX idx_comprobantes_pedido (id_pedido)
        )");
    }

    private function asegurarEvidencias()
    {
        $this->_db->exec("CREATE TABLE IF NOT EXISTS evidencias (
            id_evidencia INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido INT NOT NULL,
            id_usuario INT NOT NULL,
            tipo ENUM('ENVIO','RECEPCION','DISPUTA') NOT NULL,
            url_archivo VARCHAR(255) NOT NULL,
            descripcion TEXT,
            fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    private function enumTieneValor($table, $column, $value)
    {
        $stmt = $this->_db->prepare("SELECT COLUMN_TYPE
                                     FROM information_schema.COLUMNS
                                     WHERE TABLE_SCHEMA = DATABASE()
                                       AND TABLE_NAME = :table_name
                                       AND COLUMN_NAME = :column_name
                                     LIMIT 1");
        $stmt->bindValue(':table_name', $table, PDO::PARAM_STR);
        $stmt->bindValue(':column_name', $column, PDO::PARAM_STR);
        $stmt->execute();
        $columnType = (string)$stmt->fetchColumn();
        return strpos($columnType, "'" . $value . "'") !== false;
    }

    private function quitarUnicoPagoPorPedido()
    {
        $stmt = $this->_db->query("SELECT CONSTRAINT_NAME
                                   FROM information_schema.KEY_COLUMN_USAGE
                                   WHERE TABLE_SCHEMA = DATABASE()
                                     AND TABLE_NAME = 'pagos'
                                     AND COLUMN_NAME = 'id_pedido'
                                     AND REFERENCED_TABLE_NAME = 'pedidos'");
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($foreignKeys as $foreignKey) {
            try {
                $this->_db->exec("ALTER TABLE pagos DROP FOREIGN KEY `{$foreignKey}`");
            } catch (PDOException $e) {
            }
        }

        $stmt = $this->_db->query("SHOW INDEX FROM pagos WHERE Column_name = 'id_pedido' AND Non_unique = 0");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $index) {
            $keyName = $index['Key_name'] ?? '';
            if ($keyName === '' || strtoupper($keyName) === 'PRIMARY') {
                continue;
            }
            try {
                $this->_db->exec("ALTER TABLE pagos DROP INDEX `{$keyName}`");
            } catch (PDOException $e) {
                // Si MySQL lo necesita para una FK, dejamos el esquema usable y seguimos.
            }
        }
        try {
            $this->_db->exec("CREATE INDEX idx_pagos_pedido_vendedor ON pagos(id_pedido, id_vendedor)");
        } catch (PDOException $e) {
        }

        try {
            $this->_db->exec("ALTER TABLE pagos
                              ADD CONSTRAINT pagos_fk_pedido
                              FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido)");
        } catch (PDOException $e) {
        }

        try {
            $this->_db->exec("ALTER TABLE pagos
                              MODIFY metodo_pago VARCHAR(80) DEFAULT 'QR_CUPAZ_REGISTRADO'");
        } catch (PDOException $e) {
        }
    }

    private function crearEnviosFaltantes()
    {
        $this->_db->exec("CREATE TABLE IF NOT EXISTS envios (
            id_envio INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido INT NOT NULL,
            id_vendedor INT NOT NULL,
            estado ENUM('PENDIENTE','ENVIADO','ENTREGADO') DEFAULT 'PENDIENTE',
            fecha_envio DATETIME NULL,
            fecha_entrega DATETIME NULL,
            INDEX idx_envios_pedido (id_pedido),
            INDEX idx_envios_vendedor (id_vendedor)
        )");

        $this->_db->exec("INSERT INTO envios (id_pedido, id_vendedor, estado, fecha_envio, fecha_entrega)
                          SELECT DISTINCT pa.id_pedido,
                                 pa.id_vendedor,
                                 CASE
                                     WHEN p.estado IN ('ENTREGADO','COMPLETADO') THEN 'ENTREGADO'
                                     WHEN p.estado = 'ENVIADO' THEN 'ENVIADO'
                                     ELSE 'PENDIENTE'
                                 END,
                                 p.fecha_envio,
                                 p.fecha_entrega
                          FROM pagos pa
                          INNER JOIN pedidos p ON p.id_pedido = pa.id_pedido
                          WHERE pa.id_vendedor IS NOT NULL
                            AND pa.id_vendedor > 0
                            AND p.estado IN ('PAGO_RETENIDO','ENVIADO','ENTREGADO','COMPLETADO','EN_DISPUTA')
                            AND NOT EXISTS (
                                SELECT 1 FROM envios e
                                WHERE e.id_pedido = pa.id_pedido
                                  AND e.id_vendedor = pa.id_vendedor
                            )");
    }

    private function hasColumn($table, $column)
    {
        $key = $table . ':' . $column;
        if (!array_key_exists($key, $this->schemaCache)) {
            $stmt = $this->_db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                                         WHERE TABLE_SCHEMA = DATABASE()
                                           AND TABLE_NAME = :table_name
                                           AND COLUMN_NAME = :column_name");
            $stmt->bindValue(':table_name', $table, PDO::PARAM_STR);
            $stmt->bindValue(':column_name', $column, PDO::PARAM_STR);
            $stmt->execute();
            $this->schemaCache[$key] = ((int)$stmt->fetchColumn() > 0);
        }
        return $this->schemaCache[$key];
    }

    public function listarTodos()
    {
        $usaPagoConVendedor = $this->hasColumn('pagos', 'id_vendedor');
        $sql = "SELECT pa.id_pago,
                       pa.id_pedido,
                       " . ($usaPagoConVendedor ? "pa.id_vendedor" : "p.id_vendedor") . " AS id_vendedor,
                       pa.monto,
                       pa.estado,
                       pa.metodo_pago,
                       pa.referencia_pago,
                       pa.fecha_pago,
                       pa.fecha_liberacion,
                       cp.url_archivo AS comprobante_pago,
                       cp.referencia_cliente,
                       cp.estado AS estado_comprobante,
                       cli.nombre AS cliente,
                       ven.nombre AS vendedor,
                       p.estado AS estado_pedido
                FROM pagos pa
                INNER JOIN pedidos p ON p.id_pedido = pa.id_pedido
                INNER JOIN usuarios cli ON cli.id_usuario = p.id_cliente
                INNER JOIN usuarios ven ON ven.id_usuario = " . ($usaPagoConVendedor ? "pa.id_vendedor" : "p.id_vendedor") . "
                LEFT JOIN (
                    SELECT c1.*
                    FROM comprobantes_pago c1
                    INNER JOIN (
                        SELECT id_pedido, MAX(id_comprobante) AS id_comprobante
                        FROM comprobantes_pago
                        GROUP BY id_pedido
                    ) ult ON ult.id_comprobante = c1.id_comprobante
                ) cp ON cp.id_pedido = pa.id_pedido
                ORDER BY pa.fecha_pago DESC, pa.id_pago DESC";
        return parent::gselect($sql, []);
    }

    public function listarParaLiquidacion()
    {
        $usaPagoConVendedor = $this->hasColumn('pagos', 'id_vendedor');
        $sql = "SELECT pa.id_pago,
                       pa.id_pedido,
                       " . ($usaPagoConVendedor ? "pa.id_vendedor" : "p.id_vendedor") . " AS id_vendedor,
                       pa.monto,
                       pa.estado,
                       pa.metodo_pago,
                       pa.referencia_pago,
                       pa.fecha_pago,
                       cli.nombre AS cliente,
                       ven.nombre AS vendedor,
                       vc.titular,
                       vc.banco,
                       vc.numero_cuenta,
                       vc.qr_cobro,
                       lv.id_liquidacion,
                       lv.referencia_liquidacion,
                       lv.fecha_registro AS fecha_liquidacion
                FROM pagos pa
                INNER JOIN pedidos p ON p.id_pedido = pa.id_pedido
                INNER JOIN usuarios cli ON cli.id_usuario = p.id_cliente
                INNER JOIN usuarios ven ON ven.id_usuario = " . ($usaPagoConVendedor ? "pa.id_vendedor" : "p.id_vendedor") . "
                LEFT JOIN vendedor_cobros vc ON vc.id_vendedor = " . ($usaPagoConVendedor ? "pa.id_vendedor" : "p.id_vendedor") . "
                LEFT JOIN liquidaciones_vendedor lv ON lv.id_pago = pa.id_pago
                WHERE pa.estado = 'LIBERADO'
                ORDER BY pa.fecha_liberacion DESC, pa.id_pago DESC";
        return parent::gselect($sql, []);
    }

    public function listarPorPedido($idPedido)
    {
        $usaPagoConVendedor = $this->hasColumn('pagos', 'id_vendedor');
        $sql = "SELECT pa.id_pago,
                       pa.id_pedido,
                       " . ($usaPagoConVendedor ? "pa.id_vendedor" : "p.id_vendedor") . " AS id_vendedor,
                       pa.monto,
                       pa.estado,
                       pa.metodo_pago,
                       pa.referencia_pago,
                       pa.fecha_pago,
                       pa.fecha_liberacion,
                       ven.nombre AS vendedor
                FROM pagos pa
                INNER JOIN pedidos p ON p.id_pedido = pa.id_pedido
                INNER JOIN usuarios ven ON ven.id_usuario = " . ($usaPagoConVendedor ? "pa.id_vendedor" : "p.id_vendedor") . "
                WHERE pa.id_pedido = :id_pedido
                ORDER BY pa.id_pago ASC";
        $param = [];
        array_push($param, [':id_pedido', $idPedido, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function listarCobrosPorVendedor($idVendedor)
    {
        $sql = "SELECT pa.id_pago,
                       pa.id_pedido,
                       pa.id_vendedor,
                       pa.monto,
                       pa.estado,
                       pa.metodo_pago,
                       pa.referencia_pago,
                       pa.fecha_pago,
                       pa.fecha_liberacion,
                       p.estado AS estado_pedido,
                       p.fecha_pedido,
                       cli.nombre AS cliente,
                       GROUP_CONCAT(DISTINCT pr.nombre ORDER BY pr.nombre SEPARATOR ', ') AS productos,
                       lv.id_liquidacion,
                       lv.estado AS estado_liquidacion,
                       lv.referencia_liquidacion,
                       lv.observaciones AS observaciones_liquidacion,
                       lv.fecha_registro AS fecha_liquidacion,
                       adm.nombre AS admin_liquidacion
                FROM pagos pa
                INNER JOIN pedidos p ON p.id_pedido = pa.id_pedido
                INNER JOIN usuarios cli ON cli.id_usuario = p.id_cliente
                LEFT JOIN detalle_pedidos dp ON dp.id_pedido = pa.id_pedido
                                           AND dp.id_vendedor = pa.id_vendedor
                LEFT JOIN productos pr ON pr.id_producto = dp.id_producto
                LEFT JOIN liquidaciones_vendedor lv ON lv.id_pago = pa.id_pago
                LEFT JOIN usuarios adm ON adm.id_usuario = lv.id_admin
                WHERE pa.id_vendedor = :id_vendedor
                GROUP BY pa.id_pago, pa.id_pedido, pa.id_vendedor, pa.monto, pa.estado,
                         pa.metodo_pago, pa.referencia_pago, pa.fecha_pago, pa.fecha_liberacion,
                         p.estado, p.fecha_pedido, cli.nombre, lv.id_liquidacion, lv.estado,
                         lv.referencia_liquidacion, lv.observaciones, lv.fecha_registro, adm.nombre
                ORDER BY pa.fecha_pago DESC, pa.id_pago DESC";
        $param = [];
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function liberarPago($idPago, $idAdmin, $observacion = '')
    {
        return $this->cambiarEstadoEscrow($idPago, $idAdmin, 'LIBERADO', $observacion);
    }

    public function verificarPago($idPago, $idAdmin, $observacion = '')
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $stmt = $this->_db->prepare("SELECT id_pago, id_pedido, id_vendedor, estado
                                         FROM pagos
                                         WHERE id_pago = :id_pago
                                         LIMIT 1");
            $stmt->bindValue(':id_pago', $idPago, PDO::PARAM_INT);
            $stmt->execute();
            $pago = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pago) {
                throw new PDOException('El pago no existe.');
            }
            if (($pago['estado'] ?? '') !== 'POR_VERIFICAR') {
                throw new PDOException('Solo se pueden verificar pagos pendientes de revisión.');
            }

            $stmt = $this->_db->prepare("UPDATE pagos
                                         SET estado = 'RETENIDO',
                                             fecha_pago = NOW()
                                         WHERE id_pago = :id_pago");
            $stmt->bindValue(':id_pago', $idPago, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE comprobantes_pago
                                         SET estado = 'VERIFICADO',
                                             fecha_verificacion = NOW()
                                         WHERE id_pedido = :id_pedido
                                           AND estado = 'PENDIENTE'");
            $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("INSERT INTO envios
                (id_pedido, id_vendedor, estado)
                SELECT :id_pedido, :id_vendedor, 'PENDIENTE'
                WHERE NOT EXISTS (
                    SELECT 1 FROM envios
                    WHERE id_pedido = :id_pedido_check
                      AND id_vendedor = :id_vendedor_check
                )");
            $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
            $stmt->bindValue(':id_vendedor', (int)$pago['id_vendedor'], PDO::PARAM_INT);
            $stmt->bindValue(':id_pedido_check', (int)$pago['id_pedido'], PDO::PARAM_INT);
            $stmt->bindValue(':id_vendedor_check', (int)$pago['id_vendedor'], PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("SELECT COUNT(*) FROM pagos
                                         WHERE id_pedido = :id_pedido
                                           AND estado = 'POR_VERIFICAR'");
            $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = 'PAGO_RETENIDO'
                                             WHERE id_pedido = :id_pedido
                                               AND estado = 'PAGO_EN_VERIFICACION'");
                $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();
            }

            $mensaje = 'El administrador verifico el pago #' . (int)$idPago . '. El monto queda retenido hasta confirmar la entrega.';
            if ($observacion !== '') {
                $mensaje .= ' Observacion: ' . $observacion;
            }
            $stmt = $this->_db->prepare("INSERT INTO notificaciones
                (id_usuario, id_pedido, tipo, mensaje)
                VALUES (:id_usuario, :id_pedido, 'PAGO_RECIBIDO', :mensaje)");
            $stmt->bindValue(':id_usuario', (int)$pago['id_vendedor'], PDO::PARAM_INT);
            $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
            $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);
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

    public function reembolsarPago($idPago, $idAdmin, $observacion = '')
    {
        return $this->cambiarEstadoEscrow($idPago, $idAdmin, 'REEMBOLSADO', $observacion);
    }

    public function cancelarPago($idPago, $idAdmin, $observacion = '')
    {
        return $this->cambiarEstadoEscrow($idPago, $idAdmin, 'CANCELADO', $observacion);
    }

    private function cambiarEstadoEscrow($idPago, $idAdmin, $nuevoEstado, $observacion)
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $stmt = $this->_db->prepare("SELECT id_pago, id_pedido, id_vendedor, estado
                                         FROM pagos
                                         WHERE id_pago = :id_pago
                                         LIMIT 1");
            $stmt->bindValue(':id_pago', $idPago, PDO::PARAM_INT);
            $stmt->execute();
            $pago = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pago) {
                throw new PDOException('El pago no existe.');
            }

            if ($nuevoEstado === 'LIBERADO' && !in_array($pago['estado'], ['RETENIDO', 'EN_DISPUTA'], true)) {
                throw new PDOException('Solo se pueden liberar pagos retenidos o en disputa.');
            }
            if ($nuevoEstado === 'LIBERADO') {
                $stmt = $this->_db->prepare("SELECT COUNT(*)
                                             FROM evidencias
                                             WHERE id_pedido = :id_pedido
                                               AND tipo = 'RECEPCION'");
                $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();
                if ((int)$stmt->fetchColumn() === 0) {
                    throw new PDOException('Para liberar el pago primero debe confirmarse la entrega con evidencia.');
                }
            }
            if (in_array($nuevoEstado, ['REEMBOLSADO', 'CANCELADO'], true) && $pago['estado'] === 'LIBERADO') {
                throw new PDOException('No se puede reembolsar o cancelar un pago ya liberado.');
            }

            $stmt = $this->_db->prepare("UPDATE pagos
                                         SET estado = :estado,
                                             fecha_liberacion = CASE WHEN :estado_fecha = 'LIBERADO' THEN NOW() ELSE fecha_liberacion END
                                         WHERE id_pago = :id_pago");
            $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
            $stmt->bindValue(':estado_fecha', $nuevoEstado, PDO::PARAM_STR);
            $stmt->bindValue(':id_pago', $idPago, PDO::PARAM_INT);
            $stmt->execute();

            if ($nuevoEstado === 'CANCELADO') {
                $stmt = $this->_db->prepare("UPDATE comprobantes_pago
                                             SET estado = 'RECHAZADO',
                                                 fecha_verificacion = NOW()
                                             WHERE id_pedido = :id_pedido
                                               AND estado = 'PENDIENTE'");
                $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();
            }

            $estadoPedido = $nuevoEstado === 'LIBERADO' ? 'COMPLETADO' : $nuevoEstado;
            $stmt = $this->_db->prepare("SELECT COUNT(*) FROM pagos
                                         WHERE id_pedido = :id_pedido
                                           AND estado IN ('RETENIDO', 'EN_DISPUTA')");
            $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
            $stmt->execute();
            $pendientes = (int)$stmt->fetchColumn();

            if ($pendientes === 0) {
                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = :estado,
                                                 fecha_completado = CASE WHEN :estado_completado = 'COMPLETADO' THEN NOW() ELSE fecha_completado END
                                             WHERE id_pedido = :id_pedido");
                $stmt->bindValue(':estado', $estadoPedido, PDO::PARAM_STR);
                $stmt->bindValue(':estado_completado', $estadoPedido, PDO::PARAM_STR);
                $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();
            }

            $tipo = $nuevoEstado === 'LIBERADO' ? 'PAGO_LIBERADO' : 'REEMBOLSO_PROCESADO';
            $mensaje = 'El administrador actualizo el pago #' . (int)$idPago . ' a ' . $nuevoEstado . '.';
            if ($observacion !== '') {
                $mensaje .= ' Observacion: ' . $observacion;
            }
            foreach ([(int)$pago['id_vendedor'], $idAdmin] as $idUsuario) {
                if ($idUsuario <= 0) {
                    continue;
                }
                $stmt = $this->_db->prepare("INSERT INTO notificaciones
                    (id_usuario, id_pedido, tipo, mensaje)
                    VALUES (:id_usuario, :id_pedido, :tipo, :mensaje)");
                $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
                $stmt->bindValue(':id_pedido', (int)$pago['id_pedido'], PDO::PARAM_INT);
                $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
                $stmt->bindValue(':mensaje', $mensaje, PDO::PARAM_STR);
                $stmt->execute();
            }

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
