-- =====================================================================
-- CrazyPro-Reparaciones — Estructura de base de datos
-- MySQL 8 / MariaDB 10.4+  ·  InnoDB  ·  utf8mb4
--
-- Uso en WAMP:
--   1. phpMyAdmin → Importar → este archivo
--   ó
--   2. mysql -u root -p < schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `crazypro`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `crazypro`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Clientes del sitio (registro propio o vía Google OAuth)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(120)  NOT NULL,
  `email`          VARCHAR(190)  NOT NULL,
  `password_hash`  VARCHAR(255)  NULL,          -- NULL = cuenta creada solo con Google
  `google_id`      VARCHAR(64)   NULL,
  `avatar_url`     VARCHAR(500)  NULL,
  `whatsapp`       VARCHAR(25)   NULL,          -- solo dígitos, con lada país (521...)
  `canal_notif`    ENUM('whatsapp','correo','ambos') NOT NULL DEFAULT 'ambos',
  `tema`           ENUM('auto','oscuro','claro') NOT NULL DEFAULT 'auto',
  `creado_en`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  UNIQUE KEY `uq_usuarios_google` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cuentas de administrador (nunca se mezclan con `usuarios`)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario`        VARCHAR(60)   NOT NULL,
  `password_hash`  VARCHAR(255)  NOT NULL,
  `nombre`         VARCHAR(120)  NOT NULL,
  `activo`         TINYINT(1)    NOT NULL DEFAULT 1,
  `ultimo_acceso`  DATETIME      NULL,
  `creado_en`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Estados de orden — editables desde el CMS.
-- `protegido` = 1 impide borrarlos (estado inicial y estado final).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `estados_orden`;
CREATE TABLE `estados_orden` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(60)  NOT NULL,
  `posicion`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `color`      VARCHAR(9)   NOT NULL DEFAULT '#8B5CF6',
  `es_inicial` TINYINT(1)   NOT NULL DEFAULT 0,
  `es_final`   TINYINT(1)   NOT NULL DEFAULT 0,
  `protegido`  TINYINT(1)   NOT NULL DEFAULT 0,
  `activo`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `ix_estados_posicion` (`posicion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catálogo de servicios (sección 6 del brief)
-- precio NULL = "Precio por definir"  ·  precio 0.00 = gratuito
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `servicios`;
CREATE TABLE `servicios` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `categoria`   ENUM('computo','android','iphone','desarrollo') NOT NULL,
  `nombre`      VARCHAR(160) NOT NULL,
  `descripcion` TEXT         NULL,
  `imagen`      VARCHAR(255) NULL,
  `precio`      DECIMAL(10,2) NULL DEFAULT NULL,
  `posicion`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_en`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_servicios_cat` (`categoria`, `posicion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Aliados / negocios que refieren clientes
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `aliados`;
CREATE TABLE `aliados` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(160) NOT NULL,
  `codigo`        VARCHAR(40)  NOT NULL,        -- ej. TIENDAGONZALES
  `contacto`      VARCHAR(160) NULL,
  `telefono`      VARCHAR(25)  NULL,
  `descuento_pct` DECIMAL(5,2) NOT NULL DEFAULT 10.00,  -- descuento al cliente
  `comision`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- $ por referido al aliado
  `activo`        TINYINT(1)   NOT NULL DEFAULT 1,
  `creado_en`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aliados_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Órdenes / dispositivos
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `ordenes`;
CREATE TABLE `ordenes` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folio`          VARCHAR(20)  NOT NULL,       -- 16Sep2026-01
  `usuario_id`     INT UNSIGNED NULL,
  `servicio_id`    INT UNSIGNED NULL,
  `estado_id`      INT UNSIGNED NOT NULL,
  `aliado_id`      INT UNSIGNED NULL,
  `tipo_equipo`    ENUM('pc','laptop','android','iphone','otro') NOT NULL DEFAULT 'otro',
  `modelo`         VARCHAR(160) NULL,
  `capacidad`      VARCHAR(60)  NULL,
  `imei`           VARCHAR(40)  NULL,
  `dano_reportado` TEXT         NULL,
  `diagnostico`    TEXT         NULL,
  `presupuesto`    DECIMAL(10,2) NULL DEFAULT NULL,   -- NULL = por definir
  `anticipo`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `descuento_pct`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `nombre_contacto` VARCHAR(120) NULL,          -- para cotizaciones sin cuenta
  `email_contacto`  VARCHAR(190) NULL,
  `tel_contacto`    VARCHAR(25)  NULL,
  `origen`         ENUM('web','taller') NOT NULL DEFAULT 'web',
  `revisada`       TINYINT(1)   NOT NULL DEFAULT 0,   -- dashboard: "sin revisar"
  `fecha_promesa`  DATE         NULL,
  `creado_en`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ordenes_folio` (`folio`),
  KEY `ix_ordenes_usuario` (`usuario_id`),
  KEY `ix_ordenes_estado` (`estado_id`),
  KEY `ix_ordenes_aliado` (`aliado_id`),
  CONSTRAINT `fk_ordenes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ordenes_estado`  FOREIGN KEY (`estado_id`)  REFERENCES `estados_orden` (`id`),
  CONSTRAINT `fk_ordenes_aliado`  FOREIGN KEY (`aliado_id`)  REFERENCES `aliados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ordenes_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Bitácora de cambios de estado
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `historial_estados`;
CREATE TABLE `historial_estados` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orden_id`   INT UNSIGNED NOT NULL,
  `estado_id`  INT UNSIGNED NOT NULL,
  `admin_id`   INT UNSIGNED NULL,
  `comentario` TEXT         NULL,
  `creado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_hist_orden` (`orden_id`, `creado_en`),
  CONSTRAINT `fk_hist_orden`  FOREIGN KEY (`orden_id`)  REFERENCES `ordenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_estado` FOREIGN KEY (`estado_id`) REFERENCES `estados_orden` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Piezas usadas en cada orden
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `piezas_orden`;
CREATE TABLE `piezas_orden` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orden_id`  INT UNSIGNED NOT NULL,
  `nombre`    VARCHAR(160) NOT NULL,
  `tipo`      ENUM('generico','original','usado') NOT NULL DEFAULT 'generico',
  `cantidad`  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `precio`    DECIMAL(10,2) NULL DEFAULT NULL,   -- NULL = por definir (≠ 0.00)
  `creado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_piezas_orden` (`orden_id`),
  CONSTRAINT `fk_piezas_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Referidos: uno por cada cliente recomendado (online o "+1" manual)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `referidos`;
CREATE TABLE `referidos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `aliado_id`  INT UNSIGNED NOT NULL,
  `orden_id`   INT UNSIGNED NULL,               -- NULL si fue un "+1" manual
  `origen`     ENUM('codigo','manual') NOT NULL DEFAULT 'manual',
  `comision`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `pagado`     TINYINT(1)   NOT NULL DEFAULT 0,
  `pagado_en`  DATETIME     NULL,
  `nota`       VARCHAR(255) NULL,
  `creado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ref_aliado` (`aliado_id`, `pagado`),
  CONSTRAINT `fk_ref_aliado` FOREIGN KEY (`aliado_id`) REFERENCES `aliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_orden`  FOREIGN KEY (`orden_id`)  REFERENCES `ordenes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Bitácora de notificaciones enviadas
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `notificaciones_log`;
CREATE TABLE `notificaciones_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orden_id`   INT UNSIGNED NULL,
  `canal`      ENUM('whatsapp','correo','manual') NOT NULL,
  `destino`    VARCHAR(190) NULL,
  `mensaje`    TEXT         NULL,
  `estado`     ENUM('enviado','fallido','pendiente') NOT NULL DEFAULT 'pendiente',
  `respuesta`  TEXT         NULL,
  `creado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_notif_orden` (`orden_id`),
  CONSTRAINT `fk_notif_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CMS: textos, imágenes y ajustes editables sin tocar código
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `contenido`;
CREATE TABLE `contenido` (
  `clave`     VARCHAR(80)  NOT NULL,
  `valor`     TEXT         NULL,
  `tipo`      ENUM('texto','textarea','imagen','numero','color') NOT NULL DEFAULT 'texto',
  `grupo`     VARCHAR(40)  NOT NULL DEFAULT 'general',
  `etiqueta`  VARCHAR(160) NOT NULL,
  `posicion`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `editable`  TINYINT(1)   NOT NULL DEFAULT 1,   -- 0 = ajuste interno (ej. admin_slug)
  PRIMARY KEY (`clave`),
  KEY `ix_contenido_grupo` (`grupo`, `posicion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Rate limiting del login de administrador (sección 7 del brief)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `login_intentos`;
CREATE TABLE `login_intentos` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`        VARCHAR(45)  NOT NULL,
  `usuario`   VARCHAR(60)  NULL,
  `exito`     TINYINT(1)   NOT NULL DEFAULT 0,
  `creado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_intentos_ip` (`ip`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
