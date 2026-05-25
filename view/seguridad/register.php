<?php
require_once ROOT_DIR . '/model/UsuarioModel.php';
require_once ROOT_DIR . '/model/AuditoriaModel.php';

$mensajeError = '';
$mensajeExito = '';
$formData = [
    'email' => '',
    'nombre' => '',
    'telefono' => '',
    'ci' => '',
    'rol' => '',
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['nombre'] = trim($_POST['nombre'] ?? '');
    $formData['telefono'] = trim($_POST['telefono'] ?? '');
    $formData['ci'] = trim($_POST['ci'] ?? '');
    $formData['rol'] = strtoupper(trim($_POST['rol'] ?? ''));
    $password = $_POST['psw'] ?? '';
    $password2 = $_POST['psw1'] ?? '';
    $rolesPermitidos = ['CLIENTE', 'VENDEDOR'];

    if ($formData['email'] === '' || $formData['nombre'] === '' || $password === '' || $password2 === '') {
        $mensajeError = 'Completa los campos obligatorios para registrar el usuario.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $mensajeError = 'El correo electrónico no tiene un formato válido.';
    } elseif (!in_array($formData['rol'], $rolesPermitidos, true)) {
        $mensajeError = 'Selecciona un rol válido para el registro.';
    } elseif ($password !== $password2) {
        $mensajeError = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $mensajeError = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $usuarioModel = new UsuarioModel();
            $bootstrap = $usuarioModel->bootstrapAcceso();
            if (empty($bootstrap['ESTADO'])) {
                throw new Exception($bootstrap['ERROR'] ?? 'No se pudo preparar el acceso.');
            }
            $existente = $usuarioModel->findByCorreo($formData['email']);

            if (!empty($existente['DATA'])) {
                $mensajeError = 'Ya existe un usuario registrado con ese correo.';
            } else {
                $rolSeleccionado = $usuarioModel->findRolIdByNombre($formData['rol']);
                if (empty($rolSeleccionado['DATA'][0]['id_rol'])) {
                    throw new Exception('No se encontró el rol seleccionado.');
                }
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $resultado = $usuarioModel->register(
                    $formData['nombre'],
                    $formData['email'],
                    $hash,
                    $formData['telefono'] !== '' ? $formData['telefono'] : null,
                    $formData['ci'] !== '' ? $formData['ci'] : null,
                    (int)$rolSeleccionado['DATA'][0]['id_rol']
                );

                if (!empty($resultado['ESTADO'])) {
                    (new AuditoriaModel())->registrar('registro_usuario', 'seguridad', ['correo' => $formData['email'], 'nombre' => $formData['nombre'], 'rol' => $formData['rol']], null);
                    $login = $usuarioModel->verificarlogin($formData['email'], $password);
                    if (!empty($login['DATA'])) {
                        $_SESSION['login'] = $login['DATA'][0];
                        (new AuditoriaModel())->registrar('login_exitoso', 'seguridad', ['correo' => $formData['email']], (int)$_SESSION['login']['id_usuario']);
                        echo '<script>window.location.href ="' . HTTP_BASE . '/home/";</script>';
                        exit;
                    }

                    $mensajeExito = 'Usuario registrado correctamente. Ya puedes iniciar sesión.';
                    $formData = ['email' => '', 'nombre' => '', 'telefono' => '', 'ci' => '', 'rol' => 'CLIENTE'];
                } else {
                    $mensajeError = $resultado['ERROR'] ?? 'No se pudo registrar el usuario.';
                }
            }
        } catch (Throwable $e) {
            $mensajeError = 'No se pudo completar el registro. Revisa la base de datos y vuelve a intentar.';
        }
    }
}
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CUPAZ | Registro de usuarios</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&amp;display=fallback">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/dist/css/adminlte.min.css?v=3.2.0">
    <style>
    :root {
        --cupaz-primary: #1f6f78;
        --cupaz-primary-dark: #174e55;
        --cupaz-card: rgba(255, 255, 255, 0.95);
        --cupaz-border: #d8e2e4;
        --cupaz-text: #21343a;
        --cupaz-muted: #5f757b;
        --cupaz-success: #dff3e6;
        --cupaz-danger: #fbe4e4;
    }

    body {
        min-height: 100vh;
        margin: 0;
        background:
            linear-gradient(135deg, rgba(31, 111, 120, 0.12), rgba(240, 180, 41, 0.14)),
            linear-gradient(180deg, #fcfbf8 0%, #eef5f4 100%);
        color: var(--cupaz-text);
    }

    .auth-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
    }

    .auth-card {
        width: 100%;
        max-width: 1100px;
        display: grid;
        grid-template-columns: 0.95fr 1.05fr;
        background: var(--cupaz-card);
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 22px 60px rgba(31, 71, 77, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.65);
    }

    .auth-brand {
        padding: 44px;
        background:
            radial-gradient(circle at top left, rgba(240, 180, 41, 0.22), transparent 34%),
            linear-gradient(160deg, #f8faf7 0%, #edf6f4 100%);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .auth-brand img {
        width: 180px;
        max-width: 100%;
        margin-bottom: 24px;
    }

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(31, 111, 120, 0.08);
        color: var(--cupaz-primary-dark);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .auth-brand h1 {
        margin: 18px 0 14px;
        font-size: 36px;
        line-height: 1.12;
        font-weight: 800;
    }

    .auth-brand p {
        margin: 0;
        color: var(--cupaz-muted);
        font-size: 16px;
        line-height: 1.7;
    }

    .note-card {
        margin-top: 28px;
        padding: 18px 20px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.72);
        border: 1px solid rgba(31, 111, 120, 0.1);
    }

    .note-card h3 {
        margin: 0 0 10px;
        font-size: 16px;
        font-weight: 800;
    }

    .note-card p {
        margin: 0;
        font-size: 14px;
    }

    .auth-form-panel {
        padding: 44px 40px;
        background: rgba(255, 255, 255, 0.96);
    }

    .auth-form-wrap {
        width: 100%;
        max-width: 480px;
        margin: 0 auto;
    }

    .auth-form-wrap h2 {
        margin: 0 0 8px;
        font-size: 30px;
        font-weight: 800;
    }

    .subtitle {
        margin: 0 0 24px;
        color: var(--cupaz-muted);
        line-height: 1.6;
    }

    .alert-soft {
        border: none;
        border-radius: 16px;
        padding: 14px 16px;
        font-size: 14px;
        margin-bottom: 18px;
    }

    .alert-soft-danger {
        background: var(--cupaz-danger);
        color: #8c2f39;
    }

    .alert-soft-success {
        background: var(--cupaz-success);
        color: #286846;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 700;
        color: var(--cupaz-text);
        margin-bottom: 8px;
    }

    .input-group-text,
    .form-control {
        height: 50px;
        border-color: var(--cupaz-border);
        background: #fff;
    }

    .input-group-text {
        color: var(--cupaz-primary);
        border-right: 0;
        border-radius: 14px 0 0 14px;
        padding: 0 16px;
    }

    .form-control {
        border-left: 0;
        border-radius: 0 14px 14px 0;
        box-shadow: none;
        color: var(--cupaz-text);
    }

    .form-control:focus {
        border-color: var(--cupaz-primary);
        box-shadow: none;
    }

    .btn-cupaz {
        height: 52px;
        border: 0;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--cupaz-primary) 0%, #2d8e98 100%);
        color: #fff;
        font-weight: 700;
        box-shadow: 0 14px 28px rgba(31, 111, 120, 0.22);
    }

    .btn-cupaz:hover {
        color: #fff;
        background: linear-gradient(135deg, var(--cupaz-primary-dark) 0%, var(--cupaz-primary) 100%);
    }

    .auth-link {
        color: var(--cupaz-primary-dark);
        font-weight: 700;
    }

    .auth-link:hover {
        color: var(--cupaz-primary);
        text-decoration: none;
    }

    @media (max-width: 991px) {
        .auth-card {
            grid-template-columns: 1fr;
        }

        .auth-brand,
        .auth-form-panel {
            padding: 32px 24px;
        }
    }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <section class="auth-brand">
                <div>
                    <img src="<?php echo URL_RESOURCES; ?>adminlte/dist/img/logo.png?v=<?php echo @filemtime(ROOT_DIR . '/public/adminlte/dist/img/logo.png'); ?>" alt="CUPAZ">
                    <span class="eyebrow"><i class="fas fa-users"></i> Registro de acceso</span>
                    <h1>Crea tu cuenta para ingresar al sistema multivendedor de CUPAZ</h1>
                    <p>Este registro crea usuarios de acceso para el panel administrativo inicial del proyecto “Sistema de administración y control de E-commerce multivendedor”.</p>
                    <div class="note-card">
                        <h3>Roles de registro</h3>
                        <p>Los nuevos usuarios solo pueden registrarse como <strong>CLIENTE</strong> o <strong>VENDEDOR</strong>. El rol <strong>ADMIN</strong> queda reservado para la administración.</p>
                    </div>
                    <div class="note-card">
                        <h3>Administrador predeterminado</h3>
                        <p>Puedes ingresar también con <strong>admin@cupaz.com</strong> y la clave <strong>Admin1234</strong>.</p>
                    </div>
                </div>
                <div class="text-muted small">CUPAZ · Comerciantes Unidos de La Paz</div>
            </section>

            <section class="auth-form-panel">
                <div class="auth-form-wrap">
                    <h2>Registrar usuario</h2>
                    <p class="subtitle">Completa los datos básicos para crear una cuenta nueva y entrar al sistema.</p>

                    <?php if ($mensajeError !== ''): ?>
                        <div class="alert-soft alert-soft-danger">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($mensajeError); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensajeExito !== ''): ?>
                        <div class="alert-soft alert-soft-success">
                            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($mensajeExito); ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="post">
                        <div class="form-group">
                            <label for="email">Correo electrónico</label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                                </div>
                                <input id="email" type="email" class="form-control" name="email" placeholder="usuario@cupaz.com"
                                    value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nombre">Nombre completo</label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-user"></span></div>
                                </div>
                                <input id="nombre" type="text" class="form-control" name="nombre" placeholder="Nombre del usuario"
                                    value="<?php echo htmlspecialchars($formData['nombre']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-phone"></span></div>
                                </div>
                                <input id="telefono" type="text" class="form-control" name="telefono" placeholder="Número de contacto"
                                    value="<?php echo htmlspecialchars($formData['telefono']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="ci">CI</label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-id-card"></span></div>
                                </div>
                                <input id="ci" type="text" class="form-control" name="ci" placeholder="Carnet de identidad"
                                    value="<?php echo htmlspecialchars($formData['ci']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="rol">Rol de acceso</label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-user-tag"></span></div>
                                </div>
                                <select id="rol" class="form-control" name="rol" required>
                                    <option value="CLIENTE" <?php echo $formData['rol'] === 'CLIENTE' ? 'selected' : ''; ?>>Cliente</option>
                                    <option value="VENDEDOR" <?php echo $formData['rol'] === 'VENDEDOR' ? 'selected' : ''; ?>>Vendedor</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="psw">Contraseña</label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-lock"></span></div>
                                </div>
                                <input id="psw" type="password" class="form-control" name="psw" placeholder="Mínimo 6 caracteres" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="psw1">Confirmar contraseña</label>
                            <div class="input-group mb-4">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-lock"></span></div>
                                </div>
                                <input id="psw1" type="password" class="form-control" name="psw1" placeholder="Repite tu contraseña" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-cupaz btn-block">Crear cuenta e ingresar</button>
                        </div>

                        <p class="mb-0 text-muted">
                            ¿Ya tienes cuenta?
                            <a href="<?php echo HTTP_BASE . '/login'; ?>" class="auth-link">Volver al inicio de sesión</a>
                        </p>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo URL_RESOURCES; ?>adminlte/dist/js/adminlte.min.js?v=3.2.0"></script>
</body>
</html>
