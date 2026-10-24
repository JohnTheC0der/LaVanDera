<?php
/** Store profile, prices, benefits (Store Setup + Store Dashboard pages). */

const STORE_BAG_CODES = ['small', 'medium', 'large'];

function store_load($storeId)
{
    $st = db()->prepare(
        "SELECT u.id, u.email, u.status, u.status_note,
                s.store_name, s.contact_no, s.description, s.address,
                s.latitude, s.longitude, s.profile_image, s.banner_image, s.accepting_orders
         FROM users u JOIN stores s ON s.user_id = u.id
         WHERE u.id = ? AND u.role = 'store'"
    );
    $st->execute([$storeId]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    return [
        'id'          => (int) $r['id'],
        'email'       => $r['email'],
        'status'      => $r['status'],
        'statusNote'  => $r['status_note'],
        'name'        => $r['store_name'],
        'contact'     => $r['contact_no'],
        'address'     => $r['address'],
        'description' => $r['description'],
        'latitude'    => $r['latitude'] === null ? null : (float) $r['latitude'],
        'longitude'   => $r['longitude'] === null ? null : (float) $r['longitude'],
        'image'       => $r['profile_image'],      // path relative to public/
        'banner'      => $r['banner_image'],
        'accepting'   => (bool) $r['accepting_orders'],
        'prices'      => store_prices($storeId),
        'benefits'    => store_benefit_codes($storeId),
    ];
}

function store_prices($storeId)
{
    $st = db()->prepare(
        'SELECT b.code, sp.price FROM store_prices sp JOIN bag_types b ON b.id = sp.bag_type_id WHERE sp.store_id = ?'
    );
    $st->execute([$storeId]);
    $out = [];
    foreach ($st as $row) {
        $out[$row['code']] = (float) $row['price'];
    }
    return $out;
}

function store_benefit_codes($storeId)
{
    $st = db()->prepare(
        'SELECT bf.code FROM store_benefits sb JOIN benefits bf ON bf.id = sb.benefit_id WHERE sb.store_id = ? ORDER BY bf.id'
    );
    $st->execute([$storeId]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

function store_benefit_catalog()
{
    return db()->query('SELECT code, name, description FROM benefits ORDER BY id')->fetchAll();
}

function store_stats($storeId)
{
    $st = db()->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
                COALESCE(SUM(CASE WHEN status IN ('pending_store','waiting_for_driver','enroute_to_store',
                         'at_laundromat','waiting_for_return_driver','enroute_to_customer') THEN 1 ELSE 0 END), 0) AS attention
         FROM orders WHERE store_id = ?"
    );
    $st->execute([$storeId]);
    $r = $st->fetch();
    return ['total' => (int) $r['total'], 'completed' => (int) $r['completed'], 'attention' => (int) $r['attention']];
}

/**
 * Validate the Setup / Dashboard form (POST fields). Returns clean values or throws UserError.
 * $withExtras = true on the dashboard (accepting flag + benefits are editable there).
 */
function store_validate_input(array $in, $withExtras)
{
    $name    = trim(isset($in['store_name']) ? $in['store_name'] : '');
    $contact = trim(isset($in['contact']) ? $in['contact'] : '');
    $address = trim(isset($in['address']) ? $in['address'] : '');
    $desc    = trim(isset($in['description']) ? $in['description'] : '');

    if ($name === '' || mb_strlen($name) > 80) {
        throw new UserError('Enter a store name (up to 80 characters).');
    }
    if ($contact === '' || mb_strlen($contact) > 20 || !preg_match('/^[0-9+\-()\s]{7,20}$/', $contact)) {
        throw new UserError('Enter a valid contact number (digits, +, -, spaces; up to 20 characters).');
    }
    if ($address === '' || mb_strlen($address) > 180) {
        throw new UserError('Enter your store address (up to 180 characters).');
    }
    if (mb_strlen($desc) > 500) {
        throw new UserError('The description must be 500 characters or fewer.');
    }

    // map pin (used to work out the distance-based delivery fees)
    $lat = isset($in['latitude']) ? $in['latitude'] : '';
    $lng = isset($in['longitude']) ? $in['longitude'] : '';
    if (!is_numeric($lat) || !is_numeric($lng)
        || (float) $lat < 4 || (float) $lat > 22 || (float) $lng < 115 || (float) $lng > 128) {
        throw new UserError('Pin your store location on the map (inside the Philippines) so delivery fees can be calculated.');
    }

    $prices = [];
    foreach (STORE_BAG_CODES as $code) {
        $raw = isset($in['price_' . $code]) ? $in['price_' . $code] : '';
        if (!is_numeric($raw) || (float) $raw <= 0 || (float) $raw > 10000) {
            throw new UserError('Enter a price above zero (up to 10,000) for every bag size.');
        }
        $prices[$code] = round((float) $raw, 2);
    }

    $out = ['name' => $name, 'contact' => $contact, 'address' => $address,
            'description' => $desc, 'prices' => $prices, 'accepting' => 1, 'benefits' => null,
            'latitude' => round((float) $lat, 7), 'longitude' => round((float) $lng, 7)];

    if ($withExtras) {
        $out['accepting'] = (isset($in['accepting']) && $in['accepting'] === '1') ? 1 : 0;
        $valid = array_column(store_benefit_catalog(), 'code');
        $picked = isset($in['benefits']) && is_array($in['benefits']) ? $in['benefits'] : [];
        $out['benefits'] = array_values(array_intersect($valid, $picked));   // ignores unknown codes
    }
    return $out;
}

/**
 * Save store details. $images = ['image' => path|null, 'banner' => path|null] (new uploads only).
 * $submitForReview = true on Store Setup: moves the account to 'pending_approval'.
 */
function store_save($storeId, array $v, array $images, $submitForReview)
{
    $pdo = db();
    $old = $pdo->prepare('SELECT profile_image, banner_image FROM stores WHERE user_id = ?');
    $old->execute([$storeId]);
    $oldImgs = $old->fetch();

    $pdo->beginTransaction();
    try {
        $sql = 'UPDATE stores SET store_name = ?, contact_no = ?, address = ?, description = ?, accepting_orders = ?, latitude = ?, longitude = ?';
        $params = [$v['name'], $v['contact'], $v['address'], $v['description'], $v['accepting'], $v['latitude'], $v['longitude']];
        if (!empty($images['image'])) {
            $sql .= ', profile_image = ?';
            $params[] = $images['image'];
        }
        if (!empty($images['banner'])) {
            $sql .= ', banner_image = ?';
            $params[] = $images['banner'];
        }
        $sql .= ' WHERE user_id = ?';
        $params[] = $storeId;
        $pdo->prepare($sql)->execute($params);

        // prices: one row per bag size
        $bagIds = [];
        foreach ($pdo->query('SELECT id, code FROM bag_types') as $b) {
            $bagIds[$b['code']] = (int) $b['id'];
        }
        $exists = $pdo->prepare('SELECT 1 FROM store_prices WHERE store_id = ? AND bag_type_id = ?');
        $upd    = $pdo->prepare('UPDATE store_prices SET price = ? WHERE store_id = ? AND bag_type_id = ?');
        $ins    = $pdo->prepare('INSERT INTO store_prices (store_id, bag_type_id, price) VALUES (?, ?, ?)');
        foreach ($v['prices'] as $code => $price) {
            $exists->execute([$storeId, $bagIds[$code]]);
            if ($exists->fetch()) {
                $upd->execute([$price, $storeId, $bagIds[$code]]);
            } else {
                $ins->execute([$storeId, $bagIds[$code], $price]);
            }
        }

        // benefits (only when the form included them)
        if ($v['benefits'] !== null) {
            $pdo->prepare('DELETE FROM store_benefits WHERE store_id = ?')->execute([$storeId]);
            if ($v['benefits']) {
                $insB = $pdo->prepare('INSERT INTO store_benefits (store_id, benefit_id) VALUES (?, (SELECT id FROM benefits WHERE code = ?))');
                foreach ($v['benefits'] as $code) {
                    $insB->execute([$storeId, $code]);
                }
            }
        }

        if ($submitForReview) {
            $pdo->prepare("UPDATE users SET status = 'pending_approval', status_note = NULL WHERE id = ? AND status = 'setup_required'")
                ->execute([$storeId]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000' && (stripos($e->getMessage(), 'store_name') !== false || stripos($e->getMessage(), 'uq_stores_name') !== false)) {
            throw new UserError('That store name is already taken. Please choose another.');
        }
        throw $e;
    }

    // the old image files are no longer referenced
    if (!empty($images['image']) && $oldImgs) {
        delete_upload($oldImgs['profile_image']);
    }
    if (!empty($images['banner']) && $oldImgs) {
        delete_upload($oldImgs['banner_image']);
    }
}

/** Save the optional profile/banner uploads from a form submit (new files only). */
function store_receive_images($storeId)
{
    $saved = ['image' => null, 'banner' => null];
    try {
        $saved['image']  = save_uploaded_image('image', 'store' . $storeId . '_pfp');
        $saved['banner'] = save_uploaded_image('banner', 'store' . $storeId . '_banner');
    } catch (Throwable $e) {
        delete_upload($saved['image']);
        throw $e;
    }
    return $saved;
}
