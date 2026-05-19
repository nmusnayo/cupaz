<?php
include_once "../core/ModeloBasePDO.php";

class UsuarioModel extends ModeloBasePDO
{
    public function __construct()
    {
        parent::__construct();
    }

    // Obtener todos los usuarios
    public function findall()
    {
        $sql = "SELECT id_usuario AS id, nombre, correo AS correo_electronico FROM usuarios;";
        $param = array();
        return parent::gselect($sql, $param);
    }

    // Obtener usuario por ID
    public function findid($id)
    {
        $sql = "SELECT id_usuario AS id, nombre, correo AS correo_electronico FROM usuarios WHERE id_usuario = :id;";
        $param = array();
        array_push($param, [':id', $id, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }

    // Paginación y búsqueda
    public function findpaginateall($filter, $limit, $offset)
    {
        $sql = "SELECT id_usuario AS id, nombre, correo AS correo_electronico
                FROM usuarios
                WHERE upper(nombre) LIKE concat('%', upper(:filter), '%')
                   OR upper(correo) LIKE concat('%', upper(:filter), '%')
                LIMIT :limit
                OFFSET :offset;";
        $param = array();
        array_push($param, [':filter', $filter, PDO::PARAM_STR]);
        array_push($param, [':limit', $limit, PDO::PARAM_INT]);
        array_push($param, [':offset', $offset, PDO::PARAM_INT]);
        $var = parent::gselect($sql, $param);

        // Conteo total para paginación
        $sqlCount = "SELECT count(1) as cant
                     FROM usuarios
                     WHERE upper(nombre) LIKE concat('%', upper(:filter), '%')
                        OR upper(correo) LIKE concat('%', upper(:filter), '%')";
        $param = array();
        array_push($param, [':filter', $filter, PDO::PARAM_STR]);
        $var1 = parent::gselect($sqlCount, $param);
        $var['LENGTH'] = $var1['DATA'][0]['cant'];

        return $var;
    }

    // Registrar nuevo usuario
    public function register($correo_electronico, $nombre, $password)
    {
        $sql = "INSERT INTO usuarios (correo, nombre, password, id_rol, estado)
                VALUES (:correo, :nombre, :password, COALESCE((SELECT id_rol FROM roles WHERE nombre = 'CLIENTE' LIMIT 1), 2), 'ACTIVO')";
        $param = array();
        array_push($param, [':correo', $correo_electronico, PDO::PARAM_STR]);
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        array_push($param, [':password', $password, PDO::PARAM_STR]);
        return parent::ginsert($sql, $param);
    }

    // Verificar login
    public function verificarlogin($correo_electronico, $password)
    {
        $sql = "SELECT id_usuario AS id, nombre, correo AS correo_electronico, password
                FROM usuarios
                WHERE correo = :correo
                  AND estado = 'ACTIVO'
                LIMIT 1";
        $param = array();
        array_push($param, [':correo', $correo_electronico, PDO::PARAM_STR]);
        $usuario = parent::gselect($sql, $param);
        if (empty($usuario['DATA'][0]) || !password_verify($password, $usuario['DATA'][0]['password'])) {
            return ['ESTADO' => false, 'DATA' => [], 'NRO' => 0, 'ERROR' => 'Usuario o contraseña no válidos.'];
        }
        unset($usuario['DATA'][0]['password']);
        return $usuario;
    }

    // Actualizar usuario
    public function update($id, $nombre, $correo_electronico, $password = null)
    {
        $sql = "UPDATE usuarios SET nombre = :nombre, correo = :correo";
        $param = array();
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        array_push($param, [':correo', $correo_electronico, PDO::PARAM_STR]);

        if ($password !== null) {
            $sql .= ", password = :password";
            array_push($param, [':password', $password, PDO::PARAM_STR]);
        }

        $sql .= " WHERE id_usuario = :id";
        array_push($param, [':id', $id, PDO::PARAM_INT]);

        return parent::gupdate($sql, $param);
    }

    // Eliminar usuario
    public function delete($id)
    {
        $sql = "DELETE FROM usuarios WHERE id_usuario = :id";
        $param = array();
        array_push($param, [':id', $id, PDO::PARAM_INT]);
        return parent::gdelete($sql, $param);
    }
}
