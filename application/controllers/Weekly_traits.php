<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Weekly_traits extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('weekly_traits_model');
        $this->load->model('student_model');
        // NOTE: do NOT load a view in the constructor.
    }

    public function index()
    {
        $session_id = get_session_id();
        $branch_id  = get_loggedin_branch_id();

        // inputs
        $term    = (int)($this->input->get('term') ?: 1);
        if ($term < 1 || $term > 4) $term = 1;

        $week_no = (int)($this->input->get('week_no') ?: 1);
        if ($week_no < 1 || $week_no > 11) $week_no = 1;

        $per_page = 6;
        $page     = max(1, (int)($this->input->get('p') ?: 1));
        $offset   = ($page - 1) * $per_page;

        // --- Scope to "my students" when possible
        $allStudents = $this->student_model->get_all_students(null, null, $session_id);
        $myIds       = $this->my_student_ids($session_id);
        $students    = ($myIds)
            ? array_values(array_filter($allStudents, function ($s) use ($myIds) {
                return in_array((int)$s['student_id'], $myIds, true);
            }))
            : (is_array($allStudents) ? $allStudents : []);

        // 4-at-a-time slice
        $total          = count($students);
        $students_page  = array_slice($students, $offset, $per_page);

        // trait definitions
        $traits_def = $this->weekly_traits_model->get_traits_definition();

        // scores per student in this page
        $scores = [];
        foreach ($students_page as $s) {
            $sid = (int)$s['student_id'];
            $scores[$sid] = $this->weekly_traits_model->get_scores($sid, $session_id, $term, $week_no);
        }

        // layout wiring + page data
        $this->data['main_menu']        = 'learningcentre';
        $this->data['sub_page']         = 'weekly_traits/index'; // <- your new view
        $this->data['title']            = 'Weekly Traits';
        $this->data['term']             = $term;
        $this->data['week_no']          = $week_no;

        // for pagination controls
        $this->data['per_page']         = $per_page;
        $this->data['page']             = $page;
        $this->data['total_students']   = $total;

        // view data
        $this->data['students_page']    = $students_page;   // the 4 students shown
        $this->data['traits_def']       = $traits_def;
        $this->data['scores']           = $scores;          // [student_id] => score rows

        $this->load->view('layout/index', $this->data);
    }

    // AJAX upsert
    public function save()
    {
        if (!$this->input->is_ajax_request()) show_404();

        $session_id = get_session_id();
        $student_id = (int)$this->input->post('student_id');

        // deny saving for students not assigned to this teacher
        $myIds = $this->my_student_ids($session_id);
        if ($myIds && !in_array($student_id, $myIds, true)) {
            return $this->output->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'error', 'message' => 'Not allowed for this student.']));
        }

        $ok = $this->weekly_traits_model->save_score([
            'session_id' => $session_id,
            'branch_id'  => get_loggedin_branch_id(),
            'student_id' => $student_id,
            'teacher_id' => is_loggedin() ? get_loggedin_user_id() : null,
            'term'       => (int)$this->input->post('term'),
            'week_no'    => (int)$this->input->post('week_no'),
            'category'   => trim($this->input->post('category')),
            'trait_key'  => trim($this->input->post('trait_key')),
            'score'      => $this->input->post('score') === '' ? null : (int)$this->input->post('score'),
        ]);

        $this->output->set_content_type('application/json')
            ->set_output(json_encode(['status' => $ok ? 'success' : 'error']));
    }

    /**
     * Returns an array of student_ids the current user (teacher) is responsible for in the given session.
     * Tries multiple common schemas; if none found, returns [] (falls back to "all students" in index()).
     */
    private function my_student_ids(int $session_id): array
    {
        $teacher_id = is_loggedin() ? (int)get_loggedin_user_id() : 0;
        if ($teacher_id <= 0) return [];

        $ids = [];
        $push = function(array $rows) use (&$ids) {
            foreach ($rows as $r) { $ids[(int)$r['student_id']] = true; }
        };

        // 1) subject_assign (common in Ramom)
        if ($this->db->table_exists('subject_assign') && $this->db->table_exists('enroll')) {
            $tcol = $this->db->field_exists('teacher_id','subject_assign') ? 'teacher_id'
                  : ($this->db->field_exists('staff_id','subject_assign') ? 'staff_id' : null);
            if ($tcol) {
                $rows = $this->db->select('DISTINCT e.student_id', false)
                    ->from('subject_assign sa')
                    ->join('enroll e', 'e.class_id=sa.class_id AND e.section_id=sa.section_id', 'inner')
                    ->where("sa.$tcol", $teacher_id)
                    ->where('e.session_id', $session_id)
                    ->get()->result_array();
                $push($rows);
            }
        }

        // 2) class_teacher mapping
        if ($this->db->table_exists('class_teacher') && $this->db->table_exists('enroll')) {
            $tcol = $this->db->field_exists('teacher_id','class_teacher') ? 'teacher_id'
                  : ($this->db->field_exists('staff_id','class_teacher') ? 'staff_id' : null);
            if ($tcol) {
                $rows = $this->db->select('DISTINCT e.student_id', false)
                    ->from('class_teacher ct')
                    ->join('enroll e', 'e.class_id=ct.class_id AND (ct.section_id IS NULL OR e.section_id=ct.section_id)', 'inner')
                    ->where("ct.$tcol", $teacher_id)
                    ->where('e.session_id', $session_id)
                    ->get()->result_array();
                $push($rows);
            }
        }

        // 3) teacher_allocation (various names)
        foreach (['teacher_allocation','assign_class_teacher','staff_class_assign'] as $tbl) {
            if ($this->db->table_exists($tbl) && $this->db->table_exists('enroll')) {
                $tcol = $this->db->field_exists('teacher_id',$tbl) ? 'teacher_id'
                      : ($this->db->field_exists('staff_id',$tbl) ? 'staff_id' : null);
                $ccol = $this->db->field_exists('class_id',$tbl) ? 'class_id' : null;
                $scol = $this->db->field_exists('section_id',$tbl) ? 'section_id' : null;
                if ($tcol && $ccol) {
                    $join = "e.class_id = a.$ccol";
                    if ($scol) $join .= " AND (a.$scol IS NULL OR e.section_id = a.$scol)";
                    $rows = $this->db->select('DISTINCT e.student_id', false)
                        ->from("$tbl a")
                        ->join('enroll e', $join, 'inner')
                        ->where("a.$tcol", $teacher_id)
                        ->where('e.session_id', $session_id)
                        ->get()->result_array();
                    $push($rows);
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }
}
