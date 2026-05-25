<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class ProductoModel extends ModeloBasePDO
{
    public function __construct()
    {
        parent::__construct();
    }

    public function listarPorVendedor($idVendedor)
    {
        $sql = "SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.stock, p.estado, p.imagen_principal,
                       p.id_categoria,
                       c.nombre AS categoria, s.nombre AS subcategoria,
                       u.nombre AS vendedor, u.id_usuario AS id_vendedor
                FROM productos p
                INNER JOIN usuarios u ON u.id_usuario = p.id_vendedor
                LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                LEFT JOIN subcategorias s ON s.id_subcategoria = p.id_subcategoria
                WHERE p.id_vendedor = :id_vendedor
                ORDER BY p.fecha_registro DESC";
        $param = [];
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function listarTodos()
    {
        $sql = "SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.stock, p.estado, p.imagen_principal,
                       p.id_categoria,
                       c.nombre AS categoria, s.nombre AS subcategoria,
                       u.nombre AS vendedor, u.id_usuario AS id_vendedor
                FROM productos p
                INNER JOIN usuarios u ON u.id_usuario = p.id_vendedor
                LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                LEFT JOIN subcategorias s ON s.id_subcategoria = p.id_subcategoria
                ORDER BY p.fecha_registro DESC";
        return parent::gselect($sql, []);
    }

    public function listarCatalogo()
    {
        $sql = "SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.stock, p.estado, p.imagen_principal,
                       p.id_categoria,
                       c.nombre AS categoria, s.nombre AS subcategoria,
                       u.nombre AS vendedor, u.id_usuario AS id_vendedor
                FROM productos p
                INNER JOIN usuarios u ON u.id_usuario = p.id_vendedor
                LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                LEFT JOIN subcategorias s ON s.id_subcategoria = p.id_subcategoria
                WHERE p.estado = 'ACTIVO' AND u.estado = 'ACTIVO' AND p.stock > 0
                ORDER BY p.fecha_registro DESC";
        return parent::gselect($sql, []);
    }

    public function obtenerPorId($idProducto)
    {
        $sql = "SELECT p.*, c.nombre AS categoria, s.nombre AS subcategoria,
                       u.nombre AS vendedor
                FROM productos p
                INNER JOIN usuarios u ON u.id_usuario = p.id_vendedor
                LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                LEFT JOIN subcategorias s ON s.id_subcategoria = p.id_subcategoria
                WHERE p.id_producto = :id_producto
                LIMIT 1";
        $param = [];
        array_push($param, [':id_producto', $idProducto, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    public function crearProducto($nombre, $descripcion, $precio, $stock, $idCategoria, $idSubcategoria, $idVendedor, $imagenPrincipal)
    {
        $sql = "INSERT INTO productos
                    (nombre, descripcion, precio, stock, id_categoria, id_subcategoria, id_vendedor, imagen_principal, estado)
                VALUES
                    (:nombre, :descripcion, :precio, :stock, :id_categoria, :id_subcategoria, :id_vendedor, :imagen_principal, 'ACTIVO')";
        $param = [];
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        array_push($param, [':descripcion', $descripcion, PDO::PARAM_STR]);
        array_push($param, [':precio', $precio]);
        array_push($param, [':stock', $stock, PDO::PARAM_INT]);
        array_push($param, [':id_categoria', $idCategoria ?: null, PDO::PARAM_INT]);
        array_push($param, [':id_subcategoria', $idSubcategoria ?: null, PDO::PARAM_INT]);
        array_push($param, [':id_vendedor', $idVendedor, PDO::PARAM_INT]);
        array_push($param, [':imagen_principal', $imagenPrincipal, PDO::PARAM_STR]);
        return parent::ginsert($sql, $param);
    }
    public function actualizarProducto($idProducto, $nombre, $descripcion, $precio, $stock, $idCategoria, $idSubcategoria, $imagenPrincipal = null)
    {
        $sql = "UPDATE productos
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    precio = :precio,
                    stock = :stock,
                    id_categoria = :id_categoria,
                    id_subcategoria = :id_subcategoria,
                    estado = CASE
                        WHEN :stock_estado <= 0 THEN 'AGOTADO'
                        ELSE 'ACTIVO'
                    END";
        $param = [];
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        array_push($param, [':descripcion', $descripcion, PDO::PARAM_STR]);
        array_push($param, [':precio', $precio]);
        array_push($param, [':stock', $stock, PDO::PARAM_INT]);
        array_push($param, [':id_categoria', $idCategoria ?: null, PDO::PARAM_INT]);
        array_push($param, [':id_subcategoria', $idSubcategoria ?: null, PDO::PARAM_INT]);
        array_push($param, [':stock_estado', $stock, PDO::PARAM_INT]);

        if ($imagenPrincipal !== null && $imagenPrincipal !== '') {
            $sql .= ", imagen_principal = :imagen_principal";
            array_push($param, [':imagen_principal', $imagenPrincipal, PDO::PARAM_STR]);
        }

        $sql .= " WHERE id_producto = :id_producto";
        array_push($param, [':id_producto', $idProducto, PDO::PARAM_INT]);
        return parent::gupdate($sql, $param);
    }

    public function obtenerUltimoId()
    {
        return (int)$this->_db->lastInsertId();
    }

    public function agregarImagen($idProducto, $urlImagen, $orden = 0)
    {
        $sql = "INSERT INTO imagenes_producto (id_producto, url_imagen, orden)
                VALUES (:id_producto, :url_imagen, :orden)";
        $param = [];
        array_push($param, [':id_producto', $idProducto, PDO::PARAM_INT]);
        array_push($param, [':url_imagen', $urlImagen, PDO::PARAM_STR]);
        array_push($param, [':orden', $orden, PDO::PARAM_INT]);
        return parent::ginsert($sql, $param);
    }
    public function eliminarProducto($idProducto)
    {
        $array = [];
        try {
            $this->_db->beginTransaction();
            $stmt = $this->_db->prepare("DELETE FROM imagenes_producto WHERE id_producto = :id_producto");
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->_db->prepare("DELETE FROM productos WHERE id_producto = :id_producto");
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
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

    public function listarImagenes($idProducto)
    {
        $sql = "SELECT id_imagen, url_imagen, orden
                FROM imagenes_producto
                WHERE id_producto = :id_producto
                ORDER BY orden ASC, id_imagen ASC";
        $param = [];
        array_push($param, [':id_producto', $idProducto, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }
}
