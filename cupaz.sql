-- ============================================================
--  BASE DE DATOS
-- ============================================================
DROP DATABASE IF EXISTS ecommerce_cupaz;
CREATE DATABASE IF NOT EXISTS ecommerce_cupaz
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE ecommerce_cupaz;

-- ============================================================
-- 1. ROLES
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(150)
);

-- ============================================================
-- 2. USUARIOS
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    correo VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(30),
    ci VARCHAR(30),
    id_rol INT NOT NULL,
    estado ENUM('ACTIVO','INACTIVO','BLOQUEADO') DEFAULT 'ACTIVO',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
);

-- ============================================================
-- 3. CATEGORIAS
-- ============================================================
CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado ENUM('ACTIVO','INACTIVO') DEFAULT 'ACTIVO'
);

CREATE TABLE IF NOT EXISTS subcategorias (
    id_subcategoria INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    estado ENUM('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria)
);

-- ============================================================
-- 4. PRODUCTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    id_categoria INT,
    id_subcategoria INT,
    id_vendedor INT NOT NULL,
    imagen_principal VARCHAR(255),
    estado ENUM('ACTIVO','INACTIVO','AGOTADO') DEFAULT 'ACTIVO',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria),
    FOREIGN KEY (id_subcategoria) REFERENCES subcategorias(id_subcategoria),
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS imagenes_producto (
    id_imagen INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    url_imagen VARCHAR(255) NOT NULL,
    orden INT DEFAULT 0,
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
);

-- ============================================================
-- 5. PEDIDOS (SIN VENDEDOR)
-- ============================================================
CREATE TABLE IF NOT EXISTS pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    estado ENUM(
        'PENDIENTE_PAGO',
        'PAGO_EN_VERIFICACION',
        'PAGO_RETENIDO',
        'ENVIADO',
        'ENTREGADO',
        'COMPLETADO',
        'EN_DISPUTA',
        'REEMBOLSADO',
        'CANCELADO'
    ) DEFAULT 'PENDIENTE_PAGO',
    monto_total DECIMAL(10,2) NOT NULL,
    direccion_entrega VARCHAR(255),
    notas TEXT,
    fecha_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_envio DATETIME DEFAULT NULL,
    fecha_entrega DATETIME DEFAULT NULL,
    fecha_completado DATETIME DEFAULT NULL,
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 6. DETALLE PEDIDOS (MULTIVENDEDOR)
-- ============================================================
CREATE TABLE IF NOT EXISTS detalle_pedidos (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    id_vendedor INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto),
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 7. PAGOS (ESCROW POR VENDEDOR)
-- ============================================================
CREATE TABLE IF NOT EXISTS pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_vendedor INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    estado ENUM(
        'POR_VERIFICAR',
        'RETENIDO',
        'LIBERADO',
        'EN_DISPUTA',
        'REEMBOLSADO',
        'CANCELADO'
    ) DEFAULT 'POR_VERIFICAR',
    metodo_pago VARCHAR(80) DEFAULT 'QR_CUPAZ_REGISTRADO',
    referencia_pago VARCHAR(150),
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_liberacion DATETIME,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS admin_qr_pagos (
    id_qr INT AUTO_INCREMENT PRIMARY KEY,
    id_admin INT NOT NULL,
    titular VARCHAR(150) NOT NULL,
    qr_pago VARCHAR(255) NOT NULL,
    observaciones TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_qr_activo (activo)
);

CREATE TABLE IF NOT EXISTS comprobantes_pago (
    id_comprobante INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_cliente INT NOT NULL,
    id_qr_admin INT NULL,
    url_archivo VARCHAR(255) NOT NULL,
    referencia_cliente VARCHAR(150),
    estado ENUM('PENDIENTE','VERIFICADO','RECHAZADO') DEFAULT 'PENDIENTE',
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_verificacion DATETIME NULL,
    INDEX idx_comprobantes_pedido (id_pedido)
);

-- ============================================================
-- 8. ENVIOS (POR VENDEDOR)
-- ============================================================
CREATE TABLE IF NOT EXISTS envios (
    id_envio INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_vendedor INT NOT NULL,
    estado ENUM('PENDIENTE','ENVIADO','ENTREGADO') DEFAULT 'PENDIENTE',
    fecha_envio DATETIME,
    fecha_entrega DATETIME,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 9. EVIDENCIAS
-- ============================================================
CREATE TABLE IF NOT EXISTS evidencias (
    id_evidencia INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_usuario INT NOT NULL,
    tipo ENUM('ENVIO','RECEPCION','DISPUTA') NOT NULL,
    url_archivo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 10. DISPUTAS (POR VENDEDOR)
-- ============================================================
CREATE TABLE IF NOT EXISTS disputas (
    id_disputa INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_vendedor INT NOT NULL,
    id_cliente INT NOT NULL,
    id_admin INT,
    motivo TEXT NOT NULL,
    estado ENUM('ABIERTA','EN_REVISION','RESUELTA_CLIENTE','RESUELTA_VENDEDOR','CERRADA','ANULADA') DEFAULT 'ABIERTA',
    resolucion TEXT,
    fecha_apertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_admin) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 11. NOTIFICACIONES
-- ============================================================
CREATE TABLE IF NOT EXISTS notificaciones (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_pedido INT,
    tipo ENUM(
        'PAGO_RECIBIDO',
        'PEDIDO_ENVIADO',
        'PEDIDO_ENTREGADO',
        'PAGO_LIBERADO',
        'DISPUTA_ABIERTA',
        'DISPUTA_RESUELTA',
        'REEMBOLSO_PROCESADO'
    ) NOT NULL,
    mensaje TEXT NOT NULL,
    leida TINYINT(1) DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido)
);

-- ============================================================
-- 12. REPORTES
-- ============================================================
CREATE TABLE IF NOT EXISTS reportes (
    id_reporte INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('VENTAS','PEDIDOS','PAGOS','DISPUTAS') NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fin DATE NOT NULL,
    generado_por INT NOT NULL,
    datos_json LONGTEXT,
    fecha_generacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generado_por) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 13. QR DE ENTREGA
-- ============================================================
CREATE TABLE IF NOT EXISTS entregas_qr (
    id_entrega_qr INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_vendedor INT NOT NULL,
    token VARCHAR(120) NOT NULL UNIQUE,
    estado ENUM('GENERADO','ESCANEADO','CONFIRMADO','ANULADO') DEFAULT 'GENERADO',
    fecha_generacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_escaneo DATETIME DEFAULT NULL,
    fecha_confirmacion DATETIME DEFAULT NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido),
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 14. DATOS DE COBRO DEL VENDEDOR
-- ============================================================
CREATE TABLE IF NOT EXISTS vendedor_cobros (
    id_cobro INT AUTO_INCREMENT PRIMARY KEY,
    id_vendedor INT NOT NULL UNIQUE,
    titular VARCHAR(150) NOT NULL,
    banco VARCHAR(120),
    numero_cuenta VARCHAR(80),
    qr_cobro VARCHAR(255),
    observaciones TEXT,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 15. LIQUIDACIONES A VENDEDORES
-- ============================================================
CREATE TABLE IF NOT EXISTS liquidaciones_vendedor (
    id_liquidacion INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT NOT NULL UNIQUE,
    id_vendedor INT NOT NULL,
    id_admin INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    monto_bruto DECIMAL(10,2),
    comision_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    monto_comision DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monto_vendedor DECIMAL(10,2),
    estado ENUM('PENDIENTE','PAGADA') DEFAULT 'PAGADA',
    referencia_liquidacion VARCHAR(150),
    observaciones TEXT,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pago) REFERENCES pagos(id_pago),
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_admin) REFERENCES usuarios(id_usuario)
);

-- ============================================================
-- 13. INDICES (RENDIMIENTO)
-- ============================================================
CREATE INDEX idx_usuario_correo ON usuarios(correo);
CREATE INDEX idx_producto_categoria ON productos(id_categoria);
CREATE INDEX idx_pedido_cliente ON pedidos(id_cliente);
CREATE INDEX idx_detalle_pedido ON detalle_pedidos(id_pedido);
CREATE INDEX idx_pago_pedido ON pagos(id_pedido);

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- ROLES
INSERT INTO roles (nombre, descripcion)
SELECT 'ADMIN', 'Administrador general del sistema'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'ADMIN');

INSERT INTO roles (nombre, descripcion)
SELECT 'CLIENTE', 'Usuario comprador'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'CLIENTE');

INSERT INTO roles (nombre, descripcion)
SELECT 'VENDEDOR', 'Usuario que publica y vende productos'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'VENDEDOR');


-- USUARIO ADMIN
INSERT INTO usuarios (nombre, correo, password, telefono, ci, id_rol, estado)
SELECT
    'Administrador CUPAZ',
    'admin@cupaz.com',
    '$2y$10$IwQliGAM2hqVYU7.LcGwU.s7bHxWeyyYhlMCo5zW7cnCHtHmh89OS', -- bcrypt
    '70000000',
    '0000000',
    (SELECT id_rol FROM roles WHERE nombre = 'ADMIN' LIMIT 1),
    'ACTIVO'
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE correo = 'admin@cupaz.com');


-- ============================================================
-- INDICES PARA RENDIMIENTO
-- ============================================================

CREATE INDEX idx_productos_vendedor ON productos(id_vendedor);
CREATE INDEX idx_productos_categoria ON productos(id_categoria);
CREATE INDEX idx_productos_subcategoria ON productos(id_subcategoria);

CREATE INDEX idx_pedidos_cliente ON pedidos(id_cliente);
CREATE INDEX idx_pedidos_estado ON pedidos(estado);

CREATE INDEX idx_detalle_vendedor ON detalle_pedidos(id_vendedor);

CREATE INDEX idx_pagos_pedido ON pagos(id_pedido);
CREATE INDEX idx_pagos_vendedor ON pagos(id_vendedor);
CREATE INDEX idx_pagos_estado ON pagos(estado);

CREATE INDEX idx_envios_pedido ON envios(id_pedido);
CREATE INDEX idx_envios_vendedor ON envios(id_vendedor);

CREATE INDEX idx_notificaciones_user ON notificaciones(id_usuario, leida);
CREATE INDEX idx_entregas_qr_pedido ON entregas_qr(id_pedido);
CREATE INDEX idx_entregas_qr_vendedor ON entregas_qr(id_vendedor);
CREATE INDEX idx_liquidaciones_vendedor ON liquidaciones_vendedor(id_vendedor);

CREATE INDEX idx_disputas_estado ON disputas(estado);
CREATE INDEX idx_disputas_vendedor ON disputas(id_vendedor);
