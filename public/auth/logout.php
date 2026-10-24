<?php
/** Sign out (any role) and return to the sign-in page. */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
logout_user();
redirect(config('login_url'));
