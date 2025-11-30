<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Teacher_subjects extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('student_model');
        $this->load->model('user_model');
        $this->load->model('pace_model'); // for get_all_students() when Admin
    }

    /** Assign Subjects screen (Teachers + Admins). */
    public function index()
    {
        $role_id = (int)$this->session->userdata('loggedin_role_id');
        // Allow: Super Admin (1), Admin (2), Teacher (3)
        if (!in_array($role_id, [1, 2, 3], true)) {
            return access_denied();
        }

        $teacher_id            = (int)$this->session->userdata('loggedin_userid');
        $this->data['title']   = 'Assign Subjects';
        $this->data['main_menu'] = 'pace';
        $this->data['sub_page']  = 'teacher/assign_subjects';

        // Students list: Teachers → only theirs; Admins → all active students
        $students = ($role_id === 3)
            ? $this->student_model->get_my_students($teacher_id)
            : $this->pace_model->get_all_students();
        $this->data['students'] = $students;

        // Filters
        $student_id = (int)$this->input->get('student_id');
        $year       = (int)($this->input->get('year') ?: date('Y'));
        $this->data['year'] = $year;

        $this->data['subject_options'] = [];
        $this->data['selected_ids']    = [];

        if ($student_id > 0) {
            // Latest class for the student (grade)
            $stuRow = $this->db->select('class_id')
                ->from('enroll')
                ->where('student_id', $student_id)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get()->row_array();
            $class_id = $stuRow ? (int)$stuRow['class_id'] : 0;

            // Only subjects assigned to that class (falls back to subject_pace by grade if needed)
            $this->data['subject_options'] = $this->student_model->get_subjects_for_grade($class_id);

            // Previously assigned subjects for this student & year/session
            $this->data['selected_ids'] = $this->student_model->get_assigned_subject_ids($student_id, $year);
        }

        $this->load->view('layout/index', $this->data);
    }

    /** Persist assigned subjects (Teachers + Admins). */
    public function save()
    {
        $role_id = (int)$this->session->userdata('loggedin_role_id');
        // Allow: Super Admin (1), Admin (2), Teacher (3)
        if (!in_array($role_id, [1, 2, 3], true)) {
            return access_denied();
        }

        $teacher_id  = (int)$this->session->userdata('loggedin_userid');
        $student_id  = (int)$this->input->post('student_id');
        $year        = (int)$this->input->post('year');
        $subject_ids = (array)$this->input->post('subject_ids');

        // Teachers: restrict to their own students; Admins bypass this check
        if ($role_id === 3) {
            $allowed = array_column($this->student_model->get_my_students($teacher_id), 'student_id');
            if (!in_array($student_id, $allowed, true)) {
                return access_denied();
            }
        }

        $ok = $this->student_model->save_assigned_subjects($student_id, $subject_ids, $year, $teacher_id);
        if ($ok) {
            set_alert('success', 'Subjects assigned.');
        } else {
            set_alert('error', 'Could not save subjects.');
        }

        redirect(base_url('teacher_subjects?student_id=' . $student_id . '&year=' . $year));
    }
}
