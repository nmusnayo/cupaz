<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class AuditoriaModel extends ModeloBasePDO
{
    public function __construct()
    {
        parent::__construct();
        $this->crearTablaSiNoExiste();
    }

    public function registrar($accion, $modulo, $detalle = null, $idUsuario = null)
    {
        try {
            $idUsuario = $idUsuario ?? ($_SESSION['login']['id_usuario'] ?? null);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
            $ruta = substr($_SERVER['REQUEST_URI'] ?? '', 0, 255);

            if (is_array($detalle)) {
                $detalle = $this->describirDetalle($accion, $modulo, $detalle);
            }

            $sql = "INSERT INTO auditoria
                    (id_usuario, accion, modulo, detalle, ip, user_agent, metodo, ruta)
                    VALUES
                    (:id_usuario, :accion, :modulo, :detalle, :ip, :user_agent, :metodo, :ruta)";
            $stmt = $this->_db->prepare($sql);
            $stmt->bindValue(':id_usuario', $idUsuario !== null ? (int)$idUsuario : null, $idUsuario !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':accion', substr((string)$accion, 0, 80), PDO::PARAM_STR);
            $stmt->bindValue(':modulo', substr((string)$modulo, 0, 80), PDO::PARAM_STR);
            $stmt->bindValue(':detalle', $detalle, $detalle !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':ip', substr($ip, 0, 45), PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);
            $stmt->bindValue(':metodo', substr($metodo, 0, 10), PDO::PARAM_STR);
            $stmt->bindValue(':ruta', $ruta, PDO::PARAM_STR);
            $stmt->execute();

            return ['ESTADO' => true];
        } catch (Throwable $e) {
            return ['ESTADO' => false, 'ERROR' => $e->getMessage()];
        }
    }

    public function listar($filtros = [])
    {
        $where = [];
        $param = [];

        if (!empty($filtros['texto'])) {
            $where[] = "UPPER(CONCAT(IFNULL(a.accion, ''), IFNULL(a.modulo, ''), IFNULL(a.detalle, ''), IFNULL(u.nombre, ''), IFNULL(u.correo, ''))) LIKE :texto";
            $param[':texto'] = '%' . strtoupper($filtros['texto']) . '%';
        }
        if (!empty($filtros['modulo'])) {
            $where[] = "a.modulo = :modulo";
            $param[':modulo'] = $filtros['modulo'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = "DATE(a.fecha_evento) >= :desde";
            $param[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = "DATE(a.fecha_evento) <= :hasta";
            $param[':hasta'] = $filtros['hasta'];
        }

        $sql = "SELECT a.id_auditoria, a.id_usuario, a.accion, a.modulo, a.detalle,
                       a.ip, a.user_agent, a.metodo, a.ruta, a.fecha_evento,
                       COALESCE(u.nombre, 'Sistema') AS usuario,
                       u.correo
                FROM auditoria a
                LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY a.id_auditoria DESC LIMIT 300";

        $stmt = $this->_db->prepare($sql);
        foreach ($param as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($datos as &$fila) {
            $fila['detalle'] = $this->normalizarDetalleListado($fila['accion'] ?? '', $fila['modulo'] ?? '', $fila['detalle'] ?? '');
        }
        unset($fila);

        return [
            'ESTADO' => true,
            'DATA' => $datos,
        ];
    }

    public function resumen()
    {
        $data = [
            'total' => 0,
            'hoy' => 0,
            'usuarios' => 0,
            'modulos' => [],
        ];

        $data['total'] = (int)$this->_db->query("SELECT COUNT(*) FROM auditoria")->fetchColumn();
        $data['hoy'] = (int)$this->_db->query("SELECT COUNT(*) FROM auditoria WHERE DATE(fecha_evento) = CURDATE()")->fetchColumn();
        $data['usuarios'] = (int)$this->_db->query("SELECT COUNT(DISTINCT id_usuario) FROM auditoria WHERE id_usuario IS NOT NULL")->fetchColumn();
        $data['modulos'] = $this->_db->query("SELECT modulo, COUNT(*) AS total FROM auditoria GROUP BY modulo ORDER BY total DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    public function modulos()
    {
        $stmt = $this->_db->query("SELECT DISTINCT modulo FROM auditoria ORDER BY modulo ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function crearTablaSiNoExiste()
    {
        $this->_db->exec("CREATE TABLE IF NOT EXISTS auditoria (
            id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NULL,
            accion VARCHAR(80) NOT NULL,
            modulo VARCHAR(80) NOT NULL,
            detalle TEXT NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            metodo VARCHAR(10) NULL,
            ruta VARCHAR(255) NULL,
            fecha_evento DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_auditoria_usuario (id_usuario),
            INDEX idx_auditoria_modulo (modulo),
            INDEX idx_auditoria_fecha (fecha_evento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function limpiarDetalle($detalle)
    {
        $bloqueados = ['password', 'contrasena', 'psw', '_csrf'];
        foreach ($detalle as $key => $value) {
            if (in_array(strtolower((string)$key), $bloqueados, true)) {
                $detalle[$key] = '[PROTEGIDO]';
            } elseif (is_array($value)) {
                $detalle[$key] = $this->limpiarDetalle($value);
            } elseif (is_string($value) && strlen($value) > 500) {
                $detalle[$key] = substr($value, 0, 500) . '...';
            }
        }
        return $detalle;
    }

    private function describirDetalle($accion, $modulo, $detalle)
    {
        $detalle = $this->limpiarDetalle($detalle);
        $accion = strtolower((string)$accion);
        $modulo = strtolower((string)$modulo);
        $idInfo = $this->extraerIdPrincipal($detalle);
        $atributos = $this->extraerAtributos($detalle);

        if ($this->esVenta($accion, $modulo, $detalle)) {
            return $this->describirVenta($detalle);
        }

        if ($this->esUsuario($accion, $modulo, $detalle)) {
            return $this->describirUsuario($accion, $detalle, $idInfo, $atributos);
        }

        return $this->describirAccionGeneral($accion, $modulo, $detalle, $idInfo, $atributos);
    }

    private function normalizarDetalleListado($accion, $modulo, $detalle)
    {
        $detalle = trim((string)$detalle);
        if ($detalle === '') {
            return '';
        }

        $json = json_decode($detalle, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $this->describirDetalle($accion, $modulo, $json);
        }

        return $detalle;
    }

    private function extraerIdPrincipal($detalle)
    {
        $preferidos = [
            'id_producto',
            'id_pedido',
            'id_pago',
            'id_disputa',
            'id_categoria',
            'id_subcategoria',
            'id_usuario',
            'id_cliente',
            'id_vendedor',
            'id_envio',
            'id_qr',
            'id_cobro',
            'id_liquidacion',
            'id_reporte',
        ];

        foreach ($preferidos as $campo) {
            if (isset($detalle[$campo]) && $detalle[$campo] !== '') {
                return ['campo' => $campo, 'valor' => $detalle[$campo]];
            }
        }

        foreach ($detalle as $campo => $valor) {
            if (strpos((string)$campo, 'id_') === 0 && $valor !== '') {
                return ['campo' => (string)$campo, 'valor' => $valor];
            }
        }

        return ['campo' => '', 'valor' => ''];
    }

    private function extraerAtributos($detalle)
    {
        $omitidos = ['accion', '_csrf', 'password', 'contrasena', 'psw', 'psw1', 'items'];
        $atributos = [];
        foreach ($detalle as $campo => $valor) {
            $campo = (string)$campo;
            if (in_array(strtolower($campo), $omitidos, true) || strpos($campo, 'id_') === 0) {
                continue;
            }
            if (is_array($valor)) {
                $atributos[] = $this->etiquetaCampo($campo);
                continue;
            }
            if ((string)$valor !== '') {
                $atributos[] = $this->etiquetaCampo($campo);
            }
        }
        return array_values(array_unique($atributos));
    }

    private function esVenta($accion, $modulo, $detalle)
    {
        return strpos($modulo, 'catalogo') !== false && $accion === 'confirmar_pedido'
            || isset($detalle['metodo_pago'])
            || isset($detalle['metodo']);
    }

    private function describirVenta($detalle)
    {
        $clienteId = $detalle['id_cliente'] ?? $detalle['cliente_id'] ?? '';
        $metodo = $detalle['metodo'] ?? $detalle['metodo_pago'] ?? 'NO DEFINIDO';
        $items = $detalle['items'] ?? $detalle['cantidad_items'] ?? null;

        if ($items === null && isset($detalle['carrito']) && is_array($detalle['carrito'])) {
            $items = count($detalle['carrito']);
        }

        $partes = [];
        if ($clienteId !== '') {
            $partes[] = 'Cliente ID: ' . $clienteId;
        }
        if ($metodo !== '') {
            $partes[] = 'metodo: ' . $metodo;
        }
        if ($items !== null && $items !== '') {
            $partes[] = 'items: ' . $items;
        }

        return 'Registro venta completa' . (!empty($partes) ? '. ' . implode(', ', $partes) : '.');
    }

    private function esUsuario($accion, $modulo, $detalle)
    {
        return strpos($modulo, 'usuario') !== false
            || $accion === 'registro_usuario'
            || isset($detalle['correo']) && (isset($detalle['rol']) || isset($detalle['id_rol']) || isset($detalle['cargo']));
    }

    private function describirUsuario($accion, $detalle, $idInfo, $atributos)
    {
        $correo = $detalle['correo'] ?? $detalle['email'] ?? '';
        $cargo = $detalle['cargo'] ?? $detalle['rol'] ?? $detalle['id_rol'] ?? '';

        if (in_array($accion, ['crear', 'registro_usuario'], true) && $correo !== '') {
            $texto = 'Creo un usuario con correo ' . $correo;
            if ($cargo !== '') {
                $texto .= ' y cargo ' . $cargo;
            }
            return $texto;
        }

        if ($accion === 'editar') {
            $texto = 'Modifico usuario';
            if (!empty($idInfo['valor'])) {
                $texto .= ' ID ' . $idInfo['valor'];
            }
            if (!empty($atributos)) {
                $texto .= ': ' . implode(', ', $atributos);
            }
            return $texto;
        }

        if ($accion === 'eliminar') {
            return 'Elimino usuario' . (!empty($idInfo['valor']) ? ' ID ' . $idInfo['valor'] : '');
        }

        if ($correo !== '') {
            return 'Usuario con correo ' . $correo . ($cargo !== '' ? ' y cargo ' . $cargo : '');
        }

        return !empty($idInfo['valor']) ? 'Usuario ID ' . $idInfo['valor'] : implode(', ', $atributos);
    }

    private function describirAccionGeneral($accion, $modulo, $detalle, $idInfo, $atributos)
    {
        $entidad = $this->nombreEntidad($modulo, $idInfo['campo'] ?? '');
        $idTexto = !empty($idInfo['valor']) ? ' ID ' . $idInfo['valor'] : '';
        $nombre = $detalle['nombre'] ?? $detalle['titular'] ?? $detalle['referencia_liquidacion'] ?? '';

        if (in_array($accion, ['crear', 'generar', 'generar_qr'], true)) {
            $texto = $this->verboAccion($accion) . ' ' . $entidad;
            if ($nombre !== '') {
                $texto .= ' con nombre ' . $nombre;
            }
            if (!empty($atributos)) {
                $texto .= '. Campos: ' . implode(', ', $atributos);
            }
            return $texto;
        }

        if (in_array($accion, ['editar', 'actualizar', 'actualizar_cantidad', 'marcar_enviado', 'marcar_entregado', 'resolver_disputa', 'cancelar_disputa'], true)) {
            $texto = $this->verboAccion($accion) . ' ' . $entidad . $idTexto;
            if (!empty($atributos)) {
                $texto .= '. Campos: ' . implode(', ', $atributos);
            }
            return $texto;
        }

        if (in_array($accion, ['eliminar', 'eliminar_carrito'], true)) {
            return $this->verboAccion($accion) . ' ' . $entidad . $idTexto;
        }

        if ($accion === 'agregar_carrito') {
            $texto = 'Agrego producto al carrito' . $idTexto;
            if (!empty($detalle['cantidad'])) {
                $texto .= '. Cantidad: ' . $detalle['cantidad'];
            }
            return $texto;
        }

        if (!empty($idInfo['valor']) && !empty($atributos)) {
            return ucfirst(str_replace('_', ' ', $accion)) . ' ' . $entidad . $idTexto . '. Campos: ' . implode(', ', $atributos);
        }

        if (!empty($idInfo['valor'])) {
            return ucfirst(str_replace('_', ' ', $accion)) . ' ' . $entidad . $idTexto;
        }

        if (!empty($atributos)) {
            return ucfirst(str_replace('_', ' ', $accion)) . '. Campos: ' . implode(', ', $atributos);
        }

        return ucfirst(str_replace('_', ' ', $accion));
    }

    private function verboAccion($accion)
    {
        $mapa = [
            'crear' => 'Creo',
            'editar' => 'Modifico',
            'actualizar' => 'Actualizo',
            'actualizar_cantidad' => 'Actualizo cantidad de',
            'eliminar' => 'Elimino',
            'generar' => 'Genero',
            'generar_qr' => 'Genero QR de',
            'agregar_carrito' => 'Agrego',
            'eliminar_carrito' => 'Quito',
            'marcar_enviado' => 'Marco como enviado',
            'marcar_entregado' => 'Marco como entregado',
            'resolver_disputa' => 'Resolvio',
            'cancelar_disputa' => 'Cancelo',
        ];
        return $mapa[$accion] ?? ucfirst(str_replace('_', ' ', $accion));
    }

    private function nombreEntidad($modulo, $campoId)
    {
        $mapaCampo = [
            'id_producto' => 'producto',
            'id_pedido' => 'pedido',
            'id_pago' => 'pago',
            'id_disputa' => 'disputa',
            'id_categoria' => 'categoria',
            'id_subcategoria' => 'subcategoria',
            'id_usuario' => 'usuario',
            'id_cliente' => 'cliente',
            'id_vendedor' => 'vendedor',
            'id_envio' => 'envio',
            'id_qr' => 'QR',
            'id_cobro' => 'dato de cobro',
            'id_liquidacion' => 'liquidacion',
            'id_reporte' => 'reporte',
        ];
        if (isset($mapaCampo[$campoId])) {
            return $mapaCampo[$campoId];
        }

        $mapaModulo = [
            'productos' => 'producto',
            'categorias' => 'categoria',
            'subcategorias' => 'subcategoria',
            'pedidos' => 'pedido',
            'pagos' => 'pago',
            'liquidaciones' => 'liquidacion',
            'disputas' => 'disputa',
            'reportes' => 'reporte',
            'backups' => 'backup',
            'cobros' => 'dato de cobro',
            'ventas' => 'venta',
            'catalogo' => 'catalogo',
        ];

        $partes = explode('/', trim((string)$modulo, '/'));
        $ultimo = end($partes) ?: 'registro';
        return $mapaModulo[$ultimo] ?? rtrim(strtolower($ultimo), 's');
    }

    private function etiquetaCampo($campo)
    {
        $mapa = [
            'nombre' => 'nombre',
            'precio' => 'precio',
            'descripcion' => 'descripcion',
            'stock' => 'stock',
            'estado' => 'estado',
            'correo' => 'correo',
            'telefono' => 'telefono',
            'ci' => 'CI',
            'rol' => 'rol',
            'direccion_entrega' => 'direccion de entrega',
            'notas' => 'notas',
            'cantidad' => 'cantidad',
            'imagen_principal' => 'imagen principal',
        ];
        return $mapa[$campo] ?? str_replace('_', ' ', $campo);
    }
}
