<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Student_model extends MY_Model{

    public function __construct()
    {
        parent::__construct();
    }

    // moderator student all information
    public function save($data = array(), $getBranch = array())
    {
        $hostelID = empty($data['hostel_id']) ? 0 : $data['hostel_id'];
        $roomID = empty($data['room_id']) ? 0 : $data['room_id'];

        $previous_details = array(
            'school_name' => $this->input->post('school_name'),
            'qualification' => $this->input->post('qualification'),
            'remarks' => $this->input->post('previous_remarks'),
        );
        if (empty($previous_details)) {
            $previous_details = "";
        } else {
            $previous_details = json_encode($previous_details);
        }

        $inser_data1 = array(
            'register_no' => $this->input->post('register_no'),
            'admission_date' => (!empty($data['admission_date']) ? date("Y-m-d", strtotime($data['admission_date'])) : ""),
            'first_name' => $this->input->post('first_name'),
            'last_name' => $this->input->post('last_name'),
            'gender' => $this->input->post('gender'),
            'birthday' => (!empty($data['birthday']) ? date("Y-m-d", strtotime($data['birthday'])) : ""),
            'religion' => $this->input->post('religion'),
            'caste' => $this->input->post('caste'),
            'blood_group' => $this->input->post('blood_group'),
            'mother_tongue' => $this->input->post('mother_tongue'),
            'current_address' => $this->input->post('current_address'),
            'permanent_address' => $this->input->post('permanent_address'),
            'city' => $this->input->post('city'),
            'state' => $this->input->post('state'),
            'mobileno' => $this->input->post('mobileno'),
            'category_id' => (isset($data['category_id']) ? $data['category_id'] : 0),
            'email' => $this->input->post('email'),
            'parent_id' => $this->input->post('parent_id'),
            'route_id' => (empty($this->input->post('route_id')) ? 0 : $this->input->post('route_id')),
            'vehicle_id' => (empty($this->input->post('vehicle_id')) ? 0 : $this->input->post('vehicle_id')),
            'hostel_id' => $hostelID,
            'room_id' => $roomID,
            'previous_details' => $previous_details,
            'photo' => $this->uploadImage('student'),
        );

        // moderator guardian all information
        if (!isset($data['student_id']) && empty($data['student_id'])) {
            if (!isset($data['guardian_chk'])) {
                // add new guardian all information in db
                if (!empty($data['grd_name']) || !empty($data['father_name'])) {
                    $arrayParent = array(
                        'name' => $this->input->post('grd_name'),
                        'relation' => $this->input->post('grd_relation'),
                        'father_name' => $this->input->post('father_name'),
                        'mother_name' => $this->input->post('mother_name'),
                        'occupation' => $this->input->post('grd_occupation'),
                        'income' => $this->input->post('grd_income'),
                        'education' => $this->input->post('grd_education'),
                        'email' => $this->input->post('grd_email'),
                        'mobileno' => $this->input->post('grd_mobileno'),
                        'address' => $this->input->post('grd_address'),
                        'city' => $this->input->post('grd_city'),
                        'state' => $this->input->post('grd_state'),
                        'branch_id' => $this->application_model->get_branch_id(),
                        'photo' => $this->uploadImage('parent', 'guardian_photo'),
                    );
                    $this->db->insert('parent', $arrayParent);
                    $parentID = $this->db->insert_id();

                    // save guardian login credential information in the database
                    if ($getBranch['grd_generate'] == 1) {
                        $grd_username = $getBranch['grd_username_prefix'] . $parentID;
                        $grd_password = $getBranch['grd_default_password'];
                    } else {
                        $grd_username = $data['grd_username'];
                        $grd_password = $data['grd_password'];
                    }
                    $parent_credential = array(
                        'username' => $grd_username,
                        'role' => 6,
                        'user_id' => $parentID,
                        'password' => $this->app_lib->pass_hashed($grd_password),
                    );
                    $this->db->insert('login_credential', $parent_credential);
                } else {
                    $parentID = 0;
                }
            } else {
                $parentID = $data['parent_id'];
            }

            $inser_data1['parent_id'] = $parentID;
            // insert student all information in the database
            $this->db->insert('student', $inser_data1);
            $student_id = $this->db->insert_id();

            // save student login credential information in the database
            if ($getBranch['stu_generate'] == 1) {
                $stu_username = $getBranch['stu_username_prefix'] . $student_id;
                $stu_password = $getBranch['stu_default_password'];
            } else {
                $stu_username = $data['username'];
                $stu_password = $data['password'];

            }
            $inser_data2 = array(
                'user_id' => $student_id,
                'username' => $stu_username,
                'role' => 7,
                'password' => $this->app_lib->pass_hashed($stu_password),
            );
            $this->db->insert('login_credential', $inser_data2);

            // return student information
            $studentData = array(
                'student_id' => $student_id,
                'email' => $this->input->post('email'),
                'username' => $stu_username,
                'password' => $stu_password,
            );

            if (!empty($data['grd_name']) || !empty($data['father_name'])) {
                // send parent account activate email
                $emailData = array(
                    'name' => $this->input->post('grd_name'),
                    'username' => $grd_username,
                    'password' => $grd_password,
                    'user_role' => 6,
                    'email' => $this->input->post('grd_email'),
                );
                $this->email_model->sentStaffRegisteredAccount($emailData);
            }
            return $studentData;
        } else {
            // update student all information in the database
            $inser_data1['parent_id'] = $data['parent_id'];
            $this->db->where('id', $data['student_id']);
            $this->db->update('student', $inser_data1);

            // update login credential information in the database
            $this->db->where('user_id', $data['student_id']);
            $this->db->where('role', 7);
            $this->db->update('login_credential', array('username' => $data['username']));
        }
    }

    public function csvImport($row = array(), $classID = '', $sectionID = '', $branchID = '')
    {
        // getting existing father data
        if ($row['GuardianUsername'] !== '') {
            $getParent = $this->db->select('parent.id')
                ->from('login_credential')->join('parent', 'parent.id = login_credential.user_id', 'left')
                ->where(array('parent.branch_id' => $branchID, 'login_credential.username' => $row['GuardianUsername']))
                ->get()->row_array();
        }

        // getting branch settings
        $getSettings = $this->db->select('*')
            ->where('id', $branchID)
            ->from('branch')
            ->get()->row_array();

        if (isset($getParent) && count($getParent)) {
            $parentID = $getParent['id'];
        } else {
            // add new guardian all information in db
            $arrayParent = array(
                'name' => $row['GuardianName'],
                'relation' => $row['GuardianRelation'],
                'father_name' => $row['FatherName'],
                'mother_name' => $row['MotherName'],
                'occupation' => $row['GuardianOccupation'],
                'mobileno' => $row['GuardianMobileNo'],
                'address' => $row['GuardianAddress'],
                'email' => $row['GuardianEmail'],
                'branch_id' => $branchID,
                'photo' => 'defualt.png',
            );
            $this->db->insert('parent', $arrayParent);
            $parentID = $this->db->insert_id();

            // save guardian login credential information in the database
            if ($getSettings['grd_generate'] == 1) {
                $grd_username = $getSettings['grd_username_prefix'] . $parentID;
                $grd_password = $getSettings['grd_default_password'];
            } else {
                $grd_username = $row['GuardianUsername'];
                $grd_password = $row['GuardianPassword'];
            }
            $parent_credential = array(
                'username' => $grd_username,
                'role' => 6,
                'user_id' => $parentID,
                'password' => $this->app_lib->pass_hashed($grd_password),
            );
            $this->db->insert('login_credential', $parent_credential);
        }

        $inser_data1 = array(
            'first_name' => $row['FirstName'],
            'last_name' => $row['LastName'],
            'blood_group' => $row['BloodGroup'],
            'gender' => $row['Gender'],
            'birthday' => date("Y-m-d", strtotime($row['Birthday'])),
            'mother_tongue' => $row['MotherTongue'],
            'religion' => $row['Religion'],
            'parent_id' => $parentID,
            'caste' => $row['Caste'],
            'mobileno' => $row['Phone'],
            'city' => $row['City'],
            'state' => $row['State'],
            'current_address' => $row['PresentAddress'],
            'permanent_address' => $row['PermanentAddress'],
            'category_id' => $row['CategoryID'],
            'admission_date' => date("Y-m-d", strtotime($row['AdmissionDate'])),
            'register_no' => $row['RegisterNo'],
            'photo' => 'defualt.png',
            'email' => $row['StudentEmail'],
        );

        //save all student information in the database file
        $this->db->insert('student', $inser_data1);
        $studentID = $this->db->insert_id();

        // save student login credential information in the database
        if ($getSettings['stu_generate'] == 1) {
            $stu_username = $getSettings['stu_username_prefix'] . $studentID;
            $stu_password = $getSettings['stu_default_password'];
        } else {
            $stu_username = $row['StudentUsername'];
            $stu_password = $row['StudentPassword'];
        }

        //save student login credential
        $inser_data2 = array(
            'username' => $stu_username,
            'role' => 7,
            'user_id' => $studentID,
            'password' => $this->app_lib->pass_hashed($stu_password),
        );
        $this->db->insert('login_credential', $inser_data2);

        //save student enroll information in the database file
        $arrayEnroll = array(
            'student_id' => $studentID,
            'class_id' => $classID,
            'section_id' => $sectionID,
            'branch_id' => $branchID,
            'roll' => $row['Roll'],
            'session_id' => get_session_id(),
        );
        $this->db->insert('enroll', $arrayEnroll);
    }

public function getFeeProgress($id)
{
    $this->db->select('IFNULL(SUM(gd.amount), 0) as totalfees,IFNULL(SUM(p.amount), 0) as totalpay,IFNULL(SUM(p.discount),0) as totaldiscount');
    $this->db->from('fee_allocation as a');
    $this->db->join('fee_groups_details as gd', 'gd.fee_groups_id = a.group_id', 'inner');
    $this->db->join('fee_payment_history as p', 'p.allocation_id = a.id and p.type_id = gd.fee_type_id', 'left');
    $this->db->where('a.student_id', $id);
    $this->db->where('a.session_id', get_session_id());
    $r = $this->db->get()->row_array();
    $total_amount = floatval($r['totalfees']);
    $total_paid = floatval($r['totalpay'] + $r['totaldiscount']);
    if ($total_paid != 0) {
        $percentage = ($total_paid / $total_amount) * 100;
        return number_format($percentage);
    } else {
        return 0;
    }
} // <-- this closing brace was missing

// Backward-compatible: accepts 0/1/3 args
public function get_students_for_teacher($teacher_id = null, $session_id = null, $branch_id = null): array
{
    // Resolve defaults from session/helpers if not passed
    if ($teacher_id === null) {
        $teacher_id = (int) ($this->session->userdata('loggedin_id') ?? 0);
    }
    if ($session_id === null || (int)$session_id === 0) {
        $session_id = function_exists('get_session_id')
            ? (int) get_session_id()
            : (int) ($this->session->userdata('session_id') ?? 0);
    }
    if ($branch_id === null || (int)$branch_id === 0) {
        $branch_id = function_exists('get_loggedin_branch_id')
            ? (int) get_loggedin_branch_id()
            : (int) ($this->session->userdata('branch_id') ?? 0);
    }

    // Build query (adjust the teacher allocation join to your table if different)
    return $this->db
        ->select('s.id, s.first_name, s.last_name')
        ->from('enroll AS e')
        ->join('student AS s', 's.id = e.student_id', 'inner')
        ->join(
            'teacher_allocation AS ta',
            'ta.class_id = e.class_id AND ta.section_id = e.section_id AND ta.session_id = e.session_id',
            'inner'
        )
        ->where([
            'ta.teacher_id' => (int)$teacher_id,
            'e.session_id'  => (int)$session_id,
            'e.branch_id'   => (int)$branch_id,
            's.active'      => 1,
        ])
        ->group_by('s.id') // prevent duplicates
        ->order_by('s.first_name, s.last_name')
        ->get()->result_array();
}

    public function getStudentList($classID = '', $sectionID = '', $branchID = '', $deactivate = false, $start = '', $end = '')
    {
        $this->db->select('e.*,s.photo, CONCAT_WS(" ", s.first_name, s.last_name) as 	fullname,s.register_no,s.gender,s.admission_date,s.parent_id,s.email,s.blood_group,s.birthday,l.active,c.name as 	class_name,se.name as section_name');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 'e.student_id = s.id', 'inner');
        $this->db->join('login_credential as l', 'l.user_id = s.id and l.role = 7', 'inner');
        $this->db->join('class as c', 'e.class_id = c.id', 'left');
        $this->db->join('section as se', 'e.section_id=se.id', 'left');
        if (!empty($classID)) {
            $this->db->where('e.class_id', $classID);
        }
        if (!empty($start) && !empty($end)) {
            $this->db->where('s.admission_date >=', $start);
            $this->db->where('s.admission_date <=', $end);
        }
        $this->db->where('e.branch_id', $branchID);
        $this->db->where('e.session_id', get_session_id());
        $this->db->order_by('s.id', 'ASC');
        if ($sectionID != 'all' && !empty($sectionID)) {
            $this->db->where('e.section_id', $sectionID);
        }
        if ($deactivate == true) {
            $this->db->where('l.active', 0);
        }
        return $this->db->get();
    }

    public function getSearchStudentList($search_text)
    {
        $this->db->select('e.*,s.photo,s.first_name,s.last_name,s.register_no,s.parent_id,s.email,s.blood_group,s.birthday,c.name as 	class_name,se.name as section_name,sp.name as parent_name');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 'e.student_id = s.id', 'left');
        $this->db->join('class as c', 'e.class_id = c.id', 'left');
        $this->db->join('section as se', 'e.section_id=se.id', 'left');
        $this->db->join('parent as sp', 'sp.id = s.parent_id', 'left');
        $this->db->where('e.session_id', get_session_id());
        if (!is_superadmin_loggedin()) {
            $this->db->where('e.branch_id', get_loggedin_branch_id());
        }
        $this->db->group_start();
        $this->db->like('s.first_name', $search_text);
        $this->db->or_like('s.last_name', $search_text);
        $this->db->or_like('s.register_no', $search_text);
        $this->db->or_like('s.email', $search_text);
        $this->db->or_like('e.roll', $search_text);
        $this->db->or_like('s.blood_group', $search_text);
        $this->db->or_like('sp.name', $search_text);
        $this->db->group_end();
        $this->db->order_by('s.id', 'desc');
        return $this->db->get();
    }

    public function getSingleStudent($id = '', $enroll = false)
    {
        $this->db->select('s.*,l.username,l.active,e.class_id,e.section_id,e.id as enrollid,e.roll,e.branch_id,e.session_id,c.name as 	class_name,se.name as section_name,sc.name as category_name');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 'e.student_id = s.id', 'left');
        $this->db->join('login_credential as l', 'l.user_id = s.id and l.role = 7', 'inner');
        $this->db->join('class as c', 'e.class_id = c.id', 'left');
        $this->db->join('section as se', 'e.section_id = se.id', 'left');
        $this->db->join('student_category as sc', 's.category_id=sc.id', 'left');
        if ($enroll == true) {
            $this->db->where('e.id', $id);
        } else {
            $this->db->where('s.id', $id);
        }
        $this->db->where('e.session_id', get_session_id());
        if (!is_superadmin_loggedin()) {
            $this->db->where('e.branch_id', get_loggedin_branch_id());
        }
        $query = $this->db->get();
        if ($query->num_rows() == 0) {
            show_404();
        }
        return $query->row_array();
    }

    public function regSerNumber($school_id = '')
    {
        $registerNoPrefix = '';
        if (!empty($school_id)) {
            $schoolconfig = $this->db->select('reg_prefix_enable,reg_start_from,institution_code,reg_prefix_digit')->where(array('id' => $school_id))->get('branch')->row();
            if ($schoolconfig->reg_prefix_enable == 1) {
                $registerNoPrefix = $schoolconfig->institution_code . $schoolconfig->reg_start_from;
                $last_registerNo = $this->app_lib->studentLastRegID($school_id);
                if (!empty($last_registerNo)) {
                    $last_registerNo_digit = str_replace($schoolconfig->institution_code, "", $last_registerNo->register_no);
                    if (!is_numeric($last_registerNo_digit)) {
                        $last_registerNo_digit = $schoolconfig->reg_start_from;
                    } else {
                        $last_registerNo_digit = $last_registerNo_digit + 1;
                    }
                    $registerNoPrefix = $schoolconfig->institution_code . sprintf("%0" . $schoolconfig->reg_prefix_digit . "d", $last_registerNo_digit);
                } else {
                    $registerNoPrefix = $schoolconfig->institution_code . sprintf("%0" . $schoolconfig->reg_prefix_digit . "d", $schoolconfig->reg_start_from);
                }
            }
            return $registerNoPrefix;
        } else {
            $config = $this->db->select('institution_code,reg_prefix')->where(array('id' => 1))->get('global_settings')->row();
            if ($config->reg_prefix == 'on') {
                $prefix = $config->institution_code;
            }
            $result = $this->db->select("max(id) as id")->get('student')->row_array();
            $id = $result["id"];
            if (!empty($id)) {
                $maxNum = str_pad($id + 1, 5, '0', STR_PAD_LEFT);
            } else {
                $maxNum = '00001';
            }
            return ($prefix . $maxNum);
        }
    }

    public function getDisableReason($student_id = '')
    {
        $this->db->select("rd.*,disable_reason.name as reason");
        $this->db->from('disable_reason_details as rd');
        $this->db->join('disable_reason', 'disable_reason.id = rd.reason_id', 'left');
        $this->db->where('student_id', $student_id);
        $this->db->order_by('rd.id', 'DESC');
        $this->db->limit(1);
        $row = $this->db->get()->row();
        return $row;
    }

    public function getSiblingList($parent_id = '', $student_id = '')
    {
        $this->db->select('s.photo, s.register_no, CONCAT_WS(" ",s.first_name, s.last_name) as fullname,s.gender,s.mobileno,e.roll,e.branch_id,c.name as class_name,se.name as section_name');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 'e.student_id = s.id', 'inner');
        $this->db->join('class as c', 'e.class_id = c.id', 'left');
        $this->db->join('section as se', 'e.section_id = se.id', 'left');
        $this->db->where_not_in('s.id', $student_id);
        $this->db->where('s.parent_id', $parent_id);
        $this->db->order_by('s.id', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }

    public function getParentList($class_id = '', $section_id = '', $branch_id = '')
    {
        $this->db->select('p.name as g_name,p.father_name,p.mother_name,p.occupation,count(s.parent_id) as child,p.mobileno,s.parent_id');
        $this->db->from('student as s');
        $this->db->join('enroll as e', 'e.student_id = s.id', 'inner');
        $this->db->join('parent as p', 'p.id = s.parent_id', 'inner');
        $this->db->where('e.class_id', $class_id);
        if ($section_id != 'all') {
            $this->db->where('e.section_id', $section_id);
        }
        $this->db->where('e.branch_id', $branch_id);
        $this->db->where('e.session_id', get_session_id());
        $this->db->order_by('s.id', 'ASC');
        $this->db->group_by('p.id');
        $query = $this->db->get();
        return $query->result_array();
    }

    public function getSiblingListByClass($parent_id = '', $class_id = '', $section_id = '')
    {
        $this->db->select('s.register_no,e.id as enroll_id,CONCAT_WS(" ",s.first_name, s.last_name) as fullname,s.gender,c.name as class_name,se.name as section_name');
        $this->db->from('enroll as e');
        $this->db->join('student as s', 'e.student_id = s.id', 'inner');
        $this->db->join('class as c', 'e.class_id = c.id', 'left');
        $this->db->join('section as se', 'e.section_id = se.id', 'left');
        $this->db->where('e.class_id', $class_id);
        if ($section_id != 'all') {
            $this->db->where('e.section_id', $section_id);
        }
        $this->db->where('e.session_id', get_session_id());
        $this->db->where('s.parent_id', $parent_id);
        $this->db->order_by('s.id', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }
public function get_all_students_basic($branch_id)
{
    $this->db->select('id, first_name, last_name, register_no');
    $this->db->from('student');
    $this->db->where('branch_id', $branch_id);
    return $this->db->get()->result_array();
}
public function get_student_list_by_teacher($teacher_id)
{
    /* logic:
         teacher_allocation : teacher_id | class_id | section_id | session_id
         enroll             : student_id | class_id | section_id | session_id
       …so any student whose class+section appears in
       teacher_allocation for this teacher in the current session  */
    $this->db->select('s.id,
                       CONCAT_WS(" ", s.first_name, s.last_name) AS fullname');
    $this->db->from('teacher_allocation ta');
    $this->db->join('enroll   e', 'e.class_id   = ta.class_id   AND
                                   e.section_id = ta.section_id AND
                                   e.session_id = ta.session_id', 'inner');
    $this->db->join('student  s', 's.id = e.student_id', 'inner');
    $this->db->where('ta.teacher_id', $teacher_id);
    $this->db->where('s.branch_id', get_loggedin_branch_id());
    $this->db->group_by('s.id');

    return $this->db->get()->result_array();
}
public function get_all_students($class_id = null, $section_id = null, $session_id = null)
{
    $this->db->select('enroll.student_id, CONCAT_WS(" ", student.first_name, student.last_name) AS full_name');
    $this->db->from('enroll');
    $this->db->join('student', 'student.id = enroll.student_id');
    
    if ($class_id !== null) {
        $this->db->where('enroll.class_id', $class_id);
    }
    if ($section_id !== null) {
        $this->db->where('enroll.section_id', $section_id);
    }
    if ($session_id !== null) {
        $this->db->where('enroll.session_id', $session_id);
    } else {
        $this->db->where('enroll.session_id', get_session_id());
    }

    $this->db->where('enroll.branch_id', get_loggedin_branch_id());
    
    return $this->db->get()->result_array();
}

// ─────────────────────────────────────────────────────────────────────────────
// PACE progress counters for a student
//   - assigned  : all PACE rows for the student (any status)
//   - completed : status = 'completed'
//   - below80   : counts ORIGINAL attempts with first_attempt_score < 80
//                 (i.e., redos are included because their original attempt <80)
// ─────────────────────────────────────────────────────────────────────────────
public function get_student_pace_progress($student_id)
{
    $student_id = (int)$student_id;

    // Assigned = every row in student_assign_paces for this student
    $this->db->from('student_assign_paces');
    $this->db->where('student_id', $student_id);
    $assigned = (int)$this->db->count_all_results();

    // Completed = status completed
    $this->db->from('student_assign_paces');
    $this->db->where('student_id', $student_id);
    $this->db->where('status', 'completed');
    $completed = (int)$this->db->count_all_results();

    // Below 80% = ONLY original attempts with first_attempt_score < 80
    // (avoid counting redo rows; they often have is_redo=1 and first_attempt_score NULL)
    $below80 = $this->count_first_attempt_below80($student_id);

    return [
        'assigned'  => $assigned,
        'completed' => $completed,
        'below80'   => $below80,
    ];
}

/**
 * Count PACEs where the FIRST attempt was below 80%.
 * We intentionally restrict to original rows (is_redo = 0 OR NULL) so we don’t miscount redo rows,
 * and we require a non-null first_attempt_score.
 */
public function count_first_attempt_below80($student_id)
{
    $student_id = (int)$student_id;

    $this->db->from('student_assign_paces');
    $this->db->where('student_id', $student_id);
    // original rows only
    $this->db->group_start()
             ->where('is_redo', 0)
             ->or_where('is_redo IS NULL', null, false)
             ->group_end();
    // first attempt exists and is < 80
    $this->db->where('first_attempt_score IS NOT NULL', null, false);
    $this->db->where('first_attempt_score <', 80);

    return (int)$this->db->count_all_results();
}

// === PATCH: Student_model additions ===

/** Return array of subject_ids assigned to a student for a given year/session. */
public function get_assigned_subject_ids($student_id, $year = null)
{
    $student_id = (int)$student_id;

    if (!$this->db->table_exists('student_assigned_subjects')) {
        return [];
    }

    $qb = $this->db->select('subject_id')
        ->from('student_assigned_subjects')
        ->where('student_id', $student_id);

    // Support either session_id OR year, depending on your schema
    if ($this->db->field_exists('session_id', 'student_assigned_subjects')) {
        $qb->where('session_id', (int) get_session_id());
    } elseif ($this->db->field_exists('year', 'student_assigned_subjects')) {
        $qb->where('year', (int) ($year ?: date('Y')));
    }

    $rows = $qb->get()->result_array();           // <-- real query, not a string
    return array_map('intval', array_column($rows, 'subject_id'));
}

/** Replace assigned subjects for a student (atomic upsert). */
public function save_assigned_subjects($student_id, array $subject_ids, $year, $assigned_by)
{
    $student_id  = (int)$student_id;
    $assigned_by = (int)$assigned_by;
    $year        = (int)$year;

    $this->db->trans_start();
    $this->db->where('student_id', $student_id)->where('year', $year)->delete('student_assigned_subjects');

    foreach ($subject_ids as $sid) {
        $sid = (int)$sid;
        if ($sid > 0) {
            $this->db->insert('student_assigned_subjects', [
                'student_id'  => $student_id,
                'subject_id'  => $sid,
                'year'        => $year,
                'assigned_by' => $assigned_by,
            ]);
        }
    }
    $this->db->trans_complete();
    return $this->db->trans_status();
}

/** Subjects available for a student's grade, for checkbox list. */
public function get_subjects_for_grade($class_id)
{
    $class_id = (int)$class_id;

    // Preferred: subject_assign defines which subjects are assigned to this class (grade)
    if ($this->db->table_exists('subject_assign')) {
        return $this->db->select('s.id, s.name, s.subject_code')
            ->from('subject_assign AS sa')
            ->join('subject AS s', 's.id = sa.subject_id', 'inner')
            ->where('sa.class_id', $class_id)
            ->group_by('s.id')
            ->order_by('(s.subject_code IS NULL)', 'asc', false)
            ->order_by('CAST(s.subject_code AS UNSIGNED)', 'asc', false)
            ->order_by('s.name', 'asc')
            ->get()->result_array();
    }

    // Fallback (no subject_assign): derive grade number and use subject_pace
    $gradeRow = $this->db->select('name_numeric')->from('class')->where('id', $class_id)->get()->row_array();
    $grade_num = $gradeRow ? (int)$gradeRow['name_numeric'] : 0;

    return $this->db->select('s.id, s.name, s.subject_code')
        ->from('subject_pace sp')
        ->join('subject s', 's.id = sp.subject_id', 'inner')
        ->where('sp.grade', $grade_num)
        ->group_by('s.id')
        ->order_by('(s.subject_code IS NULL)', 'asc', false)
        ->order_by('CAST(s.subject_code AS UNSIGNED)', 'asc', false)
        ->order_by('s.name', 'asc')
        ->get()->result_array();
}

/** Minimal helper: students belonging to the logged-in teacher. */
public function get_my_students($teacher_id)
{
    $teacher_id = (int)$teacher_id;

    // Get teacher's class IDs first
    $classIDs = $this->db->select('class_id')
        ->from('teacher_allocation')
        ->where('teacher_id', $teacher_id)
        ->get()->result_array();

    if (!$classIDs) return [];

    $classIDs = array_map('intval', array_column($classIDs, 'class_id'));

    // Return consistent keys for the dropdown
    return $this->db->select('stu.id AS student_id, CONCAT(stu.first_name, " ", stu.last_name) AS full_name, en.class_id, c.name AS grade_name')
        ->from('enroll AS en')
        ->join('student AS stu', 'stu.id = en.student_id', 'inner')
        ->join('class AS c', 'c.id = en.class_id', 'left')
        ->where_in('en.class_id', $classIDs)
        ->where('stu.active', 1)
        ->group_by('stu.id')
        ->order_by('full_name', 'ASC')
        ->get()->result_array();
}

/** All active students for Admin dropdown (same keys as get_my_students). */
public function get_all_students_dropdown()
{
    return $this->db->select('stu.id AS student_id, CONCAT(stu.first_name, " ", stu.last_name) AS full_name, en.class_id, c.name AS grade_name')
        ->from('enroll AS en')
        ->join('student AS stu', 'stu.id = en.student_id', 'inner')
        ->join('class AS c', 'c.id = en.class_id', 'left')
        ->where('stu.active', 1)
        ->where('en.session_id', (int) get_session_id())
        ->group_by('stu.id')
        ->order_by('full_name', 'ASC')
        ->get()->result_array();
}

}
