-- ============================================================
-- SISTEMA FACTURAS VIÁTICOS — NUEVAS TABLAS
-- Base de datos: n8n_bc  |  Motor: MySQL
-- Ejecutar UNA SOLA VEZ antes de desplegar la aplicación
-- ============================================================

USE n8n_bc;

-- ------------------------------------------------------------
-- 1. ROLES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(50)  NOT NULL UNIQUE,
  descripcion VARCHAR(200) NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (id, nombre, descripcion) VALUES
  (1, 'admin',        'Acceso total: usuarios, todas las facturas, exportación'),
  (2, 'vendedor',     'Solo sus propias facturas (por telegram_user_id)'),
  (3, 'contabilidad', 'Consulta y exportación de todas las facturas');

-- ------------------------------------------------------------
-- 2. USUARIOS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  nombre           VARCHAR(100)  NOT NULL,
  email            VARCHAR(150)  NOT NULL UNIQUE,
  password_hash    VARCHAR(255)  NOT NULL,
  rol_id           INT UNSIGNED  NOT NULL DEFAULT 2,
  telegram_user_id VARCHAR(50)   NULL UNIQUE COMMENT 'Vincula con facturas_ocr.telegram_user_id',
  activo           TINYINT(1)    NOT NULL DEFAULT 1,
  created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by       INT UNSIGNED  NULL,
  CONSTRAINT fk_usr_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin inicial  →  password: Admin2024!
-- (regenerar hash con: php -r "echo password_hash('Admin2024!', PASSWORD_BCRYPT, ['cost'=>12]);")
INSERT IGNORE INTO usuarios (id, nombre, email, password_hash, rol_id, telegram_user_id) VALUES
  (1, 'Administrador', 'admin@empresa.com',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL);

-- ------------------------------------------------------------
-- 3. AUDITORÍA — quién editó qué y cuándo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria_facturas (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  factura_id     BIGINT UNSIGNED NOT NULL  COMMENT 'facturas_ocr.id',
  usuario_id     INT UNSIGNED    NOT NULL,
  accion         ENUM('VER','EDITAR','EXPORTAR','CREAR','ELIMINAR') NOT NULL,
  campo_editado  VARCHAR(100)    NULL,
  valor_anterior TEXT            NULL,
  valor_nuevo    TEXT            NULL,
  ip             VARCHAR(45)     NULL,
  user_agent     VARCHAR(255)    NULL,
  created_at     TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aud_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_aud_factura ON auditoria_facturas(factura_id);
CREATE INDEX IF NOT EXISTS idx_aud_usuario ON auditoria_facturas(usuario_id);
CREATE INDEX IF NOT EXISTS idx_aud_fecha   ON auditoria_facturas(created_at);

-- ------------------------------------------------------------
-- 4. ÍNDICES DE PERFORMANCE en facturas_ocr
-- ------------------------------------------------------------
-- Solo ejecutar si no existen aún:
-- ALTER TABLE facturas_ocr ADD INDEX idx_telegram (telegram_user_id);
-- ALTER TABLE facturas_ocr ADD INDEX idx_fecha    (fecha);
-- ALTER TABLE facturas_ocr ADD INDEX idx_proveedor(proveedor(50));

-- ------------------------------------------------------------
-- 5. VISTA útil para reportes
-- ------------------------------------------------------------
CREATE OR REPLACE VIEW v_facturas_detalle AS
  SELECT
    f.id,
    f.fecha,
    f.nit_proveedor,
    f.proveedor,
    f.numero_factura,
    f.serie_factura,
    f.nit_cliente,
    f.nombre_cliente,
    f.subtotal,
    f.iva,
    f.total,
    f.moneda,
    f.regimen_isr,
    f.tipo_contribuyente,
    f.cuenta_contable,
    f.descripcion_cuenta,
    f.dimension_1,
    f.dimension_2,
    f.dimension_3,
    f.nombre_responsable,
    f.url_google_drive,
    f.telegram_user_id,
    f.tipo_documento,
    f.numero_autorizacion,
    f.created_at,
    f.fecha_procesamiento,
    u.nombre      AS vendedor_nombre,
    u.email       AS vendedor_email
  FROM facturas_ocr f
  LEFT JOIN usuarios u ON u.telegram_user_id = f.telegram_user_id;
