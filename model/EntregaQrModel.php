<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class EntregaQrModel extends ModeloBasePDO
{
    public function __construct()
    {
        parent::__construct();
        $this->asegurarTabla();
    }

    private function asegurarTabla()
    {
        foreach ([
            'fecha_envio' => 'DATETIME NULL',
            'fecha_entrega' => 'DATETIME NULL',
            'fecha_completado' => 'DATETIME NULL',
        ] as $column => $definition) {
            $stmt = $this->_db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                                         WHERE TABLE_SCHEMA = DATABASE()
                                           AND TABLE_NAME = 'pedidos'
                                           AND COLUMN_NAME = :column_name");
            $stmt->bindValue(':column_name', $column, PDO::PARAM_STR);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                $this->_db->exec("ALTER TABLE pedidos ADD {$column} {$definition}");
            }
        }

        $sql = "CREATE TABLE IF NOT EXISTS entregas_qr (
                    id_entrega_qr INT AUTO_INCREMENT PRIMARY KEY,
                    id_pedido INT NOT NULL,
                    id_vendedor INT NOT NULL,
                    token VARCHAR(120) NOT NULL UNIQUE,
                    estado ENUM('GENERADO','ESCANEADO','CONFIRMADO','ANULADO') DEFAULT 'GENERADO',
                    fecha_generacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                    fecha_escaneo DATETIME NULL,
                    fecha_confirmacion DATETIME NULL,
                    INDEX idx_entregas_qr_pedido (id_pedido),
                    INDEX idx_entregas_qr_vendedor (id_vendedor)
        )";
        $this->_db->exec($sql);

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

    public function listarPorVendedor($idVendedor)
    {
        $sql = "SELECT id_entrega_qr, id_pedido, id_vendedor, token, estado,
                       fecha_generacion, fecha_escaneo, fecha_confirmacion
                FROM entregas_qr
                WHERE id_vendedor = :id_vendedor
                ORDER BY id_entrega_qr DESC";
        $param = [];
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function obtenerPorPedidoVendedor($idPedido, $idVendedor)
    {
        $sql = "SELECT id_entrega_qr, id_pedido, id_vendedor, token, estado,
                       fecha_generacion, fecha_escaneo, fecha_confirmacion
                FROM entregas_qr
                WHERE id_pedido = :id_pedido
                  AND id_vendedor = :id_vendedor
                ORDER BY id_entrega_qr DESC
                LIMIT 1";
        $param = [];
        array_push($param, [':id_pedido', $idPedido, PDO::PARAM_INT]);
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function obtenerPorToken($token)
    {
        $sql = "SELECT q.id_entrega_qr, q.id_pedido, q.id_vendedor, q.token, q.estado,
                       q.fecha_generacion, q.fecha_escaneo, q.fecha_confirmacion,
                       p.id_cliente, p.estado AS estado_pedido, p.monto_total, p.direccion_entrega,
                       cli.nombre AS cliente, ven.nombre AS vendedor
                FROM entregas_qr q
                INNER JOIN pedidos p ON p.id_pedido = q.id_pedido
                INNER JOIN usuarios cli ON cli.id_usuario = p.id_cliente
                INNER JOIN usuarios ven ON ven.id_usuario = q.id_vendedor
                WHERE q.token = :token
                LIMIT 1";
        $param = [];
        array_push($param, [':token', $token, PDO::PARAM_STR]);
        return parent::gselect($sql, $param);
    }

    public function generarParaPedido($idPedido, $idVendedor)
    {
        $array = [];
        try {
            $existente = $this->obtenerPorPedidoVendedor($idPedido, $idVendedor);
            if (!empty($existente['DATA'][0])) {
                return [
                    'ESTADO' => true,
                    'DATA' => [$existente['DATA'][0]],
                ];
            }

            $token = bin2hex(random_bytes(24));
            $sql = "INSERT INTO entregas_qr (id_pedido, id_vendedor, token, estado)
                    VALUES (:id_pedido, :id_vendedor, :token, 'GENERADO')";
            $param = [];
            array_push($param, [':id_pedido', $idPedido, PDO::PARAM_INT]);
            array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
            array_push($param, [':token', $token, PDO::PARAM_STR]);
            $insert = parent::ginsert($sql, $param);
            if (empty($insert['ESTADO'])) {
                return $insert;
            }
            return $this->obtenerPorPedidoVendedor($idPedido, $idVendedor);
        } catch (PDOException $e) {
            $array['ESTADO'] = false;
            $array['ERROR'] = $e->getMessage();
            return $array;
        }
    }

    public function marcarEscaneo($token)
    {
        $sql = "UPDATE entregas_qr
                SET estado = CASE WHEN estado = 'GENERADO' THEN 'ESCANEADO' ELSE estado END,
                    fecha_escaneo = CASE WHEN fecha_escaneo IS NULL THEN NOW() ELSE fecha_escaneo END
                WHERE token = :token";
        $param = [];
        array_push($param, [':token', $token, PDO::PARAM_STR]);
        return parent::gupdate($sql, $param);
    }

    public function confirmarEntregaPorToken($token, $idUsuario, $evidenciaRecepcion = '')
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $stmt = $this->_db->prepare("SELECT q.id_entrega_qr, q.id_pedido, q.id_vendedor, q.estado,
                                                p.id_cliente, p.estado AS estado_pedido
                                         FROM entregas_qr q
                                         INNER JOIN pedidos p ON p.id_pedido = q.id_pedido
                                         WHERE q.token = :token
                                         LIMIT 1");
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$entrega) {
                throw new PDOException('El codigo QR no existe o ya no es valido.');
            }
            if ((int)$entrega['id_cliente'] !== (int)$idUsuario) {
                throw new PDOException('Solo el cliente titular puede confirmar esta entrega.');
            }
            if (($entrega['estado'] ?? '') === 'CONFIRMADO') {
                throw new PDOException('Esta entrega ya fue confirmada anteriormente.');
            }
            if (trim($evidenciaRecepcion) === '') {
                throw new PDOException('Debes cargar una evidencia de recepción para liberar el pago.');
            }

            $stmt = $this->_db->prepare("UPDATE pagos
                                         SET estado = 'LIBERADO',
                                             fecha_liberacion = NOW()
                                         WHERE id_pedido = :id_pedido
                                           AND id_vendedor = :id_vendedor
                                           AND estado IN ('RETENIDO', 'EN_DISPUTA')");
            $stmt->bindValue(':id_pedido', (int)$entrega['id_pedido'], PDO::PARAM_INT);
            $stmt->bindValue(':id_vendedor', (int)$entrega['id_vendedor'], PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE envios
                                         SET estado = 'ENTREGADO',
                                             fecha_entrega = CASE WHEN fecha_entrega IS NULL THEN NOW() ELSE fecha_entrega END
                                         WHERE id_pedido = :id_pedido
                                           AND id_vendedor = :id_vendedor");
            $stmt->bindValue(':id_pedido', (int)$entrega['id_pedido'], PDO::PARAM_INT);
            $stmt->bindValue(':id_vendedor', (int)$entrega['id_vendedor'], PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE entregas_qr
                                         SET estado = 'CONFIRMADO',
                                             fecha_escaneo = CASE WHEN fecha_escaneo IS NULL THEN NOW() ELSE fecha_escaneo END,
                                             fecha_confirmacion = NOW()
                                         WHERE id_entrega_qr = :id_entrega_qr");
            $stmt->bindValue(':id_entrega_qr', (int)$entrega['id_entrega_qr'], PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("SELECT COUNT(*) FROM pagos
                                         WHERE id_pedido = :id_pedido
                                           AND estado IN ('RETENIDO', 'EN_DISPUTA')");
            $stmt->bindValue(':id_pedido', (int)$entrega['id_pedido'], PDO::PARAM_INT);
            $stmt->execute();
            $pagosPendientes = (int)$stmt->fetchColumn();

            if ($pagosPendientes === 0) {
                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = 'COMPLETADO',
                                                 fecha_entrega = CASE WHEN fecha_entrega IS NULL THEN NOW() ELSE fecha_entrega END,
                                                 fecha_completado = NOW()
                                             WHERE id_pedido = :id_pedido");
                $stmt->bindValue(':id_pedido', (int)$entrega['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = 'ENTREGADO',
                                                 fecha_entrega = CASE WHEN fecha_entrega IS NULL THEN NOW() ELSE fecha_entrega END
                                             WHERE id_pedido = :id_pedido
                                               AND estado IN ('PAGO_RETENIDO', 'ENVIADO')");
                $stmt->bindValue(':id_pedido', (int)$entrega['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();
            }

            $stmt = $this->_db->prepare("INSERT INTO evidencias
                (id_pedido, id_usuario, tipo, url_archivo, descripcion, fecha_subida)
                VALUES
                (:id_pedido, :id_usuario, 'RECEPCION', :url_archivo, :descripcion, NOW())");
            $stmt->bindValue(':id_pedido', (int)$entrega['id_pedido'], PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', (int)$idUsuario, PDO::PARAM_INT);
            $stmt->bindValue(':url_archivo', $evidenciaRecepcion, PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', 'Recepcion confirmada mediante QR de entrega y evidencia cargada por el cliente.', PDO::PARAM_STR);
            $stmt->execute();

            $stmt = $this->_db->prepare("INSERT INTO notificaciones
                (id_usuario, id_pedido, tipo, mensaje)
                VALUES (:id_usuario, :id_pedido, 'PAGO_LIBERADO', :mensaje)");
            $stmt->bindValue(':id_usuario', (int)$entrega['id_vendedor'], PDO::PARAM_INT);
            $stmt->bindValue(':id_pedido', (int)$entrega['id_pedido'], PDO::PARAM_INT);
            $stmt->bindValue(':mensaje', 'El cliente confirmo la entrega escaneando el QR. El pago del pedido #' . (int)$entrega['id_pedido'] . ' fue liberado.', PDO::PARAM_STR);
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
