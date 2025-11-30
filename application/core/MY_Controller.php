<?php defined('BASEPATH') or exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Always have CI Upload ready (fixes $this->upload null in models/controllers)
        $this->load->library('upload');

        // Installer check
        if ($this->config->item('installed') == false) {
            redirect(site_url('install'));
        }

        // -------- Global Config (guarded) --------
        $get_config = $this->db->get_where('global_settings', ['id' => 1])->row_array();
        $get_config = is_array($get_config) ? $get_config : [];

        // Defaults used when DB row is missing / incomplete
        $global_defaults = [
            'cache_store'       => 0,
            'timezone'          => 'Africa/Johannesburg',
            'image_extension'   => 'jpg,jpeg,png,gif',
            'image_size'        => 2048, // KB
            'file_extension'    => 'pdf,doc,docx,xls,xlsx,csv,txt',
            'file_size'         => 4096, // KB
            'currency'          => 'ZAR',
            'currency_symbol'   => 'R',
            'currency_formats'  => '0,0.00',
            'symbol_position'   => 'left',
        ];
        $get_config = array_merge($global_defaults, $get_config);

        $this->data['global_config'] = $get_config;
        $this->load->vars(['global_config' => $get_config]);

        // -------- Cache Control --------
        $this->output->set_header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        if ((int)$get_config['cache_store'] === 0) {
            $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        } else {
            $this->output->set_header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
        }
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

        // -------- Branch Overlay --------
        $branchID = $this->application_model->get_branch_id();
        if (!empty($branchID)) {
            $branch = $this->db->select('currency_formats,symbol_position,symbol,currency,timezone')
                               ->where('id', $branchID)
                               ->get('branch')->row();
            if ($branch) {
                if (!empty($branch->currency))          $get_config['currency']          = $branch->currency;
                if (!empty($branch->symbol))            $get_config['currency_symbol']    = $branch->symbol;
                if (!empty($branch->currency_formats))  $get_config['currency_formats']   = $branch->currency_formats;
                if (!empty($branch->symbol_position))   $get_config['symbol_position']    = $branch->symbol_position;
                if (!empty($branch->timezone))          $get_config['timezone']           = $branch->timezone;
            }
        }

        // -------- Theme Config (guarded) --------
        $theme_config = $this->db->get_where('theme_settings', ['id' => 1])->row_array();
        $theme_config = is_array($theme_config) ? $theme_config : [];
        $theme_defaults = [
            'dark_skin'        => 'false',
            'sidebar'          => 'light',
            'sidebar_collapse' => 'false',
        ];
        $this->data['theme_config'] = array_merge($theme_defaults, $theme_config);

        // -------- Timezone --------
        date_default_timezone_set(!empty($get_config['timezone']) ? $get_config['timezone'] : 'Africa/Johannesburg');
    }

    // ---------- Utility: public/self-service pages whitelist ----------
    protected function __is_public_page($controller, $method)
    {
        $controller = strtolower($controller);
        $method     = strtolower($method);

        // Controllers/methods that any logged-in user should reach
        $public = [
            'profile'   => ['index', 'password', 'username_change', '*'],
            'dashboard' => ['*'], // optional keep
        ];

        if (!isset($public[$controller])) return false;
        return in_array('*', $public[$controller], true) || in_array($method, $public[$controller], true);
    }

    public function get_payment_config()
    {
        $branchID = $this->application_model->get_branch_id();
        $this->db->where('branch_id', $branchID);
        $this->db->select('*')->from('payment_config');
        return $this->db->get()->row_array();
    }

    public function getBranchDetails()
    {
        $branchID = $this->application_model->get_branch_id();
        $this->db->select('*');
        $this->db->where('id', $branchID);
        $this->db->from('branch');
        $r = $this->db->get()->row_array();
        if (empty($r)) {
            return ['stu_generate' => '', 'grd_generate' => ''];
        } else {
            return $r;
        }
    }

    public function photoHandleUpload($str, $fields)
    {
        $cfg = $this->data['global_config'];
        $allowedExts   = array_map('trim', array_map('strtolower', explode(',', (string)$cfg['image_extension'])));
        $allowedSizeKB = (float)$cfg['image_size'];
        $allowedSize   = 1024 * $allowedSizeKB;

        if (isset($_FILES[$fields]) && !empty($_FILES[$fields]['name'])) {
            $file_size = (float)$_FILES[$fields]['size'];
            $file_name = (string)$_FILES[$fields]['name'];
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);

            if (@filesize($_FILES[$fields]['tmp_name']) !== false) {
                if (!in_array(strtolower($extension), $allowedExts, true)) {
                    $this->form_validation->set_message('photoHandleUpload', translate('this_file_type_is_not_allowed'));
                    return false;
                }
                if ($file_size > $allowedSize) {
                    $this->form_validation->set_message('photoHandleUpload', translate('file_size_shoud_be_less_than') . " {$allowedSizeKB} KB.");
                    return false;
                }
            } else {
                $this->form_validation->set_message('photoHandleUpload', translate('error_reading_the_file'));
                return false;
            }
            return true;
        }
    }

    public function fileHandleUpload($str, $fields)
    {
        $cfg = $this->data['global_config'];
        $allowedExts   = array_map('trim', array_map('strtolower', explode(',', (string)$cfg['file_extension'])));
        $allowedSizeKB = (float)$cfg['file_size'];
        $allowedSize   = 1024 * $allowedSizeKB;

        if (isset($_FILES[$fields]) && !empty($_FILES[$fields]['name'])) {
            $file_size = (float)$_FILES[$fields]['size'];
            $file_name = (string)$_FILES[$fields]['name'];
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);

            if (@filesize($_FILES[$fields]['tmp_name']) !== false) {
                if (!in_array(strtolower($extension), $allowedExts, true)) {
                    $this->form_validation->set_message('fileHandleUpload', translate('this_file_type_is_not_allowed'));
                    return false;
                }
                if ($file_size > $allowedSize) {
                    $this->form_validation->set_message('fileHandleUpload', translate('file_size_shoud_be_less_than') . " {$allowedSizeKB} KB.");
                    return false;
                }
            } else {
                $this->form_validation->set_message('fileHandleUpload', translate('error_reading_the_file'));
                return false;
            }
            return true;
        }
    }
}

class Admin_Controller extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Must be logged in
        if (!is_loggedin()) {
            $this->session->set_userdata('redirect_url', current_url());
            redirect(base_url('authentication'), 'refresh');
        }

        // Controller / method (lowercased)
        $controller = strtolower($this->router->fetch_class());
        $method     = strtolower($this->router->fetch_method());

        // Always allow Profile for any logged-in user
        if ($controller === 'profile') {
            return;
        }

        // === Allow PACE module for these roles; skip get_permission gate ===
        if ($controller === 'pace') {
            // CI blocks underscored methods; our diag is 'ping' (no underscore)
            if ($method === 'ping') {
                return;
            }
            $role = (int)$this->session->userdata('loggedin_role_id');
            if ($role <= 0 && function_exists('get_loggedin_role_id')) {
                $role = (int) get_loggedin_role_id();
            }
            // SA(1), Admin(2), Teacher(3), Office(4), Principal(6), Reception(8)
            if (in_array($role, [1,2,3,4,6,8], true)) {
                return; // let Pace controller handle finer RBAC itself
            }
            show_404();
            return;
        }

        // Default permission gate with whitelist
        if (!$this->__is_public_page($controller, $method)) {
            if (function_exists('get_permission') && !get_permission($controller, 'is_view')) {
                show_404();
                return;
            }
        }
    }
}

class User_Controller extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_student_loggedin() && !is_parent_loggedin()) {
            $this->session->set_userdata('redirect_url', current_url());
            redirect(base_url('authentication'), 'refresh');
        }
    }
}

class Authentication_Controller extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('authentication_model');
    }
}

class Frontend_Controller extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('home_model');
        $branchID    = $this->home_model->getDefaultBranch();
        $cms_setting = $this->db->get_where('front_cms_setting', ['branch_id' => $branchID])->row_array();
        $cms_setting = is_array($cms_setting) ? $cms_setting : ['cms_active' => 0];
        if (empty($cms_setting['cms_active'])) {
            redirect(site_url('authentication'));
        }
        $this->data['cms_setting'] = $cms_setting;
    }
}
