<?php
/**
 * Include this at the top of EVERY PHP page and API endpoint:
 *   require __DIR__ . '/../../src/bootstrap.php';     (adjust the ../ count)
 * It loads the config, starts the shared login session and loads the helpers.
 * The login / sign-up code must use the same bootstrap so everyone shares one session.
 */
define('LV_ROOT', dirname(__DIR__));

function config($key = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config/config.php';
    }
    return $key === null ? $cfg : (isset($cfg[$key]) ? $cfg[$key] : null);
}

date_default_timezone_set(config('timezone'));

if (session_status() === PHP_SESSION_NONE) {
    session_name('lavandera_sid');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/fees.php';
