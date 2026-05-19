-- Respaldo CUPAZ
-- Generado: 2026-05-19 16:37:24
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- Estructura de tabla `admin_qr_pagos`
DROP TABLE IF EXISTS `admin_qr_pagos`;
CREATE TABLE `admin_qr_pagos` (
  `id_qr` int(11) NOT NULL AUTO_INCREMENT,
  `id_admin` int(11) NOT NULL,
  `titular` varchar(150) NOT NULL,
  `qr_pago` varchar(255) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_actualizacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_qr`),
  KEY `idx_admin_qr_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `categorias`
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `comprobantes_pago`
DROP TABLE IF EXISTS `comprobantes_pago`;
CREATE TABLE `comprobantes_pago` (
  `id_comprobante` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_qr_admin` int(11) DEFAULT NULL,
  `url_archivo` varchar(255) NOT NULL,
  `referencia_cliente` varchar(150) DEFAULT NULL,
  `estado` enum('PENDIENTE','VERIFICADO','RECHAZADO') DEFAULT 'PENDIENTE',
  `fecha_subida` datetime DEFAULT current_timestamp(),
  `fecha_verificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_comprobante`),
  KEY `idx_comprobantes_pedido` (`id_pedido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `detalle_pedidos`
DROP TABLE IF EXISTS `detalle_pedidos`;
CREATE TABLE `detalle_pedidos` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED,
  PRIMARY KEY (`id_detalle`),
  KEY `id_producto` (`id_producto`),
  KEY `idx_detalle_pedido` (`id_pedido`),
  KEY `idx_detalle_vendedor` (`id_vendedor`),
  CONSTRAINT `detalle_pedidos_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`),
  CONSTRAINT `detalle_pedidos_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`),
  CONSTRAINT `detalle_pedidos_ibfk_3` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `disputas`
DROP TABLE IF EXISTS `disputas`;
CREATE TABLE `disputas` (
  `id_disputa` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `motivo` text NOT NULL,
  `estado` enum('ABIERTA','EN_REVISION','RESUELTA_CLIENTE','RESUELTA_VENDEDOR','CERRADA','ANULADA') DEFAULT 'ABIERTA',
  `resolucion` text DEFAULT NULL,
  `fecha_apertura` datetime DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_disputa`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_admin` (`id_admin`),
  KEY `idx_disputas_estado` (`estado`),
  KEY `idx_disputas_vendedor` (`id_vendedor`),
  CONSTRAINT `disputas_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`),
  CONSTRAINT `disputas_ibfk_2` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `disputas_ibfk_3` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `disputas_ibfk_4` FOREIGN KEY (`id_admin`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `entregas_qr`
DROP TABLE IF EXISTS `entregas_qr`;
CREATE TABLE `entregas_qr` (
  `id_entrega_qr` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `token` varchar(120) NOT NULL,
  `estado` enum('GENERADO','ESCANEADO','CONFIRMADO','ANULADO') DEFAULT 'GENERADO',
  `fecha_generacion` datetime DEFAULT current_timestamp(),
  `fecha_escaneo` datetime DEFAULT NULL,
  `fecha_confirmacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_entrega_qr`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_entregas_qr_pedido` (`id_pedido`),
  KEY `idx_entregas_qr_vendedor` (`id_vendedor`),
  CONSTRAINT `entregas_qr_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`),
  CONSTRAINT `entregas_qr_ibfk_2` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `envios`
DROP TABLE IF EXISTS `envios`;
CREATE TABLE `envios` (
  `id_envio` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `estado` enum('PENDIENTE','ENVIADO','ENTREGADO') DEFAULT 'PENDIENTE',
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  PRIMARY KEY (`id_envio`),
  KEY `idx_envios_pedido` (`id_pedido`),
  KEY `idx_envios_vendedor` (`id_vendedor`),
  CONSTRAINT `envios_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`),
  CONSTRAINT `envios_ibfk_2` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `evidencias`
DROP TABLE IF EXISTS `evidencias`;
CREATE TABLE `evidencias` (
  `id_evidencia` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo` enum('ENVIO','RECEPCION','DISPUTA') NOT NULL,
  `url_archivo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_evidencia`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `evidencias_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`),
  CONSTRAINT `evidencias_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `imagenes_producto`
DROP TABLE IF EXISTS `imagenes_producto`;
CREATE TABLE `imagenes_producto` (
  `id_imagen` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `url_imagen` varchar(255) NOT NULL,
  `orden` int(11) DEFAULT 0,
  PRIMARY KEY (`id_imagen`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `imagenes_producto_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `liquidaciones_vendedor`
DROP TABLE IF EXISTS `liquidaciones_vendedor`;
CREATE TABLE `liquidaciones_vendedor` (
  `id_liquidacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_pago` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `monto_bruto` decimal(10,2) DEFAULT NULL,
  `comision_porcentaje` decimal(5,2) NOT NULL DEFAULT 10.00,
  `monto_comision` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_vendedor` decimal(10,2) DEFAULT NULL,
  `estado` enum('PENDIENTE','PAGADA') DEFAULT 'PAGADA',
  `referencia_liquidacion` varchar(150) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_liquidacion`),
  UNIQUE KEY `id_pago` (`id_pago`),
  KEY `id_admin` (`id_admin`),
  KEY `idx_liquidaciones_vendedor` (`id_vendedor`),
  CONSTRAINT `liquidaciones_vendedor_ibfk_1` FOREIGN KEY (`id_pago`) REFERENCES `pagos` (`id_pago`),
  CONSTRAINT `liquidaciones_vendedor_ibfk_2` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `liquidaciones_vendedor_ibfk_3` FOREIGN KEY (`id_admin`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `notificaciones`
DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_pedido` int(11) DEFAULT NULL,
  `tipo` enum('PAGO_RECIBIDO','PEDIDO_ENVIADO','PEDIDO_ENTREGADO','PAGO_LIBERADO','DISPUTA_ABIERTA','DISPUTA_RESUELTA','REEMBOLSO_PROCESADO') NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notificacion`),
  KEY `id_pedido` (`id_pedido`),
  KEY `idx_notificaciones_user` (`id_usuario`,`leida`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `notificaciones_ibfk_2` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `pagos`
DROP TABLE IF EXISTS `pagos`;
CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `estado` enum('POR_VERIFICAR','RETENIDO','LIBERADO','EN_DISPUTA','REEMBOLSADO','CANCELADO') DEFAULT 'POR_VERIFICAR',
  `metodo_pago` varchar(80) DEFAULT 'QR_CUPAZ_REGISTRADO',
  `referencia_pago` varchar(150) DEFAULT NULL,
  `fecha_pago` datetime DEFAULT current_timestamp(),
  `fecha_liberacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_pago`),
  KEY `idx_pago_pedido` (`id_pedido`),
  KEY `idx_pagos_pedido` (`id_pedido`),
  KEY `idx_pagos_vendedor` (`id_vendedor`),
  KEY `idx_pagos_estado` (`estado`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`),
  CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `pedidos`
DROP TABLE IF EXISTS `pedidos`;
CREATE TABLE `pedidos` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `estado` enum('PENDIENTE_PAGO','PAGO_EN_VERIFICACION','PAGO_RETENIDO','ENVIADO','ENTREGADO','COMPLETADO','EN_DISPUTA','REEMBOLSADO','CANCELADO') DEFAULT 'PENDIENTE_PAGO',
  `monto_total` decimal(10,2) NOT NULL,
  `direccion_entrega` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_pedido` datetime DEFAULT current_timestamp(),
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  `fecha_completado` datetime DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `idx_pedido_cliente` (`id_cliente`),
  KEY `idx_pedidos_cliente` (`id_cliente`),
  KEY `idx_pedidos_estado` (`estado`),
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `productos`
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `id_categoria` int(11) DEFAULT NULL,
  `id_subcategoria` int(11) DEFAULT NULL,
  `id_vendedor` int(11) NOT NULL,
  `imagen_principal` varchar(255) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','AGOTADO') DEFAULT 'ACTIVO',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_producto`),
  KEY `idx_producto_categoria` (`id_categoria`),
  KEY `idx_productos_vendedor` (`id_vendedor`),
  KEY `idx_productos_categoria` (`id_categoria`),
  KEY `idx_productos_subcategoria` (`id_subcategoria`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`),
  CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`id_subcategoria`) REFERENCES `subcategorias` (`id_subcategoria`),
  CONSTRAINT `productos_ibfk_3` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `reportes`
DROP TABLE IF EXISTS `reportes`;
CREATE TABLE `reportes` (
  `id_reporte` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('VENTAS','PEDIDOS','PAGOS','DISPUTAS') NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fin` date NOT NULL,
  `generado_por` int(11) NOT NULL,
  `datos_json` longtext DEFAULT NULL,
  `fecha_generacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_reporte`),
  KEY `generado_por` (`generado_por`),
  CONSTRAINT `reportes_ibfk_1` FOREIGN KEY (`generado_por`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de tabla `reportes`
INSERT INTO `reportes` (`id_reporte`, `tipo`, `periodo_inicio`, `periodo_fin`, `generado_por`, `datos_json`, `fecha_generacion`) VALUES ('1', 'VENTAS', '2026-05-01', '2026-05-19', '1', '[]', '2026-05-19 10:34:03');
INSERT INTO `reportes` (`id_reporte`, `tipo`, `periodo_inicio`, `periodo_fin`, `generado_por`, `datos_json`, `fecha_generacion`) VALUES ('2', 'VENTAS', '2026-05-01', '2026-05-19', '1', '[]', '2026-05-19 10:36:54');

-- Estructura de tabla `roles`
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de tabla `roles`
INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`) VALUES ('1', 'ADMIN', 'Administrador general del sistema');
INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`) VALUES ('2', 'CLIENTE', 'Usuario comprador');
INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`) VALUES ('3', 'VENDEDOR', 'Usuario que publica y vende productos');

-- Estructura de tabla `subcategorias`
DROP TABLE IF EXISTS `subcategorias`;
CREATE TABLE `subcategorias` (
  `id_subcategoria` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_subcategoria`),
  KEY `id_categoria` (`id_categoria`),
  CONSTRAINT `subcategorias_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estructura de tabla `usuarios`
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `ci` varchar(30) DEFAULT NULL,
  `id_rol` int(11) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO','BLOQUEADO') DEFAULT 'ACTIVO',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `correo` (`correo`),
  KEY `id_rol` (`id_rol`),
  KEY `idx_usuario_correo` (`correo`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de tabla `usuarios`
INSERT INTO `usuarios` (`id_usuario`, `nombre`, `correo`, `password`, `telefono`, `ci`, `id_rol`, `estado`, `fecha_registro`) VALUES ('1', 'Administrador CUPAZ', 'admin@cupaz.com', '$2y$10$IwQliGAM2hqVYU7.LcGwU.s7bHxWeyyYhlMCo5zW7cnCHtHmh89OS', '70000000', '0000000', '1', 'ACTIVO', '2026-05-17 12:29:54');
INSERT INTO `usuarios` (`id_usuario`, `nombre`, `correo`, `password`, `telefono`, `ci`, `id_rol`, `estado`, `fecha_registro`) VALUES ('2', 'Grupo 11 TS', 'grupo11@gmail.com', '$2y$10$CyHxv7sAu5ytbbCN3h6syueQ7SI72xhQQdjiN0swU6PedwXWA27Ze', '70000001', '1000000', '3', 'ACTIVO', '2026-05-17 12:31:03');

-- Estructura de tabla `vendedor_cobros`
DROP TABLE IF EXISTS `vendedor_cobros`;
CREATE TABLE `vendedor_cobros` (
  `id_cobro` int(11) NOT NULL AUTO_INCREMENT,
  `id_vendedor` int(11) NOT NULL,
  `titular` varchar(150) NOT NULL,
  `banco` varchar(120) DEFAULT NULL,
  `numero_cuenta` varchar(80) DEFAULT NULL,
  `qr_cobro` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cobro`),
  UNIQUE KEY `id_vendedor` (`id_vendedor`),
  CONSTRAINT `vendedor_cobros_ibfk_1` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
