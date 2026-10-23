CREATE DATABASE IF NOT EXISTS factorysystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE factorysystem;

-- =========================
-- Users and authentication
-- =========================
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Suppliers
-- =========================
CREATE TABLE suppliers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Products / inventory items
-- =========================
CREATE TABLE products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    unit VARCHAR(50) DEFAULT 'pcs',
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    warehouse_location VARCHAR(150) DEFAULT NULL,
    last_stock_take TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_product (product_id),
    CONSTRAINT fk_inventory_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Purchase / stock movement
-- =========================
CREATE TABLE purchase_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(100) NOT NULL UNIQUE,
    order_date DATE NOT NULL,
    expected_date DATE DEFAULT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft', 'pending', 'received', 'cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_purchase_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_po_item_order
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_po_item_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_stock_movement_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movement_user
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Sales / customers
-- =========================
CREATE TABLE customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(100) NOT NULL UNIQUE,
    order_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'packed', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_sales_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_sales_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sales_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_sales_item_order
        FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_sales_item_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Optional app settings
-- =========================
CREATE TABLE settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Example seed data
-- =========================
INSERT INTO users (username, password_hash, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'admin@example.com', 'admin'),
('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Warehouse Manager', 'manager@example.com', 'manager');

INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES
('Global Parts Ltd', 'John Smith', '+123456789', 'sales@globalparts.com', '10 Industrial Road'),
('Factory Materials Co', 'Mary Johnson', '+123456788', 'orders@factorymaterials.com', '25 Supply Lane');

INSERT INTO products (sku, name, category, unit, cost_price, selling_price, reorder_level, description) VALUES
('SKU-1001', 'Steel Rod', 'Raw Material', 'pcs', 15.00, 25.00, 50, 'High strength steel rod for fabrication'),
('SKU-1002', 'Bolt Set', 'Hardware', 'box', 5.50, 9.00, 100, 'Assorted industrial bolts'),
('SKU-1003', 'Safety Gloves', 'Protective Gear', 'pair', 8.00, 14.00, 30, 'Protective work gloves');

INSERT INTO inventory (product_id, stock_quantity, warehouse_location) VALUES
(1, 120, 'A-01'),
(2, 245, 'B-02'),
(3, 80, 'C-01');

-- =========================
-- Useful indexes
-- =========================
CREATE INDEX idx_products_category ON products(category);
CREATE INDEX idx_inventory_location ON inventory(warehouse_location);
CREATE INDEX idx_stock_movement_product ON stock_movements(product_id, created_at);
CREATE INDEX idx_sales_order_date ON sales_orders(order_date);
CREATE INDEX idx_purchase_order_date ON purchase_orders(order_date);

-- =========================
-- End of schema
-- =========================
