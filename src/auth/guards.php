<?php
/**
 * Guards for CUSTOMER and RIDER pages / APIs.
 * (The store guards already live in src/auth.php, written by your groupmate.)
 *
 * A "guard" is a function you call at the very top of a page. If the visitor is not
 * allowed in, it redirects (pages) or answers with a JSON error (APIs) and STOPS the script,
 * so nothing below it ever runs.
 */

/** Link to the rider wait page, written relative to public/customer, public/rider and public/auth. */
function rider_pending_url()
{
    return '../auth/Rider_Pending_Approval_Page.php';
}

/** Customer PAGES: only a signed-in, active customer gets through. */
function guard_customer_page()
{
    $u = current_user();    // re-reads the user from the database on every request
    if (!$u || $u['role'] !== 'customer' || $u['status'] !== 'active') {
        redirect(config('login_url'));
    }
    return $u;
}

/** Rider PAGES: must be a rider AND approved. Everyone else is sent to the wait page, which explains why. */
function guard_rider_page()
{
    $u = current_user();
    if (!$u || $u['role'] !== 'rider') {
        redirect(config('login_url'));
    }
    if ($u['status'] !== 'active') {            // pending_approval / rejected / suspended
        redirect(rider_pending_url());
    }
    return $u;
}

/** Customer API endpoints: answers 401/403 as JSON instead of redirecting. */
function require_customer_api()
{
    $u = current_user();
    if (!$u) {
        json_fail('Please sign in.', 401);
    }
    if ($u['role'] !== 'customer') {
        json_fail('This action is only for customer accounts.', 403);
    }
    if ($u['status'] !== 'active') {
        json_fail('Your account is not allowed to do this right now.', 403);
    }
    return $u;
}

/** Rider API endpoints. */
function require_rider_api()
{
    $u = current_user();
    if (!$u) {
        json_fail('Please sign in.', 401);
    }
    if ($u['role'] !== 'rider') {
        json_fail('This action is only for rider accounts.', 403);
    }
    if ($u['status'] !== 'active') {
        json_fail('Your rider account is not approved yet.', 403);
    }
    return $u;
}
