<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Authentication_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('user_agent');
    }

    // checking login credential
    public function login_credential($username, $password)
    {
        $this->db->select('*');
        $this->db->from('login_credential');
        $this->db->where('username', $username);
        $this->db->limit(1);
        $query = $this->db->get();
        if ($query->num_rows() == 1) {
            $verify_password = $this->app_lib->verify_password($password, $query->row()->password);
            if ($verify_password) {
                return $query->row();
            }
        }
        return false;
    }

    /**
     * Map role -> user table for basic details.
     * Parent = 9, Student = 7, Staff (incl. Principal=6) = everything else.
     */
    public function getUserNameByRoleID($roleID, $userID = '')
    {
        if ((int)$roleID === 9) {
            // Parent
            $sql = "SELECT `name`,`email`,`mobileno`,`photo`,`branch_id`
                    FROM `parent`
                    WHERE `id` = " . $this->db->escape($userID);
            return $this->db->query($sql)->row_array();
        } elseif ((int)$roleID === 7) {
            // Student
            $sql = "SELECT `student`.`id`, `mobileno`,
                           CONCAT_WS(' ',`student`.`first_name`, `student`.`last_name`) as `name`,
                           `student`.`email`, `student`.`photo`, `enroll`.`branch_id`
                    FROM `enroll`
                    INNER JOIN `student` ON `student`.`id` = `enroll`.`student_id`
                    WHERE `student`.`id` = " . $this->db->escape($userID) . " LIMIT 1";
            return $this->db->query($sql)->row_array();
        } else {
            // Staff (includes Principal=6)
            $sql = "SELECT `name`,`mobileno`,`email`,`photo`,`branch_id`
                    FROM `staff`
                    WHERE `id` = " . $this->db->escape($userID);
            return $this->db->query($sql)->row_array();
        }
    }

    // password forgotten
    public function lose_password($username)
    {
        if (!empty($username)) {
            $this->db->select('*');
            $this->db->from('login_credential');
            $this->db->where('username', $username);
            $this->db->limit(1);
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                $login_credential = $query->row();
                $getUser = $this->getUserNameByRoleID($login_credential->role, $login_credential->user_id);
                $key = hash('sha512', $login_credential->role . $login_credential->username . app_generate_hash());
                $query = $this->db->get_where('reset_password', array('login_credential_id' => $login_credential->id));
                if ($query->num_rows() > 0) {
                    $this->db->where('login_credential_id', $login_credential->id);
                    $this->db->delete('reset_password');
                }
                $arrayReset = array(
                    'key' => $key,
                    'login_credential_id' => $login_credential->id,
                    'username' => $login_credential->username,
                );
                $this->db->insert('reset_password', $arrayReset);
                // send email for forgot password
                $this->load->model('email_model');
                $arrayData = array(
                    'role' => $login_credential->role,
                    'branch_id' => $getUser['branch_id'],
                    'username' => $login_credential->username,
                    'name' => $getUser['name'],
                    'reset_url' => base_url('authentication/pwreset?key=' . $key),
                    'email' => $getUser['email'],
                );
                $this->email_model->sentForgotPassword($arrayData);
                return true;
            }
        }
        return false;
    }

    public function urlaliasToBranch($url_alias)
    {
        $saasExisting = $this->app_lib->isExistingAddon('saas');
        if ($saasExisting && $this->db->table_exists("custom_domain")) {
            $getDomain = $this->getCurrentDomain();
            if (!empty($getDomain)) {
                return $getDomain->school_id;
            }
        }

        $get = $this->db->select('branch_id')->where('url_alias', $url_alias)->get('front_cms_setting')->row_array();
        if (empty($url_alias) || empty($get)) {
            return null;
        } else {
            return $get['branch_id'];
        }
    }

    public function getSegment($id = '')
    {
        $segment = $this->uri->segment($id);
        if (empty($segment)) {
            return '';
        } else {
            return $segment . '/';
        }
    }

    public function getCurrentDomain()
    {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $url = rtrim($url, '/');
        $domain = parse_url($url, PHP_URL_HOST);
        if (substr($domain, 0, 4) == 'www.') {
            $domain = str_replace('www.', '', $domain);
        }
        $getDomain = $this->db->select('school_id')->get_where('custom_domain', array('status' => 1, 'url' => $domain))->row();
        return $getDomain;
    }

    public function getSchoolDeatls($url_alias = '')
    {
        $branchID = '';
        $saasExisting = $this->app_lib->isExistingAddon('saas');
        if ($saasExisting && $this->db->table_exists("custom_domain")) {
            $getDomain = $this->getCurrentDomain();
            if (!empty($getDomain)) {
                $branchID = $getDomain->school_id;
            }
        }

        if (!empty($url_alias) || !empty($branchID)) {
            $this->db->select('fs.facebook_url,fs.twitter_url,fs.linkedin_url,fs.youtube_url,branch.address,branch.school_name');
            $this->db->from('front_cms_setting as fs');
            $this->db->join('branch', 'branch.id = fs.branch_id', 'left');
            if (empty($branchID)) {
                $this->db->where('fs.url_alias', $url_alias);
            } else {
                $this->db->where('fs.branch_id', $branchID);
            }
            $get = $this->db->get()->row();
            if (empty($get)) {
                return '';
            } else {
                return $get;
            }
        } else {
            return '';
        }
    }

    /**
     * Internal helper: map role id to loggedin_type
     * parent=9, student=7, else staff (incl. principal=6)
     */
    private function mapRoleToType($roleId)
    {
        $roleId = (int)$roleId;
        if ($roleId === 9) return 'parent';
        if ($roleId === 7) return 'student';
        return 'staff';
    }

    function sessionSet($sessionData = [])
    {
        // Ensure correct loggedin_type regardless of caller
        if (!isset($sessionData['loggedin_role_id'])) {
            // nothing we can do; set as staff by default
            $sessionData['loggedin_type'] = 'staff';
        } else {
            $computedType = $this->mapRoleToType($sessionData['loggedin_role_id']);
            if (empty($sessionData['loggedin_type']) ||
                !in_array($sessionData['loggedin_type'], ['staff','student','parent'], true)) {
                $sessionData['loggedin_type'] = $computedType;
            } else {
                // if caller sent a value but it's wrong for the role, correct it
                if ($sessionData['loggedin_type'] !== $computedType) {
                    $sessionData['loggedin_type'] = $computedType;
                }
            }
        }

        $this->session->set_userdata($sessionData);
        $this->db->update('login_credential', array('last_login' => date('Y-m-d H:i:s')), array('id' => $sessionData['loggedin_id']));
        // login Log details save in DB here
        $this->loginLog($sessionData['loggedin_userid'], $sessionData['loggedin_role_id'], $sessionData['loggedin_branch']);
    }

    function loginLog($userID = 0, $role = 0, $branchID = '')
    {
        if ($this->agent->is_browser()) {
            $browser = $this->agent->browser() . ' ' . $this->agent->version();
        } elseif ($this->agent->is_robot()) {
            $browser = $this->agent->robot();
        } elseif ($this->agent->is_mobile()) {
            $browser = $this->agent->mobile();
        } else {
            $browser = 'Unknown';
        }
        $ip_address = $this->input->ip_address();
        $data = array(
            'user_id' => $userID,
            'role' => $role,
            'ip' =>  ($ip_address == "::1" ? "127.0.0.1" : $ip_address),
            'platform' => $this->agent->platform(),
            'browser' => $browser,
            'timestamp'   => date('Y-m-d H:i:s'),
            'branch_id'   => $branchID,
        );
        $this->db->insert('login_log', $data);
    }

    public function getStudentLoginStatus($id = '')
    {
        $get = $this->db->select('IFNULL(student_login, 1) as login')->where('id', $id)->get('branch')->row()->login;
        return $get;
    }
    
    public function getParentLoginStatus($id = '')
    {
        $get = $this->db->select('IFNULL(parent_login, 1) as login')->where('id', $id)->get('branch')->row()->login;
        return $get;
    }
}
