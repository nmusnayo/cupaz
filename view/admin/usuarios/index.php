<?php
require_once ROOT_DIR . '/model/UsuarioModel.php';

$usuarioModel = new UsuarioModel();
$usuarioModel->bootstrapAcceso();
$mensajeError = '';
$mensajeExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'crear';

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $ci = trim($_POST['ci'] ?? '');
        $rol = strtoupper(trim($_POST['rol'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($nombre === '' || $correo === '' || $password === '') {
            $mensajeError = 'Completa nombre, correo y contraseña.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensajeError = 'Ingresa un correo válido.';
        } else {
            $rolData = $usuarioModel->findRolIdByNombre($rol);
            if (empty($rolData['DATA'][0]['id_rol'])) {
                $mensajeError = 'El rol seleccionado no existe.';
            } else {
                $existente = $usuarioModel->findByCorreo($correo);
                if (!empty($existente['DATA'])) {
                    $mensajeError = 'Ya existe un usuario con ese correo.';
                } else {
                    $resultado = $usuarioModel->register($nombre, $correo, password_hash($password, PASSWORD_BCRYPT), $telefono !== '' ? $telefono : null, $ci !== '' ? $ci : null, (int) $rolData['DATA'][0]['id_rol']);
                    $mensajeExito = !empty($resultado['ESTADO']) ? 'Usuario creado correctamente.' : '';
                    $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo crear el usuario.') : '';
                }
            }
        }
    } elseif ($accion === 'editar') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $correo = trim($_POST['correo'] ?? '');
        $rolData = $usuarioModel->findRolIdByNombre(strtoupper(trim($_POST['rol'] ?? 'CLIENTE')));
        $existente = $usuarioModel->findByCorreo($correo);

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensajeError = 'Ingresa un correo válido para actualizar el usuario.';
        } elseif (!empty($existente['DATA']) && (int) $existente['DATA'][0]['id_usuario'] !== $idUsuario) {
            $mensajeError = 'Ese correo ya pertenece a otro usuario.';
        } elseif (empty($rolData['DATA'][0]['id_rol'])) {
            $mensajeError = 'El rol seleccionado no existe.';
        } else {
            $passwordNueva = trim($_POST['password'] ?? '');
            $resultado = $usuarioModel->actualizarUsuario(
                $idUsuario,
                trim($_POST['nombre'] ?? ''),
                $correo,
                trim($_POST['telefono'] ?? '') !== '' ? trim($_POST['telefono']) : null,
                trim($_POST['ci'] ?? '') !== '' ? trim($_POST['ci']) : null,
                (int) $rolData['DATA'][0]['id_rol'],
                strtoupper(trim($_POST['estado'] ?? 'ACTIVO')),
                $passwordNueva !== '' ? password_hash($passwordNueva, PASSWORD_BCRYPT) : null
            );
            $mensajeExito = !empty($resultado['ESTADO']) ? 'Usuario actualizado correctamente.' : '';
            $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo actualizar el usuario.') : '';
        }
    } elseif ($accion === 'eliminar') {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        if ($idUsuario === (int) ($_SESSION['login']['id_usuario'] ?? 0)) {
            $mensajeError = 'No puedes eliminar tu propia cuenta desde este módulo.';
        } else {
            $resultado = $usuarioModel->eliminarUsuario($idUsuario);
            $mensajeExito = !empty($resultado['ESTADO']) ? 'Usuario eliminado correctamente.' : '';
            $mensajeError = empty($resultado['ESTADO']) ? ($resultado['ERROR'] ?? 'No se pudo eliminar el usuario.') : '';
        }
    }
}

$roles = $usuarioModel->listarRoles()['DATA'] ?? [];
$usuarios = $usuarioModel->findall()['DATA'] ?? [];
$idUsuarioActual = (int) ($_SESSION['login']['id_usuario'] ?? 0);
?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-users-cog"></i> Administración</span>
                    <h1 class="page-title">Usuarios y roles</h1>
                    <p class="page-subtitle">Gestiona el acceso a la plataforma con más color, mejor separación visual y
                        acciones en modales para crear, modificar o eliminar usuarios.</p>
                </div>

                <div class="metric-strip">
                    <div class="metric-card">
                        <div class="metric-label">Usuarios registrados</div>
                        <div class="metric-value"><?php echo count($usuarios); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Roles disponibles</div>
                        <div class="metric-value"><?php echo count($roles); ?></div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">Estado del módulo</div>
                        <div class="metric-value" style="font-size:20px;">Activo</div>
                    </div>
                </div>

                <div class="module-full">
                    <div class="surface-card">
                        <div class="card-header d-flex justify-content-between">
                            <h3 class="card-title">Usuarios registrados</h3>

                            <div class="d-flex align-items-end">
                                <button type="button" class="btn btn-cupaz-primary" data-toggle="modal"
                                    data-target="#modalCrearUsuario">
                                    <i class="fas fa-plus mr-2"></i>Nuevo usuario
                                </button>
                            </div>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-cupaz table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Teléfono</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong><br>
                                                <span
                                                    class="helper-text"><?php echo htmlspecialchars($usuario['ci'] ?? 'Sin CI'); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($usuario['correo']); ?></td>
                                            <td><span
                                                    class="badge-cupaz badge-soft"><?php echo htmlspecialchars($usuario['rol'] ?? ''); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $estadoUsuario = strtoupper($usuario['estado'] ?? '');
                                                $claseEstado = 'badge-warning-soft';
                                                if ($estadoUsuario === 'ACTIVO') {
                                                    $claseEstado = 'badge-soft';
                                                } elseif ($estadoUsuario === 'BLOQUEADO') {
                                                    $claseEstado = 'badge-danger-soft';
                                                }
                                                ?>
                                                <span
                                                    class="badge-cupaz <?php echo $claseEstado; ?>"><?php echo htmlspecialchars($usuario['estado']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-cupaz-outline"
                                                    data-toggle="modal"
                                                    data-target="#modalEditarUsuario<?php echo (int) $usuario['id_usuario']; ?>">
                                                    <i class="fas fa-pen mr-1"></i>Editar
                                                </button>
                                                <?php if ((int) $usuario['id_usuario'] !== $idUsuarioActual): ?>
                                                    <button type="button" class="btn btn-sm btn-danger-soft" data-toggle="modal"
                                                        data-target="#modalEliminarUsuario<?php echo (int) $usuario['id_usuario']; ?>">
                                                        <i class="fas fa-trash mr-1"></i>Eliminar
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge-cupaz badge-warning-soft">Cuenta actual</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" class="cupaz-form">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header">
                    <h5 class="modal-title">Añadir usuario</h5><button type="button" class="close"
                        data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Nombre</label><input type="text" name="nombre"
                                class="form-control" required></div>
                        <div class="col-md-6 form-group"><label>Correo</label><input type="email" name="correo"
                                class="form-control" required></div>
                        <div class="col-md-6 form-group"><label>Teléfono</label><input type="text" name="telefono"
                                class="form-control"></div>
                        <div class="col-md-6 form-group"><label>CI</label><input type="text" name="ci"
                                class="form-control"></div>
                        <div class="col-md-6 form-group">
                            <label>Rol</label>
                            <select name="rol" class="custom-select">
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?php echo htmlspecialchars($rol['nombre']); ?>">
                                        <?php echo htmlspecialchars($rol['nombre']); ?>
                                    </option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group"><label>Contraseña</label><input type="password" name="password"
                                class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-cupaz-primary">Guardar usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($usuarios as $usuario): ?>
    <div class="modal fade" id="modalEditarUsuario<?php echo (int) $usuario['id_usuario']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post" class="cupaz-form">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_usuario" value="<?php echo (int) $usuario['id_usuario']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Modificar usuario</h5><button type="button" class="close"
                            data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 form-group"><label>Nombre</label><input type="text" name="nombre"
                                    class="form-control" value="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                    required></div>
                            <div class="col-md-6 form-group"><label>Correo</label><input type="email" name="correo"
                                    class="form-control" value="<?php echo htmlspecialchars($usuario['correo']); ?>"
                                    required></div>
                            <div class="col-md-6 form-group"><label>Teléfono</label><input type="text" name="telefono"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>"></div>
                            <div class="col-md-6 form-group"><label>CI</label><input type="text" name="ci"
                                    class="form-control" value="<?php echo htmlspecialchars($usuario['ci'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Rol</label>
                                <select name="rol" class="custom-select">
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?php echo htmlspecialchars($rol['nombre']); ?>" <?php echo (($usuario['rol'] ?? '') === $rol['nombre']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rol['nombre']); ?>
                                        </option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Estado</label>
                                <select name="estado" class="custom-select">
                                    <?php foreach (['ACTIVO', 'INACTIVO', 'BLOQUEADO'] as $estado): ?>
                                        <option value="<?php echo $estado; ?>" <?php echo (($usuario['estado'] ?? '') === $estado) ? 'selected' : ''; ?>><?php echo $estado; ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12 form-group"><label>Nueva contraseña</label><input type="password"
                                    name="password" class="form-control"
                                    placeholder="Déjalo vacío para conservar la actual"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-cupaz-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEliminarUsuario<?php echo (int) $usuario['id_usuario']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_usuario" value="<?php echo (int) $usuario['id_usuario']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Eliminar usuario</h5><button type="button" class="close"
                            data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">¿Seguro que deseas eliminar a
                        <strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong>?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cupaz-outline" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger-soft">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require ROOT_VIEW . '/template/footer.php'; ?>