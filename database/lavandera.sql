-- =====================================================================
-- LaVanDera: On-Demand Laundry Aggregator and Logistics Platform
-- Database schema + required seed data
-- Target: MySQL 8.0+ / MariaDB 10.4+ (XAMPP-compatible), InnoDB, utf8mb4
-- Import:  mysql -u root -p < lavandera.sql   (or phpMyAdmin > Import)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS lavandera
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lavandera;

-- Drop in dependency order so the script can be re-run during development.
DROP VIEW  IF EXISTS v_available_jobs;
DROP VIEW  IF EXISTS v_admin_stats;
DROP VIEW  IF EXISTS v_store_listing;
DROP TABLE IF EXISTS delivery_jobs;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS store_benefits;
DROP TABLE IF EXISTS benefits;
DROP TABLE IF EXISTS store_prices;
DROP TABLE IF EXISTS bag_types;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS riders;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

-- ---------------------------------------------------------------------
-- 1. ACCOUNTS
-- One `users` row per login (all four roles sign in with email+password).
-- Role-specific details live in customers / riders / stores (1-to-1).
--
-- users.status drives what happens at sign-in:
--   active            -> allowed in (customers start here; riders/stores after approval)
--   setup_required    -> store signed up but has not finished the Store Setup page
--   pending_approval  -> rider just signed up, or store finished setup; show the wait page
--   rejected          -> admin rejected the account (reason in status_note)
--   suspended         -> admin disabled the account later
-- App rules on sign-up:  customer = active | rider = pending_approval | store = setup_required
-- When a store submits Store Setup: setup_required -> pending_approval.
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email         VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,            -- store a bcrypt/argon hash, never plain text
  role          ENUM('customer','rider','store','admin') NOT NULL,
  status        ENUM('active','setup_required','pending_approval','rejected','suspended')
                NOT NULL DEFAULT 'active',
  status_note   VARCHAR(255) NULL,                -- e.g. rejection reason shown to the user
  reviewed_by   INT UNSIGNED NULL,                -- admin who approved/rejected
  reviewed_at   DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  CONSTRAINT fk_users_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE customers (
  user_id    INT UNSIGNED NOT NULL,
  full_name  VARCHAR(120) NOT NULL,
  contact_no VARCHAR(20)  NOT NULL,
  address    VARCHAR(500) NOT NULL,
  latitude   DECIMAL(10,7) NULL,                  -- used to compute distance / send & return fees
  longitude  DECIMAL(10,7) NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE riders (
  user_id    INT UNSIGNED NOT NULL,
  full_name  VARCHAR(120) NOT NULL,
  contact_no VARCHAR(20)  NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_riders_user FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- A store row is created at sign-up with only name/contact; the Store Setup
-- page fills in the rest (description, address, images, prices).
CREATE TABLE stores (
  user_id          INT UNSIGNED NOT NULL,
  store_name       VARCHAR(150) NOT NULL,
  contact_no       VARCHAR(20)  NOT NULL,
  description      TEXT NULL,
  address          VARCHAR(500) NULL,
  latitude         DECIMAL(10,7) NULL,
  longitude        DECIMAL(10,7) NULL,
  profile_image    VARCHAR(255) NULL,             -- file path/URL (PFP)
  banner_image     VARCHAR(255) NULL,
  is_featured      TINYINT(1) NOT NULL DEFAULT 0, -- admin-controlled; shown on Customer Home
  accepting_orders TINYINT(1) NOT NULL DEFAULT 1, -- store can pause new orders
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_stores_name (store_name),
  CONSTRAINT fk_stores_user FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. STORE PRICING AND BENEFITS
-- ---------------------------------------------------------------------
CREATE TABLE bag_types (
  id        TINYINT UNSIGNED NOT NULL,
  code      VARCHAR(10) NOT NULL,                 -- 'small' | 'medium' | 'large'
  name      VARCHAR(30) NOT NULL,
  weight_kg SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bag_code (code)
) ENGINE=InnoDB;

-- Each store sets one price per bag type during Store Setup (editable later).
CREATE TABLE store_prices (
  store_id    INT UNSIGNED NOT NULL,
  bag_type_id TINYINT UNSIGNED NOT NULL,
  price       DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (store_id, bag_type_id),
  CONSTRAINT chk_store_price CHECK (price >= 0),
  CONSTRAINT fk_sp_store FOREIGN KEY (store_id) REFERENCES stores (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_bag   FOREIGN KEY (bag_type_id) REFERENCES bag_types (id)
) ENGINE=InnoDB;

-- Benefits a store can switch on (catalog + junction). Add rows to `benefits`
-- later for new perks without changing the schema.
CREATE TABLE benefits (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(30)  NOT NULL,
  name        VARCHAR(60)  NOT NULL,
  description VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_benefit_code (code)
) ENGINE=InnoDB;

CREATE TABLE store_benefits (
  store_id   INT UNSIGNED NOT NULL,
  benefit_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (store_id, benefit_id),
  CONSTRAINT fk_sb_store   FOREIGN KEY (store_id)   REFERENCES stores (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_sb_benefit FOREIGN KEY (benefit_id) REFERENCES benefits (id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. ORDERS
-- Status flow (matches the page descriptions):
--   pending_store            customer placed order; store must accept/reject
--   rejected                 store rejected it (reason in store_rejection_reason)
--   waiting_for_driver       store confirmed; SEND job is open on Rider Available Orders
--   enroute_to_store         a rider accepted the send job (carrying laundry [+ cash] to store)
--   at_laundromat            store pressed "Confirm Delivery"
--   waiting_for_return_driver store pressed "Return to Customer"; RETURN job is open
--   enroute_to_customer      a rider accepted the return job ("Enroute to you")
--   completed                customer pressed "Confirm Return"
--   cancelled                customer cancelled before a rider picked it up
--
-- Address/coordinates/prices are COPIED onto the order (snapshots) so history
-- stays correct if a customer or store later edits their profile or prices.
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id             INT UNSIGNED NOT NULL,
  store_id                INT UNSIGNED NOT NULL,
  bag_type_id             TINYINT UNSIGNED NOT NULL,
  payment_method          ENUM('cash','gcash') NOT NULL,
  payment_status          ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  gcash_reference         VARCHAR(100) NULL,
  -- fees shown on the Confirm Order page
  store_fee               DECIMAL(10,2) NOT NULL,   -- store's price for the chosen bag
  send_fee                DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- customer -> store, charged to customer
  return_fee              DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- store -> customer, charged to customer
  total_price             DECIMAL(10,2) NOT NULL,
  free_delivery           TINYINT(1) NOT NULL DEFAULT 0,  -- store had "Free delivery" at order time
  distance_km             DECIMAL(6,2) NOT NULL,          -- customer <-> store
  -- snapshots
  customer_address        VARCHAR(500) NOT NULL,
  customer_latitude       DECIMAL(10,7) NULL,
  customer_longitude      DECIMAL(10,7) NULL,
  store_address           VARCHAR(500) NOT NULL,
  store_latitude          DECIMAL(10,7) NULL,
  store_longitude         DECIMAL(10,7) NULL,
  notes                   VARCHAR(500) NULL,
  status                  ENUM('pending_store','rejected','waiting_for_driver','enroute_to_store',
                               'at_laundromat','waiting_for_return_driver','enroute_to_customer',
                               'completed','cancelled') NOT NULL DEFAULT 'pending_store',
  store_rejection_reason  VARCHAR(255) NULL,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at            DATETIME NULL,
  PRIMARY KEY (id),
  CONSTRAINT chk_order_total CHECK (total_price = store_fee + send_fee + return_fee),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (user_id),
  CONSTRAINT fk_orders_store    FOREIGN KEY (store_id)    REFERENCES stores (user_id),
  CONSTRAINT fk_orders_bag      FOREIGN KEY (bag_type_id) REFERENCES bag_types (id),
  KEY idx_orders_customer (customer_id, created_at),
  KEY idx_orders_store_status (store_id, status),
  KEY idx_orders_status (status)
) ENGINE=InnoDB;

-- Timeline of every status change (powers "order tracking" and admin stats).
CREATE TABLE order_status_history (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NOT NULL,
  status     ENUM('pending_store','rejected','waiting_for_driver','enroute_to_store',
                  'at_laundromat','waiting_for_return_driver','enroute_to_customer',
                  'completed','cancelled') NOT NULL,
  changed_by INT UNSIGNED NULL,                   -- user who triggered the change
  note       VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_osh_order (order_id, created_at),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id)   REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_osh_user  FOREIGN KEY (changed_by) REFERENCES users (id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. DELIVERY JOBS (what riders see)
-- Each order has up to two jobs:
--   'send'   customer -> store   (created when the store CONFIRMS the order)
--            completed when the store presses "Confirm Delivery"
--   'return' store -> customer   (created when the store presses "Return to Customer")
--            completed when the customer presses "Confirm Return"
-- Rider Available Orders  = jobs with status 'available'
-- Rider Current Orders    = jobs with rider_id = me AND status 'accepted'
-- To avoid two riders grabbing the same job, accept it atomically:
--   UPDATE delivery_jobs SET rider_id=?, status='accepted', accepted_at=NOW()
--   WHERE id=? AND status='available';      -- then check affected rows = 1
-- ---------------------------------------------------------------------
CREATE TABLE delivery_jobs (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id        INT UNSIGNED NOT NULL,
  job_type        ENUM('send','return') NOT NULL,
  rider_id        INT UNSIGNED NULL,
  status          ENUM('available','accepted','completed','cancelled') NOT NULL DEFAULT 'available',
  pickup_address  VARCHAR(500) NOT NULL,
  dropoff_address VARCHAR(500) NOT NULL,
  distance_km     DECIMAL(6,2) NOT NULL,
  rider_pay       DECIMAL(8,2) NOT NULL,          -- paid to the rider, based on distance
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at     DATETIME NULL,
  completed_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_job_order_type (order_id, job_type),
  KEY idx_jobs_board (status, job_type),
  KEY idx_jobs_rider (rider_id, status),
  CONSTRAINT fk_jobs_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_jobs_rider FOREIGN KEY (rider_id) REFERENCES riders (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. SETTINGS (admin-editable values; keeps fee rules out of app code)
-- Rider pay / delivery fee rule used by the app:
--   fee = base_fee + per_km_fee * max(0, distance_km - base_distance_km), rounded to whole pesos
-- ---------------------------------------------------------------------
CREATE TABLE settings (
  setting_key   VARCHAR(50)  NOT NULL,
  setting_value VARCHAR(100) NOT NULL,
  description   VARCHAR(255) NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. VIEWS
-- ---------------------------------------------------------------------
-- Customer Stores / Home / View Store pages: approved stores with their 3 prices.
CREATE VIEW v_store_listing AS
SELECT
  s.user_id AS store_id,
  s.store_name, s.description, s.address, s.latitude, s.longitude,
  s.contact_no, s.profile_image, s.banner_image, s.is_featured, s.accepting_orders,
  MAX(CASE WHEN b.code = 'small'  THEN sp.price END) AS price_small,
  MAX(CASE WHEN b.code = 'medium' THEN sp.price END) AS price_medium,
  MAX(CASE WHEN b.code = 'large'  THEN sp.price END) AS price_large,
  EXISTS (SELECT 1 FROM store_benefits sb JOIN benefits bf ON bf.id = sb.benefit_id
          WHERE sb.store_id = s.user_id AND bf.code = 'FREE_DELIVERY') AS has_free_delivery
FROM stores s
JOIN users u ON u.id = s.user_id AND u.status = 'active'
LEFT JOIN store_prices sp ON sp.store_id = s.user_id
LEFT JOIN bag_types b ON b.id = sp.bag_type_id
GROUP BY s.user_id, s.store_name, s.description, s.address, s.latitude, s.longitude,
         s.contact_no, s.profile_image, s.banner_image, s.is_featured, s.accepting_orders;

-- Rider Available Orders page.
CREATE VIEW v_available_jobs AS
SELECT j.id AS job_id, j.order_id, j.job_type, j.pickup_address, j.dropoff_address,
       j.distance_km, j.rider_pay, j.created_at, s.store_name
FROM delivery_jobs j
JOIN orders o ON o.id = j.order_id
JOIN stores s ON s.user_id = o.store_id
WHERE j.status = 'available';

-- Admin Dashboard: one row of headline numbers.
CREATE VIEW v_admin_stats AS
SELECT
  (SELECT COUNT(*) FROM users WHERE role = 'customer')                              AS total_customers,
  (SELECT COUNT(*) FROM users WHERE role = 'rider' AND status = 'active')           AS active_riders,
  (SELECT COUNT(*) FROM users WHERE role = 'rider' AND status = 'pending_approval') AS pending_riders,
  (SELECT COUNT(*) FROM users WHERE role = 'store' AND status = 'active')           AS active_stores,
  (SELECT COUNT(*) FROM users WHERE role = 'store' AND status = 'pending_approval') AS pending_stores,
  (SELECT COUNT(*) FROM orders)                                                     AS total_orders,
  (SELECT COUNT(*) FROM orders
     WHERE status NOT IN ('completed','cancelled','rejected'))                      AS in_progress_orders,
  (SELECT COUNT(*) FROM orders WHERE status = 'completed')                          AS completed_orders,
  (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status = 'completed')     AS completed_order_value;

-- ---------------------------------------------------------------------
-- 7. REQUIRED SEED DATA
-- ---------------------------------------------------------------------
INSERT INTO bag_types (id, code, name, weight_kg) VALUES
  (1, 'small',  'Small Bag',  5),
  (2, 'medium', 'Medium Bag', 10),
  (3, 'large',  'Large Bag',  15);

INSERT INTO benefits (code, name, description) VALUES
  ('FREE_DELIVERY',   'Free delivery',          'waives the send and return rider fees for customers'),
  ('ECO_PACKAGING',   'Eco-friendly packaging', 'returns laundry in eco-friendly packaging'),
  ('EXPRESS_SERVICE', 'Express service',        'offers faster turnaround on orders');

-- Delivery fee defaults (from the proposal: PHP 40 covers the first 2 km, +PHP 10 per extra km).
INSERT INTO settings (setting_key, setting_value, description) VALUES
  ('base_delivery_fee',   '40.00', 'Delivery/rider fee covering the first base_distance_km (PHP)'),
  ('base_distance_km',    '2',     'Distance covered by the base fee (km)'),
  ('per_km_fee',         '10.00', 'Extra fee per km beyond base_distance_km (PHP)');

-- Default admin account.  DEV ONLY: the password is  password  -- change it before any real use.
-- (Hash is the widely used bcrypt hash of "password". Replace it with your own, e.g. in PHP:
--  password_hash('your-password', PASSWORD_DEFAULT) or in Node: bcrypt.hashSync('your-password', 10).)
INSERT INTO users (email, password_hash, role, status) VALUES
  ('admin@lavandera.test',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'admin', 'active');
