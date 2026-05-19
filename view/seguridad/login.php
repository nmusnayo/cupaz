<?php
require_once ROOT_DIR . '/model/UsuarioModel.php';

$mensajeError = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['psw'] ?? '';

    if ($email === '' || $password === '') {
        $mensajeError = 'Completa correo y contraseña para ingresar.';
    } else {
        try {
            $usuarioModel = new UsuarioModel();
            $bootstrap = $usuarioModel->bootstrapAcceso();
            if (empty($bootstrap['ESTADO'])) {
                throw new Exception($bootstrap['ERROR'] ?? 'No se pudo preparar el acceso.');
            }
            $resultado = $usuarioModel->verificarlogin($email, $password);

            if (!empty($resultado['ESTADO']) && !empty($resultado['DATA'])) {
                $_SESSION['login'] = $resultado['DATA'][0];
                echo '<script>window.location.href ="' . HTTP_BASE . '/home/";</script>';
                exit;
            }

            $mensajeError = $resultado['ERROR'] ?? 'Usuario o contraseña no válidos.';
        } catch (Throwable $e) {
            $mensajeError = 'No se pudo iniciar sesión. Verifica la conexión con la base de datos.';
        }
    }
}
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CUPAZ | Iniciar sesión</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&amp;display=fallback">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/dist/css/adminlte.min.css?v=3.2.0">
    <style>
    :root {
        --cupaz-primary: #1f6f78;
        --cupaz-primary-dark: #174e55;
        --cupaz-accent: #f0b429;
        --cupaz-card: rgba(255, 255, 255, 0.95);
        --cupaz-border: #d8e2e4;
        --cupaz-text: #21343a;
        --cupaz-muted: #5f757b;
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
        max-width: 1020px;
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        background: var(--cupaz-card);
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 22px 60px rgba(31, 71, 77, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.65);
    }

    .auth-brand {
        padding: 48px;
        background:
            radial-gradient(circle at top left, rgba(240, 180, 41, 0.22), transparent 34%),
            linear-gradient(160deg, #f8faf7 0%, #edf6f4 100%);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .auth-brand img {
        width: 190px;
        max-width: 100%;
        margin-bottom: 28px;
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
        font-size: 38px;
        line-height: 1.1;
        font-weight: 800;
    }

    .auth-brand p {
        margin: 0;
        color: var(--cupaz-muted);
        font-size: 16px;
        line-height: 1.7;
    }

    .feature-list {
        margin: 28px 0 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 14px;
    }

    .feature-list li {
        display: flex;
        gap: 12px;
        color: var(--cupaz-text);
        font-weight: 600;
    }

    .feature-list i {
        color: var(--cupaz-primary);
        margin-top: 3px;
    }

    .auth-form-panel {
        padding: 48px 40px;
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.96);
    }

    .auth-form-wrap {
        width: 100%;
        max-width: 420px;
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
        background: var(--cupaz-danger);
        color: #8c2f39;
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
                    <span class="eyebrow"><i class="fas fa-store"></i> Comerciantes Unidos de La Paz</span>
                    <h1>Sistema de administración y control de E-commerce multivendedor</h1>
                    <p>Un acceso más claro, moderno y alineado al proyecto CUPAZ para gestionar usuarios, catálogo y operación comercial desde un solo lugar.</p>
                    <ul class="feature-list">
                        <li><i class="fas fa-check-circle"></i><span>Acceso centralizado para administración y seguimiento.</span></li>
                        <li><i class="fas fa-check-circle"></i><span>Base lista para roles, usuarios, categorías y productos.</span></li>
                        <li><i class="fas fa-check-circle"></i><span>Diseño claro y más acorde al contexto comercial de CUPAZ.</span></li>
                    </ul>
                </div>
                <div class="text-muted small">CUPAZ · Plataforma inicial de acceso</div>
            </section>

            <section class="auth-form-panel">
                <div class="auth-form-wrap">
                    <h2>Iniciar sesión</h2>
                    <p class="subtitle">Ingresa con tu cuenta para acceder al panel principal del sistema.</p>

<?php if ($mensajeError !== ''): ?>
                        <div class="alert-soft">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($mensajeError); ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3 text-muted small">
                        Usuario administrador predeterminado: <strong>admin@cupaz.com</strong> / <strong>Admin1234</strong>
                    </div>

                    <form action="" method="post">
                        <div class="form-group">
                            <label for="email">Correo electrónico</label>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                                </div>
                                <input id="email" type="email" class="form-control" name="email" placeholder="usuario@cupaz.com"
                                    value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="psw">Contraseña</label>
                            <div class="input-group mb-4">
                                <div class="input-group-prepend">
                                    <div class="input-group-text"><span class="fas fa-lock"></span></div>
                                </div>
                                <input id="psw" type="password" class="form-control" name="psw" placeholder="Tu contraseña" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-cupaz btn-block">Entrar al sistema</button>
                        </div>

                        <p class="mb-0 text-muted">
                            ¿Aún no tienes cuenta?
                            <a href="<?php echo HTTP_BASE . '/register'; ?>" class="auth-link">Crear usuario nuevo</a>
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
