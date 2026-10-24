<?php
/**
 * LaVanDera configuration. Defaults match a fresh XAMPP install.
 * Change values here only; nothing else in the project hard-codes them.
 */
// Page files in public/auth/. When those pages are converted to PHP, change the extension here (one place).
$loginFile   = 'Log_In_Page.html';
$pendingFile = 'Store_Pending_Approval_Page.php';

return [
    // Database (XAMPP default: user "root", empty password)
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'lavandera',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
        'timezone' => '+08:00',      // keep MySQL and PHP on Philippine time
    ],
    'timezone' => 'Asia/Manila',

    // Page links. *_file = file name inside public/auth/ (for links between auth pages);
    // *_url = the same page as linked from pages inside public/store/ (and API redirects).
    'login_file'         => $loginFile,
    'login_url'          => '../auth/' . $loginFile,
    'pending_store_file' => $pendingFile,
    'pending_store_url'  => '../auth/' . $pendingFile,

    // Store images: saved under public/uploads/stores/, path stored in the database.
    'upload_max_bytes' => 2 * 1024 * 1024,
    'upload_dir'       => dirname(__DIR__, 2) . '/public/uploads/stores',
    'upload_url_path'  => 'uploads/stores',      // relative to public/
];
