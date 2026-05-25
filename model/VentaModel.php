<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class VentaModel extends ModeloBasePDO
{
    private $schemaCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->asegurarEsquemaOperativo();
    }

    private function hasColumn($table, $column)
    {
        $key = 'column:' . $table . ':' . $column;
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

    private function asegurarEsquemaOperativo()
    {
        if (!$this->hasColumn('pedidos', 'fecha_envio')) {
            $this->_db->exec("ALTER TABLE pedidos ADD fecha_envio DATETIME NULL");
        }
        if (!$this->hasColumn('pedidos', 'fecha_entrega')) {
            $this->_db->exec("ALTER TABLE pedidos ADD fecha_entrega DATETIME NULL");
        }
        if (!$this->hasColumn('pedidos', 'fecha_completado')) {
            $this->_db->exec("ALTER TABLE pedidos ADD fecha_completado DATETIME NULL");
        }
        if (!$this->hasTable('envios')) {
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
        }
        if (!$this->hasColumn('detalle_pedidos', 'id_vendedor')) {
            $this->_db->exec("ALTER TABLE detalle_pedidos ADD id_vendedor INT NULL AFTER id_producto");
            $this->schemaCache['column:detalle_pedidos:id_vendedor'] = true;
        }
        if (!$this->hasColumn('pagos', 'id_vendedor')) {
            $this->_db->exec("ALTER TABLE pagos ADD id_vendedor INT NULL AFTER id_pedido");
            $this->schemaCache['column:pagos:id_vendedor'] = true;
        }
        $this->_db->exec("UPDATE detalle_pedidos dp
                          INNER JOIN productos pr ON pr.id_producto = dp.id_producto
                          SET dp.id_vendedor = pr.id_vendedor
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

    private function hasTable($table)
    {
        $key = 'table:' . $table;
        if (!array_key_exists($key, $this->schemaCache)) {
            $stmt = $this->_db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                                         WHERE TABLE_SCHEMA = DATABASE()
                                           AND TABLE_NAME = :table_name");
            $stmt->bindValue(':table_name', $table, PDO::PARAM_STR);
            $stmt->execute();
            $this->schemaCache[$key] = ((int)$stmt->fetchColumn() > 0);
        }
        return $this->schemaCache[$key];
    }

    public function listarPorVendedor($idVendedor)
    {
        if ($this->hasTable('envios')) {
            $sql = "SELECT COALESCE(e.id_envio, 0) AS id_envio,
                           p.id_pedido,
                           dp.id_vendedor,
                           COALESCE(e.estado, 'PENDIENTE_PAGO') AS estado_envio,
                           e.fecha_envio,
                           e.fecha_entrega,
                           p.estado AS estado_pedido,
                           p.monto_total,
                           p.direccion_entrega,
                           p.fecha_pedido,
                           c.nombre AS cliente,
                           GROUP_CONCAT(DISTINCT pr.nombre ORDER BY pr.nombre SEPARATOR ', ') AS productos,
                           COALESCE(SUM(dp.cantidad * dp.precio_unitario), 0) AS monto_vendedor
                    FROM detalle_pedidos dp
                    INNER JOIN pedidos p ON p.id_pedido = dp.id_pedido
                    INNER JOIN usuarios c ON c.id_usuario = p.id_cliente
                    LEFT JOIN envios e ON e.id_pedido = dp.id_pedido AND e.id_vendedor = dp.id_vendedor
                    LEFT JOIN productos pr ON pr.id_producto = dp.id_producto
                    WHERE dp.id_vendedor = :id_vendedor
                    GROUP BY e.id_envio, p.id_pedido, dp.id_vendedor, e.estado, e.fecha_envio, e.fecha_entrega,
                             p.estado, p.monto_total, p.direccion_entrega, p.fecha_pedido, c.nombre
                    ORDER BY p.fecha_pedido DESC, p.id_pedido DESC";
            $param = [];
            array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
            return parent::gselect($sql, $param);
        }

        $sql = "SELECT p.id_pedido,
                       p.id_pedido AS id_envio,
                       dp.id_vendedor,
                       CASE
                           WHEN p.estado IN ('ENTREGADO', 'COMPLETADO') THEN 'ENTREGADO'
                           WHEN p.estado = 'ENVIADO' THEN 'ENVIADO'
                           ELSE 'PENDIENTE'
                       END AS estado_envio,
                       p.fecha_envio,
                       p.fecha_entrega,
                       p.estado AS estado_pedido,
                       p.monto_total,
                       p.direccion_entrega,
                       p.fecha_pedido,
                       c.nombre AS cliente,
                       GROUP_CONCAT(DISTINCT pr.nombre ORDER BY pr.nombre SEPARATOR ', ') AS productos,
                       COALESCE(SUM(dp.cantidad * dp.precio_unitario), p.monto_total) AS monto_vendedor
                FROM pedidos p
                INNER JOIN usuarios c ON c.id_usuario = p.id_cliente
                LEFT JOIN detalle_pedidos dp ON dp.id_pedido = p.id_pedido
                LEFT JOIN productos pr ON pr.id_producto = dp.id_producto
                WHERE dp.id_vendedor = :id_vendedor
                GROUP BY p.id_pedido, dp.id_vendedor, p.estado, p.fecha_envio, p.fecha_entrega,
                         p.monto_total, p.direccion_entrega, p.fecha_pedido, c.nombre
                ORDER BY p.fecha_pedido DESC, p.id_pedido DESC";
        $param = [];
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function actualizarEstadoEnvio($idEnvio, $idVendedor, $nuevoEstado)
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            if ($this->hasTable('envios')) {
                $stmt = $this->_db->prepare("UPDATE envios
                                             SET estado = :estado,
                                                 fecha_envio = CASE WHEN :estado_envio = 'ENVIADO' THEN NOW() ELSE fecha_envio END,
                                                 fecha_entrega = CASE WHEN :estado_entrega = 'ENTREGADO' THEN NOW() ELSE fecha_entrega END
                                             WHERE id_envio = :id_envio
                                               AND id_vendedor = :id_vendedor");
                $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
                $stmt->bindValue(':estado_envio', $nuevoEstado, PDO::PARAM_STR);
                $stmt->bindValue(':estado_entrega', $nuevoEstado, PDO::PARAM_STR);
                $stmt->bindValue(':id_envio', $idEnvio, PDO::PARAM_INT);
                $stmt->bindValue(':id_vendedor', $idVendedor, PDO::PARAM_INT);
                $stmt->execute();

                $stmt = $this->_db->prepare("SELECT id_pedido FROM envios WHERE id_envio = :id_envio AND id_vendedor = :id_vendedor LIMIT 1");
                $stmt->bindValue(':id_envio', $idEnvio, PDO::PARAM_INT);
                $stmt->bindValue(':id_vendedor', $idVendedor, PDO::PARAM_INT);
                $stmt->execute();
                $idPedido = (int)$stmt->fetchColumn();

                if ($idPedido <= 0) {
                    throw new PDOException('El envio no existe o no pertenece al vendedor.');
                }

                $estadoPedido = $nuevoEstado === 'ENTREGADO' ? 'ENTREGADO' : 'ENVIADO';
                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = :estado,
                                                 fecha_envio = CASE WHEN :estado_envio = 'ENVIADO' AND fecha_envio IS NULL THEN NOW() ELSE fecha_envio END,
                                                 fecha_entrega = CASE WHEN :estado_entrega = 'ENTREGADO' AND fecha_entrega IS NULL THEN NOW() ELSE fecha_entrega END
                                             WHERE id_pedido = :id_pedido
                                               AND estado IN ('PAGO_RETENIDO', 'ENVIADO', 'ENTREGADO')");
                $stmt->bindValue(':estado', $estadoPedido, PDO::PARAM_STR);
                $stmt->bindValue(':estado_envio', $estadoPedido, PDO::PARAM_STR);
                $stmt->bindValue(':estado_entrega', $estadoPedido, PDO::PARAM_STR);
                $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $estadoPedido = $nuevoEstado === 'ENTREGADO' ? 'ENTREGADO' : 'ENVIADO';
                $stmt = $this->_db->prepare("UPDATE pedidos
                                             SET estado = :estado,
                                                 fecha_envio = CASE WHEN :estado_envio = 'ENVIADO' THEN NOW() ELSE fecha_envio END,
                                                 fecha_entrega = CASE WHEN :estado_entrega = 'ENTREGADO' THEN NOW() ELSE fecha_entrega END
                                             WHERE id_pedido = :id_pedido
                                               AND id_vendedor = :id_vendedor");
                $stmt->bindValue(':estado', $estadoPedido, PDO::PARAM_STR);
                $stmt->bindValue(':estado_envio', $estadoPedido, PDO::PARAM_STR);
                $stmt->bindValue(':estado_entrega', $estadoPedido, PDO::PARAM_STR);
                $stmt->bindValue(':id_pedido', $idEnvio, PDO::PARAM_INT);
                $stmt->bindValue(':id_vendedor', $idVendedor, PDO::PARAM_INT);
                $stmt->execute();

                $idPedido = $idEnvio;
            }

            $stmt = $this->_db->prepare("SELECT id_cliente FROM pedidos WHERE id_pedido = :id_pedido LIMIT 1");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pedido) {
                $tipo = $nuevoEstado === 'ENTREGADO' ? 'PEDIDO_ENTREGADO' : 'PEDIDO_ENVIADO';
                $mensaje = $nuevoEstado === 'ENTREGADO'
                    ? 'El vendedor marcó como entregado el pedido #' . $idPedido . '.'
                    : 'El vendedor marcó como enviado el pedido #' . $idPedido . '.';

                $stmt = $this->_db->prepare("INSERT INTO notificaciones
                    (id_usuario, id_pedido, tipo, mensaje)
                    VALUES (:id_usuario, :id_pedido, :tipo, :mensaje)");
                $stmt->bindValue(':id_usuario', (int)$pedido['id_cliente'], PDO::PARAM_INT);
                $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
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
