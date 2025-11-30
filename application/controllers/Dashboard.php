<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 5.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Dashboard.php
 */

class Dashboard extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('dashboard_model');
    }

    public function index()
    {
        // --- Student / Parent (use dedicated views) --------------------------
        if (is_student_loggedin()) {
            $this->data['title']      = translate('welcome_to') . ' ' . $this->session->userdata('name');
            $this->data['student_id'] = get_loggedin_user_id();
            $this->data['school_id']  = get_loggedin_branch_id();
            $this->data['sub_page']   = 'dashboard/student_view';
        } elseif (is_parent_loggedin()) {
            $studentID = $this->session->userdata('myChildren_id');
            if (!empty($studentID)) {
                $this->data['title'] = get_type_name_by_id('student', $studentID, 'first_name') . ' - ' . translate('dashboard');
            } else {
                $this->data['title'] = translate('welcome_to') . ' ' . $this->session->userdata('name');
            }
            $this->data['student_id'] = (int) $studentID;
            $this->data['school_id']  = get_loggedin_branch_id();
            $this->data['sub_page']   = 'dashboard/parent_view';
        } else {
            // --- Staff (admins, teachers, principal, etc.) -------------------
            $role_id = (int) $this->session->userdata('loggedin_role_id');
            $user_id = (int) $this->session->userdata('user_id');

            switch ($role_id) {
                // Super Admin / Admin (keep as-is)
                case 1:
                case 2:
                    $this->data['sub_page'] = 'dashboard/admin';
                    break;

                // Teacher (your custom block preserved)
                case 3:
                    $this->data['sub_page'] = 'dashboard/teacher';
                    $this->load->model('student_model');

                    $teacher_id = get_loggedin_user_id();
                    $branch_id  = (int) get_loggedin_branch_id();                 // add this
                    $students   = $this->student_model->get_students_for_teacher($teacher_id, 0, $branch_id); // pass all 3 args
                    $student_ids = array_column($students, 'id');

                    $this->data['my_students_count'] = count($student_ids);
                    $this->data['my_students']       = $students;
                    $this->data['pending_scores']    = $this->dashboard_model->get_pending_scores($student_ids);
                    break;

                // Principal (6) -> use staff dashboard
                case 6:
                    $this->data['sub_page'] = 'dashboard/admin'; // or 'dashboard/principal' if you add one
                    break;

                // Parent (9) -> parent dashboard (for safety if a helper is reused)
                case 9:
                    $this->data['sub_page'] = 'dashboard/parent';
                    break;

                // Student (7) here is a fallback (normally caught above)
                case 7:
                    $this->data['sub_page'] = 'dashboard/student';
                    break;

                // Everyone else -> generic staff dashboard
                default:
                    $this->data['sub_page'] = 'dashboard/index';
                    break;
            }

            // ----- Branch title and common dashboard data --------------------
            if (is_superadmin_loggedin()) {
                if ($this->input->get('school_id')) {
                    $schoolID            = (int) $this->input->get('school_id');
                    $this->data['title'] = get_type_name_by_id('branch', $schoolID) . ' ' . translate('branch_dashboard');
                } else {
                    $schoolID            = null;
                    $this->data['title'] = translate('all_branch_dashboard');
                }
            } else {
                $schoolID            = get_loggedin_branch_id();
                $this->data['title'] = get_type_name_by_id('branch', $schoolID) . ' ' . translate('branch_dashboard');
            }

            $this->data['school_id'] = $schoolID;

            $getSQLMode              = $this->application_model->getSQLMode();
            $this->data['sqlMode']   = $getSQLMode;
            $this->data['fees_summary'] = ($getSQLMode == false)
                ? $this->dashboard_model->annualFeessummaryCharts($schoolID)
                : ['total_fee' => 0, 'total_paid' => 0, 'total_due' => 0];

            $this->data['student_by_class']  = $this->dashboard_model->getStudentByClass($schoolID);
            $this->data['income_vs_expense'] = $this->dashboard_model->getIncomeVsExpense($schoolID);
            $this->data['weekend_attendance']= $this->dashboard_model->getWeekendAttendance($schoolID);
            $this->data['get_monthly_admission'] = $this->dashboard_model->getMonthlyAdmission($schoolID);
            $this->data['get_voucher']       = $this->dashboard_model->getVoucher($schoolID);
            $this->data['get_transport_route']= $this->dashboard_model->get_transport_route($schoolID);
            $this->data['get_total_student'] = $this->dashboard_model->get_total_student($schoolID);

            $this->data['student_count'] = ($schoolID)
                ? $this->db->where('branch_id', $schoolID)->count_all_results('enroll')
                : $this->db->count_all_results('enroll');

            $this->data['teacher_count'] = ($schoolID)
                ? $this->db->where(['branch_id' => $schoolID, 'role' => 3])->count_all_results('login_credential')
                : $this->db->where('role', 3)->count_all_results('login_credential');

            $this->data['parent_count'] = ($schoolID)
                ? $this->db->where('branch_id', $schoolID)->count_all_results('parent')
                : $this->db->count_all_results('parent');
        }

        // Assets / language
        $language = 'en';
        $jsArray = [
            'vendor/chartjs/chart.min.js',
            'vendor/echarts/echarts.common.min.js',
            'vendor/moment/moment.js',
            'vendor/fullcalendar/fullcalendar.js',
        ];
        if ($this->session->userdata('set_lang') != 'english') {
            $language   = $this->dashboard_model->languageShortCodes($this->session->userdata('set_lang'));
            $jsArray[]  = "vendor/fullcalendar/locale/$language.js";
        }

        $this->data['headerelements'] = [
            'css' => ['vendor/fullcalendar/fullcalendar.css'],
            'js'  => $jsArray,
        ];
        $this->data['language']  = $language;
        $this->data['main_menu'] = 'dashboard';

        $this->load->view('layout/index', $this->data);
    }
}
