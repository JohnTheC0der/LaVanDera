<?php
/**
 * Login session helpers shared by every role.
 *
 * CONTRACT FOR THE LOGIN / SIGN-UP CODE:
 *   After the password is verified (or a new account is created), call
 *       login_user(['id' => $userId, 'role' => $role]);
 *   then redirect. That is all the other pages need. Pages always re-read the
 *   user's status from the database, so admin approvals/rejections apply instantly.
 *
 * Redirect rules after sign-in (users.status):
 *   store: setup_required -> Store_Setup_Page.php | pending_approval -> pending page
 *          active -> Store_Dashboard_Page.php     | rejected/suspended -> show a message
 *   rider: pending_approval -> wait page | active -> rider dashboard
 */

function login_user(array $user)
{
    session_regenerate_id(true);
    // drop leftovers from an earlier "waiting for approval" visit, so another account's status is never shown to this user
    unset($_SESSION['pending_store_id'], $_SESSION['pending_rider_id']);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role']    = $user['role'];
}

function logout_user()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** The signed-in user, freshly read from the database (null if signed out). `name` = store / customer / rider name. */
function current_user()
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $st = db()->prepare(
        "SELECT u.id, u.email, u.role, u.status, u.status_note,
                COALESCE(s.store_name, c.full_name, r.full_name, 'LaVanDera Admin') AS name
         FROM users u
         LEFT JOIN stores s ON s.user_id = u.id
         LEFT JOIN customers c ON c.user_id = u.id
         LEFT JOIN riders r ON r.user_id = u.id
         WHERE u.id = ?"
    );
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    if (!$u) {
        logout_user();
        return null;
    }
    $u['id'] = (int) $u['id'];
    return $u;
}

function pending_store_url()
{
    return config('pending_store_url');
}

/**
 * A store that is waiting for approval is NOT signed in, but the pending-approval page still
 * needs to know which application to show. This signs the store out and remembers only its id
 * in the (fresh) session. The sign-in code should call remember_pending_store($id) too when a
 * correct email+password belongs to a store whose status is 'pending_approval'.
 */
function sign_out_pending_store($storeId)
{
    $_SESSION = [];
    session_regenerate_id(true);
    remember_pending_store($storeId);
}

function remember_pending_store($storeId)
{
    $_SESSION['pending_store_id'] = (int) $storeId;
}

/**
 * Gate for store PAGES (call at the very top, before any output).
 *   'portal' = Dashboard / Orders: only approved (active) stores get in.
 *   'setup'  = Store Setup page: only stores that still need to finish setup.
 * Returns the user row; rejected/suspended users are returned so the page can show a message.
 */
function guard_store_page($mode)
{
    $u = current_user();
    if (!$u || $u['role'] !== 'store') {
        redirect(config('login_url'));
    }
    $status = $u['status'];
    if ($mode === 'setup') {
        if ($status === 'active') {
            redirect('Store_Dashboard_Page.php');
        }
        if ($status === 'pending_approval') {
            redirect(pending_store_url());
        }
    } else {
        if ($status === 'setup_required') {
            redirect('Store_Setup_Page.php');
        }
        if ($status === 'pending_approval') {
            redirect(pending_store_url());
        }
    }
    return $u;
}

/** Gate for store API endpoints. $allowed = list of users.status values that may call it. */
function require_store_api(array $allowed = ['active'])
{
    $u = current_user();
    if (!$u) {
        json_fail('Please sign in.', 401);
    }
    if ($u['role'] !== 'store') {
        json_fail('This action is only for store accounts.', 403);
    }
    if (!in_array($u['status'], $allowed, true)) {
        json_fail('Your store account is not allowed to do this right now.', 403);
    }
    return $u;
}

/** Gate for admin PAGES (call at the very top, before any output). Returns the admin user row. */
function guard_admin_page()
{
    $u = current_user();
    if (!$u || $u['role'] !== 'admin' || $u['status'] !== 'active') {
        redirect(config('login_url'));
    }
    return $u;
}

/** Gate for admin API endpoints. */
function require_admin_api()
{
    $u = current_user();
    if (!$u) {
        json_fail('Please sign in.', 401);
    }
    if ($u['role'] !== 'admin' || $u['status'] !== 'active') {
        json_fail('This action is only for administrators.', 403);
    }
    return $u;
}
