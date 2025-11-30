<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| ROUTES – PACE routes first (to beat any wildcards), then everything else.
| Keep the single catch-all LAST.
| -------------------------------------------------------------------------
*/

/* ---------- PACE block (FIRST) ---------- */
$route['pace']                              = 'pace/assign';
$route['pace/mark_completed']               = 'pace/mark_completed';
$route['pace/update_status']                = 'pace/update_status';
$route['pace/update_assign_status']         = 'pace/update_assign_status';

$route['pace/order']                        = 'pace/order';
$route['pace/order/save']                   = 'pace/order_save';
$route['pace/order/update-status']          = 'pace/update_order_status';

$route['pace/orders']                       = 'pace/orders_all';
$route['pace/invoices']                     = 'pace/invoices';
$route['pace/invoice/(:num)']               = 'pace/invoice_view/$1';
$route['pace/invoice/mark']                 = 'pace/ajax_mark_invoice';

$route['pace/order-batches']                = 'pace/order_batches';
$route['pace/orders-batches']               = 'pace/orders_batches';
$route['pace/orders-batches/(:num)']        = 'pace/orders_batch_view/$1';
$route['pace/orders-batches/mark']          = 'pace/orders_batch_mark';
$route['pace/orders-batches/update-status'] = 'pace/batch_update_status';

$route['pace/assign']                       = 'pace/assign';
$route['pace/assign/list']                  = 'pace/ajax_list_assignments';
$route['pace/assign/single']                = 'pace/ajax_assign_single';
$route['pace/assign/subject']               = 'pace/ajax_assign_to_child';

/* FIX: the view calls /pace/load_assign_grid but controller method is ajax_list_assignments */
$route['pace/load_assign_grid']             = 'pace/load_assign_grid';

$route['pace/batch_assign']                 = 'pace/batch_assign';

$route['pace/record-score']                 = 'pace/record_score';
$route['pace/record-score/save']            = 'pace/record_score_save';

$route['pace/assign-subjects']              = 'pace/assign_subjects';
$route['pace/assign_subjects']              = 'pace/assign_subjects';       // underscore alias
$route['pace/assign-subjects/save']         = 'pace/assign_subjects_save';
$route['pace/assign_subjects/save']         = 'pace/assign_subjects_save';  // underscore alias

/* Backward-compat aliases (old links) */
$route['pace/assign_paces']                 = 'pace/assign';
$route['pace/assign-paces']                 = 'pace/assign';
$route['pace/order_paces']                  = 'pace/order';
$route['pace/order-paces']                  = 'pace/order';



/*
| -------------------------------------------------------------------------
| EVERYTHING ELSE (unchanged order)
| -------------------------------------------------------------------------
*/

/* ---- Addon autoloader (unchanged) ---- */
spl_autoload_register(function($className) {
    if (substr($className, -6) == "_Addon") {
        $file = APPPATH . 'core/' . $className . '.php';
        if (file_exists($file) && is_file($file)) {
            @include_once($file);
        }
    }
});

/* ---- Include additional route files (unchanged) ---- */
$routes_path = APPPATH . 'config/my_routes/';
if (is_dir($routes_path)) {
    $routes = scandir($routes_path);
    foreach ($routes as $r_file) {
        if ($r_file === '.' || $r_file === '..' || $r_file === 'index.html') continue;
        $route_path = $routes_path . $r_file;
        if (file_exists($route_path)) {
            @include_once $route_path;
        }
    }
}

/* ---- Strip any global catch-alls added above (safety) ---- */
if (isset($route) && is_array($route)) {
    // generic
    if (isset($route['(:any)'])) unset($route['(:any)']);
    if (isset($route['(:any)/(:any)'])) unset($route['(:any)/(:any)']);
    // don’t let anyone shadow PACE
    if (isset($route['pace/(:any)'])) unset($route['pace/(:any)']);
    // CRITICAL: remove student wildcards that force list page
    if (isset($route['student/(:any)'])) unset($route['student/(:any)']);
    if (isset($route['student/(.+)'])) unset($route['student/(.+)']);
    if (isset($route['student/.+'])) unset($route['student/.+']);
    if (isset($route['student/(:any)/(:any)'])) unset($route['student/(:any)/(:any)']);
}

/* ---------- Front-site per-branch routes (unchanged) ---------- */
$route['(:any)/authentication']      = 'authentication/index/$1';
$route['(:any)/forgot']              = 'authentication/forgot/$1';
$route['(:any)/teachers']            = 'home/teachers';
$route['(:any)/events']              = 'home/events';
$route['(:any)/news']                = 'home/news/';
$route['(:any)/about']               = 'home/about';
$route['(:any)/faq']                 = 'home/faq';
$route['(:any)/admission']           = 'home/admission';
$route['(:any)/gallery']             = 'home/gallery';
$route['(:any)/contact']             = 'home/contact';
$route['(:any)/admit_card']          = 'home/admit_card';
$route['(:any)/exam_results']        = 'home/exam_results';
$route['(:any)/certificates']        = 'home/certificates';
$route['(:any)/page/(:any)']         = 'home/page/$2';
$route['(:any)/gallery_view/(:any)'] = 'home/gallery_view/$2';
$route['(:any)/event_view/(:num)']   = 'home/event_view/$2';
$route['(:any)/news_view/(:any)']    = 'home/news_view/$2';

/* ---------- Core app areas (unchanged) ---------- */
$route['dashboard']                = 'dashboard/index';
$route['branch']                   = 'branch/index';
$route['attachments']              = 'attachments/index';
$route['homework']                 = 'homework/index';
$route['onlineexam']               = 'onlineexam/index';
$route['hostels']                  = 'hostels/index';
$route['event']                    = 'event/index';
$route['accounting']               = 'accounting/index';
$route['school_settings']          = 'school_settings/index';
$route['role']                     = 'role/index';
$route['sessions']                 = 'sessions/index';
$route['translations']             = 'translations/index';
$route['cron_api']                 = 'cron_api/index';
$route['modules']                  = 'modules/index';
$route['system_student_field']     = 'system_student_field/index';
$route['custom_field']             = 'custom_field/index';
$route['backup']                   = 'backup/index';
$route['advance_salary']           = 'advance_salary/index';
$route['system_update']            = 'system_update/index';
$route['certificate']              = 'certificate/index';
$route['payroll']                  = 'payroll/index';
$route['leave']                    = 'leave/index';
$route['award']                    = 'award/index';
$route['classes']                  = 'classes/index';
$route['student_promotion']        = 'student_promotion/index';
$route['live_class']               = 'live_class/index';
$route['exam']                     = 'exam/index';
$route['sections']                 = 'sections/index';

$route['employee']                 = 'employee/index';
$route['employee/(:any)']          = 'employee/$1';
$route['designation']              = 'designation/index';
$route['designation/(:any)']       = 'designation/$1';
$route['department']               = 'department/index';
$route['department/(:any)']        = 'department/$1';

/* (keep these as they were) */
$route['student/profile/(:num)'] = 'student/view/$1';
$route['student/view/(:num)']    = 'student/profile/$1';
$route['student/profile/(:num)'] = 'student/profile/$1';
$route['student/profile']        = 'student/profile';

/* ---------- Notification / Events (unchanged + explicit JSON endpoint) ---------- */
$route['notification']                  = 'notification/index';
$route['notification/open/(:num)']      = 'notification/open/$1';
$route['notification/clar']             = 'notification/clear';
$route['notification/mark_read/(:num)'] = 'notification/mark_read/$1';

$route['event/get_events_list'] = 'event/get_events_list';
$route['get_events_list']       = 'event/get_events_list';

$route['subjectpace']                   = 'subjectpace/index';
$route['subjectpace/save']              = 'subjectpace/save';
$route['subjectpace/delete/(:num)']     = 'subjectpace/delete/$1';

$route['monitor_goal_check']                       = 'monitor_goal_check/index';
$route['monitor_goal_check/set_term_dates']        = 'monitor_goal_check/set_term_dates';
$route['monitor_goal_check/save_term_date']        = 'monitor_goal_check/save_term_date';
$route['monitor_goal_check/update_term_end_date']  = 'monitor_goal_check/update_term_end_date';

$route['spc/save_reading_program']      = 'spc/save_reading_program';
$route['weekly_traits']                 = 'weekly_traits/index';
$route['weekly_traits/save']            = 'weekly_traits/save';

$route['assign-pace']                   = 'assign_pace/index';

$route['event/calendar']                = 'event/calendar';
$route['event/feed']                    = 'event/feed';
$route['event/save_quick']              = 'event/save_quick';

$route['projection/get']                = 'projection/get';
$route['projection/save']               = 'projection/save';
$route['profile']                       = 'profile/index';

/* ---------- Profile & password routes (EXPLICIT) ---------- */
$route['profile']            = 'profile/index';
$route['profile/password']   = 'profile/password';

$route['principal/profile']  = 'profile/index';
$route['principal/password'] = 'profile/password';

$route['staff/profile']      = 'profile/index';
$route['staff/password']     = 'profile/password';

$route['employee/profile']   = 'profile/index';
$route['employee/password']  = 'profile/password';

$route['user/profile']       = 'profile/index';
$route['user/password']      = 'profile/password';

/* ---------- Auth / default / translate ---------- */
$route['authentication']       = 'authentication/index';
$route['install']              = 'install/index';
$route['404_override']         = 'errors';
$route['translate_uri_dashes'] = FALSE;

if (!empty($saas_default) && $saas_default == true) {
    $route['default_controller'] = 'saas_website/index';
} else {
    $route['default_controller'] = 'home';
}

/* --- SPC hard routes --- */
$route['spc']       = 'spc/index';
$route['spc/index'] = 'spc/index';

$route['pace'] = 'pace/order';
$route['pace/(:any)'] = 'pace/$1';
$route['pace/ping']    = 'pace/ping';

// --- Password route aliases (fix 404) ---
$route['employee/password']        = 'profile/password';
$route['employee/change_password'] = 'profile/password';

$route['__maildiag'] = 'diag_mail/send';
$route['get_events_list'] = 'ajax/get_events_list';

// --- PACE pre-invoice orders (review + check) ---
$route['pace/orders_batches_pre']                 = 'pace/orders_batches_pre';
$route['pace/orders_batches']                     = 'pace/orders_batches';
$route['pace/orders_batch_edit/(:num)']           = 'pace/orders_batch_edit/$1';
$route['pace/orders_batch_update']                = 'pace/orders_batch_update';
$route['pace/orders_batch_check/(:num)']          = 'pace/orders_batch_check/$1';
$route['pace/orders_batch_uncheck/(:num)']        = 'pace/orders_batch_uncheck/$1';
$route['pace/orders_batch_create_invoice/(:num)'] = 'pace/orders_batch_create_invoice/$1';
$route['pace/invoice_view/(:num)']                = 'pace/invoice_view/$1';

$route['pace/orders_mark_paid/(:num)'] = 'pace/orders_mark_paid/$1';
$route['pace/invoice_mark_paid/(:num)']   = 'pace/invoice_mark_paid/$1';
$route['pace/invoice_mark_issued/(:num)'] = 'pace/invoice_mark_issued/$1';
$route['pace/orders/check-bill/(:num)'] = 'pace_orders/check_and_bill/$1';
$route['pace/check_orders/(:num)'] = 'pace/check_orders/$1';
$route['pace/orders_batches/(:any)/(:num)'] = 'pace/orders_batches?group=$1&student_id=$2';

$route['ai/assist'] = 'ai/assist';


/* ---------- ONE catch-all: keep ABSOLUTELY LAST ---------- */
$route['(:any)'] = 'home/index/$1';
