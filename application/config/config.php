<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Base Site URL
|--------------------------------------------------------------------------
| Use a fixed base URL in production. Pulled from .env if present.
*/
$config['base_url'] = rtrim(getenv('APP_URL') ?: 'https://app.eduassistance.co.za', '/') . '/';

/*
|--------------------------------------------------------------------------
| Index File
|--------------------------------------------------------------------------
*/
$config['index_page'] = '';

/*
|--------------------------------------------------------------------------
| URI PROTOCOL
|--------------------------------------------------------------------------
*/
$config['uri_protocol']	= 'AUTO';

/*
|--------------------------------------------------------------------------
| URL suffix
|--------------------------------------------------------------------------
*/
$config['url_suffix'] = '';

/*
|--------------------------------------------------------------------------
| Default Language
|--------------------------------------------------------------------------
*/
$config['language']	= 'english';

/*
|--------------------------------------------------------------------------
| Default Character Set
|--------------------------------------------------------------------------
*/
$config['charset'] = 'UTF-8';

/*
|--------------------------------------------------------------------------
| Enable/Disable System Hooks
|--------------------------------------------------------------------------
*/
$config['enable_hooks'] = TRUE;

/*
|--------------------------------------------------------------------------
| Class Extension Prefix
|--------------------------------------------------------------------------
*/
$config['subclass_prefix'] = 'MY_';

/*
|--------------------------------------------------------------------------
| Composer auto-loading
|--------------------------------------------------------------------------
*/
$config['composer_autoload'] = FALSE;

/*
|--------------------------------------------------------------------------
| Allowed URL Characters
|--------------------------------------------------------------------------
*/
$config['permitted_uri_chars'] = 'a-z 0-9~%.:_\-';

/*
|--------------------------------------------------------------------------
| Enable Query Strings
|--------------------------------------------------------------------------
*/
$config['enable_query_strings'] = FALSE;
$config['controller_trigger']  = 'c';
$config['function_trigger']    = 'm';
$config['directory_trigger']   = 'd';

/*
|--------------------------------------------------------------------------
| Allow $_GET array (deprecated)
|--------------------------------------------------------------------------
*/
$config['allow_get_array'] = TRUE;

/*
|--------------------------------------------------------------------------
| Error Logging Threshold
|--------------------------------------------------------------------------
| Keep 4 while deploying; switch to 1 in steady-state prod if logs get big.
*/
$config['log_threshold'] = 4;

/*
|--------------------------------------------------------------------------
| Error Logging Directory Path
|--------------------------------------------------------------------------
*/
$config['log_path'] = '';

/*
|--------------------------------------------------------------------------
| Log File Extension
|--------------------------------------------------------------------------
*/
$config['log_file_extension'] = '';

/*
|--------------------------------------------------------------------------
| Log File Permissions
|--------------------------------------------------------------------------
*/
$config['log_file_permissions'] = 0644;

/*
|--------------------------------------------------------------------------
| Date Format for Logs
|--------------------------------------------------------------------------
*/
$config['log_date_format'] = 'Y-m-d H:i:s';

/*
|--------------------------------------------------------------------------
| Error Views Directory Path
|--------------------------------------------------------------------------
*/
$config['error_views_path'] = '';

/*
|--------------------------------------------------------------------------
| Cache Directory Path
|--------------------------------------------------------------------------
*/
$config['cache_path'] = '';

/*
|--------------------------------------------------------------------------
| Cache Include Query String
|--------------------------------------------------------------------------
*/
$config['cache_query_string'] = FALSE;

/*
|--------------------------------------------------------------------------
| Encryption Key
|--------------------------------------------------------------------------
| Pull from .env; fall back to existing key if not set.
*/
$config['encryption_key'] = getenv('APP_KEY') ?: 'd7f8e0c1b2a3f4e5c6d7e8f9a0b1c2d3f4e5a6b7c8d9e0f1';

/*
|--------------------------------------------------------------------------
| Session Variables
|--------------------------------------------------------------------------
| Use database sessions. Ensure table `ci_sessions` exists.
*/
$config['sess_driver']             = 'database';
$config['sess_cookie_name']        = 'rm_session';
$config['sess_expiration']         = 7200;
$config['sess_save_path']          = 'ci_sessions'; // table name
$config['sess_match_ip']           = FALSE;
$config['sess_time_to_update']     = 300;
$config['sess_regenerate_destroy'] = FALSE;

/*
|--------------------------------------------------------------------------
| Cookie Related Variables
|--------------------------------------------------------------------------
| Secure/HttpOnly cookies for HTTPS subdomain.
*/
$config['cookie_prefix']   = '';
$config['cookie_domain']   = getenv('COOKIE_DOMAIN') ?: 'app.eduassistance.co.za';
$config['cookie_path']     = '/';
$config['cookie_secure']   = TRUE;
$config['cookie_httponly'] = TRUE;

/*
|--------------------------------------------------------------------------
| Standardize newlines (deprecated)
|--------------------------------------------------------------------------
*/
$config['standardize_newlines'] = FALSE;

/*
|--------------------------------------------------------------------------
| Global XSS Filtering (deprecated)
|--------------------------------------------------------------------------
*/
$config['global_xss_filtering'] = TRUE;

/*
|--------------------------------------------------------------------------
| Cross Site Request Forgery
|--------------------------------------------------------------------------
*/
$config['csrf_protection']  = TRUE;
$config['csrf_token_name']  = 'school_csrf_name';
$config['csrf_cookie_name'] = 'school_cookie_name';
$config['csrf_expire']      = 7200;
$config['csrf_regenerate']  = FALSE;
$config['csrf_exclude_uris'] = array();

if (
    $config['csrf_protection'] === TRUE
    && isset($_SERVER['REQUEST_URI'])
    && (
        strpos($_SERVER['REQUEST_URI'],'feespayment/') !== FALSE
        || strpos($_SERVER['REQUEST_URI'],'admissionpayment/') !== FALSE
        || strpos($_SERVER['REQUEST_URI'],'onlineexam_payment/') !== FALSE
        || strpos($_SERVER['REQUEST_URI'],'subscription/') !== FALSE
        || strpos($_SERVER['REQUEST_URI'],'saas_payment/') !== FALSE
    )
){
    $config['csrf_protection'] = FALSE;
}

/*
|--------------------------------------------------------------------------
| Output Compression
|--------------------------------------------------------------------------
*/
$config['compress_output'] = FALSE;

/*
|--------------------------------------------------------------------------
| Master Time Reference
|--------------------------------------------------------------------------
*/
$config['time_reference'] = 'local';

/*
|--------------------------------------------------------------------------
| Rewrite PHP Short Tags
|--------------------------------------------------------------------------
*/
$config['rewrite_short_tags'] = FALSE;

/*
|--------------------------------------------------------------------------
| Reverse Proxy IPs
|--------------------------------------------------------------------------
*/
$config['proxy_ips'] = '';

/*
|--------------------------------------------------------------------------
| Installer Flags / Product Name (project-specific)
|--------------------------------------------------------------------------
*/
$config['installed']     = TRUE;
$config['product_name']  = 'ramom_school_v6.9';
