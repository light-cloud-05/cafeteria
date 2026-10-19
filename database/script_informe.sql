-- Esquema de una instalacion nueva, basado en las migraciones hasta 2026-10-14.
-- Este archivo documenta la estructura; no actualiza una base existente.
-- No incluye datos de ejemplo. La carga inicial se realiza con DatabaseSeeder.
CREATE DATABASE IF NOT EXISTS sistema_inventario
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_inventario;

CREATE TABLE roles (
    rol_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE permisos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE categorias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255) NULL,
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE proveedores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(255) NULL,
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- Catalogo inicial: Efectivo, QR y Mixto. Ventas guarda el nombre como texto.
CREATE TABLE metodo_pagos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id BIGINT UNSIGNED NOT NULL,
    nombreCompleto VARCHAR(255) NOT NULL,
    nombreUsuario VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(255) NULL,
    estado BOOLEAN NOT NULL DEFAULT TRUE,
    email VARCHAR(255) NULL UNIQUE,
    deleted_at TIMESTAMP NULL,
    session_token VARCHAR(64) NULL,
    session_last_activity BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(rol_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rol_permisos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idRol BIGINT UNSIGNED NOT NULL,
    idPermiso BIGINT UNSIGNED NOT NULL,
    fechaAsignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (idRol) REFERENCES roles(rol_id) ON DELETE CASCADE,
    FOREIGN KEY (idPermiso) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE productos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id BIGINT UNSIGNED NOT NULL,
    proveedor_id BIGINT UNSIGNED NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion VARCHAR(255) NULL,
    precio DECIMAL(8,2) NOT NULL,
    disponible BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE promociones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    aplicaSobre VARCHAR(50) NULL,
    valor DECIMAL(10,2) NOT NULL,
    topeDescuento DECIMAL(10,2) NULL,
    fechaInicio DATE NOT NULL,
    fechaFin DATE NOT NULL,
    horaInicio TIME NULL,
    horaFin TIME NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    producto_id BIGINT UNSIGNED NULL,
    producto_ids JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

CREATE TABLE stocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id BIGINT UNSIGNED NOT NULL UNIQUE,
    stock_actual DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_minimo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fecha_actualizacion DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE movimiento_inventarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    producto_id BIGINT UNSIGNED NOT NULL,
    proveedor_id BIGINT UNSIGNED NULL,
    tipo VARCHAR(255) NOT NULL,
    cantidad DECIMAL(8,2) NOT NULL,
    fecha_hora DATETIME NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
) ENGINE=InnoDB;

CREATE TABLE pedidos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NOT NULL,
    fechaHora DATETIME NOT NULL,
    total DECIMAL(8,2) NOT NULL,
    subtotal DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    descuento DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    promocion_id BIGINT UNSIGNED NULL,
    estado VARCHAR(255) NOT NULL,
    fecha_numero DATE NULL,
    numero_diario INT UNSIGNED NULL,
    stock_reservado BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY pedidos_fecha_numero_numero_diario_unique (fecha_numero, numero_diario),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (promocion_id) REFERENCES promociones(id)
) ENGINE=InnoDB;

CREATE TABLE numeradores_pedidos (
    fecha DATE PRIMARY KEY,
    ultimo INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE detalle_pedidos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id BIGINT UNSIGNED NOT NULL,
    producto_id BIGINT UNSIGNED NOT NULL,
    promocion_id BIGINT UNSIGNED NULL,
    cantidad INT NOT NULL,
    precioUnitario DECIMAL(8,2) NOT NULL,
    opcion VARCHAR(255) NOT NULL,
    subtotal DECIMAL(8,2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (promocion_id) REFERENCES promociones(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ventas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id BIGINT UNSIGNED NOT NULL UNIQUE,
    usuario_id BIGINT UNSIGNED NOT NULL,
    fecha_hora DATETIME NOT NULL,
    total DECIMAL(8,2) NOT NULL,
    metodo_pago VARCHAR(255) NOT NULL,
    monto_recibido DECIMAL(8,2) NOT NULL,
    cambio DECIMAL(8,2) NOT NULL,
    estado VARCHAR(255) NOT NULL,
    monto_qr DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    monto_efectivo DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE cierre_cajas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idUsuario BIGINT UNSIGNED NOT NULL,
    fechaInicio DATETIME NOT NULL,
    fechaFin DATETIME NULL,
    totalSistema DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    totalDeclarado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    diferencia DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    observacion TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (idUsuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bitacoras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    idUsuario BIGINT UNSIGNED NOT NULL,
    tablaAfectada VARCHAR(50) NOT NULL,
    idRegistroAfectado BIGINT UNSIGNED NOT NULL,
    accion VARCHAR(50) NOT NULL,
    valoresAnteriores TEXT NULL,
    valoresNuevos TEXT NULL,
    fechaHora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX bitacoras_fechahora_index (fechaHora),
    INDEX bitacoras_idusuario_fechahora_index (idUsuario, fechaHora),
    INDEX bitacoras_accion_fechahora_index (accion, fechaHora),
    FOREIGN KEY (idUsuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tablas auxiliares del framework.
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)
) ENGINE=InnoDB;

-- La tabla migrations y su historial los administra Laravel al ejecutar migraciones.
