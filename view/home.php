<?php require(ROOT_VIEW . '/template/header.php'); ?>
<?php $rol = $_SESSION['login']['rol'] ?? 'Usuario'; ?>
<?php
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
?>
<div class="content-wrapper">
    <style>
    .cupaz-hero {
        border-radius: 28px;
        padding: 32px;
        background:
            radial-gradient(circle at top right, rgba(240, 180, 41, 0.18), transparent 28%),
            linear-gradient(135deg, #ffffff 0%, #eef6f5 100%);
        border: 1px solid #dbe8e8;
        box-shadow: 0 16px 40px rgba(31, 71, 77, 0.08);
    }

    .cupaz-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #e9f4f4;
        color: #1f6f78;
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .cupaz-title {
        margin: 18px 0 10px;
        font-size: 34px;
        font-weight: 800;
        color: #25373c;
    }

    .cupaz-lead {
        max-width: 760px;
        color: #62797f;
        font-size: 16px;
        line-height: 1.7;
    }

    .cupaz-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin-top: 24px;
    }

    .cupaz-stat {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #dbe8e8;
        padding: 22px;
        min-height: 160px;
    }

    .cupaz-stat .icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eaf4f4;
        color: #1f6f78;
        font-size: 20px;
        margin-bottom: 14px;
    }

    .cupaz-stat h3 {
        font-size: 18px;
        font-weight: 800;
        color: #25373c;
        margin-bottom: 10px;
    }

    .cupaz-stat p {
        color: #698086;
        margin: 0;
        line-height: 1.6;
    }

    .cupaz-alert {
        margin-bottom: 18px;
        padding: 16px 18px;
        border-radius: 18px;
        background: #fbe4e4;
        color: #8c2f39;
        border: 1px solid #f0c8c8;
    }

    @media (max-width: 991px) {
        .cupaz-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <div class="content-header">
        <div class="container-fluid">
            <div class="cupaz-hero">
                <?php if ($flashError !== ''): ?>
                    <div class="cupaz-alert">
                        <i class="fas fa-ban mr-2"></i><?php echo htmlspecialchars($flashError); ?>
                    </div>
                <?php endif; ?>
                <span class="cupaz-kicker"><i class="fas fa-store"></i> CUPAZ activo</span>
                <h1 class="cupaz-title">Bienvenido(a), <?php echo htmlspecialchars($_SESSION['login']['nombre']); ?></h1>
                <p class="cupaz-lead">
                    Ya tienes acceso al <strong>Sistema de administración y control de E-commerce multivendedor</strong>.
                    Esta primera versión quedó orientada a la nueva base para trabajar con roles, usuarios, categorías y productos, mostrando opciones según tu perfil.
                </p>

                <div class="cupaz-grid">
                    <div class="cupaz-stat">
                        <div class="icon"><i class="fas fa-user-shield"></i></div>
                        <h3>Acceso habilitado</h3>
                        <p>Tu sesión está iniciada correctamente con el rol <strong><?php echo htmlspecialchars($rol); ?></strong>.</p>
                    </div>
                    <?php if (strtoupper($rol) === 'ADMIN'): ?>
                        <div class="cupaz-stat">
                            <div class="icon"><i class="fas fa-users-cog"></i></div>
                            <h3>Administración completa</h3>
                            <p>Como administrador puedes supervisar usuarios, categorías, subcategorías, productos, pedidos y pagos del sistema.</p>
                        </div>
                        <div class="cupaz-stat">
                            <div class="icon"><i class="fas fa-shield-alt"></i></div>
                            <h3>Control de accesos</h3>
                            <p>Tu perfil puede entrar a todos los módulos, incluyendo las vistas de cliente y vendedor para revisar la operación completa.</p>
                        </div>
                    <?php elseif (strtoupper($rol) === 'VENDEDOR'): ?>
                        <div class="cupaz-stat">
                            <div class="icon"><i class="fas fa-box-open"></i></div>
                            <h3>Espacio de vendedor</h3>
                            <p>Tu perfil ya puede publicar productos, revisar ventas y actualizar el estado de tus envíos.</p>
                        </div>
                        <div class="cupaz-stat">
                            <div class="icon"><i class="fas fa-cash-register"></i></div>
                            <h3>Operación comercial</h3>
                            <p>Verás un menú orientado a catálogo, ventas y seguimiento, mientras los módulos administrativos quedan restringidos.</p>
                        </div>
                    <?php else: ?>
                        <div class="cupaz-stat">
                            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                            <h3>Espacio de cliente</h3>
                            <p>Tu perfil ya puede ver el catálogo, realizar pedidos, pagar con QR y abrir reclamos cuando haga falta.</p>
                        </div>
                        <div class="cupaz-stat">
                            <div class="icon"><i class="fas fa-user-check"></i></div>
                            <h3>Acceso limitado por rol</h3>
                            <p>Las funciones administrativas no aparecen para tu perfil y quedan protegidas por permisos.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require(ROOT_VIEW . '/template/footer.php'); ?>
