-- =====================================================================
-- LaVanDera: OPTIONAL sample/demo data for development and screenshots.
-- Run AFTER lavandera.sql.  Do NOT load this into a real deployment.
-- Every demo account below uses the password:  password
-- (same dev-only hash as the seeded admin)
-- =====================================================================
USE lavandera;

SET @pw = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- ---- Accounts (ids 2-8; admin is id 1) -------------------------------
INSERT INTO users (id, email, password_hash, role, status, status_note) VALUES
  (2, 'maria.santos@example.com',  @pw, 'customer', 'active',           NULL),
  (3, 'juan.delacruz@example.com', @pw, 'customer', 'active',           NULL),
  (4, 'rico.rider@example.com',    @pw, 'rider',    'active',           NULL),
  (5, 'ana.rider@example.com',     @pw, 'rider',    'pending_approval', NULL),
  (6, 'kapitolyo.wash@example.com',@pw, 'store',    'active',           NULL),
  (7, 'bubbles.laundry@example.com',@pw,'store',    'active',           NULL),
  (8, 'newstore.laundry@example.com',@pw,'store',   'pending_approval', NULL);

INSERT INTO customers (user_id, full_name, contact_no, address, latitude, longitude) VALUES
  (2, 'Maria Santos',  '09171234567', '12 Shaw Blvd, Kapitolyo, Pasig City',      14.5724000, 121.0565000),
  (3, 'Juan Dela Cruz','09181234567', '45 Sandoval Ave, Pinagbuhatan, Pasig City', 14.5590000, 121.0970000);

INSERT INTO riders (user_id, full_name, contact_no) VALUES
  (4, 'Rico Reyes', '09191234567'),
  (5, 'Ana Lopez',  '09201234567');

INSERT INTO stores (user_id, store_name, contact_no, description, address, latitude, longitude,
                    profile_image, banner_image, is_featured, accepting_orders) VALUES
  (6, 'Kapitolyo Wash Hub', '09211234567',
      'Neighborhood laundromat with same-day wash-and-fold and careful sorting of whites and colors.',
      '88 Brixton St, Pasig Palengke, Pasig City', 14.5764000, 121.0851000,
      'uploads/stores/kapitolyo_pfp.jpg', 'uploads/stores/kapitolyo_banner.jpg', 1, 1),
  (7, 'Bubbles Laundry Pinagbuhatan', '09221234567',
      'Affordable per-load laundry with free pickup and delivery within Pasig.',
      '7 Caruncho Ext, Pinagbuhatan, Pasig City', 14.5700000, 121.0900000,
      'uploads/stores/bubbles_pfp.jpg', 'uploads/stores/bubbles_banner.jpg', 1, 1),
  (8, 'Fresh Fold Express', '09231234567',
      'Newly set up store awaiting admin review.',
      '3 Ortigas Ave Ext, Pasig City', 14.5850000, 121.0750000, NULL, NULL, 0, 1);

INSERT INTO store_prices (store_id, bag_type_id, price) VALUES
  (6, 1,  50.00), (6, 2,  90.00), (6, 3, 130.00),
  (7, 1,  45.00), (7, 2,  80.00), (7, 3, 115.00),
  (8, 1,  55.00), (8, 2,  95.00), (8, 3, 135.00);

-- Bubbles Laundry offers free delivery
INSERT INTO store_benefits (store_id, benefit_id)
  SELECT 7, id FROM benefits WHERE code = 'FREE_DELIVERY';

-- Admin reviewed the two active stores and the active rider
UPDATE users SET reviewed_by = 1, reviewed_at = NOW() WHERE id IN (4, 6, 7);

-- ---- Orders ----------------------------------------------------------
-- Fee rule: 40 + 10/km beyond 2 km, rounded to whole pesos.
-- #1 COMPLETED   Maria -> Kapitolyo Wash Hub, Medium, Cash, 3.4 km (fee 54 each way)
-- #2 AT LAUNDROMAT Juan -> Bubbles (free delivery), Small, Cash, 1.8 km (rider still earns 40)
-- #3 WAITING FOR DRIVER Maria -> Kapitolyo Wash Hub, Large, Cash, 3.4 km
-- #4 PENDING STORE Juan -> Kapitolyo Wash Hub, Small, Cash, 2.9 km (fee 49 each way)
INSERT INTO orders (id, customer_id, store_id, bag_type_id, payment_method, payment_status,
                    store_fee, send_fee, return_fee, total_price, free_delivery, distance_km,
                    customer_address, customer_latitude, customer_longitude,
                    store_address, store_latitude, store_longitude,
                    status, created_at, completed_at) VALUES
  (1, 2, 6, 2, 'cash',  'paid',   90.00, 54.00, 54.00, 198.00, 0, 3.40,
      '12 Shaw Blvd, Kapitolyo, Pasig City', 14.5724000, 121.0565000,
      '88 Brixton St, Pasig Palengke, Pasig City', 14.5764000, 121.0851000,
      'completed', NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 3 DAY),
  (2, 3, 7, 1, 'cash',  'paid',   45.00,  0.00,  0.00,  45.00, 1, 1.80,
      '45 Sandoval Ave, Pinagbuhatan, Pasig City', 14.5590000, 121.0970000,
      '7 Caruncho Ext, Pinagbuhatan, Pasig City', 14.5700000, 121.0900000,
      'at_laundromat', NOW() - INTERVAL 1 DAY, NULL),
  (3, 2, 6, 3, 'cash',  'unpaid', 130.00, 54.00, 54.00, 238.00, 0, 3.40,
      '12 Shaw Blvd, Kapitolyo, Pasig City', 14.5724000, 121.0565000,
      '88 Brixton St, Pasig Palengke, Pasig City', 14.5764000, 121.0851000,
      'waiting_for_driver', NOW() - INTERVAL 2 HOUR, NULL),
  (4, 3, 6, 1, 'cash',  'unpaid',   50.00, 49.00, 49.00, 148.00, 0, 2.90,
      '45 Sandoval Ave, Pinagbuhatan, Pasig City', 14.5590000, 121.0970000,
      '88 Brixton St, Pasig Palengke, Pasig City', 14.5764000, 121.0851000,
      'pending_store', NOW() - INTERVAL 10 MINUTE, NULL);

INSERT INTO delivery_jobs (order_id, job_type, rider_id, status, pickup_address, dropoff_address,
                           distance_km, rider_pay, created_at, accepted_at, completed_at) VALUES
  (1, 'send',   4,    'completed', '12 Shaw Blvd, Kapitolyo, Pasig City', '88 Brixton St, Pasig Palengke, Pasig City',
      3.40, 54.00, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 5 DAY),
  (1, 'return', 4,    'completed', '88 Brixton St, Pasig Palengke, Pasig City', '12 Shaw Blvd, Kapitolyo, Pasig City',
      3.40, 54.00, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 3 DAY),
  (2, 'send',   4,    'completed', '45 Sandoval Ave, Pinagbuhatan, Pasig City', '7 Caruncho Ext, Pinagbuhatan, Pasig City',
      1.80, 40.00, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
  (3, 'send',   NULL, 'available', '12 Shaw Blvd, Kapitolyo, Pasig City', '88 Brixton St, Pasig Palengke, Pasig City',
      3.40, 54.00, NOW() - INTERVAL 1 HOUR, NULL, NULL);

INSERT INTO order_status_history (order_id, status, changed_by, note, created_at) VALUES
  (1, 'pending_store',            2, 'Order placed',               NOW() - INTERVAL 5 DAY),
  (1, 'waiting_for_driver',       6, 'Store confirmed order',      NOW() - INTERVAL 5 DAY),
  (1, 'enroute_to_store',         4, 'Rider accepted send job',    NOW() - INTERVAL 5 DAY),
  (1, 'at_laundromat',            6, 'Store confirmed delivery',   NOW() - INTERVAL 5 DAY),
  (1, 'waiting_for_return_driver',6, 'Store marked ready to return',NOW() - INTERVAL 4 DAY),
  (1, 'enroute_to_customer',      4, 'Rider accepted return job',  NOW() - INTERVAL 4 DAY),
  (1, 'completed',                2, 'Customer confirmed return',  NOW() - INTERVAL 3 DAY),
  (2, 'pending_store',            3, 'Order placed',               NOW() - INTERVAL 1 DAY),
  (2, 'waiting_for_driver',       7, 'Store confirmed order',      NOW() - INTERVAL 1 DAY),
  (2, 'enroute_to_store',         4, 'Rider accepted send job',    NOW() - INTERVAL 1 DAY),
  (2, 'at_laundromat',            7, 'Store confirmed delivery',   NOW() - INTERVAL 1 DAY),
  (3, 'pending_store',            2, 'Order placed',               NOW() - INTERVAL 2 HOUR),
  (3, 'waiting_for_driver',       6, 'Store confirmed order',      NOW() - INTERVAL 1 HOUR),
  (4, 'pending_store',            3, 'Order placed',               NOW() - INTERVAL 10 MINUTE);
