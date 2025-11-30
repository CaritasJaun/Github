<?php
/**
 * CodeIgniter Front Controller (index.php)
 * Minimal .env loader + production-safe defaults
 */

/* ───────────────── .env loader ───────────────── */
$__envFile = __DIR__ . '/.env';
if (is_file($__envFile) && is_readable($__envFile)) {
    foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $__line) {
        if ($__line === '' || $__line[0] === '#' || strpos($__line, '=') === false) continue;
        list($__k, $__v) = array_map('trim', explode('=', $__line, 2));
        $__v = trim($__v, "\"'");
        $_ENV[$__k] = $_SERVER[$__k] = $__v;
        if (function_exists('putenv')) putenv($__k.'='.$__v);
    }
}
date_default_timezone_set(getenv('TIMEZONE') ?: 'Africa/Johannesburg');

/* ──────────────── environment ──────────────── */
$__appEnv = getenv('APP_ENV');
define('ENVIRONMENT', $__appEnv ? $__appEnv : (isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'production'));

/* resource limits */
ini_set('max_execution_time', 300);
ini_set('memory_limit', '128M');
ini_set('post_max_size', '128M');
ini_set('upload_max_filesize', '128M');

/* ──────────────── error reporting ──────────────── */
switch (ENVIRONMENT) {
    case 'development':
        error_reporting(-1);
        ini_set('display_errors', 1);
        break;
    case 'testing':
    case 'production':
    default:
        ini_set('display_errors', 0);
        if (version_compare(PHP_VERSION, '5.3', '>=')) {
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        } else {
            error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);
        }
        break;
}

/* system and app paths */
$system_path       = 'system';
$application_folder= 'application';
$view_folder       = '';

/* resolve system path */
if (defined('STDIN')) chdir(dirname(__FILE__));
if (($_temp = realpath($system_path)) !== false) {
    $system_path = $_temp . DIRECTORY_SEPARATOR;
} else {
    $system_path = strtr(rtrim($system_path, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
}
if (!is_dir($system_path)) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'Your system folder path does not appear to be set correctly.';
    exit(3);
}

/* main path constants */
define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', $system_path);
define('FCPATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('SYSDIR', basename(BASEPATH));

/* application path */
if (is_dir($application_folder)) {
    if (($_temp = realpath($application_folder)) !== false) $application_folder = $_temp;
    else $application_folder = strtr(rtrim($application_folder, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
} elseif (is_dir(BASEPATH . $application_folder . DIRECTORY_SEPARATOR)) {
    $application_folder = BASEPATH . strtr(trim($application_folder, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
} else {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'Your application folder path does not appear to be set correctly.';
    exit(3);
}
define('APPPATH', $application_folder . DIRECTORY_SEPARATOR);

/* view path */
if (!isset($view_folder[0]) && is_dir(APPPATH.'views'.DIRECTORY_SEPARATOR)) {
    $view_folder = APPPATH.'views';
} elseif (is_dir($view_folder)) {
    if (($_temp = realpath($view_folder)) !== false) $view_folder = $_temp;
    else $view_folder = strtr(rtrim($view_folder, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
} elseif (is_dir(APPPATH.$view_folder.DIRECTORY_SEPARATOR)) {
    $view_folder = APPPATH . strtr(trim($view_folder, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
} else {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'Your view folder path does not appear to be set correctly.';
    exit(3);
}
define('VIEWPATH', $view_folder . DIRECTORY_SEPARATOR);

/* boot CodeIgniter */
require_once BASEPATH.'core/CodeIgniter.php';
