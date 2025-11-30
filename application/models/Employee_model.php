<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Employee_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // ---- helpers to keep roles clean -------------------------------------------------
    private function staffRoleIds()
    {
        // staff roles (Parent=9 and Student=7 are NOT staff)
        return [2,3,4,5,6,8];
    }

    // keep one row per (user_id, role); delete duplicate copies of the same role
    private function dedupeUserRoles($userId)
    {
        $sql = "
            DELETE lc FROM login_credential lc
            JOIN (
                SELECT MIN(id) keep_id, user_id, role
                FROM login_credential
                WHERE user_id = ?
                GROUP BY user_id, role
                HAVING COUNT(*) > 1
            ) x
              ON x.user_id = lc.user_id AND x.role = lc.role
            WHERE lc.id <> x.keep_id
        ";
        $this->db->query($sql, [(int)$userId]);
    }

    // ensure at most ONE staff role; keep the chosen $keepRole if provided and present
    private function enforceSingleStaffRole($userId, $keepRole = null)
    {
        $staff = $this->staffRoleIds();
        // fetch staff-role rows for this user
        $rows = $this->db->select('id, role')
            ->from('login_credential')
            ->where('user_id', (int)$userId)
            ->where_in('role', $staff)
            ->order_by('id', 'ASC')
            ->get()->result_array();

        if (count($rows) <= 1) return; // already OK

        // pick which row to keep
        $keepId = null;
        if ($keepRole !== null) {
            foreach ($rows as $r) {
                if ((int)$r['role'] === (int)$keepRole) {
                    $keepId = (int)$r['id'];
                    break;
                }
            }
        }
        if ($keepId === null) {
            // default: keep earliest row
            $keepId = (int)$rows[0]['id'];
        }

        // delete other staff-role rows (do NOT touch Parent=9)
        $this->db->where('user_id', (int)$userId);
        $this->db->where_in('role', $staff);
        $this->db->where('id <>', $keepId);
        $this->db->delete('login_credential');
    }
    // -----------------------------------------------------------------------------------

    // moderator employee all information
    public function save($data, $role = null, $id = null)
    {
        // Are we updating an existing staff member?
        $isUpdate = !empty($data['staff_id']);
        $existing = [];
        if ($isUpdate) {
            $existing = $this->db
                ->get_where('staff', ['id' => (int)$data['staff_id']])
                ->row_array() ?: [];
        }

        // Photo: keep existing if no new file selected
        $newPhoto = $this->uploadImage('staff'); // input name: user_photo
        $photo = !empty($newPhoto)
            ? $newPhoto
            : ($data['old_user_photo'] ?? ($existing['photo'] ?? null));

        // Accept both *_id and raw columns; fall back to existing when missing
        $designation = $data['designation_id'] ?? ($existing['designation'] ?? null);
        $department  = $data['department_id']  ?? ($existing['department']  ?? null);

        // Dates: normalize joining_date only if provided; else keep existing
        $joining_date = !empty($data['joining_date'])
            ? date("Y-m-d", strtotime($data['joining_date']))
            : ($existing['joining_date'] ?? null);

        // Build payload, falling back to existing values where needed
        $payload = array(
            'branch_id'          => $this->application_model->get_branch_id(),
            'name'               => $data['name']               ?? ($existing['name'] ?? ''),
            'sex'                => $data['sex']                ?? ($existing['sex'] ?? ''),
            'religion'           => $data['religion']           ?? ($existing['religion'] ?? ''),
            'blood_group'        => $data['blood_group']        ?? ($existing['blood_group'] ?? ''),
            'birthday'           => $data['birthday']           ?? ($existing['birthday'] ?? null),
            'mobileno'           => $data['mobile_no']          ?? ($existing['mobileno'] ?? ''),
            'present_address'    => $data['present_address']    ?? ($existing['present_address'] ?? ''),
            'permanent_address'  => $data['permanent_address']  ?? ($existing['permanent_address'] ?? ''),
            'designation'        => $designation,
            'department'         => $department,
            'joining_date'       => $joining_date,
            'qualification'      => $data['qualification']      ?? ($existing['qualification'] ?? ''),
            'experience_details' => $data['experience_details'] ?? ($existing['experience_details'] ?? ''),
            'total_experience'   => $data['total_experience']   ?? ($existing['total_experience'] ?? ''),
            'email'              => $data['email']              ?? ($existing['email'] ?? ''),
            'facebook_url'       => $data['facebook']           ?? ($existing['facebook_url'] ?? ''),
            'linkedin_url'       => $data['linkedin']           ?? ($existing['linkedin_url'] ?? ''),
            'twitter_url'        => $data['twitter']            ?? ($existing['twitter_url'] ?? ''),
        );
        if (!empty($photo)) {
            $payload['photo'] = $photo;
        }

        // Insert vs Update
        if (!$isUpdate) {
            // New staff row
            $payload['staff_id'] = substr(app_generate_hash(), 3, 7);
            $this->db->insert('staff', $payload);
            $employeeID = $this->db->insert_id();

            // Create login row only if username/role present
            $login = array(
                'user_id'  => $employeeID,
                'username' => ($data['username'] ?? ($data['email'] ?? null)),
                'role'     => $data['user_role'] ?? null,
                'active'   => 1,
            );
            if (!empty($data['password'])) {
                $login['password'] = $this->app_lib->pass_hashed($data['password']);
            }
            if (!empty($login['username']) || !empty($login['role'])) {
                $this->db->insert('login_credential', $login);
                // clean up roles: no duplicate role rows, and only one staff role (parent allowed in addition)
                $this->dedupeUserRoles($employeeID);
                if (!empty($login['role'])) {
                    $this->enforceSingleStaffRole($employeeID, (int)$login['role']);
                } else {
                    $this->enforceSingleStaffRole($employeeID);
                }
            }

            // Optional bank info
            if (!isset($data['chkskipped'])) {
                $data['staff_id'] = $employeeID;
                $this->bankSave($data);
            }
            return $employeeID;
        }

        // Update existing
        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('id', (int)$data['staff_id']);
        $this->db->update('staff', $payload);

        $db_error = $this->db->error();
        if (($db_error['code'] ?? 0) !== 0) {
            log_message('error', 'DB error updating staff ID '.$data['staff_id'].': '.$db_error['message']);
            return false;
        }

        // Update login only for fields you posted (prevents null overwrites)
        $login_update = array();
        if (!empty($data['username'])) $login_update['username'] = $data['username'];
        if (!empty($data['user_role'])) $login_update['role']     = $data['user_role'];
        if (!empty($data['password']))  $login_update['password'] = $this->app_lib->pass_hashed($data['password']);

        if (!empty($login_update)) {
            $this->db->where('user_id', (int)$data['staff_id']);
            // don’t touch Student (7); Parent (9) is allowed to co-exist
            $this->db->where_not_in('role', array(7));
            $this->db->update('login_credential', $login_update);

            // clean up any role mess created elsewhere
            $this->dedupeUserRoles((int)$data['staff_id']);
            $this->enforceSingleStaffRole((int)$data['staff_id'], isset($login_update['role']) ? (int)$login_update['role'] : null);
        }

        return true;
    }

    // GET SINGLE EMPLOYEE DETAILS
    public function getSingleStaff($id = '')
    {
        $this->db->select('staff.*,staff_designation.name as designation_name,staff_department.name as department_name,login_credential.role as role_id,login_credential.active,login_credential.username, roles.name as role');
        $this->db->from('staff');
        // allow all staff roles (incl. Principal=6); still exclude students (7)
        $this->db->join('login_credential', 'login_credential.user_id = staff.id and login_credential.role != "7"', 'inner');
        $this->db->join('roles', 'roles.id = login_credential.role', 'left');
        $this->db->join('staff_designation', 'staff_designation.id = staff.designation', 'left');
        $this->db->join('staff_department', 'staff_department.id = staff.department', 'left');
        $this->db->where('staff.id', $id);
        if (!is_superadmin_loggedin()) {
            $this->db->where('staff.branch_id', get_loggedin_branch_id());
        }
        $query = $this->db->get();
        if ($query->num_rows() == 0) {
            show_404();
        }
        return $query->row_array();
    }

    // get staff all list
   public function getStaffList($branchID = '', $role_id = '', $active = 1)
{
    $this->db->select('
        staff.*,
        staff_designation.name AS designation_name,
        staff_department.name  AS department_name,
        login_credential.role  AS role_id,
        roles.name             AS role
    ');
    $this->db->from('staff');
    $this->db->join(
        'login_credential',
        'login_credential.user_id = staff.id AND login_credential.role != "7"',
        'inner'
    );
    $this->db->join('roles',             'roles.id = login_credential.role', 'left');
    $this->db->join('staff_designation', 'staff_designation.id = staff.designation', 'left');
    $this->db->join('staff_department',  'staff_department.id  = staff.department',  'left');

    if ((string)$branchID !== '' && (int)$branchID > 0) {
        $this->db->where('staff.branch_id', (int)$branchID);
    }

    if ($role_id !== '') {
        $this->db->where('login_credential.role', (int)$role_id);
    }

    // 👇 Principal tab: accept typo "Princial" and/or designation id 4
    if ((int)$role_id === 6) {
        $this->db->group_start();
            $this->db->like('staff_designation.name', 'Princi', 'after'); // Principal / Princial
            $this->db->or_where('staff.designation', 4);                  // designation id = 4
        $this->db->group_end();
    }

    $this->db->where('login_credential.active', (int)$active);
    $this->db->group_by('staff.id');
    $this->db->order_by('staff.id', 'ASC');

    return $this->db->get()->result();
}

public function get_list_by_role($a, $b = null)
{
    // Accept either calling convention:
    //   get_list_by_role($role_id, $branch_id)
    //   get_list_by_role($branch_id, $role_id)
    $staffRoles = $this->staffRoleIds();

    $role   = '';
    $branch = '';

    if (in_array((int)$a, $staffRoles, true)) {
        // First arg is role_id
        $role   = (int)$a;
        $branch = ((int)$b > 0) ? (int)$b : '';
    } else {
        // First arg is branch_id (or empty for SA)
        $branch = ((int)$a > 0) ? (int)$a : '';
        $role   = isset($b) ? (int)$b : '';
    }

    return $this->getStaffList($branch, $role, 1);
}


    public function get_schedule_by_id($id)
    {
        $this->db->select('timetable_class.*,subject.name as subject_name,class.name as class_name,section.name as section_name');
        $this->db->from('timetable_class');
        $this->db->join('subject', 'subject.id = timetable_class.subject_id', 'inner');
        $this->db->join('class', 'class.id = timetable_class.class_id', 'inner');
        $this->db->join('section', 'section.id = timetable_class.section_id', 'inner');
        $this->db->where('timetable_class.teacher_id', $id);
        $this->db->where('timetable_class.session_id', get_session_id());
        return $this->db->get();
    }

    public function bankSave($data)
    {
        $inser_data = array(
            'staff_id' => $data['staff_id'],
            'bank_name' => $data['bank_name'],
            'holder_name' => $data['holder_name'],
            'bank_branch' => $data['bank_branch'],
            'bank_address' => $data['bank_address'],
            'ifsc_code' => $data['ifsc_code'],
            'account_no' => $data['account_no'],
        );
        if (isset($data['bank_id'])) {
            $this->db->where('id', $data['bank_id']);
            $this->db->update('staff_bank_account', $inser_data);
        } else {
            $this->db->insert('staff_bank_account', $inser_data);
        }
    }

    public function csvImport($row, $branchID, $userRole, $designationID, $departmentID)
    {
        // Helpers
        $get = function(array $keys) use ($row) {
            foreach ($keys as $k) {
                if (isset($row[$k]) && trim($row[$k]) !== '') {
                    return trim($row[$k]);
                }
            }
            return '';
        };
        $parseDate = function($v) {
            if (!isset($v) || $v === '') return null;
            $t = strtotime($v);
            return $t ? date('Y-m-d', $t) : null;
        };
        $randomPass = function($len = 10) {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            $o = '';
            for ($i=0;$i<$len;$i++) $o .= $chars[random_int(0, strlen($chars)-1)];
            return $o;
        };

        // Read (case-insensitive) CSV headers gracefully
        $name        = $get(['Name','name','FullName','Full Name']);
        $gender      = strtolower($get(['Gender','gender'])) === 'female' ? 'female' : (strtolower($get(['Gender','gender'])) === 'male' ? 'male' : '');
        $religion    = $get(['Religion','religion']);
        $blood_group = $get(['BloodGroup','Blood Group','blood_group','blood group']);
        $dob         = $parseDate($get(['DateOfBirth','Date of Birth','DOB','dob']));
        $joinDate    = $parseDate($get(['JoiningDate','Joining Date','join_date','joining_date']));
        $qual        = $get(['Qualification','qualification']);
        $mobile      = $get(['MobileNo','Mobile No','Mobile','Phone','mobile_no']);
        $addr1       = $get(['PresentAddress','Present Address','present_address']);
        $addr2       = $get(['PermanentAddress','Permanent Address','permanent_address']);
        $email       = strtolower($get(['Email','email','E-mail','e-mail']));
        $passwordRow = $get(['Password','password','Pass','pass']);

        if ($name === '' && $email === '') {
            // nothing to import on this row
            return true;
        }

        // Defaults
        if ($passwordRow === '') $passwordRow = $randomPass(10);

        // Build staff row (match your DB column names)
        $staff = array(
            'branch_id'         => (int)$branchID,
            'name'              => $name,
            'sex'               => $gender,
            'religion'          => $religion,
            'blood_group'       => $blood_group,
            'birthday'          => $dob,
            'joining_date'      => $joinDate,
            'qualification'     => $qual,
            'mobileno'          => $mobile,
            'present_address'   => $addr1,
            'permanent_address' => $addr2,
            'email'             => $email,                 // you keep email also on staff table
            'designation'       => (int)$designationID,
            'department'        => (int)$departmentID,
            'photo'             => 'defualt.png',
            'staff_id'          => substr(app_generate_hash(), 3, 7),
        );

        $this->db->trans_start();

        // Insert staff
        $this->db->insert('staff', $staff);
        $employeeID = $this->db->insert_id();

        // Prepare login row
        $username = ($email !== '' ? $email : 'user'.$employeeID);
        // Ensure username is unique if table has a unique index
        $exists = $this->db->get_where('login_credential', ['username' => $username])->row_array();
        if ($exists) {
            $username = $username . '.' . $employeeID;
        }

        $login = array(
            'active'   => 1,
            'user_id'  => $employeeID,
            'username' => $username,
            'role'     => (int)$userRole,
            'password' => $this->app_lib->pass_hashed($passwordRow),
        );
        $this->db->insert('login_credential', $login);

        // clean up any dupes, and enforce single staff role (parent allowed)
        $this->dedupeUserRoles($employeeID);
        $this->enforceSingleStaffRole($employeeID, (int)$userRole);

        $this->db->trans_complete();

        if (!$this->db->trans_status()) {
            $dbErr = $this->db->error();
            log_message('error', 'CSV Import failed for staff ['.$email.']: '.$dbErr['message']);
            return false;
        }
        return true;
    }

    public function update($id, $data)
    {
        $this->db->where('id', (int)$id);
        $this->db->update('staff', $data);

        $db_error = $this->db->error();
        if ($db_error['code'] != 0) {
            log_message('error', 'DB error updating staff ID '.$id.': '.$db_error['message']);
            return false;
        }

        return $this->db->affected_rows() >= 0;
    }

    public function update_staff($id, array $data)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return ['success' => false, 'error' => ['code' => -1, 'message' => 'Invalid staff ID']];
        }

        // Only allow columns that exist on `staff`
        $allowed = [
            'name','mobile_no','present_address','permanent_address','email',
            'qualification','joining_date','designation_id','department_id',
            'gender','sex','religion','blood_group','branch_id','updated_at'
        ];
        $safe = array_intersect_key($data, array_flip($allowed));
        if (empty($safe)) {
            return ['success' => true, 'error' => ['code' => 0, 'message' => 'No changes']];
        }

        $this->db->trans_start();
        $this->db->where('id', $id);
        $this->db->update('staff', $safe);
        $dbError = $this->db->error(); // ['code'=>0 if ok]
        $failed  = ($dbError['code'] ?? 0) !== 0;
        $this->db->trans_complete();

        return [
            'success' => $this->db->trans_status() && !$failed,
            'error'   => $dbError,
        ];
    }
}
