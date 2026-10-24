<?php
/**
 * Sign-up and sign-in for EVERY role (customer, rider, store).
 * Follows the contract in src/README.md:
 *   - passwords use password_hash() / password_verify()
 *   - new accounts start as: customer = active | rider = pending_approval | store = setup_required
 *   - after a correct password we call login_user() (from src/auth.php) and tell the page where to go
 */
require_once __DIR__ . '/guards.php';

/** Same idea as sign_out_pending_store(): the rider is NOT signed in, but the wait page remembers who they are. */
function sign_out_pending_rider($riderId)
{
    $_SESSION = [];                              // forget any previous login
    session_regenerate_id(true);                 // new session id (blocks session fixation)
    $_SESSION['pending_rider_id'] = (int) $riderId;
}

/**
 * Where the browser should go next. Paths are relative to public/auth/ (where the login page lives).
 * Change '.html' to '.php' here as soon as you convert that page.
 */
function account_destination($role, $status)
{
    if ($role === 'admin') {
        return '../admin/Admin_Dashboard_Page.html';
    }
    if ($role === 'customer') {
        return '../customer/Customer_Home_Page.php';
    }
    if ($role === 'rider') {
        return $status === 'active' ? '../rider/lavandera_rider_dashboard.php' : rider_pending_url();
    }
    // store
    if ($status === 'setup_required') {
        return '../store/Store_Setup_Page.php';
    }
    if ($status === 'active') {
        return '../store/Store_Dashboard_Page.php';
    }
    return pending_store_url();                  // pending_approval / rejected / suspended
}

/**
 * Create an account. $in = ['name','email','password','contact', and 'address' for customers].
 * Returns ['role', 'status', 'redirect'].  Throws UserError (shown to the user) for bad input.
 */
function account_signup($role, array $in)
{
    if (!in_array($role, ['customer', 'rider', 'store'], true)) {
        throw new UserError('Choose an account type.');
    }

    // ---- 1. read + clean the input (never trust the browser) ----
    $name     = trim(isset($in['name']) ? (string) $in['name'] : '');
    $email    = strtolower(trim(isset($in['email']) ? (string) $in['email'] : ''));
    $password = isset($in['password']) ? (string) $in['password'] : '';
    $contact  = trim(isset($in['contact']) ? (string) $in['contact'] : '');
    $address  = trim(isset($in['address']) ? (string) $in['address'] : '');

    // ---- 2. validate ----
    if ($name === '' || mb_strlen($name) > ($role === 'store' ? 80 : 120)) {
        throw new UserError($role === 'store' ? 'Enter a store name (up to 80 characters).' : 'Enter your full name.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        throw new UserError('Enter a valid email address.');
    }
    if (strlen($password) < 8 || strlen($password) > 72) {   // 72 = the most bcrypt can use
        throw new UserError('Your password must be 8 to 72 characters long.');
    }
    if (!preg_match('/^[0-9+\-()\s]{7,20}$/', $contact)) {
        throw new UserError('Enter a valid contact number.');
    }
    if ($role === 'customer' && (mb_strlen($address) < 5 || mb_strlen($address) > 500)) {
        throw new UserError('Enter your pickup / delivery address.');
    }

    // ---- 3. save: users row + the role-specific row, all-or-nothing (a transaction) ----
    $status = ['customer' => 'active', 'rider' => 'pending_approval', 'store' => 'setup_required'][$role];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO users (email, password_hash, role, status) VALUES (?, ?, ?, ?)')
            ->execute([$email, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
        $id = (int) $pdo->lastInsertId();

        if ($role === 'customer') {
            $pdo->prepare('INSERT INTO customers (user_id, full_name, contact_no, address) VALUES (?, ?, ?, ?)')
                ->execute([$id, $name, $contact, $address]);
        } elseif ($role === 'rider') {
            $pdo->prepare('INSERT INTO riders (user_id, full_name, contact_no) VALUES (?, ?, ?)')
                ->execute([$id, $name, $contact]);
        } else {
            $pdo->prepare('INSERT INTO stores (user_id, store_name, contact_no) VALUES (?, ?, ?)')
                ->execute([$id, $name, $contact]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {         // 23000 = "duplicate value in a UNIQUE column"
            if (stripos($e->getMessage(), 'uq_stores_name') !== false) {
                throw new UserError('That store name is already taken. Please choose another.');
            }
            throw new UserError('That email is already registered. Try signing in instead.');
        }
        throw $e;                                // anything else is a real bug: api_run() logs it
    }

    // ---- 4. session: who is "signed in" right after sign-up? ----
    if ($role === 'rider') {
        sign_out_pending_rider($id);             // riders wait for admin approval, so NOT signed in
    } else {
        login_user(['id' => $id, 'role' => $role]);
    }
    $redirect = account_destination($role, $status);
    if ($role === 'customer') {
        // A brand-new customer has an address but no MAP PIN yet, and without the pin we cannot work out delivery fees.
        // So the profile page (with the map) is the best first stop.
        $redirect = '../customer/Customer_Profile_Page.php';
    }
    return ['role' => $role, 'status' => $status, 'redirect' => $redirect];
}

/** Check email + password and start the session. Returns ['redirect' => '...']. */
function account_login($email, $password)
{
    $email = strtolower(trim((string) $email));
    $st = db()->prepare('SELECT id, password_hash, role, status FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    // Always run password_verify, even for unknown emails, so the response time does not reveal
    // which emails exist. (The fake hash below is just the bcrypt hash of the word "password".)
    $hash = $u ? $u['password_hash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $ok = password_verify((string) $password, $hash);
    if (!$u || !$ok) {
        throw new UserError('Incorrect email or password.');   // same message for both cases on purpose
    }

    $id = (int) $u['id'];
    $role = $u['role'];
    $status = $u['status'];

    // Stores and riders that are not approved are NOT signed in; the wait page reads their status.
    if ($role === 'store' && in_array($status, ['pending_approval', 'rejected', 'suspended'], true)) {
        sign_out_pending_store($id);
        return ['redirect' => account_destination($role, $status)];
    }
    if ($role === 'rider' && $status !== 'active') {
        sign_out_pending_rider($id);
        return ['redirect' => account_destination($role, $status)];
    }
    if ($status === 'suspended' || $status === 'rejected') {    // customers / admins
        throw new UserError('This account is not allowed to sign in. Please contact LaVanDera support.');
    }

    login_user(['id' => $id, 'role' => $role]);
    return ['redirect' => account_destination($role, $status)];
}
