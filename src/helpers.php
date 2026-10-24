<?php
/** Small shared helpers (output escaping, JSON responses, CSRF, image uploads). */

/** A problem the user can fix (shown as-is in the UI). Anything else becomes a generic error. */
class UserError extends RuntimeException
{
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** JSON that is safe to print inside a <script> tag. */
function js_json($value)
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function now()
{
    return date('Y-m-d H:i:s');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/* ---------- JSON API ---------- */

function json_out($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_fail($message, $status = 400)
{
    json_out(['ok' => false, 'error' => $message], $status);
}

function json_input()
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

/** Run an API handler: user errors -> 422, anything unexpected -> logged + generic 500. */
function api_run($handler)
{
    ini_set('display_errors', '0');          // never corrupt the JSON with PHP warnings
    try {
        $handler();
    } catch (UserError $e) {
        json_fail($e->getMessage(), 422);
    } catch (Throwable $e) {
        error_log('[LaVanDera] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        json_fail('Something went wrong on the server. Please try again.', 500);
    }
}

/* ---------- CSRF ---------- */

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check()
{
    $sent = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) $sent)) {
        json_fail('Your session expired. Reload the page and try again.', 419);
    }
}

/* ---------- Image uploads ---------- */

/**
 * Save an uploaded image from $_FILES[$field]. Returns the path to store in the
 * database (relative to public/, e.g. "uploads/stores/store6_pfp_ab12cd.jpg"),
 * or null when no file was chosen. Throws UserError for bad files.
 */
function save_uploaded_image($field, $prefix)
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new UserError('Each image must be ' . (config('upload_max_bytes') / 1048576) . ' MB or smaller.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new UserError('The image upload failed. Please try again.');
    }
    if ($file['size'] > config('upload_max_bytes')) {
        throw new UserError('Each image must be ' . (config('upload_max_bytes') / 1048576) . ' MB or smaller.');
    }
    $info  = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        throw new UserError('Choose a JPG, PNG, GIF or WebP image.');
    }
    $dir = config('upload_dir');
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Cannot create upload folder ' . $dir);
    }
    $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Cannot save uploaded file to ' . $dir);
    }
    return config('upload_url_path') . '/' . $name;
}

/** Delete a previously saved upload (only files inside the uploads folder). */
function delete_upload($relativePath)
{
    if (!$relativePath) {
        return;
    }
    $prefix = config('upload_url_path') . '/';
    if (strpos($relativePath, $prefix) !== 0) {
        return;
    }
    $path = config('upload_dir') . '/' . basename($relativePath);
    if (is_file($path)) {
        @unlink($path);
    }
}
