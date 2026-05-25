<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class PedidoModel extends ModeloBasePDO
{
    private $schemaCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->asegurarEsquemaOperativo();
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
        $this->asegurarEstadosPedido();
        $this->asegurarEstadosPago();
        $this->asegurarComprobantesPago();
        $this->asegurarEvidencias();
        try {
            $this->asegurarEntregasQr();
        } catch (Throwable $e) {
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
            $this->_db->exec("UPDATE detalle_pedidos dp
                              INNER JOIN productos p ON p.id_producto = dp.id_producto
                              SET dp.id_vendedor = p.id_vendedor
                              WHERE dp.id_vendedor IS NULL");
        }
        if (!$this->hasColumn('pagos', 'id_vendedor')) {
            $this->_db->exec("ALTER TABLE pagos ADD id_vendedor INT NULL AFTER id_pedido");
            $this->schemaCache['column:pagos:id_vendedor'] = true;
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
        $this->_db->exec("UPDATE pagos
                          SET metodo_pago = 'QR_CUPAZ_REGISTRADO'
                          WHERE metodo_pago IS NULL
                             OR metodo_pago = ''
                             OR metodo_pago = 'SIMULADO'");
    }

    private function asegurarEstadosPedido()
    {
        if (!$this->enumTieneValor('pedidos', 'estado', 'PAGO_EN_VERIFICACION')) {
            $this->_db->exec("ALTER TABLE pedidos MODIFY estado ENUM('PENDIENTE_PAGO','PAGO_EN_VERIFICACION','PAGO_RETENIDO','ENVIADO','ENTREGADO','COMPLETADO','EN_DISPUTA','REEMBOLSADO','CANCELADO') DEFAULT 'PENDIENTE_PAGO'");
        }
    }

    private function asegurarEstadosPago()
    {
        if (!$this->enumTieneValor('pagos', 'estado', 'POR_VERIFICAR')) {
            $this->_db->exec("ALTER TABLE pagos MODIFY estado ENUM('POR_VERIFICAR','RETENIDO','LIBERADO','EN_DISPUTA','REEMBOLSADO','CANCELADO') DEFAULT 'POR_VERIFICAR'");
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

    private function asegurarEntregasQr()
    {
        $this->_db->exec("CREATE TABLE IF NOT EXISTS entregas_qr (
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
        return strpos((string)$stmt->fetchColumn(), "'" . $value . "'") !== false;
    }

    private function hasTable($table)
    {
        $key = 'table:' . $table;
        if (!array_key_exists($key, $this->schemaCache)) {
            $stmt = $this->_db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name");
            $stmt->bindValue(':table_name', $table, PDO::PARAM_STR);
            $stmt->execute();
            $this->schemaCache[$key] = ((int)$stmt->fetchColumn() > 0);
        }
        return $this->schemaCache[$key];
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

    public function crearDesdeCarrito($idCliente, array $items, $direccionEntrega, $notas = '')
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $pedidosPorVendedor = [];
            $usaPedidoConVendedor = $this->hasColumn('pedidos', 'id_vendedor');
            $usaDetalleConVendedor = $this->hasColumn('detalle_pedidos', 'id_vendedor');

            foreach ($items as $item) {
                $idProducto = (int)($item['id_producto'] ?? 0);
                $cantidad = max(1, (int)($item['cantidad'] ?? 1));

                $stmt = $this->_db->prepare("SELECT id_producto, id_vendedor, precio, stock, nombre, estado
                                             FROM productos
                                             WHERE id_producto = :id_producto
                                             LIMIT 1");
                $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
                $stmt->execute();
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$producto) {
                    throw new PDOException("El producto seleccionado no existe.");
                }
                if (($producto['estado'] ?? '') !== 'ACTIVO') {
                    throw new PDOException("El producto \"{$producto['nombre']}\" no está disponible.");
                }
                if ((int)$producto['stock'] < $cantidad) {
                    throw new PDOException("No hay stock suficiente para \"{$producto['nombre']}\".");
                }

                $idVendedor = (int)$producto['id_vendedor'];
                $precio = (float)$producto['precio'];
                $pedidosPorVendedor[$idVendedor][] = [
                    'id_producto' => (int)$producto['id_producto'],
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                ];
            }

            $idsPedidos = [];

            foreach ($pedidosPorVendedor as $idVendedor => $lineas) {
                $montoTotal = 0.0;
                foreach ($lineas as $linea) {
                    $montoTotal += $linea['precio'] * $linea['cantidad'];
                }

                if ($usaPedidoConVendedor) {
                    $stmt = $this->_db->prepare("INSERT INTO pedidos
                        (id_cliente, id_vendedor, estado, monto_total, direccion_entrega, notas, fecha_pedido)
                        VALUES
                        (:id_cliente, :id_vendedor, 'PENDIENTE_PAGO', :monto_total, :direccion_entrega, :notas, NOW())");
                    $stmt->bindValue(':id_vendedor', $idVendedor, PDO::PARAM_INT);
                } else {
                    $stmt = $this->_db->prepare("INSERT INTO pedidos
                        (id_cliente, estado, monto_total, direccion_entrega, notas, fecha_pedido)
                        VALUES
                        (:id_cliente, 'PENDIENTE_PAGO', :monto_total, :direccion_entrega, :notas, NOW())");
                }
                $stmt->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
                $stmt->bindValue(':monto_total', $montoTotal);
                $stmt->bindValue(':direccion_entrega', $direccionEntrega, PDO::PARAM_STR);
                $stmt->bindValue(':notas', $notas, PDO::PARAM_STR);
                $stmt->execute();

                $idPedido = (int)$this->_db->lastInsertId();
                $idsPedidos[] = $idPedido;

                foreach ($lineas as $linea) {
                    if ($usaDetalleConVendedor) {
                        $stmt = $this->_db->prepare("INSERT INTO detalle_pedidos
                            (id_pedido, id_producto, id_vendedor, cantidad, precio_unitario)
                            VALUES
                            (:id_pedido, :id_producto, :id_vendedor, :cantidad, :precio_unitario)");
                        $stmt->bindValue(':id_vendedor', $idVendedor, PDO::PARAM_INT);
                    } else {
                        $stmt = $this->_db->prepare("INSERT INTO detalle_pedidos
                            (id_pedido, id_producto, cantidad, precio_unitario)
                            VALUES
                            (:id_pedido, :id_producto, :cantidad, :precio_unitario)");
                    }
                    $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                    $stmt->bindValue(':id_producto', $linea['id_producto'], PDO::PARAM_INT);
                    $stmt->bindValue(':cantidad', $linea['cantidad'], PDO::PARAM_INT);
                    $stmt->bindValue(':precio_unitario', $linea['precio']);
                    $stmt->execute();
                }

                $stmt = $this->_db->prepare("INSERT INTO notificaciones
                    (id_usuario, id_pedido, tipo, mensaje)
                    VALUES (:id_usuario, :id_pedido, 'PAGO_RECIBIDO', :mensaje)");
                $stmt->bindValue(':id_usuario', $idCliente, PDO::PARAM_INT);
                $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                $stmt->bindValue(':mensaje', 'Tu pedido #' . $idPedido . ' fue creado. Ahora solo falta registrar el pago desde el QR.', PDO::PARAM_STR);
                $stmt->execute();
            }

            $this->_db->commit();
            $array['ESTADO'] = true;
            $array['ID_PEDIDO'] = $idsPedidos[0] ?? 0;
            $array['ID_PEDIDOS'] = $idsPedidos;
        } catch (PDOException $e) {
            if ($this->_db->inTransaction()) {
                $this->_db->rollBack();
            }
            $array['ESTADO'] = false;
            $array['ERROR'] = $e->getMessage();
        }
        return $array;
    }

    public function confirmarPagoPedido($idPedido, $idCliente, $metodoPago = 'QR_CUPAZ', $comprobantePago = '', $referenciaCliente = '', $idQrAdmin = null)
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $selectVendedor = $this->hasColumn('pedidos', 'id_vendedor') ? ', id_vendedor' : '';
            $stmt = $this->_db->prepare("SELECT id_pedido, estado, monto_total{$selectVendedor}
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
            if (($pedido['estado'] ?? '') !== 'PENDIENTE_PAGO') {
                throw new PDOException('El pedido ya no está pendiente de pago.');
            }
            if (trim($comprobantePago) === '') {
                throw new PDOException('Debes adjuntar el comprobante del pago realizado al QR de CUPAZ.');
            }

            $stmt = $this->_db->prepare("SELECT COUNT(*) FROM pagos WHERE id_pedido = :id_pedido");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() > 0) {
                throw new PDOException('El pedido ya tiene un pago registrado.');
            }

            if ($this->hasColumn('pagos', 'id_vendedor')) {
                if ($this->hasColumn('detalle_pedidos', 'id_vendedor')) {
                    $stmt = $this->_db->prepare("SELECT dp.id_vendedor,
                                                        SUM(dp.cantidad * dp.precio_unitario) AS monto_vendedor
                                                 FROM detalle_pedidos dp
                                                 WHERE dp.id_pedido = :id_pedido
                                                 GROUP BY dp.id_vendedor");
                    $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                    $stmt->execute();
                    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $pagos = [[
                        'id_vendedor' => (int)($pedido['id_vendedor'] ?? 0),
                        'monto_vendedor' => (float)$pedido['monto_total'],
                    ]];
                }

                foreach ($pagos as $pago) {
                    $referencia = 'QR-' . $idPedido . '-' . (int)$pago['id_vendedor'] . '-' . strtoupper(bin2hex(random_bytes(2)));
                    $stmt = $this->_db->prepare("INSERT INTO pagos
                        (id_pedido, id_vendedor, monto, estado, metodo_pago, referencia_pago, fecha_pago)
                        VALUES
                        (:id_pedido, :id_vendedor, :monto, 'POR_VERIFICAR', :metodo_pago, :referencia_pago, NOW())");
                    $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                    $stmt->bindValue(':id_vendedor', (int)$pago['id_vendedor'], PDO::PARAM_INT);
                    $stmt->bindValue(':monto', (float)$pago['monto_vendedor']);
                    $stmt->bindValue(':metodo_pago', $metodoPago, PDO::PARAM_STR);
                    $stmt->bindValue(':referencia_pago', $referencia, PDO::PARAM_STR);
                    $stmt->execute();

                }
            } else {
                $referencia = 'QR-' . $idPedido . '-' . strtoupper(bin2hex(random_bytes(3)));
                $stmt = $this->_db->prepare("INSERT INTO pagos
                    (id_pedido, monto, estado, metodo_pago, referencia_pago, fecha_pago)
                    VALUES
                    (:id_pedido, :monto, 'POR_VERIFICAR', :metodo_pago, :referencia_pago, NOW())");
                $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                $stmt->bindValue(':monto', (float)$pedido['monto_total']);
                $stmt->bindValue(':metodo_pago', $metodoPago, PDO::PARAM_STR);
                $stmt->bindValue(':referencia_pago', $referencia, PDO::PARAM_STR);
                $stmt->execute();
            }

            $stmt = $this->_db->prepare("INSERT INTO comprobantes_pago
                (id_pedido, id_cliente, id_qr_admin, url_archivo, referencia_cliente, estado, fecha_subida)
                VALUES (:id_pedido, :id_cliente, :id_qr_admin, :url_archivo, :referencia_cliente, 'PENDIENTE', NOW())");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);
            if ($idQrAdmin === null) {
                $stmt->bindValue(':id_qr_admin', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':id_qr_admin', (int)$idQrAdmin, PDO::PARAM_INT);
            }
            $stmt->bindValue(':url_archivo', $comprobantePago, PDO::PARAM_STR);
            $stmt->bindValue(':referencia_cliente', $referenciaCliente, PDO::PARAM_STR);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE pedidos
                                         SET estado = 'PAGO_EN_VERIFICACION'
                                         WHERE id_pedido = :id_pedido");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE productos p
                                         INNER JOIN detalle_pedidos dp ON dp.id_producto = p.id_producto
                                         SET p.stock = p.stock - dp.cantidad,
                                             p.estado = CASE
                                                 WHEN (p.stock - dp.cantidad) <= 0 THEN 'AGOTADO'
                                                 ELSE p.estado
                                             END
                                         WHERE dp.id_pedido = :id_pedido");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
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

    public function confirmarRecepcionPedido($idPedido, $idCliente, $evidenciaRecepcion = '')
    {
        $array = [];
        try {
            $this->_db->beginTransaction();

            $selectVendedor = $this->hasColumn('pedidos', 'id_vendedor') ? ', id_vendedor' : '';
            $stmt = $this->_db->prepare("SELECT id_pedido, estado{$selectVendedor}
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
            if (!in_array($pedido['estado'], ['PAGO_RETENIDO', 'ENVIADO', 'ENTREGADO'], true)) {
                throw new PDOException('Este pedido aún no puede confirmarse como recibido.');
            }
            if ($this->hasTable('entregas_qr')) {
                $stmt = $this->_db->prepare("SELECT COUNT(*)
                                             FROM entregas_qr
                                             WHERE id_pedido = :id_pedido");
                $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                $stmt->execute();
                if ((int)$stmt->fetchColumn() > 0) {
                    throw new PDOException('Este pedido debe confirmarse escaneando el QR de entrega del vendedor.');
                }
            }
            if (trim($evidenciaRecepcion) === '') {
                throw new PDOException('Debes cargar una evidencia de recepción para liberar el pago.');
            }

            $stmt = $this->_db->prepare("UPDATE pedidos
                                         SET estado = 'COMPLETADO',
                                             fecha_completado = NOW()
                                         WHERE id_pedido = :id_pedido");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE pagos
                                         SET estado = 'LIBERADO',
                                             fecha_liberacion = NOW()
                                         WHERE id_pedido = :id_pedido
                                           AND estado IN ('RETENIDO', 'EN_DISPUTA')");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE envios
                                         SET estado = 'ENTREGADO',
                                             fecha_entrega = CASE WHEN fecha_entrega IS NULL THEN NOW() ELSE fecha_entrega END
                                         WHERE id_pedido = :id_pedido");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("INSERT INTO evidencias
                (id_pedido, id_usuario, tipo, url_archivo, descripcion, fecha_subida)
                VALUES (:id_pedido, :id_usuario, 'RECEPCION', :url_archivo, :descripcion, NOW())");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $idCliente, PDO::PARAM_INT);
            $stmt->bindValue(':url_archivo', $evidenciaRecepcion, PDO::PARAM_STR);
            $stmt->bindValue(':descripcion', 'Recepcion confirmada por el cliente con evidencia cargada.', PDO::PARAM_STR);
            $stmt->execute();

            $stmt = $this->_db->prepare("SELECT DISTINCT id_vendedor
                                         FROM pagos
                                         WHERE id_pedido = :id_pedido");
            $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
            $stmt->execute();
            $vendedores = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($vendedores as $idVendedor) {
                $stmt = $this->_db->prepare("INSERT INTO notificaciones
                    (id_usuario, id_pedido, tipo, mensaje)
                    VALUES (:id_usuario, :id_pedido, 'PAGO_LIBERADO', :mensaje)");
                $stmt->bindValue(':id_usuario', $idVendedor, PDO::PARAM_INT);
                $stmt->bindValue(':id_pedido', $idPedido, PDO::PARAM_INT);
                $stmt->bindValue(':mensaje', 'El pago retenido del pedido #' . $idPedido . ' fue liberado al vendedor.', PDO::PARAM_STR);
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

    public function listarPorCliente($idCliente)
    {
        $usaDetalleConVendedor = $this->hasColumn('detalle_pedidos', 'id_vendedor');
        $usaEntregasQr = $this->hasTable('entregas_qr');
        $selectQr = $usaEntregasQr
            ? ", MAX(eq.token) AS token_qr_entrega,
                       MAX(eq.estado) AS estado_qr_entrega"
            : ", '' AS token_qr_entrega,
                       '' AS estado_qr_entrega";
        $joinQr = $usaEntregasQr ? "LEFT JOIN entregas_qr eq ON eq.id_pedido = p.id_pedido" : "";
        $sql = "SELECT p.id_pedido,
                       p.estado,
                       p.monto_total,
                       p.direccion_entrega,
                       p.notas,
                       p.fecha_pedido,
                       GROUP_CONCAT(DISTINCT pr.nombre ORDER BY pr.nombre SEPARATOR ', ') AS productos,
                       " . ($usaDetalleConVendedor
                            ? "GROUP_CONCAT(DISTINCT v.nombre ORDER BY v.nombre SEPARATOR ', ')"
                            : "MAX(v.nombre)") . " AS vendedores,
                       COALESCE(MAX(pagos_resumen.total_retenido), 0) AS total_retenido,
                       COALESCE(MAX(pagos_resumen.total_liberado), 0) AS total_liberado,
                       COALESCE(MAX(pagos_resumen.total_por_verificar), 0) AS total_por_verificar
                       {$selectQr}
                FROM pedidos p
                LEFT JOIN detalle_pedidos dp ON dp.id_pedido = p.id_pedido
                LEFT JOIN productos pr ON pr.id_producto = dp.id_producto
                LEFT JOIN usuarios v ON v.id_usuario = dp.id_vendedor
                {$joinQr}
                LEFT JOIN (
                    SELECT id_pedido,
                           SUM(CASE WHEN estado = 'RETENIDO' THEN monto ELSE 0 END) AS total_retenido,
                           SUM(CASE WHEN estado = 'LIBERADO' THEN monto ELSE 0 END) AS total_liberado,
                           SUM(CASE WHEN estado = 'POR_VERIFICAR' THEN monto ELSE 0 END) AS total_por_verificar
                    FROM pagos
                    GROUP BY id_pedido
                ) pagos_resumen ON pagos_resumen.id_pedido = p.id_pedido
                WHERE p.id_cliente = :id_cliente
                GROUP BY p.id_pedido, p.estado, p.monto_total, p.direccion_entrega, p.notas, p.fecha_pedido
                ORDER BY p.fecha_pedido DESC";
        $param = [];
        array_push($param, [':id_cliente', $idCliente, PDO::PARAM_INT]);
        $resultado = parent::gselect($sql, $param);
        if (!empty($resultado['ESTADO'])) {
            return $resultado;
        }
        return $this->listarPorClienteBasico($idCliente);
    }

    public function listarTodos()
    {
        $usaDetalleConVendedor = $this->hasColumn('detalle_pedidos', 'id_vendedor');
        $usaEntregasQr = $this->hasTable('entregas_qr');
        $usaEvidencias = $this->hasTable('evidencias');
        $selectQr = $usaEntregasQr
            ? ", MAX(eq.estado) AS estado_qr_entrega,
                       MAX(CASE WHEN eq.estado = 'CONFIRMADO' THEN 1 ELSE 0 END) AS qr_confirmado,
                       MAX(CASE WHEN eq.estado = 'CONFIRMADO' THEN eq.fecha_confirmacion ELSE NULL END) AS fecha_verificacion_qr"
            : ", '' AS estado_qr_entrega,
                       0 AS qr_confirmado,
                       NULL AS fecha_verificacion_qr";
        $selectEvidencias = $usaEvidencias
            ? ", MAX(CASE WHEN ev.tipo = 'RECEPCION' THEN ev.url_archivo ELSE NULL END) AS evidencia_recepcion,
                       MAX(CASE WHEN ev.tipo = 'RECEPCION' AND ev.descripcion LIKE '%QR%' THEN ev.url_archivo ELSE NULL END) AS evidencia_recepcion_qr"
            : ", '' AS evidencia_recepcion,
                       '' AS evidencia_recepcion_qr";
        $joinQr = $usaEntregasQr ? "LEFT JOIN entregas_qr eq ON eq.id_pedido = p.id_pedido" : "";
        $joinEvidencias = $usaEvidencias ? "LEFT JOIN evidencias ev ON ev.id_pedido = p.id_pedido" : "";
        $sql = "SELECT p.id_pedido,
                       p.estado,
                       p.monto_total,
                       p.direccion_entrega,
                       p.fecha_pedido,
                       c.nombre AS cliente,
                       " . ($usaDetalleConVendedor
                            ? "GROUP_CONCAT(DISTINCT v.nombre ORDER BY v.nombre SEPARATOR ', ')"
                            : "MAX(v.nombre)") . " AS vendedores,
                       GROUP_CONCAT(DISTINCT pr.nombre ORDER BY pr.nombre SEPARATOR ', ') AS productos
                       {$selectQr}
                       {$selectEvidencias}
                FROM pedidos p
                INNER JOIN usuarios c ON c.id_usuario = p.id_cliente
                LEFT JOIN detalle_pedidos dp ON dp.id_pedido = p.id_pedido
                LEFT JOIN usuarios v ON v.id_usuario = dp.id_vendedor
                LEFT JOIN productos pr ON pr.id_producto = dp.id_producto
                {$joinQr}
                {$joinEvidencias}
                GROUP BY p.id_pedido, p.estado, p.monto_total, p.direccion_entrega, p.fecha_pedido, c.nombre
                ORDER BY p.fecha_pedido DESC";
        $resultado = parent::gselect($sql, []);
        if (!empty($resultado['ESTADO'])) {
            return $resultado;
        }
        return $this->listarTodosBasico();
    }

    private function listarPorClienteBasico($idCliente)
    {
        $usaDetalleConVendedor = $this->hasColumn('detalle_pedidos', 'id_vendedor');
        $sql = "SELECT p.id_pedido,
                       p.estado,
                       p.monto_total,
                       p.direccion_entrega,
                       p.notas,
                       p.fecha_pedido,
                       GROUP_CONCAT(DISTINCT pr.nombre ORDER BY pr.nombre SEPARATOR ', ') AS productos,
                       " . ($usaDetalleConVendedor
                            ? "GROUP_CONCAT(DISTINCT v.nombre ORDER BY v.nombre SEPARATOR ', ')"
                            : "MAX(v.nombre)") . " AS vendedores,
                       0 AS total_retenido,
                       0 AS total_liberado,
                       0 AS total_por_verificar,
                       '' AS token_qr_entrega,
                       '' AS estado_qr_entrega
                FROM pedidos p
                LEFT JOIN detalle_pedidos dp ON dp.id_pedido = p.id_pedido
                LEFT JOIN productos pr ON pr.id_producto = dp.id_producto
                LEFT JOIN usuarios v ON v.id_usuario = dp.id_vendedor
                WHERE p.id_cliente = :id_cliente
                GROUP BY p.id_pedido, p.estado, p.monto_total, p.direccion_entrega, p.notas, p.fecha_pedido
                ORDER BY p.fecha_pedido DESC";
        return parent::gselect($sql, [[':id_cliente', $idCliente, PDO::PARAM_INT]]);
    }

    private function listarTodosBasico()
    {
        $usaDetalleConVendedor = $this->hasColumn('detalle_pedidos', 'id_vendedor');
        $sql = "SELECT p.id_pedido,
                       p.estado,
                       p.monto_total,
                       p.direccion_entrega,
                       p.fecha_pedido,
                       c.nombre AS cliente,
                       " . ($usaDetalleConVendedor
                            ? "GROUP_CONCAT(DISTINCT v.nombre ORDER BY v.nombre SEPARATOR ', ')"
                            : "MAX(v.nombre)") . " AS vendedores,
                       GROUP_CONCAT(DISTINCT pr.nombre ORDER BY pr.nombre SEPARATOR ', ') AS productos,
                       '' AS estado_qr_entrega,
                       0 AS qr_confirmado,
                       NULL AS fecha_verificacion_qr,
                       '' AS evidencia_recepcion,
                       '' AS evidencia_recepcion_qr
                FROM pedidos p
                INNER JOIN usuarios c ON c.id_usuario = p.id_cliente
                LEFT JOIN detalle_pedidos dp ON dp.id_pedido = p.id_pedido
                LEFT JOIN usuarios v ON v.id_usuario = dp.id_vendedor
                LEFT JOIN productos pr ON pr.id_producto = dp.id_producto
                GROUP BY p.id_pedido, p.estado, p.monto_total, p.direccion_entrega, p.fecha_pedido, c.nombre
                ORDER BY p.fecha_pedido DESC";
        return parent::gselect($sql, []);
    }

    public function obtenerResumenPagoPedido($idPedido, $idCliente)
    {
        $sql = "SELECT p.id_pedido,
                       p.estado,
                       p.monto_total,
                       p.fecha_pedido,
                       c.nombre AS cliente
                FROM pedidos p
                INNER JOIN usuarios c ON c.id_usuario = p.id_cliente
                WHERE p.id_pedido = :id_pedido
                  AND p.id_cliente = :id_cliente
                LIMIT 1";
        $param = [];
        array_push($param, [':id_pedido', $idPedido, PDO::PARAM_INT]);
        array_push($param, [':id_cliente', $idCliente, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }
}
