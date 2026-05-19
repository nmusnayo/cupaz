<?php
require_once ROOT_DIR . '/model/EntregaQrModel.php';

$entregaModel = new EntregaQrModel();
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$mensajeError = '';
$mensajeExito = '';
$entrega = null;
$rolActual = strtoupper($_SESSION['login']['rol'] ?? '');
$idUsuario = (int)($_SESSION['login']['id_usuario'] ?? 0);
$sesionIniciada = isset($_SESSION['login']['id_usuario']);

function guardarEvidenciaRecepcion($file)
{
    if (!isset($file['tmp_name']) || $file['tmp_name'] === '' || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }
    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        return null;
    }
    $uploadDir = ROOT_UPLOAD . '/evidencias';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = 'evidencia_recepcion_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMime[$mime];
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }
    return 'uploads/evidencias/' . $fileName;
}

if ($token !== '') {
    $entregaData = $entregaModel->obtenerPorToken($token);
    $entrega = $entregaData['DATA'][0] ?? null;
    if ($entrega !== null) {
        $entregaModel->marcarEscaneo($token);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'confirmar_entrega_qr') {
    if (!isset($_SESSION['login']['id_usuario'])) {
        $mensajeError = 'Debes iniciar sesión como cliente para confirmar la entrega.';
    } elseif (!in_array($rolActual, ['CLIENTE', 'ADMIN'], true)) {
        $mensajeError = 'Solo un cliente puede confirmar la recepción de este pedido.';
    } else {
        $evidencia = guardarEvidenciaRecepcion($_FILES['evidencia_recepcion'] ?? []);
        if ($evidencia === null) {
            $resultado = ['ESTADO' => false, 'ERROR' => 'Debes subir una evidencia válida en imagen o PDF.'];
        } else {
            $resultado = $entregaModel->confirmarEntregaPorToken($token, $idUsuario, $evidencia);
        }
        if (!empty($resultado['ESTADO'])) {
            $mensajeExito = 'Entrega confirmada correctamente. El pago fue liberado y se registró la evidencia.';
            $entregaData = $entregaModel->obtenerPorToken($token);
            $entrega = $entregaData['DATA'][0] ?? null;
        } else {
            $mensajeError = $resultado['ERROR'] ?? 'No se pudo confirmar la entrega.';
        }
    }
}
?>
<?php if ($sesionIniciada): ?>
<?php require ROOT_VIEW . '/template/header.php'; ?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="module-shell">
                <div class="page-hero">
                    <span class="page-kicker"><i class="fas fa-qrcode"></i> Confirmación de entrega</span>
                    <h1 class="page-title">Recepción mediante QR</h1>
                    <p class="page-subtitle">Escanea el código del paquete y confirma que recibiste correctamente el producto para liberar el pago retenido.</p>
                </div>

                <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
                <?php if ($mensajeExito !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensajeExito); ?></div><?php endif; ?>

                <div class="surface-card">
                    <div class="card-header"><h3 class="card-title">Detalle del código</h3></div>
                    <div class="card-body">
                        <?php if ($entrega === null): ?>
                            <p class="section-note">El token no existe o ya no está disponible.</p>
                        <?php else: ?>
                            <div class="metric-strip">
                                <div class="metric-card">
                                    <div class="metric-label">Pedido</div>
                                    <div class="metric-value">#<?php echo (int)$entrega['id_pedido']; ?></div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-label">Vendedor</div>
                                    <div class="metric-value" style="font-size:20px;"><?php echo htmlspecialchars($entrega['vendedor'] ?? ''); ?></div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-label">Estado QR</div>
                                    <div class="metric-value" style="font-size:20px;"><?php echo htmlspecialchars($entrega['estado'] ?? ''); ?></div>
                                </div>
                            </div>

                            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($entrega['cliente'] ?? ''); ?></p>
                            <p><strong>Dirección:</strong> <?php echo htmlspecialchars($entrega['direccion_entrega'] ?? ''); ?></p>
                            <p><strong>Monto:</strong> Bs <?php echo number_format((float)($entrega['monto_total'] ?? 0), 2); ?></p>

                            <?php if (($entrega['estado'] ?? '') !== 'CONFIRMADO'): ?>
                                <form method="post" enctype="multipart/form-data" class="mt-4 cupaz-form">
                                    <input type="hidden" name="accion" value="confirmar_entrega_qr">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                    <div class="form-group">
                                        <label>Evidencia de recepción</label>
                                        <input type="file" name="evidencia_recepcion" class="form-control" accept="image/*,.pdf" required>
                                    </div>
                                    <button type="submit" class="btn btn-cupaz-primary">
                                        <i class="fas fa-check-circle mr-2"></i>Confirmar recepción del pedido
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="section-note">La recepción ya fue confirmada anteriormente mediante este QR.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require ROOT_VIEW . '/template/footer.php'; ?>
<?php else: ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CUPAZ | Confirmación de entrega</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f8f7; color:#25373c; margin:0; padding:32px; }
        .wrap { max-width:760px; margin:0 auto; background:#fff; border:1px solid #d7e4e5; border-radius:24px; padding:28px; box-shadow:0 18px 42px rgba(28,62,67,.08); }
        .tag { display:inline-block; padding:8px 14px; border-radius:999px; background:#e8f3f3; color:#1f6f78; font-weight:700; font-size:12px; text-transform:uppercase; }
        h1 { margin:18px 0 10px; }
        p { line-height:1.7; }
        .alert { padding:14px 16px; border-radius:16px; margin-bottom:16px; }
        .alert-danger { background:#fbe4e4; color:#8c2f39; }
        .card { margin-top:18px; padding:18px; border:1px solid #d7e4e5; border-radius:18px; background:#fbfcfc; }
        .btn { display:inline-block; margin-top:14px; padding:12px 18px; border-radius:12px; text-decoration:none; background:#1f6f78; color:#fff; font-weight:700; }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="tag">Confirmación de entrega</span>
        <h1>Escaneo de QR recibido</h1>
        <?php if ($mensajeError !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($mensajeError); ?></div><?php endif; ?>
        <?php if ($entrega !== null): ?>
            <div class="card">
                <p><strong>Pedido:</strong> #<?php echo (int)$entrega['id_pedido']; ?></p>
                <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($entrega['vendedor'] ?? ''); ?></p>
                <p><strong>Monto:</strong> Bs <?php echo number_format((float)($entrega['monto_total'] ?? 0), 2); ?></p>
            </div>
        <?php endif; ?>
        <p>Para confirmar la recepción y liberar el pago retenido, primero debes iniciar sesión con la cuenta del cliente que realizó el pedido.</p>
        <a class="btn" href="<?php echo HTTP_BASE; ?>/login">Iniciar sesión</a>
    </div>
</body>
</html>
<?php endif; ?>
