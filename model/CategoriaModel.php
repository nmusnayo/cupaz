<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";

class CategoriaModel extends ModeloBasePDO
{
    public function __construct()
    {
        parent::__construct();
    }

    public function listarCategorias()
    {
        $sql = "SELECT id_categoria, nombre, descripcion, estado
                FROM categorias
                ORDER BY nombre ASC";
        return parent::gselect($sql, []);
    }

    public function listarSubcategorias()
    {
        $sql = "SELECT s.id_subcategoria, s.nombre, s.estado, s.id_categoria,
                       c.nombre AS categoria
                FROM subcategorias s
                INNER JOIN categorias c ON c.id_categoria = s.id_categoria
                ORDER BY c.nombre ASC, s.nombre ASC";
        return parent::gselect($sql, []);
    }

    public function crearCategoria($nombre, $descripcion)
    {
        $sql = "INSERT INTO categorias (nombre, descripcion, estado)
                VALUES (:nombre, :descripcion, 'ACTIVO')";
        $param = [];
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        array_push($param, [':descripcion', $descripcion, PDO::PARAM_STR]);
        return parent::ginsert($sql, $param);
    }
    public function actualizarCategoria($idCategoria, $nombre, $descripcion, $estado)
    {
        $sql = "UPDATE categorias
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    estado = :estado
                WHERE id_categoria = :id_categoria";
        $param = [];
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        array_push($param, [':descripcion', $descripcion, PDO::PARAM_STR]);
        array_push($param, [':estado', $estado, PDO::PARAM_STR]);
        array_push($param, [':id_categoria', $idCategoria, PDO::PARAM_INT]);
        return parent::gupdate($sql, $param);
    }
    public function eliminarCategoria($idCategoria)
    {
        $sql = "DELETE FROM categorias WHERE id_categoria = :id_categoria";
        $param = [];
        array_push($param, [':id_categoria', $idCategoria, PDO::PARAM_INT]);
        return parent::gdelete($sql, $param);
    }

    public function crearSubcategoria($idCategoria, $nombre)
    {
        $sql = "INSERT INTO subcategorias (id_categoria, nombre, estado)
                VALUES (:id_categoria, :nombre, 'ACTIVO')";
        $param = [];
        array_push($param, [':id_categoria', $idCategoria, PDO::PARAM_INT]);
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        return parent::ginsert($sql, $param);
    }
    public function actualizarSubcategoria($idSubcategoria, $idCategoria, $nombre, $estado)
    {
        $sql = "UPDATE subcategorias
                SET id_categoria = :id_categoria,
                    nombre = :nombre,
                    estado = :estado
                WHERE id_subcategoria = :id_subcategoria";
        $param = [];
        array_push($param, [':id_categoria', $idCategoria, PDO::PARAM_INT]);
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        array_push($param, [':estado', $estado, PDO::PARAM_STR]);
        array_push($param, [':id_subcategoria', $idSubcategoria, PDO::PARAM_INT]);
        return parent::gupdate($sql, $param);
    }
    public function eliminarSubcategoria($idSubcategoria)
    {
        $sql = "DELETE FROM subcategorias WHERE id_subcategoria = :id_subcategoria";
        $param = [];
        array_push($param, [':id_subcategoria', $idSubcategoria, PDO::PARAM_INT]);
        return parent::gdelete($sql, $param);
    }
}
