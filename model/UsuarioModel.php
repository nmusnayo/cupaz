<?php
require_once dirname(__DIR__) . "/core/ModeloBasePDO.php";
class UsuarioModel extends ModeloBasePDO
{
    private $adminCorreo = 'admin@cupaz.com';
    private $adminNombre = 'Administrador CUPAZ';
    private $adminPasswordHash = '$2y$10$IwQliGAM2hqVYU7.LcGwU.s7bHxWeyyYhlMCo5zW7cnCHtHmh89OS';

    public function __construct()
    {
        parent::__construct();
    }
    public function bootstrapAcceso()
    {
        $array = array();
        try {
            $this->_db->beginTransaction();

            $roles = [
                ['nombre' => 'ADMIN', 'descripcion' => 'Administrador general del sistema'],
                ['nombre' => 'CLIENTE', 'descripcion' => 'Usuario comprador'],
                ['nombre' => 'VENDEDOR', 'descripcion' => 'Usuario que publica y vende productos'],
            ];
            foreach ($roles as $rol) {
                $stmt = $this->_db->prepare("INSERT INTO roles (nombre, descripcion)
                    SELECT :nombre, :descripcion
                    WHERE NOT EXISTS (
                        SELECT 1 FROM roles WHERE nombre = :nombre_check
                    )");
                $stmt->bindValue(':nombre', $rol['nombre'], PDO::PARAM_STR);
                $stmt->bindValue(':descripcion', $rol['descripcion'], PDO::PARAM_STR);
                $stmt->bindValue(':nombre_check', $rol['nombre'], PDO::PARAM_STR);
                $stmt->execute();
            }

            $stmt = $this->_db->prepare("INSERT INTO usuarios (nombre, correo, password, telefono, ci, id_rol, estado)
                SELECT
                    :nombre_admin,
                    :correo_admin,
                    :password,
                    '70000000',
                    '0000000',
                    r.id_rol,
                    'ACTIVO'
                FROM roles r
                WHERE r.nombre = 'ADMIN'
                  AND NOT EXISTS (
                      SELECT 1 FROM usuarios WHERE correo = :correo_admin_check
                  )
                LIMIT 1");
            $stmt->bindValue(':nombre_admin', $this->adminNombre, PDO::PARAM_STR);
            $stmt->bindValue(':correo_admin', $this->adminCorreo, PDO::PARAM_STR);
            $stmt->bindValue(':correo_admin_check', $this->adminCorreo, PDO::PARAM_STR);
            $stmt->bindValue(':password', $this->adminPasswordHash, PDO::PARAM_STR);
            $stmt->execute();

            $stmt = $this->_db->prepare("UPDATE usuarios u
                JOIN roles r ON r.nombre = 'ADMIN'
                SET
                    u.nombre = :nombre_admin_update,
                    u.password = :password_update,
                    u.id_rol = r.id_rol,
                    u.estado = 'ACTIVO'
                WHERE u.correo = :correo_admin_update");
            $stmt->bindValue(':nombre_admin_update', $this->adminNombre, PDO::PARAM_STR);
            $stmt->bindValue(':password_update', $this->adminPasswordHash, PDO::PARAM_STR);
            $stmt->bindValue(':correo_admin_update', $this->adminCorreo, PDO::PARAM_STR);
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
    public function findall()
    {
        $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.ci, u.estado, u.fecha_registro,
                       u.id_rol, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id_rol = u.id_rol
                ORDER BY u.id_usuario DESC;";
        $param = array();
        return parent::gselect($sql, $param);
    }
    public function findid($p_id_usuario)
    {
        $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.ci, u.estado, u.fecha_registro,
                       u.id_rol, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id_rol = u.id_rol
                WHERE u.id_usuario = :p_id_usuario;";
        $param = array();
        array_push($param, [':p_id_usuario', $p_id_usuario, PDO::PARAM_INT]);
        return parent::gselect($sql, $param);
    }
    public function findByCorreo($p_correo)
    {
        $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.ci, u.estado, u.fecha_registro,
                       u.id_rol, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id_rol = u.id_rol
                WHERE u.correo = :p_correo
                LIMIT 1;";
        $param = array();
        array_push($param, [':p_correo', $p_correo, PDO::PARAM_STR]);
        return parent::gselect($sql, $param);
    }
    public function findRolIdByNombre($p_nombre_rol)
    {
        $sql = "SELECT id_rol, nombre
                FROM roles
                WHERE nombre = :p_nombre_rol
                LIMIT 1;";
        $param = array();
        array_push($param, [':p_nombre_rol', strtoupper($p_nombre_rol), PDO::PARAM_STR]);
        return parent::gselect($sql, $param);
    }
    public function listarRoles()
    {
        $sql = "SELECT id_rol, nombre, descripcion
                FROM roles
                ORDER BY id_rol ASC";
        return parent::gselect($sql, []);
    }
    public function actualizarUsuario($idUsuario, $nombre, $correo, $telefono, $ci, $idRol, $estado, $password = null)
    {
        $sql = "UPDATE usuarios
                SET nombre = :nombre,
                    correo = :correo,
                    telefono = :telefono,
                    ci = :ci,
                    id_rol = :id_rol,
                    estado = :estado";
        $param = [];
        array_push($param, [':nombre', $nombre, PDO::PARAM_STR]);
        array_push($param, [':correo', $correo, PDO::PARAM_STR]);
        array_push($param, [':telefono', $telefono, PDO::PARAM_STR]);
        array_push($param, [':ci', $ci, PDO::PARAM_STR]);
        array_push($param, [':id_rol', $idRol, PDO::PARAM_INT]);
        array_push($param, [':estado', $estado, PDO::PARAM_STR]);

        if ($password !== null && $password !== '') {
            $sql .= ", password = :password";
            array_push($param, [':password', $password, PDO::PARAM_STR]);
        }

        $sql .= " WHERE id_usuario = :id_usuario";
        array_push($param, [':id_usuario', $idUsuario, PDO::PARAM_INT]);
        return parent::gupdate($sql, $param);
    }
    public function eliminarUsuario($idUsuario)
    {
        $sql = "DELETE FROM usuarios WHERE id_usuario = :id_usuario";
        $param = [];
        array_push($param, [':id_usuario', $idUsuario, PDO::PARAM_INT]);
        return parent::gdelete($sql, $param);
    }
    public function findpaginateall($p_filtro, $p_limit, $p_offset)
    {
        $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.ci, u.estado, u.fecha_registro,
                       u.id_rol, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id_rol = u.id_rol
                WHERE upper(concat(
                    IFNULL(u.nombre, ''),
                    IFNULL(u.correo, ''),
                    IFNULL(u.telefono, ''),
                    IFNULL(u.ci, ''),
                    IFNULL(r.nombre, '')
                )) LIKE concat('%', upper(IFNULL(:p_filtro, '')), '%')
                LIMIT :p_limit
                OFFSET :p_offset";
        $param = array();
        array_push($param, [':p_filtro', $p_filtro, PDO::PARAM_STR]);
        array_push($param, [':p_limit', $p_limit, PDO::PARAM_INT]);
        array_push($param, [':p_offset', $p_offset, PDO::PARAM_INT]);
        $var = parent::gselect($sql, $param);

        $sqlCount = "SELECT COUNT(*) AS cant
                     FROM usuarios u
                     LEFT JOIN roles r ON r.id_rol = u.id_rol
                     WHERE upper(concat(
                        IFNULL(u.nombre, ''),
                        IFNULL(u.correo, ''),
                        IFNULL(u.telefono, ''),
                        IFNULL(u.ci, ''),
                        IFNULL(r.nombre, '')
                     )) LIKE concat('%', upper(IFNULL(:p_filtro, '')), '%')";
        $param = array();
        array_push($param, [':p_filtro', $p_filtro, PDO::PARAM_STR]);
        $var1 = parent::gselect($sqlCount, $param);
        $var['LENGTH'] = $var1['DATA'][0]['cant'];
        return $var;
    }
    public function findAuthByCorreo($p_correo)
    {
        $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.password, u.id_rol, r.nombre AS rol, u.estado
                FROM usuarios u
                LEFT JOIN roles r ON r.id_rol = u.id_rol
                WHERE u.correo = :p_correo
                LIMIT 1";
        $param = array();
        array_push($param, [':p_correo', $p_correo, PDO::PARAM_STR]);
        return parent::gselect($sql, $param);
    }
    public function verificarlogin($p_correo, $p_password_plain)
    {
        $usuario = $this->findAuthByCorreo($p_correo);

        if (empty($usuario['ESTADO']) || empty($usuario['DATA'])) {
            return [
                'ESTADO' => false,
                'ERROR' => 'Usuario o contraseña no válidos.',
            ];
        }

        $data = $usuario['DATA'][0];
        if (($data['estado'] ?? '') !== 'ACTIVO') {
            return [
                'ESTADO' => false,
                'ERROR' => 'La cuenta no está activa.',
            ];
        }

        $hashGuardado = $data['password'] ?? '';
        $loginValido = false;
        $rehash = false;

        if ($hashGuardado !== '' && password_verify($p_password_plain, $hashGuardado)) {
            $loginValido = true;
            $rehash = password_needs_rehash($hashGuardado, PASSWORD_BCRYPT);
        } else {
            $legacyHash = hash('sha512', md5($p_password_plain));
            if ($hashGuardado === $legacyHash) {
                $loginValido = true;
                $rehash = true;
            }
        }

        if (!$loginValido) {
            return [
                'ESTADO' => false,
                'ERROR' => 'Usuario o contraseña no válidos.',
            ];
        }

        if ($rehash) {
            $nuevoHash = password_hash($p_password_plain, PASSWORD_BCRYPT);
            $stmt = $this->_db->prepare("UPDATE usuarios SET password = :password WHERE id_usuario = :id_usuario");
            $stmt->bindValue(':password', $nuevoHash, PDO::PARAM_STR);
            $stmt->bindValue(':id_usuario', (int)$data['id_usuario'], PDO::PARAM_INT);
            $stmt->execute();
        }

        unset($data['password']);
        return [
            'ESTADO' => true,
            'DATA' => [$data],
            'NRO' => 1,
        ];
    }
    public function register($p_nombre, $p_correo, $p_password, $p_telefono = null, $p_ci = null, $p_id_rol = 2)
    {
        $sql = "INSERT INTO usuarios (nombre, correo, password, telefono, ci, id_rol, estado)
                VALUES (
                    :p_nombre,
                    :p_correo,
                    :p_password,
                    :p_telefono,
                    :p_ci,
                    COALESCE((SELECT id_rol FROM roles WHERE nombre = '' LIMIT 1), NULLIF(:p_id_rol, 0)),
                    'ACTIVO'
                );";
        $param = array();
        array_push($param, [':p_nombre', $p_nombre, PDO::PARAM_STR]);
        array_push($param, [':p_correo', $p_correo, PDO::PARAM_STR]);
        array_push($param, [':p_password', $p_password, PDO::PARAM_STR]);
        array_push($param, [':p_telefono', $p_telefono, PDO::PARAM_STR]);
        array_push($param, [':p_ci', $p_ci, PDO::PARAM_STR]);
        array_push($param, [':p_id_rol', $p_id_rol, PDO::PARAM_INT]);

        return parent::ginsert($sql, $param);
    }
    public function update($p_id_usuario, $p_nombre, $p_correo, $p_password, $p_telefono, $p_ci, $p_id_rol, $p_estado)
    {
        $sql = "UPDATE usuarios SET
                    nombre = :p_nombre,
                    correo = :p_correo,
                    password = :p_password,
                    telefono = :p_telefono,
                    ci = :p_ci,
                    id_rol = :p_id_rol,
                    estado = :p_estado
                WHERE id_usuario = :p_id_usuario";
        $param = array();
        array_push($param, [':p_id_usuario', $p_id_usuario, PDO::PARAM_INT]);
        array_push($param, [':p_nombre', $p_nombre, PDO::PARAM_STR]);
        array_push($param, [':p_correo', $p_correo, PDO::PARAM_STR]);
        array_push($param, [':p_password', $p_password, PDO::PARAM_STR]);
        array_push($param, [':p_telefono', $p_telefono, PDO::PARAM_STR]);
        array_push($param, [':p_ci', $p_ci, PDO::PARAM_STR]);
        array_push($param, [':p_id_rol', $p_id_rol, PDO::PARAM_INT]);
        array_push($param, [':p_estado', $p_estado, PDO::PARAM_STR]);
        return parent::gupdate($sql, $param);
    }
    public function delete($p_id_usuario)
    {
        $sql = "DELETE FROM usuarios WHERE id_usuario = :p_id_usuario";
        $param = array();
        array_push($param, [':p_id_usuario', $p_id_usuario, PDO::PARAM_INT]);
        return parent::gdelete($sql, $param);
    }
}
