<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class DisputaModel extends ModeloBasePDO
{
    private $schemaCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->asegurarEsquemaOperativo();
    }

    private function asegurarEsquemaOperativo()
    {
        if (!$this->enumTieneValor('disputas', 'estado', 'ANULADA')) {
            $this->_db->exec("ALTER TABLE disputas MODIFY estado ENUM('ABIERTA','EN_REVISION','RESUELTA_CLIENTE','RESUELTA_VENDEDOR','CERRADA','ANULADA') DEFAULT 'ABIERTA'");
        }
        if (!$this->hasColumn('disputas', 'id_vendedor')) {
            $this->_db->exec("ALTER TABLE disputas ADD id_vendedor INT NULL AFTER id_pedido");
            $this->schemaCache['disputas:id_vendedor'] = true;
            $this->_db->exec("UPDATE disputas d
                              INNER JOIN (
                                  SELECT id_pedido, MIN(id_vendedor) AS id_vendedor
                                  FROM detalle_pedidos
                                  WHERE id_vendedor IS NOT NULL AND id_vendedor > 0
                                  GROUP BY id_pedido
                              ) dp ON dp.id_pedido = d.id_pedido
                              SET d.id_vendedor = dp.id_vendedor
                              WHERE d.id_vendedor IS NULL OR d.id_vendedor = 0");
        }
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
        return strpos((string)$stmt->fetchColumn(), "'" . $value . "'") !== false;
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

    public function listarTodas()
    {
        $sql = "SELECT d.id_disputa, d.id_pedido, d.id_cliente, d.id_admin,
                       d.id_vendedor,
                       d.motivo, d.estado, d.resolucion, d.fecha_apertura, d.fecha_resolucion,
                       cli.nombre AS cliente, ven.nombre AS vendedor, adm.nombre AS admin,
                       p.monto_total, p.estado AS estado_pedido
                FROM disputas d
                INNER JOIN pedidos p ON p.id_pedido = d.id_pedido
                INNER JOIN usuarios cli ON cli.id_usuario = d.id_cliente
                INNER JOIN usuarios ven ON ven.id_usuario = d.id_vendedor
                LEFT JOIN usuarios adm ON adm.id_usuario = d.id_admin
                ORDER BY d.fecha_apertura DESC, d.id_disputa DESC";
        return parent::gselect($sql, []);
    }

    public function listarPorCliente($idCliente)
    {
        $sql = "SELECT d.id_disputa, d.id_pedido, d.id_cliente, d.id_admin,
                       d.id_vendedor,
                       d.motivo, d.estado, d.resolucion, d.fecha_apertura, d.fecha_resolucion,
                       cli.nombre AS cliente, ven.nombre AS vendedor, adm.nombre AS admin,
                       p.estado AS estado_pedido
                FROM disputas d
                INNER JOIN pedidos p ON p.id_pedido = d.id_pedido
                INNER JOIN usuarios cli ON cli.id_usuario = d.id_cliente
                INNER JOIN usuarios ven ON ven.id_usuario = d.id_vendedor
                LEFT JOIN usuarios adm ON adm.id_usuario = d.id_admin
                WHERE d.id_cliente = :id_cliente
                ORDER BY d.fecha_apertura DESC, d.id_disputa DESC";
        $param = [];
        array_push($param, [':id_cliente', $idCliente, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function listarPorVendedor($idVendedor)
    {
        $sql = "SELECT d.id_disputa, d.id_pedido, d.id_cliente, d.id_admin,
                       d.id_vendedor,
                       d.motivo, d.estado, d.resolucion, d.fecha_apertura, d.fecha_resolucion,
                       cli.nombre AS cliente, ven.nombre AS vendedor, adm.nombre AS admin,
                       p.estado AS estado_pedido
                FROM disputas d
                INNER JOIN pedidos p ON p.id_pedido = d.id_pedido
                INNER JOIN usuarios cli ON cli.id_usuario = d.id_cliente
                INNER JOIN usuarios ven ON ven.id_usuario = d.id_vendedor
                LEFT JOIN usuarios adm ON adm.id_usuario = d.id_admin
                WHERE d.id_vendedor = :id_vendedor
                ORDER BY d.fecha_apertura DESC, d.id_disputa DESC";
        $param = [];
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function crearDisputa($idPedido, $idVendedor, $idCliente, $motivo)
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $usaPagoConVendedor = $this->hasColumn('pagos', 'id_vendedor');

            $stmt = $this->_db->prepare("SELECT id_pedido
                                         FROM pedidos
                                         WHERE id_pedido = :id_pedido
                                           AND id_cliente = :id_cliente
                                         LIMIT 1");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
            $stmt->execute();
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                throw new PDOException('El pedido no existe o no pertenece al cliente.');
            }

            $idVendedorReal = $idVendedor;

            if ($idVendedorReal <= 0) {
                throw new PDOException('Selecciona un vendedor valido para la disputa.');
            }

            $stmt = $this->_db->prepare("SELECT COUNT(*)
                                         FROM detalle_pedidos
                                         WHERE id_pedido = :id_pedido
                                           AND id_vendedor = :id_vendedor");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->bindValue(':id_vendedor', $idVendedorReal, PDO::PARAM_INT);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                throw new PDOException('El vendedor seleccionado no pertenece a este pedido.');
            }

            $stmt = $this->_db->prepare("SELECT id_disputa
                                         FROM disputas
                                         WHERE id_pedido = :id_pedido
                                           AND id_vendedor = :id_vendedor
                                           AND estado IN ('ABIERTA', 'EN_REVISION')
                                         LIMIT 1");
            $stmt->bindValue(':id_vendedor', $idVendedorReal, PDO::PARAM_INT);
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                throw new PDOException('Ya existe una disputa activa para este pedido.');
            }

            $stmt = $this->_db->prepare("INSERT INTO disputas
                (id_pedido, id_vendedor, id_cliente, motivo, estado, fecha_apertura)
                VALUES
                (:id_pedido, :id_vendedor, :id_cliente, :motivo, 'ABIERTA', NOW())");
            $stmt->bindValue(':id_vendedor', $idVendedorReal, PDO::PARAM_INT);
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
            $stmt->bindValue(':motivo', $motivo, PDO::PARAM_STR);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE pedidos
                                         SET estado = 'EN_DISPUTA'
                                         WHERE id_pedido = :id_pedido");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();

            if ($usaPagoConVendedor) {
                $stmt = $this->_db->prepare("UPDATE pagos
                                             SET estado = 'EN_DISPUTA'
                                             WHERE id_pedido = :id_pedido
                                               AND id_vendedor = :id_vendedor
                                               AND estado = 'RETENIDO'");
                $stmt->bindValue(':id_vendedor', $idVendedorReal, PDO::PARAM_INT);
            } else {
                $stmt = $this->_db->prepare("UPDATE pagos
                                             SET estado = 'EN_DISPUTA'
                                             WHERE id_pedido = :id_pedido
                                               AND estado = 'RETENIDO'");
            }
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();

            foreach ([$idVendedorReal, $idCliente] as $idUsuario) {
                $stmt = $this->_db->prepare("INSERT INTO notificaciones
                    (id_usuario, id_pedido, tipo, mensaje)
                    VALUES (:id_usuario, :id_pedido, 'DISPUTA_ABIERTA', :mensaje)");
                $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
                $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                $stmt->bindValue(':mensaje', 'Se abrió una disputa para el pedido #' . $idPedido . '.', PDO::PARAM_STR);
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

    public function resolverDisputa($idDisputa, $idAdmin, $estadoResolucion, $resolucion)
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $usaPagoConVendedor = $this->hasColumn('pagos', 'id_vendedor');

            $sql = "SELECT d.id_disputa, d.id_pedido, d.id_cliente, d.estado,
                           d.id_vendedor
                    FROM disputas d
                    INNER JOIN pedidos p ON p.id_pedido = d.id_pedido
                    WHERE d.id_disputa = :id_disputa
                    LIMIT 1";
            $stmt = $this->_db->prepare($sql);
            $stmt->bindValue(':id_disputa', $idDisputa, PDO::PARAM_INT);
            $stmt->execute();
            $disputa = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$disputa) {
                throw new PDOException('La disputa no existe.');
            }

            $stmt = $this->_db->prepare("UPDATE disputas
                                         SET id_admin = :id_admin,
                                             estado = :estado,
                                             resolucion = :resolucion,
                                             fecha_resolucion = NOW()
                                         WHERE id_disputa = :id_disputa");
            $stmt->bindValue(':id_admin', $idAdmin, PDO::PARAM_INT);
            $stmt->bindValue(':estado', $estadoResolucion, PDO::PARAM_STR);
            $stmt->bindValue(':resolucion', $resolucion, PDO::PARAM_STR);
            $stmt->bindValue(':id_disputa', $idDisputa, PDO::PARAM_INT);
            $stmt->execute();

            if ($estadoResolucion === 'RESUELTA_CLIENTE') {
                if ($usaPagoConVendedor) {
                    $stmt = $this->_db->prepare("UPDATE pagos
                                                 SET estado = 'REEMBOLSADO'
                                                 WHERE id_pedido = :id_pedido
                                                   AND id_vendedor = :id_vendedor");
                    $stmt->bindValue(':id_vendedor', (int)$disputa['id_vendedor'], PDO::PARAM_INT);
                } else {
                    $stmt = $this->_db->prepare("UPDATE pagos
                                                 SET estado = 'REEMBOLSADO'
                                                 WHERE id_pedido = :id_pedido");
                }
                $stmt->bindValue(':id_pedido', (int)$disputa['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = 'REEMBOLSADO'
                                             WHERE id_pedido = :id_pedido");
                $stmt->bindValue(':id_pedido', (int)$disputa['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();
            } elseif ($estadoResolucion === 'RESUELTA_VENDEDOR') {
                if ($usaPagoConVendedor) {
                    $stmt = $this->_db->prepare("UPDATE pagos
                                                 SET estado = 'LIBERADO',
                                                     fecha_liberacion = NOW()
                                                 WHERE id_pedido = :id_pedido
                                                   AND id_vendedor = :id_vendedor");
                    $stmt->bindValue(':id_vendedor', (int)$disputa['id_vendedor'], PDO::PARAM_INT);
                } else {
                    $stmt = $this->_db->prepare("UPDATE pagos
                                                 SET estado = 'LIBERADO',
                                                     fecha_liberacion = NOW()
                                                 WHERE id_pedido = :id_pedido");
                }
                $stmt->bindValue(':id_pedido', (int)$disputa['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = 'COMPLETADO'
                                             WHERE id_pedido = :id_pedido");
                $stmt->bindValue(':id_pedido', (int)$disputa['id_pedido'], PDO::PARAM_INT);
                $stmt->execute();
            }

            foreach ([(int)$disputa['id_cliente'], (int)$disputa['id_vendedor']] as $idUsuario) {
                $stmt = $this->_db->prepare("INSERT INTO notificaciones
                    (id_usuario, id_pedido, tipo, mensaje)
                    VALUES (:id_usuario, :id_pedido, 'DISPUTA_RESUELTA', :mensaje)");
                $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
                $stmt->bindValue(':id_pedido', (int)$disputa['id_pedido'], PDO::PARAM_INT);
                $stmt->bindValue(':mensaje', 'La disputa del pedido #' . (int)$disputa['id_pedido'] . ' fue actualizada a ' . $estadoResolucion . '.', PDO::PARAM_STR);
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

    public function cancelarPorCliente($idDisputa, $idCliente, $motivo = 'Reclamo anulado por el cliente.')
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $stmt = $this->_db->prepare("SELECT id_disputa, id_pedido, id_vendedor, estado
                                         FROM disputas
                                         WHERE id_disputa = :id_disputa
                                           AND id_cliente = :id_cliente
                                         LIMIT 1");
            $stmt->bindValue(':id_disputa', $idDisputa, PDO::PARAM_INT);
            $stmt->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
            $stmt->execute();
            $disputa = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$disputa) {
                throw new PDOException('El reclamo no existe o no pertenece al cliente.');
            }
            if (($disputa['estado'] ?? '') !== 'ABIERTA') {
                throw new PDOException('Solo se pueden anular reclamos abiertos que aún no fueron revisados.');
            }

            $stmt = $this->_db->prepare("UPDATE disputas
                                         SET estado = 'ANULADA',
                                             resolucion = :resolucion,
                                             fecha_resolucion = NOW()
                                         WHERE id_disputa = :id_disputa");
            $stmt->bindValue(':resolucion', $motivo, PDO::PARAM_STR);
            $stmt->bindValue(':id_disputa', $idDisputa, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE pagos
                                         SET estado = 'RETENIDO'
                                         WHERE id_pedido = :id_pedido
                                           AND id_vendedor = :id_vendedor
                                           AND estado = 'EN_DISPUTA'");
            $stmt->bindValue(':id_pedido', (int)$disputa['id_pedido'], PDO::PARAM_INT);
            $stmt->bindValue(':id_vendedor', (int)$disputa['id_vendedor'], PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("SELECT COUNT(*)
                                         FROM disputas
                                         WHERE id_pedido = :id_pedido
                                           AND estado IN ('ABIERTA','EN_REVISION')");
            $stmt->bindValue(':id_pedido', (int)$disputa['id_pedido'], PDO::PARAM_INT);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = 'PAGO_RETENIDO'
                                             WHERE id_pedido = :id_pedido
                                               AND estado = 'EN_DISPUTA'");
                $stmt->bindValue(':id_pedido', (int)$disputa['id_pedido'], PDO::PARAM_INT);
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
