<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (function_exists('csrf_token')): ?>
        <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <title>CUPAZ | Panel principal</title>

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <link rel="stylesheet"
        href="<?php echo URL_RESOURCES; ?>adminlte/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <link rel="stylesheet"
        href="<?php echo URL_RESOURCES; ?>adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/plugins/jqvmap/jqvmap.min.css">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet"
        href="<?php echo URL_RESOURCES; ?>adminlte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/plugins/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="<?php echo URL_RESOURCES; ?>adminlte/plugins/summernote/summernote-bs4.min.css">

    <style>
    :root {
        --cupaz-primary: #1f6f78;
        --cupaz-primary-soft: #e8f3f3;
        --cupaz-primary-dark: #164d54;
        --cupaz-accent: #f0b429;
        --cupaz-text: #25373c;
        --cupaz-muted: #698086;
        --cupaz-border: #d7e4e5;
        --cupaz-bg: #f5f8f7;
        --cupaz-surface: #ffffff;
        --cupaz-shadow: 0 18px 42px rgba(28, 62, 67, 0.08);
    }

    body {
        color: var(--cupaz-text);
        background: var(--cupaz-bg);
    }

    .layout-fixed .main-sidebar {
        background: linear-gradient(180deg, #f9fbfb 0%, #eef5f4 100%);
        border-right: 1px solid var(--cupaz-border);
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden;
    }

    .main-sidebar .sidebar {
        flex: 1 1 auto;
        min-height: 0;
        height: auto !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        padding-right: 6px;
        padding-bottom: 28px;
    }

    .main-sidebar .sidebar::-webkit-scrollbar {
        width: 8px;
    }

    .main-sidebar .sidebar::-webkit-scrollbar-thumb {
        background: rgba(31, 111, 120, 0.22);
        border-radius: 999px;
    }

    .main-header.navbar {
        background: rgba(255, 255, 255, 0.92) !important;
        border-bottom: 1px solid var(--cupaz-border);
        backdrop-filter: blur(10px);
    }

    .main-header .nav-link,
    .main-header .navbar-nav .nav-link {
        color: var(--cupaz-text) !important;
        border-radius: 12px;
    }

    .main-header .nav-link:hover {
        background: var(--cupaz-primary-soft);
        color: var(--cupaz-primary) !important;
    }

    .content-wrapper {
        background: linear-gradient(180deg, #f7faf9 0%, #f1f6f5 100%);
    }

    .nav-sidebar>.nav-item>.nav-link.active {
        background: linear-gradient(135deg, var(--cupaz-primary) 0%, #2d8e98 100%) !important;
        color: #fff !important;
        box-shadow: 0 10px 24px rgba(31, 111, 120, 0.18);
    }

    .nav-sidebar .nav-link {
        color: var(--cupaz-text) !important;
        border-radius: 14px;
        margin-bottom: 6px;
    }

    .nav-sidebar .nav-link:hover {
        background: var(--cupaz-primary-soft);
        color: var(--cupaz-primary) !important;
    }

    .nav-sidebar .nav-treeview>.nav-item>.nav-link {
        background: rgba(255, 255, 255, 0.76);
        border: 1px solid rgba(31, 111, 120, 0.08);
    }

    .card-header.text-center {
        background: transparent !important;
        border-bottom: 1px solid var(--cupaz-border);
        padding: 24px 18px;
    }

    .brand-logo-card img {
        max-width: 180px;
        margin: 0 auto;
        display: block;
    }

    .user-badge {
        margin: 14px 0 24px;
        padding: 14px 16px;
        border-radius: 16px;
        background: #fff;
        border: 1px solid var(--cupaz-border);
    }

    .user-badge .name {
        font-weight: 700;
    }

    .user-badge .role {
        color: var(--cupaz-muted);
        font-size: 13px;
    }

    .form-control-sidebar {
        background: #fff !important;
        color: var(--cupaz-text) !important;
        border: 1px solid var(--cupaz-border) !important;
    }

    .dropdown-menu {
        border-radius: 16px;
        border: 1px solid var(--cupaz-border);
        box-shadow: 0 18px 36px rgba(34, 62, 68, 0.12);
    }

    .dropdown-item:hover {
        background: var(--cupaz-primary-soft);
        color: var(--cupaz-primary);
    }

    .module-shell {
        padding: 28px 0 32px;
    }

    .page-hero {
        border-radius: 28px;
        padding: 30px 32px;
        margin-bottom: 22px;
        background:
            radial-gradient(circle at top right, rgba(240, 180, 41, 0.18), transparent 24%),
            linear-gradient(135deg, #ffffff 0%, #edf6f5 100%);
        border: 1px solid rgba(31, 111, 120, 0.18);
        border-left: 8px solid var(--cupaz-accent);
        box-shadow: var(--cupaz-shadow);
    }

    .page-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--cupaz-primary-soft);
        color: var(--cupaz-primary);
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .page-title {
        margin: 16px 0 8px;
        font-size: 31px;
        font-weight: 800;
        color: var(--cupaz-text);
    }

    .page-subtitle {
        margin: 0;
        max-width: 820px;
        color: var(--cupaz-muted);
        line-height: 1.7;
        font-size: 15px;
    }

    .surface-card {
        border: 1px solid var(--cupaz-border);
        border-radius: 24px;
        box-shadow: var(--cupaz-shadow);
        overflow: hidden;
        background: var(--cupaz-surface);
        position: relative;
    }

    .surface-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 6px;
        height: 100%;
        background: linear-gradient(180deg, var(--cupaz-primary) 0%, var(--cupaz-accent) 100%);
    }

    .surface-card .card-header {
        background: linear-gradient(180deg, rgba(31, 111, 120, 0.12), rgba(31, 111, 120, 0.03));
        border-bottom: 1px solid var(--cupaz-border);
        padding: 20px 24px 18px 28px;
    }

    .surface-card .card-title {
        font-weight: 800;
        color: var(--cupaz-text);
        margin: 0;
    }

    .surface-card .card-body {
        padding: 24px 24px 24px 28px;
    }

    .surface-card + .surface-card {
        margin-top: 4px;
    }

    .module-grid {
        display: grid;
        grid-template-columns: 360px minmax(0, 1fr);
        gap: 22px;
    }

    .metric-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 22px;
    }

    .metric-card {
        background: #fff;
        border: 1px solid var(--cupaz-border);
        border-radius: 20px;
        padding: 18px 20px;
        box-shadow: 0 12px 28px rgba(28, 62, 67, 0.05);
        border-top: 5px solid var(--cupaz-primary);
    }

    .metric-label {
        color: var(--cupaz-muted);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
    }

    .metric-value {
        margin-top: 8px;
        font-size: 28px;
        font-weight: 800;
        color: var(--cupaz-text);
    }

    .cupaz-form .form-group label {
        font-size: 13px;
        font-weight: 700;
        color: var(--cupaz-text);
        margin-bottom: 8px;
    }

    .cupaz-form .form-control,
    .cupaz-form .custom-select {
        border-radius: 14px;
        border: 1px solid var(--cupaz-border);
        min-height: 46px;
        box-shadow: none;
    }

    .cupaz-form textarea.form-control {
        min-height: 110px;
    }

    .cupaz-form .form-control:focus,
    .cupaz-form .custom-select:focus {
        border-color: var(--cupaz-primary);
        box-shadow: 0 0 0 0.2rem rgba(31, 111, 120, 0.08);
    }

    .btn-cupaz-primary {
        background: linear-gradient(135deg, var(--cupaz-primary) 0%, #2d8e98 100%);
        border: 0;
        color: #fff;
        border-radius: 14px;
        min-height: 48px;
        font-weight: 700;
        box-shadow: 0 14px 28px rgba(31, 111, 120, 0.16);
    }

    .btn-cupaz-primary:hover {
        color: #fff;
        background: linear-gradient(135deg, var(--cupaz-primary-dark) 0%, var(--cupaz-primary) 100%);
    }

    .btn-cupaz-outline {
        border: 1px solid rgba(31, 111, 120, 0.18);
        color: var(--cupaz-primary-dark);
        border-radius: 12px;
        background: #fff;
        font-weight: 700;
    }

    .btn-cupaz-outline:hover {
        background: var(--cupaz-primary-soft);
        color: var(--cupaz-primary-dark);
    }

    .btn-danger-soft {
        background: #fff0f0;
        color: #a33d3d;
        border: 1px solid #f0c4c4;
        border-radius: 12px;
        font-weight: 700;
    }

    .btn-danger-soft:hover {
        background: #fde7e7;
        color: #922f2f;
    }

    .table-cupaz {
        margin-bottom: 0;
    }

    .table-cupaz thead th {
        border-top: 0;
        border-bottom: 2px solid #dbe7e8;
        color: var(--cupaz-muted);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: linear-gradient(180deg, #fbfdfd 0%, #f2f7f7 100%);
        padding: 15px 14px;
    }

    .table-cupaz td {
        vertical-align: middle;
        border-top: 1px solid #edf3f3;
        padding: 15px 14px;
    }

    .table-cupaz tbody tr:hover {
        background: #fbfdfd;
    }

    .table-cupaz tbody tr:nth-child(even) {
        background: #fcfefe;
    }

    .badge-cupaz {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 700;
    }

    .badge-soft {
        background: #edf7f7;
        color: var(--cupaz-primary);
    }

    .badge-warning-soft {
        background: #fff4d9;
        color: #9b6b00;
    }

    .badge-danger-soft {
        background: #fde8e8;
        color: #a03a3a;
    }

    .catalog-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
    }

    .catalog-card {
        border: 1px solid var(--cupaz-border);
        border-radius: 24px;
        background: #fff;
        overflow: hidden;
        box-shadow: var(--cupaz-shadow);
    }

    .catalog-card img,
    .catalog-placeholder {
        width: 100%;
        height: 220px;
        object-fit: cover;
        background: linear-gradient(135deg, #e7f3f2, #f6faf9);
    }

    .catalog-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--cupaz-primary);
        font-size: 42px;
    }

    .catalog-body {
        padding: 20px;
    }

    .catalog-title {
        font-size: 20px;
        font-weight: 800;
        color: var(--cupaz-text);
        margin-bottom: 8px;
    }

    .catalog-copy {
        color: var(--cupaz-muted);
        line-height: 1.6;
        min-height: 70px;
        margin-bottom: 14px;
    }

    .catalog-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
    }

    .catalog-price {
        font-size: 24px;
        font-weight: 800;
        color: var(--cupaz-primary-dark);
    }

    .helper-text {
        color: var(--cupaz-muted);
        font-size: 13px;
    }

    .actions-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        margin-bottom: 16px;
        padding: 14px 16px;
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(31, 111, 120, 0.06), rgba(240, 180, 41, 0.06));
        border: 1px solid rgba(31, 111, 120, 0.12);
    }

    .section-note {
        color: var(--cupaz-muted);
        font-size: 14px;
        margin: 0;
    }

    .modal-content {
        border-radius: 24px;
        border: 1px solid var(--cupaz-border);
        overflow: hidden;
    }

    .modal-header {
        background: linear-gradient(135deg, rgba(31, 111, 120, 0.1), rgba(240, 180, 41, 0.08));
        border-bottom: 1px solid var(--cupaz-border);
    }

    .modal-title {
        font-weight: 800;
        color: var(--cupaz-text);
    }

    .modal-footer {
        border-top: 1px solid var(--cupaz-border);
    }

    .modal-body {
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfc 100%);
    }

    @media (max-width: 1199px) {
        .catalog-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991px) {
        .module-grid,
        .metric-strip,
        .catalog-grid {
            grid-template-columns: 1fr;
        }

        .page-hero {
            padding: 24px;
        }
    }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <?php $rolActual = strtoupper($_SESSION['login']['rol'] ?? ''); ?>
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?php echo HTTP_BASE; ?>/home/" class="nav-link">Inicio</a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?php echo HTTP_BASE; ?>/logout" class="nav-link">Salir</a>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-user"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <center>
                            <span class="dropdown-item dropdown-header">Cuenta</span>
                            <div class="dropdown-divider"></div>
                            <div class="user-panel">
                                <img src="<?php echo URL_RESOURCES; ?>adminlte/dist/img/user7-128x128.jpg"
                                    class="img-circle elevation-2" alt="User Image">
                            </div>
                            <div class="dropdown-divider"></div>
                            <span class="dropdown-item"><?php echo $_SESSION['login']['nombre']; ?></span>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="<?php echo HTTP_BASE; ?>/logout">
                                <i class="fas fa-sign-out-alt nav-icon"></i>
                                Salir
                            </a>
                        </center>
                    </div>
                </li>
            </ul>
        </nav>

        <aside class="main-sidebar elevation-4">
            <div class="card-header text-center brand-logo-card">
                <img src="<?php echo URL_RESOURCES; ?>adminlte/dist/img/logo.png?v=<?php echo @filemtime(ROOT_DIR . '/public/adminlte/dist/img/logo.png'); ?>" alt="Logo">
            </div>

            <div class="sidebar">
                <div class="user-badge">
                    <div class="name"><?php echo $_SESSION['login']['nombre']; ?></div>
                    <div class="role"><?php echo $_SESSION['login']['rol'] ?? 'Usuario activo'; ?></div>
                </div>

                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="false">
                        <li class="nav-item menu-open">
                            <a href="#" class="nav-link active">
                                <i class="nav-icon fas fa-store"></i>
                                <p>
                                    Administración
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?php echo HTTP_BASE; ?>/home/" class="nav-link">
                                        <i class="far fa-id-badge nav-icon"></i>
                                        <p>Panel principal</p>
                                    </a>
                                </li>
                                <!--//clasificacion de modulo segun el rol-->
                                <?php if ($rolActual === 'ADMIN'): ?>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/usuarios" class="nav-link">
                                            <i class="fas fa-users nav-icon"></i>
                                            <p>Usuarios y roles</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/categorias" class="nav-link">
                                            <i class="fas fa-tags nav-icon"></i>
                                            <p>Categorías</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/subcategorias" class="nav-link">
                                            <i class="fas fa-sitemap nav-icon"></i>
                                            <p>Subcategorías</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/vendor/productos" class="nav-link">
                                            <i class="fas fa-box-open nav-icon"></i>
                                            <p>Productos</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/client/catalogo" class="nav-link">
                                            <i class="fas fa-store nav-icon"></i>
                                            <p>Catálogo general</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/pedidos" class="nav-link">
                                            <i class="fas fa-clipboard-list nav-icon"></i>
                                            <p>Pedidos</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/pagos" class="nav-link">
                                            <i class="fas fa-qrcode nav-icon"></i>
                                            <p>Pagos y escrow</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/liquidaciones" class="nav-link">
                                            <i class="fas fa-wallet nav-icon"></i>
                                            <p>Liquidaciones</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/disputas" class="nav-link">
                                            <i class="fas fa-balance-scale nav-icon"></i>
                                            <p>Disputas</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/reportes" class="nav-link">
                                            <i class="fas fa-chart-bar nav-icon"></i>
                                            <p>Reportes</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/backups" class="nav-link">
                                            <i class="fas fa-database nav-icon"></i>
                                            <p>Backups</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/admin/auditoria" class="nav-link">
                                            <i class="fas fa-user-shield nav-icon"></i>
                                            <p>Auditoria</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/client/pedidos" class="nav-link">
                                            <i class="fas fa-receipt nav-icon"></i>
                                            <p>Vista de cliente</p>
                                        </a>
                                    </li>
                                <?php elseif ($rolActual === 'VENDEDOR'): ?>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/vendor/productos" class="nav-link">
                                            <i class="fas fa-box-open nav-icon"></i>
                                            <p>Mis productos</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/vendor/ventas" class="nav-link">
                                            <i class="fas fa-receipt nav-icon"></i>
                                            <p>Mis ventas</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/vendor/cobros" class="nav-link">
                                            <i class="fas fa-university nav-icon"></i>
                                            <p>Mis cobros</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/vendor/disputas" class="nav-link">
                                            <i class="fas fa-balance-scale nav-icon"></i>
                                            <p>Reclamos recibidos</p>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/client/catalogo" class="nav-link">
                                            <i class="fas fa-shopping-basket nav-icon"></i>
                                            <p>Catálogo</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/client/pedidos" class="nav-link">
                                            <i class="fas fa-truck nav-icon"></i>
                                            <p>Mis pedidos</p>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="<?php echo HTTP_BASE; ?>/client/disputas" class="nav-link">
                                            <i class="fas fa-exclamation-circle nav-icon"></i>
                                            <p>Mis reclamos</p>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>
