<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 7.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Student.php
 * @copyright : Reserved RamomCoder Team
 */

class Student extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->helpers('download');
        $this->load->helpers('custom_fields');
        $this->load->model('student_model');
        $this->load->model('email_model');
        $this->load->model('sms_model');
        $this->load->model('student_fields_model');
	    $this->load->model('Student_overview_model');
	    $this->load->model('Projection_model');
    }

    public function index()
    {
        redirect(base_url('student/view'));
    }

    /* student form validation rules */
    protected function student_validation()
    {
        $branchID = $this->application_model->get_branch_id();
        $getBranch = $this->getBranchDetails();
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'trim|required');
        }
        $this->form_validation->set_rules('year_id', translate('academic_year'), 'trim|required');
        $this->form_validation->set_rules('first_name', translate('first_name'), 'trim|required');
        $this->form_validation->set_rules('class_id', translate('class'), 'trim|required');
        $this->form_validation->set_rules('section_id', translate('section'), 'trim|required');
        $this->form_validation->set_rules('register_no', translate('register_no'), 'trim|required|callback_unique_registerid');
        // checking profile photo format
        $this->form_validation->set_rules('user_photo', translate('profile_picture'), 'callback_photoHandleUpload[user_photo]');

        // system fields validation rules
        $validArr = array();
        $validationArr = $this->student_fields_model->getStatusArr($branchID);
        foreach ($validationArr as $key => $value) {
            if ($value->status && $value->required) {
                $validArr[$value->prefix] = 1;
            }
        }
        if (isset($validArr['admission_date'])) {
            $this->form_validation->set_rules('admission_date', translate('admission_date'), 'trim|required');
        }
        if (isset($validArr['student_photo'])) {
            if (isset($_FILES["user_photo"]) && empty($_FILES["user_photo"]['name']) && empty($_POST['old_user_photo'])) {
                $this->form_validation->set_rules('user_photo', translate('profile_picture'), 'required');
            }
        }
        if (isset($validArr['roll'])) {
            $this->form_validation->set_rules('roll', translate('roll'), 'trim|numeric|required|callback_unique_roll');
        } else {
            $this->form_validation->set_rules('roll', translate('roll'), 'trim|numeric|callback_unique_roll');
        }
        if (isset($validArr['last_name'])) {
            $this->form_validation->set_rules('last_name', translate('last_name'), 'trim|required');
        }
        if (isset($validArr['gender'])) {
            $this->form_validation->set_rules('gender', translate('gender'), 'trim|required');
        }
        if (isset($validArr['birthday'])) {
            $this->form_validation->set_rules('birthday', translate('birthday'), 'trim|required');
        }
        if (isset($validArr['category'])) {
            $this->form_validation->set_rules('category_id', translate('category'), 'trim|required');
        }
        if (isset($validArr['religion'])) {
            $this->form_validation->set_rules('religion', translate('religion'), 'trim|required');
        }
        if (isset($validArr['caste'])) {
            $this->form_validation->set_rules('caste', translate('caste'), 'trim|required');
        }
        if (isset($validArr['blood_group'])) {
            $this->form_validation->set_rules('blood_group', translate('blood_group'), 'trim|required');
        }
        if (isset($validArr['mother_tongue'])) {
            $this->form_validation->set_rules('mother_tongue', translate('mother_tongue'), 'trim|required');
        }
        if (isset($validArr['present_address'])) {
            $this->form_validation->set_rules('current_address', translate('present_address'), 'trim|required');
        }
        if (isset($validArr['permanent_address'])) {
            $this->form_validation->set_rules('permanent_address', translate('permanent_address'), 'trim|required');
        }
        if (isset($validArr['city'])) {
            $this->form_validation->set_rules('city', translate('city'), 'trim|required');
        }
        if (isset($validArr['state'])) {
            $this->form_validation->set_rules('state', translate('state'), 'trim|required');
        }
        if (isset($validArr['student_email'])) {
            $this->form_validation->set_rules('email', translate('email'), 'trim|required|valid_email');
        }
        if (isset($validArr['student_mobile_no'])) {
            $this->form_validation->set_rules('mobileno', translate('mobile_no'), 'trim|required|numeric');
        }
        if (isset($validArr['previous_school_details'])) {
            $this->form_validation->set_rules('school_name', translate('school_name'), 'trim|required');
            $this->form_validation->set_rules('qualification', translate('qualification'), 'trim|required');
        }

        if ($getBranch['stu_generate'] == 0 || isset($_POST['student_id'])) {
$this->form_validation->set_rules('username', translate('username'), 'trim|valid_email|callback_unique_username_if_present');

            if (!isset($_POST['student_id'])) {
                $this->form_validation->set_rules('password', translate('password'), 'trim|required|min_length[4]');
                $this->form_validation->set_rules('retype_password', translate('retype_password'), 'trim|required|matches[password]');
            }
        }
        
        // custom fields validation rules
        $class_slug = $this->router->fetch_class();
        $customFields = getCustomFields($class_slug);
        foreach ($customFields as $fields_key => $fields_value) {
            if ($fields_value['required']) {
                $fieldsID   = $fields_value['id'];
                $fieldLabel = $fields_value['field_label'];
                $this->form_validation->set_rules("custom_fields[student][" . $fieldsID . "]", $fieldLabel, 'trim|required');
            }
        }
    }

    /* student admission information are prepared and stored in the database here */
    public function add()
    {
        // check access permission
        if (!get_permission('student', 'is_add')) {
            access_denied();
        }

        // check saas student add limit
        if($this->app_lib->isExistingAddon('saas')) {
            if (!checkSaasLimit('student')) {
                set_alert('error', translate('update_your_package'));
                redirect(site_url('dashboard'));
            }
        }
        $getBranch = $this->getBranchDetails();
        $branchID = $this->application_model->get_branch_id();
        $this->data['getBranch'] = $getBranch;
        $this->data['branch_id'] = $branchID;
        $this->data['sub_page'] = 'student/add';
        $this->data['main_menu'] = 'admission';
        $this->data['register_id'] = $this->student_model->regSerNumber($branchID);
        $this->data['title'] = translate('create_admission');
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/dropify/css/dropify.min.css',
            ),
            'js' => array(
                'js/student.js',
                'vendor/dropify/js/dropify.min.js',
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

    public function save() {
        if ($_POST) {
            // check access permission
            if (!get_permission('student', 'is_add')) {
                ajax_access_denied();
            }

            // check saas student add limit
            if($this->app_lib->isExistingAddon('saas')) {
                if (!checkSaasLimit('student')) {
                    ajax_access_denied();
                }
            }

            $getBranch = $this->getBranchDetails();
            $branchID = $this->application_model->get_branch_id();
        
            $this->student_validation();
            if (!isset($_POST['guardian_chk'])) {

                // system fields validation rules
                $validArr = array();
                $validationArr = $this->student_fields_model->getStatusArr($branchID);
                foreach ($validationArr as $key => $value) {
                    if ($value->status && $value->required) {
                        $validArr[$value->prefix] = 1;
                    }
                }

                if (isset($validArr['guardian_name'])) {
                    $this->form_validation->set_rules('grd_name', translate('name'), 'trim|required');
                }
                if (isset($validArr['guardian_relation'])) {
                    $this->form_validation->set_rules('grd_relation', translate('relation'), 'trim|required');
                }
                if (isset($validArr['father_name'])) {
                    $this->form_validation->set_rules('father_name', translate('father_name'), 'trim|required');
                }
                if (isset($validArr['mother_name'])) {
                    $this->form_validation->set_rules('mother_name', translate('mother_name'), 'trim|required');
                }
                if (isset($validArr['guardian_occupation'])) {
                    $this->form_validation->set_rules('grd_occupation', translate('occupation'), 'trim|required');
                }
                if (isset($validArr['guardian_income'])) {
                    $this->form_validation->set_rules('grd_income', translate('occupation'), 'trim|required|numeric');
                }
                if (isset($validArr['guardian_education'])) {
                    $this->form_validation->set_rules('grd_education', translate('education'), 'trim|required');
                }
                if (isset($validArr['guardian_email'])) {
                    $this->form_validation->set_rules('grd_email', translate('email'), 'trim|required');
                }
                if (isset($validArr['guardian_mobile_no'])) {
                    $this->form_validation->set_rules('grd_mobileno', translate('mobile_no'), 'trim|required|numeric');
                }
                if (isset($validArr['guardian_address'])) {
                    $this->form_validation->set_rules('grd_address', translate('address'), 'trim|required');
                }
                if (isset($validArr['guardian_photo'])) {
                    if (isset($_FILES["guardian_photo"]) && empty($_FILES["guardian_photo"]['name'])) {
                        $this->form_validation->set_rules('guardian_photo', translate('guardian_picture'), 'required');
                    }
                }
                if (isset($validArr['guardian_city'])) {
                    $this->form_validation->set_rules('grd_city', translate('city'), 'trim|required');
                }
                if (isset($validArr['guardian_state'])) {
                    $this->form_validation->set_rules('grd_state', translate('state'), 'trim|required');
                }

                if ($getBranch['grd_generate'] == 0) {
                    if (isset($validArr['grd_username'])) {
                        $this->form_validation->set_rules('grd_username', translate('username'), 'trim|required|callback_get_valid_guardian_username');
                    }
                    if (isset($validArr['grd_password'])) {
                        $this->form_validation->set_rules('grd_password', translate('password'), 'trim|required');
                        $this->form_validation->set_rules('grd_retype_password', translate('retype_password'), 'trim|required|matches[grd_password]');
                    }
                }
            } else {
                $this->form_validation->set_rules('parent_id', translate('guardian'), 'required');
            }
            if ($this->form_validation->run() == true) {
                $post = $this->input->post();
                //save all student information in the database file
                $studentData = $this->student_model->save($post, $getBranch);
                $studentID = $studentData['student_id'];
                //save student enroll information in the database file
                $arrayEnroll = array(
                    'student_id' => $studentID,
                    'class_id' => $post['class_id'],
                    'section_id' => $post['section_id'],
                    'roll' => (isset($post['roll']) ? $post['roll'] : 0),
                    'session_id' => $post['year_id'],
                    'branch_id' => $branchID,
                );
                $this->db->insert('enroll', $arrayEnroll);

                // handle custom fields data
                $class_slug = $this->router->fetch_class();
                $customField = $this->input->post("custom_fields[$class_slug]");
                if (!empty($customField)) {
                    saveCustomFields($customField, $studentID, $class_slug);
                }

                // send student admission email
                $this->email_model->studentAdmission($studentData);
                // send account activate sms
                $this->sms_model->send_sms($arrayEnroll, 1);

                set_alert('success', translate('information_has_been_saved_successfully'));
                $url = base_url('student/add');
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    /* csv file to import student information and stored in the database here */
    public function csv_import()
    {
        // check access permission
        if (!get_permission('multiple_import', 'is_add')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['save'])) {
            $err_msg = "";
            $i = 0;
            $this->load->library('csvimport');
            // form validation rules
            if (is_superadmin_loggedin() == true) {
                $this->form_validation->set_rules('branch_id', 'Branch', 'trim|required');
            }
            $this->form_validation->set_rules('class_id', 'Class', 'trim|required');
            $this->form_validation->set_rules('section_id', 'Section', 'trim|required');
            if (isset($_FILES["userfile"]) && empty($_FILES['userfile']['name'])) {
                $this->form_validation->set_rules('userfile', 'CSV File', 'required');
            }
            if ($this->form_validation->run() == true) {
                $classID = $this->input->post('class_id');
                $sectionID = $this->input->post('section_id');
                $csv_array = $this->csvimport->get_array($_FILES["userfile"]["tmp_name"]);
                if ($csv_array) {
                    $columnHeaders = array('FirstName','LastName','BloodGroup','Gender','Birthday','MotherTongue','Religion','Caste','Phone','City','State','PresentAddress','PermanentAddress','CategoryID','Roll','RegisterNo','AdmissionDate','StudentEmail','StudentUsername','StudentPassword','GuardianName','GuardianRelation','FatherName','MotherName','GuardianOccupation','GuardianMobileNo','GuardianAddress','GuardianEmail','GuardianUsername','GuardianPassword');
                    $csvData = array();
                    foreach ($csv_array as $row) {
                        if ($i == 0) {
                            $csvData = array_keys($row);
                        }
                        $csv_chk = array_diff($columnHeaders, $csvData);
                        if (count($csv_chk) <= 0) {
                            $schoolSettings = $this->student_model->get('branch', array('id' => $branchID), true, false, 'unique_roll');
                            $unique_roll = $schoolSettings['unique_roll'];

                            $r = $this->csvCheckExistsData($row['StudentUsername'], $row['Roll'], $row['RegisterNo'], $classID, $sectionID, $branchID, $unique_roll);
                            if ($r['status'] == false) {
                                $err_msg .= $row['FirstName'] . ' ' . $row['LastName'] . " - Imported Failed : " . $r['message'] . "<br>";
                            } else {
                                $this->student_model->csvImport($row, $classID, $sectionID, $branchID);
                                $i++;
                            }
                        } else {
                            set_alert('error', translate('invalid_csv_file'));
                            redirect(base_url("student/csv_import"));
                        }
                    }
                    if ($err_msg != null) {
                        $this->session->set_flashdata('csvimport', $err_msg);
                    }
                    if ($i > 0) {
                        set_alert('success', $i . ' Students Have Been Successfully Added');
                    }
                    redirect(base_url("student/csv_import"));
                } else {
                    set_alert('error', translate('invalid_csv_file'));
                    redirect(base_url("student/csv_import"));
                }
            }
        }
        $this->data['title'] = translate('multiple_import');
        $this->data['branch_id'] = $branchID;
        $this->data['sub_page'] = 'student/multi_add';
        $this->data['main_menu'] = 'admission';
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/dropify/css/dropify.min.css',
            ),
            'js' => array(
                'vendor/dropify/js/dropify.min.js',
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

    /* showing disable authentication student list */
    public function disable_authentication()
    {
        // check access permission
        if (!get_permission('student_disable_authentication', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $classID = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $this->data['students'] = $this->application_model->getStudentListByClassSection($classID, $sectionID, $branchID, true);
        }

        if (isset($_POST['auth'])) {
            if (!get_permission('student_disable_authentication', 'is_add')) {
                access_denied();
            }
            $stafflist = $this->input->post('views_bulk_operations');
            if (isset($stafflist)) {
                foreach ($stafflist as $id) {
                    $this->db->where(array('role' => 7, 'user_id' => $id));
                    $this->db->update('login_credential', array('active' => 1));

                    $this->db->where('id', $id);
                    $this->db->update('student', array('active' => 1));
                }
                set_alert('success', translate('information_has_been_updated_successfully'));
            } else {
                set_alert('error', 'Please select at least one item');
            }
            redirect(base_url('student/disable_authentication'));
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('deactivate_account');
        $this->data['sub_page'] = 'student/disable_authentication';
        $this->data['main_menu'] = 'student';
        $this->load->view('layout/index', $this->data);
    }

    // add new student category
    public function category()
    {
        if (isset($_POST['category'])) {
            if (!get_permission('student_category', 'is_add')) {
                access_denied();
            }
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('category_name', translate('category_name'), 'trim|required|callback_unique_category');
            if ($this->form_validation->run() !== false) {
                $arrayData = array(
                    'name' => $this->input->post('category_name'),
                    'branch_id' => $this->application_model->get_branch_id(),
                );
                $this->db->insert('student_category', $arrayData);
                set_alert('success', translate('information_has_been_saved_successfully'));
                redirect(base_url('student/category'));
            }
        }
        $this->data['title'] = translate('student') . " " . translate('details');
        $this->data['sub_page'] = 'student/category';
        $this->data['main_menu'] = 'admission';
        $this->load->view('layout/index', $this->data);
    }

    // update existing student category
    public function category_edit()
    {
        if (!get_permission('student_category', 'is_edit')) {
            ajax_access_denied();
        }
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('category_name', translate('category_name'), 'trim|required|callback_unique_category');
        if ($this->form_validation->run() !== false) {
            $category_id = $this->input->post('category_id');
            $arrayData = array(
                'name' => $this->input->post('category_name'),
                'branch_id' => $this->application_model->get_branch_id(),
            );
            $this->db->where('id', $category_id);
            $this->db->update('student_category', $arrayData);
            set_alert('success', translate('information_has_been_updated_successfully'));
            $array  = array('status' => 'success');
        } else {
            $error = $this->form_validation->error_array();
            $array = array('status' => 'fail','error' => $error);
        }
        echo json_encode($array);
    }

    // delete student category from database
    public function category_delete($id)
    {
        if (get_permission('student_category', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('student_category');
        }
    }

    // student category details send by ajax
    public function categoryDetails()
    {
        if (get_permission('student_category', 'is_edit')) {
            $id = $this->input->post('id');
            $this->db->where('id', $id);
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $query = $this->db->get('student_category');
            $result = $query->row_array();
            echo json_encode($result);
        }
    }

    /* validate here, if the check student category name */
    public function unique_category($name)
    {
        $branchID = $this->application_model->get_branch_id();
        $category_id = $this->input->post('category_id');
        if (!empty($category_id)) {
            $this->db->where_not_in('id', $category_id);
        }
        $this->db->where(array('name' => $name, 'branch_id' => $branchID));
        $uniform_row = $this->db->get('student_category')->num_rows();
        if ($uniform_row == 0) {
            return true;
        } else {
            $this->form_validation->set_message("unique_category", translate('already_taken'));
            return false;
        }
    }

    /* showing student list by class and section */
    public function view()
    {
        // check access permission
        if (!get_permission('student', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $classID = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $this->data['students'] = $this->application_model->getStudentListByClassSection($classID, $sectionID, $branchID, false, true);
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('student_list');
        $this->data['main_menu'] = 'student';
        $this->data['sub_page'] = 'student/view';
        $this->data['headerelements'] = array(
            'js' => array(
                'js/student.js'
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

   /* profile preview and information are updating here */
public function profile($id = '')
{
    /* -------------------- permissions -------------------- */
    if (!get_permission('student', 'is_edit')) {
        access_denied();
    }

    /* -------------------- models -------------------- */
    $this->load->model('fees_model');
    $this->load->model('exam_model');
    $this->load->model('report_model');
    if (!isset($this->Student_overview_model)) {
        $this->load->model('Student_overview_model');
    }
    // For "Committed vs Actual" + inline projection table
    $this->load->model('Projection_model');

/* -------------------- student -------------------- */
$id = (int)$id;

// Resolve to a valid student_id even if an enroll.id was passed.
$student_id = 0;

// 1) Assume it's already a student.id
$row = $this->db->select('id')->from('student')->where('id', $id)->limit(1)->get()->row_array();
if (!empty($row['id'])) {
    $student_id = (int)$row['id'];
} else {
    // 2) Try enroll.id
    $row = $this->db->select('student_id')->from('enroll')->where('id', $id)->limit(1)->get()->row_array();
    if (empty($row['student_id'])) {
        // 3) Some DBs used enroll.enroll_id as PK—support that too
        $row = $this->db->select('student_id')->from('enroll')->where('enroll_id', $id)->limit(1)->get()->row_array();
    }
    if (!empty($row['student_id'])) {
        $student_id = (int)$row['student_id'];
    }
}

if ($student_id <= 0) { show_404(); return; }

$getStudent = $this->student_model->getSingleStudent($student_id, true);

    /* -------------------- make sure $session_id exists BEFORE it’s used -------------------- */
    $session_id = (int) get_session_id();                                   // app helper (preferred)
    if ($session_id <= 0) $session_id = (int)($getStudent['session_id'] ?? 0);
    if ($session_id <= 0) {
        $session_id = (int)$this->db->select('session_id')
            ->from('enroll')
            ->where('student_id', $studentId)
            ->order_by('id', 'DESC')
            ->limit(1)->get()->row('session_id');
    }
    if ($session_id <= 0) $session_id = (int)($this->session->userdata('session_id') ?? 0);

    $branch_id  = (int) $this->application_model->get_branch_id();

    /* -------------------- overview notes (quarter-agnostic) -------------------- */
    $this->data['overview'] = $this->Student_overview_model->get_or_create($studentId, $session_id);

    /* -------------------- selected quarter (?q=) -------------------- */
    $q = $this->input->get('q');
    $selected_term = (is_numeric($q) ? (int)$q : 0);  // 0 = All, 1..4 = Q1..Q4
    if ($selected_term < 0 || $selected_term > 4) { $selected_term = 0; }
    $this->data['SELECTED_TERM'] = $selected_term;

    /* =================== Quarter metrics (Absences / Merits / Demerits / Avg pages) =================== */
    // Days absent per term
    $abs_by_term = $this->report_model->get_days_absent_by_term($studentId);
    $days_absent = ($selected_term >= 1 && $selected_term <= 4)
        ? (int) ($abs_by_term['q' . $selected_term] ?? 0)
        : (int) array_sum(is_array($abs_by_term) ? $abs_by_term : []);

    // Pull merits / demerits / pages from monitor_goal_check
    $qb = $this->db->select('attendance_status, merit, demerit, goals_json')
                   ->from('monitor_goal_check')
                   ->where('student_id', $studentId);
    if ($selected_term >= 1 && $selected_term <= 4) {
        $qb->where('term_id', $selected_term);
    }
    $rows = $qb->get()->result_array();

    $merits = 0; $demerits = 0;
    $total_pages = 0.0; $days_counted = 0;

    // helper: absent?
    $was_absent = function(array $r): bool {
        $att = strtoupper(trim((string)($r['attendance_status'] ?? '')));
        if ($att === 'A' || $att === 'ABSENT' || preg_match('/\bA\b/', $att)) return true;

        $gjstr = (string)($r['goals_json'] ?? '');
        if ($gjstr === '') return false;
        if (preg_match('/"att"\s*:\s*"A"/i', $gjstr)) return true;

        $gj = json_decode($gjstr, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($gj)) return false;
        $flag = false;
        array_walk_recursive($gj, function ($v, $k) use (&$flag) {
            if (strtolower((string)$k) === 'att' && strtoupper((string)$v) === 'A') $flag = true;
        });
        return $flag;
    };

    // helper: sum pages "done" regardless of key naming
    $sum_done_pages = function($gj): float {
        if (!is_array($gj)) return 0.0;
        $sum = 0.0;
        array_walk_recursive($gj, function ($v, $k) use (&$sum) {
            $lk = strtolower((string)$k);
            if (in_array($lk, ['done','d','pages','p','p_done','pages_done'], true) && is_numeric($v)) {
                $sum += (float)$v;
            }
        });
        return $sum;
    };

    foreach ($rows as $r) {
        $merits   += (int)($r['merit'] ?? 0);
        $demerits += (int)($r['demerit'] ?? 0);

        if ($was_absent($r)) continue; // skip absent days for avg pages/day

        $gj = json_decode((string)($r['goals_json'] ?? ''), true);
        $pages_today = $sum_done_pages($gj);
        if ($pages_today > 0) {
            $total_pages += $pages_today;
            $days_counted++;
        }
    }
    $avg_pages_per_day = $days_counted > 0 ? round($total_pages / $days_counted, 1) : 0.0;

    $this->data['term_metrics'] = [
        'days_absent'       => $days_absent,
        'merits'            => $merits,
        'demerits'          => $demerits,
        'avg_pages_per_day' => $avg_pages_per_day,
    ];
    /* =================== /Quarter metrics =================== */

    /* =================== Term PACE counts (Assigned / Completed / Below 80%) =================== */
    $this->db->select('status, first_attempt_score, second_attempt_score, term');
    $this->db->from('student_assign_paces');
    $this->db->where('student_id', $studentId);
    $this->db->where('session_id', $session_id);
    if ($selected_term >= 1 && $selected_term <= 4) {
        // column may store 1..4 or 'Q1'..'Q4'
        $this->db->group_start()
            ->where('term', $selected_term)
            ->or_where('term', 'Q' . $selected_term)
            ->or_where('term', 'q' . $selected_term)
        ->group_end();
    }
    $paceRows = $this->db->get()->result_array();

    $assigned_q  = count($paceRows);
    $completed_q = 0;
    $below80_q   = 0;

    foreach ($paceRows as $row) {
        $status = strtolower((string)($row['status'] ?? ''));
        if ($status === 'completed') $completed_q++;

        $s1 = (int)($row['first_attempt_score']  ?? 0);
        $s2 = (int)($row['second_attempt_score'] ?? 0);
        $best = max($s1, $s2);
        if ($best > 0 && $best < 80) $below80_q++;
    }

    if ($selected_term >= 1 && $selected_term <= 4) {
        if (!is_array($this->data['overview'])) $this->data['overview'] = [];
        $this->data['overview']['assigned_total']   = $assigned_q;
        $this->data['overview']['completed_total']  = $completed_q;
        $this->data['overview']['below80_total']    = $below80_q;
        $this->data['overview']['progress_percent'] = ($assigned_q > 0)
            ? round(($completed_q / $assigned_q) * 100)
            : 0;
    }
    /* =================== /Term PACE counts =================== */

    /* =================== Projection vs Actual (per-student) =================== */
    $y = $this->input->get('year');
    $proj_year = is_numeric($y) ? (int)$y : (int)date('Y');

    // subjects this student actually has for the chosen year (not just session)
    $assigned_subjects = $this->Projection_model->get_student_subjects($studentId, $session_id, $proj_year);

    $proj_labels    = [];
    $proj_committed = [];
    $proj_actual    = [];

    foreach ($assigned_subjects as $sub) {
        $sid = (int)$sub['id'];
        $proj_labels[]    = $sub['name'];
        // committed counts include ONLY approved projection rows (model handles approval flag)
        $proj_committed[] = $this->Projection_model->committed_count($studentId, $sid, $proj_year);
        $proj_actual[]    = $this->Projection_model->actual_completed_count($studentId, $sid, $session_id);
    }

    $this->data['proj_labels']            = $proj_labels;
    $this->data['proj_committed']         = $proj_committed;
    $this->data['proj_actual']            = $proj_actual;
    $this->data['proj_year']              = $proj_year;
    $this->data['proj_assigned_subjects'] = $assigned_subjects;

    // ----- Inline Projection Planner (role-aware) -----
    $role_id    = (int) $this->session->userdata('loggedin_role_id'); // adjust if your IDs differ
    $is_teacher = in_array($role_id, [3], true);
    $is_student = ($role_id === 7);
    $this->data['proj_can_edit'] = ($is_teacher || $is_student);
    $this->data['proj_student_edit_needs_approval'] = $is_student;

    $student_class_id   = (int) ($getStudent['class_id'] ?? 0);
    $student_section_id = (int) ($getStudent['section_id'] ?? 0);

    $this->data['proj_rows'] = $this->Projection_model->get_student_projection_rows(
        $branch_id,
        $studentId,
        $session_id,
        $student_class_id,
        $proj_year
    );

    // ---------- PACE options per subject (from catalog by class/grade/section) ----------
    $progress_map  = [];
    $completed_map = [];

    $this->data['proj_pace_options_map'] = $this->Projection_model
        ->build_planner_options_from_catalog(
            (int)$student_class_id,
            $this->data['proj_rows'],
            (int)$student_section_id
        );

    foreach ($this->data['proj_rows'] as $r) {
        $sid = (int)$r['subject_id'];
        $completed = (int)$this->Projection_model->actual_completed_count($studentId, $sid, $session_id);

        $planned = 0;
        if (isset($r['pacelist']) && is_array($r['pacelist'])) {
            $tmp = array_filter($r['pacelist'], function ($v) {
                return ((string)$v) !== '';
            });
            $planned = count($tmp);
        }

        $progress_map[$sid]  = ($planned > 0) ? min(100, (int)round(($completed / $planned) * 100)) : 0;
        $completed_map[$sid] = $completed;
    }

    $this->data['proj_progress_map']  = $progress_map;
    $this->data['proj_completed_map'] = $completed_map;

    /* =================== /Projection vs Actual =================== */

    /* -------------------- profile update (existing behaviour) -------------------- */
    if ($this->input->method() === 'post') {
        $this->session->set_flashdata('profile_tab', 1);
        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->student_validation();
        $this->form_validation->set_rules('parent_id', translate('guardian'), 'required');

        if ($this->form_validation->run() == true) {
            $post = $this->input->post();
            $studentID = $this->student_model->save($post);

            $arrayEnroll = array(
                'class_id'   => $this->input->post('class_id'),
                'section_id' => $this->input->post('section_id'),
                'roll'       => $this->input->post('roll'),
                'session_id' => $this->input->post('year_id'),
                'branch_id'  => $this->data['branch_id'],
            );
            $this->db->where('id', $getStudent['enrollid'])->update('enroll', $arrayEnroll);

            $class_slug  = $this->router->fetch_class();
            $customField = $this->input->post("custom_fields[$class_slug]");
            if (!empty($customField)) {
                saveCustomFields($customField, $id, $class_slug);
            }

            set_alert('success', translate('information_has_been_updated_successfully'));
            redirect(base_url('student/profile/' . $id));
        } else {
            set_alert('error', validation_errors());
        }
    }

    /* -------------------- view payload -------------------- */
    $this->data['student']   = $getStudent;
    $this->data['title']     = translate('student_profile');
    $this->data['sub_page']  = 'student/profile';
    $this->data['main_menu'] = 'student';
    $this->data['headerelements'] = array(
        'css' => array('vendor/dropify/css/dropify.min.css'),
        'js'  => array('js/student.js', 'vendor/dropify/js/dropify.min.js'),
    );

    $this->load->view('layout/index', $this->data);
}



    /* student information delete here */
    public function delete_data($eid = '', $sid = '')
    {
        if (get_permission('student', 'is_delete')) {
            $branchID = get_type_name_by_id('enroll', $eid, 'branch_id');
            // Check student restrictions
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('student_id', $sid)->delete('enroll');
            if ($this->db->affected_rows() > 0) {
                $this->db->where('id', $sid)->delete('student');
                $this->db->where(array('user_id' => $sid, 'role' => 7))->delete('login_credential');

                $r = $this->db->select('id')->where('student_id', $sid)->get('fee_allocation')->result_array();
                $this->db->where_in('student_id', $sid)->delete('fee_allocation');
                $r = array_column($r, 'id');
                if (!empty($r)) {
                    $this->db->where_in('allocation_id', $r)->delete('fee_payment_history');
                }

                $get_field = $this->db->where(array('form_to' => 'student', 'branch_id' => $branchID))->get('custom_field')->result_array();
                $field_id = array_column($get_field, 'id');
                $this->db->where('relid', $sid);
                $this->db->where_in('field_id', $field_id);
                $this->db->delete('custom_fields_values');
            }
        }
    }

    // student document details are create here / ajax
    public function document_create()
    {
        if (!get_permission('student', 'is_edit')) {
            ajax_access_denied();
        }
        $this->form_validation->set_rules('document_title', translate('document_title'), 'trim|required');
        $this->form_validation->set_rules('document_category', translate('document_category'), 'trim|required');
        if (isset($_FILES['document_file']['name']) && empty($_FILES['document_file']['name'])) {
            $this->form_validation->set_rules('document_file', translate('document_file'), 'required');
        }
        if ($this->form_validation->run() !== false) {
            $insert_doc = array(
                'student_id' => $this->input->post('patient_id'),
                'title' => $this->input->post('document_title'),
                'type' => $this->input->post('document_category'),
                'remarks' => $this->input->post('remarks'),
            );

            // uploading file using codeigniter upload library
            $config['upload_path'] = './uploads/attachments/documents/';
            $config['allowed_types'] = 'gif|jpg|png|pdf|docx|csv|txt';
            $config['max_size'] = '2048';
            $config['encrypt_name'] = true;
            $this->upload->initialize($config);
            if ($this->upload->do_upload("document_file")) {
                $insert_doc['file_name'] = $this->upload->data('orig_name');
                $insert_doc['enc_name'] = $this->upload->data('file_name');
                $this->db->insert('student_documents', $insert_doc);
                set_alert('success', translate('information_has_been_saved_successfully'));
            } else {
                set_alert('error', strip_tags($this->upload->display_errors()));
            }
            $this->session->set_flashdata('documents_details', 1);
            echo json_encode(array('status' => 'success'));
        } else {
            $error = $this->form_validation->error_array();
            echo json_encode(array('status' => 'fail', 'error' => $error));
        }
        
    }

    // student document details are update here / ajax
    public function document_update()
    {
        if (!get_permission('student', 'is_edit')) {
            ajax_access_denied();
        }
        // validate inputs
        $this->form_validation->set_rules('document_title', translate('document_title'), 'trim|required');
        $this->form_validation->set_rules('document_category', translate('document_category'), 'trim|required');
        if ($this->form_validation->run() !== false) {
            $document_id = $this->input->post('document_id');
            $insert_doc = array(
                'title' => $this->input->post('document_title'),
                'type' => $this->input->post('document_category'),
                'remarks' => $this->input->post('remarks'),
            );
            if (isset($_FILES["document_file"]) && !empty($_FILES['document_file']['name'])) {
                $config['upload_path'] = './uploads/attachments/documents/';
                $config['allowed_types'] = 'gif|jpg|png|pdf|docx|csv|txt';
                $config['max_size'] = '2048';
                $config['encrypt_name'] = true;
                $this->upload->initialize($config);
                if ($this->upload->do_upload("document_file")) {
                    $exist_file_name = $this->input->post('exist_file_name');
                    $exist_file_path = FCPATH . 'uploads/attachments/documents/' . $exist_file_name;
                    if (file_exists($exist_file_path)) {
                        unlink($exist_file_path);
                    }
                    $insert_doc['file_name'] = $this->upload->data('orig_name');
                    $insert_doc['enc_name'] = $this->upload->data('file_name');
                    set_alert('success', translate('information_has_been_updated_successfully'));
                } else {
                    set_alert('error', strip_tags($this->upload->display_errors()));
                }
            }
            $this->db->where('id', $document_id);
            $this->db->update('student_documents', $insert_doc);
            echo json_encode(array('status' => 'success'));
            $this->session->set_flashdata('documents_details', 1);
        } else {
            $error = $this->form_validation->error_array();
            echo json_encode(array('status' => 'fail', 'error' => $error));
        }
        
    }

    // student document details are delete here
    public function document_delete($id)
    {
        if (get_permission('student', 'is_edit')) {
            $enc_name = $this->db->select('enc_name')->where('id', $id)->get('student_documents')->row()->enc_name;
            $file_name = FCPATH . 'uploads/attachments/documents/' . $enc_name;
            if (file_exists($file_name)) {
                unlink($file_name);
            }
            $this->db->where('id', $id);
            $this->db->delete('student_documents');
            $this->session->set_flashdata('documents_details', 1);
        }
    }

    public function document_details()
    {
        $id = $this->input->post('id');
        $this->db->where('id', $id);
        $query = $this->db->get('student_documents');
        $result = $query->row_array();
        echo json_encode($result);
    }

    // file downloader
    public function documents_download()
    {
        $encrypt_name = urldecode($this->input->get('file'));
        if(preg_match('/^[^.][-a-z0-9_.]+[a-z]$/i', $encrypt_name)) {
            $file_name = $this->db->select('file_name')->where('enc_name', $encrypt_name)->get('student_documents')->row()->file_name;
            if (!empty($file_name)) {
                $this->load->helper('download');
                force_download($file_name, file_get_contents('./uploads/attachments/documents/' . $encrypt_name));
            }
        }
    }

    /* sample csv downloader */
    public function csv_Sampledownloader()
    {
        $this->load->helper('download');
        $data = file_get_contents('uploads/multi_student_sample.csv');
        force_download("multi_student_sample.csv", $data);
    }

    /* validate here, if the check multi admission  email and roll */
    public function some_function($unique_roll, $student_username = '')

    {
        $array = array();
        if (!empty($roll)) {

            if ($unique_roll != 0) {
                if ($unique_roll == 2) {
                    $this->db->where('section_id', $section_id);
                }
                $this->db->where(array('roll' => $roll, 'class_id' => $class_id, 'branch_id' => $branchID));
                $rollQuery = $this->db->get('enroll');
                if ($rollQuery->num_rows() > 0) {
                    $array['status'] = false;
                    $array['message'] = "Roll Already Exists.";
                    return $array;
                }
            }
        }
        if ($student_username !== '') {
            $this->db->where('username', $student_username);
            $query = $this->db->get_where('login_credential');
            if ($query->num_rows() > 0) {
                $array['status'] = false;
                $array['message'] = "Student Username Already Exists.";
                return $array;
            }
        }
        if ($registerno !== '') {
            $this->db->where('register_no', $registerno);
            $query = $this->db->get_where('student');
            if ($query->num_rows() > 0) {
                $array['status'] = false;
                $array['message'] = "Student Register No Already Exists.";
                return $array;
            }
        } else {
            $array['status'] = false;
            $array['message'] = "Register No Is Required.";
            return $array; 
        }

        $array['status'] = true;
        return $array;
    }

    // unique valid username verification is done here
    public function unique_username($username)
    {
        if ($this->input->post('student_id')) {
            $student_id = $this->input->post('student_id');
            $login_id = $this->app_lib->get_credential_id($student_id, 'student');
            $this->db->where_not_in('id', $login_id);
        }
        $this->db->where('username', $username);
        $query = $this->db->get('login_credential');
        if ($query->num_rows() > 0) {
            $this->form_validation->set_message("unique_username", translate('already_taken'));
            return false;
        } else {
            return true;
        }
    }

    /* unique valid guardian email address verification is done here */
    public function get_valid_guardian_username($username)
    {
        $this->db->where('username', $username);
        $query = $this->db->get('login_credential');
        if ($query->num_rows() > 0) {
            $this->form_validation->set_message("get_valid_guardian_username", translate('username_has_already_been_used'));
            return false;
        } else {
            return true;
        }
    }

    /* unique valid student roll verification is done here */
    public function unique_roll($roll)
    {
        if (empty($roll)) {
            return true;
        }
        $branchID = $this->application_model->get_branch_id();
        $schoolSettings = $this->student_model->get('branch', array('id' => $branchID), true, false, 'unique_roll');
        $unique_roll = $schoolSettings['unique_roll'];
        if (empty($unique_roll) && $unique_roll == 0) {
            return true;
        }

        $classID = $this->input->post('class_id');
        $sectionID = $this->input->post('section_id');
        if ($this->uri->segment(3)) {
            $studentID = $this->db->select('student_id')->where('id', $this->uri->segment(3))->get('enroll')->row()->student_id;
            $this->db->where_not_in('student_id', $studentID);
        }
        if ($unique_roll == 2) {
            $this->db->where('section_id', $sectionID);
        }
        $this->db->where(array('roll' => $roll, 'class_id' => $classID, 'branch_id' => $branchID, 'session_id' => get_session_id()));
        $q = $this->db->get('enroll')->num_rows();
        if ($q == 0) {
            return true;
        } else {
            $this->form_validation->set_message("unique_roll", translate('already_taken'));
            return false;
        }
    }

    /* unique valid register ID verification is done here */
    public function unique_registerid($register)
    {
        $branchID = $this->application_model->get_branch_id();
        if ($this->uri->segment(3)) {
            $studentID = $this->db->select('student_id')->where('id', $this->uri->segment(3))->get('enroll')->row()->student_id;
            $this->db->where_not_in('id', $studentID);
        }
        $this->db->where('register_no', $register);
        $query = $this->db->get('student')->num_rows();
        if ($query == 0) {
            return true;
        } else {
            $this->form_validation->set_message("unique_registerid", translate('already_taken'));
            return false;
        }
    }

    public function search()
    {
        // check access permission
        if (!get_permission('student', 'is_view')) {
            access_denied();
        }

        $search_text = $this->input->post('search_text');
        $this->data['query'] = $this->student_model->getSearchStudentList(trim($search_text));
        $this->data['title'] = translate('searching_results');
        $this->data['sub_page'] = 'student/search';
        $this->data['main_menu'] = '';
        $this->load->view('layout/index', $this->data);
    }

    /* student password change here */
    public function change_password()
    {
        if (get_permission('student', 'is_edit')) {
            if (!isset($_POST['authentication'])) {
                $this->form_validation->set_rules('password', translate('password'), 'trim|required|min_length[4]');
            } else {
                $this->form_validation->set_rules('date', translate('date'), 'trim|required');
                $this->form_validation->set_rules('reason_id', translate('disable_reason'), 'trim|required');
            }
            if ($this->form_validation->run() !== false) {
                $studentID = $this->input->post('student_id');
                $password = $this->input->post('password');
                if (!isset($_POST['authentication'])) {
                    $this->db->where('role', 7);
                    $this->db->where('user_id', $studentID);
                    $this->db->update('login_credential', array('password' => $this->app_lib->pass_hashed($password)));
                }else{
                    $this->db->where('role', 7);
                    $this->db->where('user_id', $studentID);
                    $this->db->update('login_credential', array('active' => 0));
                    
                    $this->db->where('id', $studentID);
                    $this->db->update('student', array('active' => 0));

                    // insert disable reason history in DB
                    $insertData = array(
                        'student_id' => $studentID, 
                        'reason_id' => $this->input->post('reason_id'), 
                        'note' => $this->input->post('note'), 
                        'date' => date("Y-m-d", strtotime($this->input->post('date'))), 
                    );
                    $this->db->insert('disable_reason_details', $insertData);
                }
                set_alert('success', translate('information_has_been_updated_successfully'));
                $array  = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        } 
    }

    // student quick details
    public function quickDetails()
    {
        $id = $this->input->post('student_id');
        $this->db->select('student.*,enroll.student_id,enroll.roll,student_category.name as cname');
        $this->db->from('enroll');
        $this->db->join('student', 'student.id = enroll.student_id', 'inner');
        $this->db->join('student_category', 'student_category.id = student.category_id', 'left');
        $this->db->where('enroll.id', $id);
        $row = $this->db->get()->row();
        $data['photo'] = get_image_url('student', $row->photo);
        $data['full_name'] = $row->first_name . " " . $row->last_name;
        $data['student_category'] = $row->cname;
        $data['register_no'] = $row->register_no;
        $data['roll'] = $row->roll;
        $data['admission_date'] = empty($row->admission_date) ? "N/A" : _d($row->admission_date);
        $data['birthday'] = empty($row->birthday) ? "N/A" : _d($row->birthday);
        $data['blood_group'] = empty($row->blood_group) ? "N/A" : $row->blood_group;
        $data['religion'] = empty($row->religion) ? "N/A" : $row->religion;
        $data['email'] = $row->email;
        $data['mobileno'] = empty($row->mobileno) ? "N/A" : $row->mobileno;
        $data['state'] = empty($row->state) ? "N/A" : $row->state;
        $data['address'] = empty($row->current_address) ? "N/A" : $row->current_address;
        echo json_encode($data);
    }

    public function bulk_delete()
    {
        $status = 'success';
        $message = translate('information_deleted');
        if (get_permission('student', 'is_delete')) {
            $arrayID = $this->input->post('array_id');
            foreach ($arrayID as $key => $row) {
                $branchID = get_type_name_by_id('enroll', $row, 'branch_id');
                $get_field = $this->db->where(array('form_to' => 'student', 'branch_id' => $branchID))->get('custom_field')->result_array();
                $field_id = array_column($get_field, 'id');
                $this->db->where('relid', $row);
                $this->db->where_in('field_id', $field_id);
                $this->db->delete('custom_fields_values');
            }

            $this->db->where_in('student_id', $arrayID)->delete('enroll');
            $this->db->where_in('id', $arrayID)->delete('student');
            $this->db->where_in('user_id', $arrayID)->where('role', 7)->delete('login_credential');

            $r = $this->db->select('id')->where_in('student_id', $arrayID)->get('fee_allocation')->result_array();
            $this->db->where_in('student_id', $arrayID)->delete('fee_allocation');
            $r = array_column($r, 'id');
            if (!empty($r)) {
                $this->db->where_in('allocation_id', $r)->delete('fee_payment_history');
            }
        } else {
            $message = translate('access_denied');
            $status = 'error';
        }
        echo json_encode(array('status' => $status, 'message' => $message));
    }


    /* student login credential list by class and section */
    public function login_credential_reports()
    {
        // check access permission
        if (!get_permission('student', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $classID = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $this->data['students'] = $this->application_model->getStudentListByClassSection($classID, $sectionID, $branchID, false, true);
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('login_credential');
        $this->data['main_menu'] = 'student_repots';
        $this->data['sub_page'] = 'student/login_credential_reports';
        $this->load->view('layout/index', $this->data);
    }

    public function password_reset($type)
    {
        if ($_POST) {
            $this->form_validation->set_rules('new_password', 'New Password', 'trim|required|min_length[4]');
            $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'trim|required|min_length[4]|matches[new_password]');
            if ($this->form_validation->run() == true) {
                $new_password = $this->input->post('new_password');
                if (!empty($type)) {
                    if ($type == 'student') {
                        $student_id = $this->input->post('student_id');
                        if (!is_superadmin_loggedin()) {
                            $chkID = $this->db->select('id')->where(['student_id' => $student_id, 'branch_id' => get_loggedin_branch_id()])->get('enroll')->row();
                            if (empty($chkID)) {
                                exit;
                            }
                        }
                        $this->db->where('user_id', $student_id);
                        $this->db->where('role', 7);
                    }
                    if ($type == 'parent') {
                        $parent_id = $this->input->post('parent_id');
                        if (!is_superadmin_loggedin()) {
                            $chkID = $this->db->select('id')->where(['id' => $parent_id, 'branch_id' => get_loggedin_branch_id()])->get('parent')->row();
                            if (empty($chkID)) {
                                exit;
                            }
                        }
                        $this->db->where('user_id', $parent_id);
                        $this->db->where('role', 6);
                    }
                    $this->db->update('login_credential', array('password' => $this->app_lib->pass_hashed($new_password)));
                }
                set_alert('success', translate('information_has_been_updated_successfully'));
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    /* student admission list by date */
    public function admission_reports()
    {
        // check access permission
        if (!get_permission('student', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $classID = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $daterange = explode(' - ', $this->input->post('daterange'));
            $start = date("Y-m-d", strtotime($daterange[0]));
            $end = date("Y-m-d", strtotime($daterange[1]));
            $this->data['start'] = $start;
            $this->data['end'] = $end;
            $this->data['students'] = $this->student_model->getStudentList($classID, $sectionID, $branchID, false, $start, $end)->result_array();
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('admission_reports');
        $this->data['main_menu'] = 'student_repots';
        $this->data['sub_page'] = 'student/admission_reports';
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/daterangepicker/daterangepicker.css',
            ),
            'js' => array(
                'vendor/moment/moment.js',
                'vendor/daterangepicker/daterangepicker.js',
            ),
        ); 
        $this->load->view('layout/index', $this->data);
    }

    public function classsection_reports()
    {
        // check access permission
        if (!get_permission('student', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('class_&_section');
        $this->data['main_menu'] = 'student_repots';
        $this->data['sub_page'] = 'student/classsection_reports';
        $this->load->view('layout/index', $this->data);
    }

    // add new student deactivate reason
    public function disable_reason()
    {
        if (isset($_POST['disable_reason'])) {
            if (!get_permission('disable_reason', 'is_add')) {
                access_denied();
            }
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('name', translate('reason'), 'trim|required');
            if ($this->form_validation->run() !== false) {
                $arrayData = array(
                    'name' => $this->input->post('name'),
                    'branch_id' => $this->application_model->get_branch_id(),
                );
                $this->db->insert('disable_reason', $arrayData);
                set_alert('success', translate('information_has_been_saved_successfully'));
                redirect(base_url('student/disable_reason'));
            }
        }
        $this->data['title'] = translate('deactivate_reason');
        $this->data['categorylist'] = $this->app_lib->getTable('disable_reason');
        $this->data['sub_page'] = 'student/disable_reason';
        $this->data['main_menu'] = 'student';
        $this->load->view('layout/index', $this->data);
    }

    // update existing student deactivate reason
    public function disable_reason_edit()
    {
        if (!get_permission('disable_reason', 'is_edit')) {
            ajax_access_denied();
        }
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('name', translate('reason'), 'trim|required');
        if ($this->form_validation->run() !== false) {
            $category_id = $this->input->post('reason_id');
            $arrayData = array(
                'name' => $this->input->post('name'),
                'branch_id' => $this->application_model->get_branch_id(),
            );
            $this->db->where('id', $category_id);
            $this->db->update('disable_reason', $arrayData);
            set_alert('success', translate('information_has_been_updated_successfully'));
            $array  = array('status' => 'success');
        } else {
            $error = $this->form_validation->error_array();
            $array = array('status' => 'fail','error' => $error);
        }
        echo json_encode($array);
    }

    // delete student deactivate reason from database
    public function disable_reason_delete($id)
    {
        if (get_permission('disable_reason', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('disable_reason');
        }
    }

    // student disable reason details send by ajax
    public function disableReasonDetails()
    {
        if (get_permission('disable_reason', 'is_edit')) {
            $id = $this->input->post('id');
            $this->db->where('id', $id);
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $query = $this->db->get('disable_reason');
            $result = $query->row_array();
            echo json_encode($result);
        }
    }

    public function sibling_report()
    {
        // check access permission
        if (!get_permission('student', 'is_view')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $classID = $this->input->post('class_id');
            $sectionID = $this->input->post('section_id');
            $getParentsList = $this->student_model->getParentList($classID, $sectionID, $branchID);
            $list = array();
            foreach ($getParentsList as $key => $parent) {
                if (intval($parent['child']) > 1) {
                    $getParentsList[$key]['student'] = $this->student_model->getSiblingListByClass($parent['parent_id'], $classID, $sectionID);
                    $list[] = $getParentsList[$key];
                }
            }
            $this->data['students'] = $list;
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('sibling_report');
        $this->data['main_menu'] = 'student_repots';
        $this->data['sub_page'] = 'student/sibling_report';
        $this->load->view('layout/index', $this->data);
    }

public function save_overview_field()
{
    if (!$this->input->is_ajax_request()) show_404();

    if (!get_permission('student', 'is_edit')) {
        return $this->output->set_content_type('application/json')
            ->set_output(json_encode(['success' => false, 'msg' => translate('access_denied')]));
    }

    $student_id = (int)$this->input->post('student_id');
    $session_id = (int)get_session_id();
    $field      = (string)$this->input->post('field');
    $value      = trim((string)$this->input->post('value'));

    $ok = $this->Student_overview_model->save_field(
        $student_id,
        $session_id,
        $field,
        $value,
        (int)$this->session->userdata('loggedin_user_id')
    );

    // refresh CSRF
    $csrf = [
        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
    ];

    return $this->output->set_content_type('application/json')
        ->set_output(json_encode(['success' => $ok, 'value' => $value] + $csrf));
}

// --- validation callback for student profile picture upload ---
public function photoHandleUpload($str, $field)
{
    // Allow empty (no new photo chosen)
    if (empty($_FILES[$field]['name'])) {
        return true;
    }

    $this->load->library('upload');

    $uploadPath = FCPATH . 'uploads/images/student/';
    if (!is_dir($uploadPath)) {
        @mkdir($uploadPath, 0755, true);
    }

    $config = array(
        'upload_path'   => $uploadPath,
        'allowed_types' => 'jpg|jpeg|png|gif',
        'max_size'      => 2048,
        'encrypt_name'  => true,
    );
    $this->upload->initialize($config);

    if (!$this->upload->do_upload($field)) {
        $this->form_validation->set_message('photoHandleUpload', trim($this->upload->display_errors('', '')));
        return false;
    }

    $data = $this->upload->data();
    $_POST['user_photo'] = $data['file_name'];
    return true;
}

public function unique_username_if_present($username)
{
    // Allow empty (optional username)
    if ($username === null || $username === '') {
        return true;
    }

    // If you have a login_credential row id in the form, pass it so update excludes self
    $login_id = (int)($this->input->post('login_id') ?? 0);

    // Use your model to check uniqueness; implement if not present
    $exists = $this->db->where('username', $username)
                       ->where('id !=', $login_id)
                       ->limit(1)
                       ->get('login_credential')
                       ->num_rows() > 0;

    if ($exists) {
        $this->form_validation->set_message('unique_username_if_present', translate('this_username_already_exists'));
        return false;
    }
    return true;
}
public function pace_numbers()
{
    if (!get_permission('student', 'is_edit')) show_error('No permission', 403);
    $student_id = (int)$this->input->get('student_id');
    $subject_id = (int)$this->input->get('subject_id');

    $this->load->model('Projection_model');
    $opts = $this->Projection_model->pace_numbers_for_subject($student_id, $subject_id);

    $ret = [
        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        'status'  => true,
        'options' => array_values($opts),
    ];
    return $this->output->set_content_type('application/json')->set_output(json_encode($ret));
}


// Returns JSON: { ok:true, options:[1013,1014,...] }
public function pace_options()
{
    if (!$this->input->is_ajax_request()) show_404();

    $studentId = (int)$this->input->get('student_id');
    $subjectId = (int)$this->input->get('subject_id');

    // TODO: replace the logic below with YOUR grade/phase rules.
    // Example: look up student's class/phase and map to a PACE range.
    $student = $this->student_model->getSingleStudent($studentId, true);
    $classId = (int)($student['class_id'] ?? 0);

    // Example mapping table (fallback): pull from DB if you have subject-grade ranges
    // Here we try subject_pace_ranges: subject_id, class_id, start_pace, end_pace
    $range = $this->db->get_where('subject_pace_ranges', [
        'subject_id' => $subjectId,
        'class_id'   => $classId
    ])->row_array();

    $start = (int)($range['start_pace'] ?? 0);
    $end   = (int)($range['end_pace'] ?? 0);

    $options = [];
    if ($start > 0 && $end >= $start) {
        for ($i = $start; $i <= $end; $i++) $options[] = (string)$i;
    }

    // If no mapping exists, fall back to an empty list (or a sensible default)
    $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode(['ok' => true, 'options' => $options]));
}

public function save_projection()
{
    $this->output->set_content_type('application/json');

    if (strtolower($this->input->method()) !== 'post') {
        return $this->_json_fail('Invalid request method');
    }

    $student_id = (int)$this->input->post('student_id', true);
    $year       = (int)$this->input->post('year', true);
    $posted     = $this->input->post('projections', true); // projections[subject_id][p1..p12]

    // NEW: Pages Planner fields
    $avg_pages  = $this->input->post('planner_avg_pages', true);
    $weeks_left = $this->input->post('planner_weeks_left', true);

    if ($student_id <= 0 || $year <= 0) {
        return $this->_json_fail('Missing student or year');
    }
    if (!is_array($posted)) $posted = [];

    // Normalize grid
    $grid = [];
    foreach ($posted as $sid => $slots) {
        $sid = (int)$sid;
        if ($sid <= 0) continue;
        $row = [];
        for ($i = 1; $i <= 12; $i++) {
            $k = 'p'.$i;
            $v = $slots[$k] ?? '';
            $row[$k] = ($v !== '' && is_numeric($v)) ? (int)$v : '';
        }
        $grid[$sid] = $row;
    }

    $this->db->trans_start();
    // Save grid (existing)
    $ok1 = $this->Projection_model->save_projection_grid($student_id, $year, $grid);
    // Save meta (NEW)
    $ok2 = $this->Projection_model->save_projection_meta($student_id, $year, [
        'avg_pages_per_pace' => is_numeric($avg_pages) ? (int)$avg_pages : '',
        'weeks_left'         => is_numeric($weeks_left) ? (int)$weeks_left : '',
    ]);
    $this->db->trans_complete();

    if (!$ok1 || !$ok2 || $this->db->trans_status() === false) {
        $err = $this->db->error();
        log_message('error', 'save_projection failed: '.($err['message'] ?? 'unknown').' :: student='.$student_id.' year='.$year.' rows='.count($grid));
        return $this->_json_fail('Could not save projection');
    }

    return $this->_json_ok(['saved' => true]);
}

/* ---------- JSON helpers (leave as-is if you already have equivalents) ---------- */
private function _json_ok($data = [])
{
    $data = is_array($data) ? $data : [];
    $data['status'] = 1;
    $data[$this->security->get_csrf_token_name()] = $this->security->get_csrf_hash();
    return $this->output->set_output(json_encode($data));
}
private function _json_fail($msg)
{
    $out = [
        'status'  => 0,
        'message' => $msg,
        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
    ];
    return $this->output->set_output(json_encode($out));
}

}
