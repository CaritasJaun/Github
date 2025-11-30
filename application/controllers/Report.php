<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Report extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('report_model');
        $this->load->helper('report'); // convert_to_symbol()
    }

    /// Progress report for one student / one year
public function progress_report()
{
    $this->load->model('student_model');
    $this->load->model('report_model');
    $this->load->model('weekly_traits_model');
    $this->load->model('Spc_model');

    $session_id = get_session_id();                 // ← year in your system
    $student_id = (int)$this->input->get('student_id', true);

    /* ───────────────────────────── NEW: resolve term & role flags ───────────────────────────── */
    $term = (int)$this->input->get('term', true);
    if ($term < 1 || $term > 4) { // default current quarter
        $m = (int)date('n');
        $term = ($m <= 3 ? 1 : ($m <= 6 ? 2 : ($m <= 9 ? 3 : 4)));
    }

    $roleRaw     = $this->session->userdata('role');
    $isTeacher   = (function_exists('is_teacher_loggedin')   && is_teacher_loggedin())
                || strtolower((string)$roleRaw) === 'teacher'   || (int)$roleRaw === 3;
    $isPrincipal = (function_exists('is_principal_loggedin') && is_principal_loggedin())
                || strtolower((string)$roleRaw) === 'principal' || (int)$roleRaw === 6;
    $isParent    = (function_exists('is_parent_loggedin')    && is_parent_loggedin())
                || strtolower((string)$roleRaw) === 'parent'    || (int)$roleRaw === 4 || (int)$roleRaw === 7;
    /* ───────────────────────────────────────────────────────────────────────────────────────── */

    // --- Build the student list, restricted for teachers ---
    $allStudents = $this->student_model->get_all_students(null, null, $session_id);
    $students    = $allStudents;

    if ($isTeacher) {
        $myIds = $this->my_student_ids($session_id); // class/subject mappings

        $students = array_values(array_filter($allStudents, function ($s) use ($myIds) {
            return in_array((int)$s['student_id'], $myIds, true);
        }));

        $isAllowed = !empty($student_id) && array_filter($students, fn($s) => (int)$s['student_id'] === (int)$student_id);
        if (!$isAllowed) {
            $student_id = !empty($students) ? (int)$students[0]['student_id'] : null;
        }
    } else {
        $isAllowed = !empty($student_id) && array_filter($allStudents, fn($s) => (int)$s['student_id'] === (int)$student_id);
        if (!$isAllowed) {
            $student_id = !empty($allStudents) ? (int)$allStudents[0]['student_id'] : null;
        }
    }

    $isAllowed = !empty($student_id) && array_filter($students, fn($s) => (int)$s['student_id'] === (int)$student_id);
    if (!$isAllowed) {
        $student_id = !empty($students) ? (int)$students[0]['student_id'] : null;
    }

    // ── NEW: progress counters (Assigned / Completed / Below 80 by first attempt)
    $progress_counters = !empty($student_id)
        ? $this->student_model->get_student_pace_progress((int)$student_id)
        : ['assigned' => 0, 'completed' => 0, 'below80' => 0];

    /* ────────────────────── NEW: default view payload incl. workflow ─────────────────────── */
    $workflow = [];
    $parent_locked = false;   // parent cannot see until principal completes
    $print_allowed = false;   // teacher can print after completion

    if (!empty($student_id)) {
        // ensure a row exists for this cycle (student/term/year)
        $workflow = $this->report_model->get_workflow((int)$student_id, (int)$term, (int)$session_id);
        $parent_locked = ($isParent && !empty($workflow) && $workflow['status'] !== 'completed');
        $print_allowed = ($isTeacher && !empty($workflow) && $workflow['status'] === 'completed');
    }
    /* ─────────────────────────────────────────────────────────────────────────────────────── */

    $data = [
        'students'            => $students,
        'selected_student'    => $student_id,
        'year'                => $session_id,
        'term'                => $term,            // ← NEW (used by workflow actions)
        'subjects'            => [],
        'comments'            => [],
        'attendance'          => [],
        'scriptures'          => [],
        'reading'             => [],
        'traits'              => [],
        'general_assignments' => [],
        'traits_term_avg'     => [],
        'elective_alias'      => [],
        'progress_counters'   => $progress_counters,
        'workflow'            => $workflow,        // ← NEW
        'parent_locked'       => $parent_locked,   // ← NEW (view can hide for parents)
        'print_allowed'       => $print_allowed,   // ← NEW (show Print button to teacher)
        'role_flags'          => [                 // ← NEW (view convenience)
            'isTeacher'   => $isTeacher,
            'isPrincipal' => $isPrincipal,
            'isParent'    => $isParent,
        ],
    ];

    if (!empty($student_id)) {
        $results  = $this->report_model->get_student_subject_pace_scores($student_id, $session_id);

        // Build using subject_code as the sort key
        $subjects_by_code = [];

        foreach ($results as $row) {
            $sid        = (int)$row['subject_id'];
            $scode      = (int)$row['subject_code'];   // from model
            $sname      = $row['subject_name'];
            $quarterKey = strtoupper(trim($row['quarter'])); // Q1..Q4
            $paceNumber = $row['pace_number'];
            $score      = round($row['percentage'], 2);

            $s_score = isset($row['s_score']) && $row['s_score'] !== '' && $row['s_score'] !== null
                ? round((float)$row['s_score'], 2)
                : $score;

            $m_score = isset($row['m_score']) && $row['m_score'] !== '' && $row['m_score'] !== null
                ? round((float)$row['m_score'], 2)
                : null;

            if (!isset($subjects_by_code[$scode])) {
                $subjects_by_code[$scode] = [
                    'id'          => $sid,
                    'name'        => $sname,
                    'paces'       => [],
                    'quarters'    => [],
                    'yearly_avg'  => 0,
                    'symbol'      => '',
                ];
            }

            $subjects_by_code[$scode]['paces'][$quarterKey][] = [
                'pace_number' => $paceNumber,
                'percentage'  => $score,
                's_score'     => $s_score,
                'm_score'     => $m_score,
            ];
            $subjects_by_code[$scode]['quarters'][$quarterKey][] = $score;
        }

        // 👉 Sort by subject_code and flatten to a numeric array
        ksort($subjects_by_code, SORT_NUMERIC);
        $data['subjects'] = array_values($subjects_by_code);

        // Compute yearly averages + symbols
        foreach ($subjects_by_code as &$sub) {
            $termAverages = [];
            foreach ($sub['quarters'] as $scores) {
                $avg = count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
                $termAverages[] = $avg;
            }
            $yearAvg           = count($termAverages) ? round(array_sum($termAverages) / count($termAverages), 2) : 0;
            $sub['yearly_avg'] = $yearAvg;
            $sub['symbol']     = convert_to_symbol($yearAvg);
        }
        unset($sub);

        // Elective aliases (rename "Elective N" only when the subject actually appears on the report)
        $aliases = $this->Spc_model->get_elective_aliases((int)$student_id, (int)$session_id);
        if ($aliases) {
            foreach ($subjects_by_code as &$sub) {
                $nm = (string)($sub['name'] ?? '');
                // match by subject id when available
                if (preg_match('/^Elective\s*\d+/i', $nm)) {
                    $sid = $sub['id'] ?? 0;
                    if ($sid && !empty($aliases[$sid])) {
                        $sub['name'] = $aliases[$sid];
                    }
                }
            }
            unset($sub);
        }
        $data['elective_alias'] = $aliases ?: [];

        // Sort by subject_code explicitly and flatten (ensures 1..N order)
        ksort($subjects_by_code, SORT_NUMERIC);
        $subjects = [];
        foreach ($subjects_by_code as $sub) {
            $subjects[$sub['id']] = $sub; // keep subject_id keys for view if needed
        }

        // Report sections
        $data['subjects']            = $subjects;
        $data['comments']            = $this->report_model->get_comments($student_id, $session_id);
        $data['traits_term_avg']     = $this->weekly_traits_model->get_term_averages($student_id, $session_id);
        $data['general_assignments'] = $this->report_model->get_spc_general_assignments($student_id, $session_id);
        $data['reading']             = $this->report_model->get_spc_reading_program($student_id, $session_id);
        $data['traits']              = $this->report_model->get_traits($student_id, $session_id);
        $data['attendance']          = $this->report_model->get_days_absent_by_term($student_id);
        $data['scripture_notes']     = $this->report_model->get_scripture_notes_by_term($student_id);
        $data['grade_label']         = $this->report_model->get_student_grade_label($student_id, $session_id);
    }

    $data['main_menu']    = 'learningcentre';
    $data['sub_page']     = 'report/progress_report';
    $data['title']        = 'Progress Report';
    $data['student_id']   = $student_id;
    $data['theme_config'] = [
        'dark_skin'     => false,
        'sidebar_theme' => 'default',
        'layout_mode'   => 'default',
    ];

    $this->data = array_merge($this->data, $data);
    $this->load->view('layout/index', $this->data);
}

    public function save_comments()
    {
        $student_id        = $this->input->post('student_id');
        $teacher_comment   = $this->input->post('teacher_comment');
        $principal_comment = $this->input->post('principal_comment');
        $session_id        = get_session_id();

        $this->load->library('upload');

        $data = [
            'teacher_comment'   => $teacher_comment,
            'principal_comment' => $principal_comment,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        if (!empty($_FILES['teacher_signature']['name'])) {
            $config = [
                'upload_path'   => './uploads/signatures/',
                'allowed_types' => 'jpg|jpeg|png|gif',
                'file_name'     => 'teacher_' . time(),
            ];
            $this->upload->initialize($config);
            if ($this->upload->do_upload('teacher_signature')) {
                $data['teacher_signature'] = $this->upload->data('file_name');
            }
        }

        if (!empty($_FILES['principal_signature']['name'])) {
            $config = [
                'upload_path'   => './uploads/signatures/',
                'allowed_types' => 'jpg|jpeg|png|gif',
                'file_name'     => 'principal_' . time(),
            ];
            $this->upload->initialize($config);
            if ($this->upload->do_upload('principal_signature')) {
                $data['principal_signature'] = $this->upload->data('file_name');
            }
        }

        $exists = $this->db
            ->where('student_id', $student_id)
            ->where('year', $session_id)
            ->get('report_comments')
            ->row();

        if ($exists) {
            $this->db->where('student_id', $student_id)
                     ->where('year', $session_id)
                     ->update('report_comments', $data);
        } else {
            $data['student_id'] = $student_id;
            $data['year']       = $session_id;
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('report_comments', $data);
        }

        $this->load->model('notification_model');
        if ($this->session->userdata('role') === 'teacher' && !empty($teacher_comment)) {
            $this->notification_model->send_to_principal([
                'student_id' => $student_id,
                'message'    => "New teacher comment submitted for student ID {$student_id}.",
                'url'        => base_url("report/progress_report?student_id={$student_id}"),
            ]);
        }

        echo json_encode(['success' => true, 'principal_signature' => $data['principal_signature'] ?? null]);
    }

    public function save_traits()
    {
        $student_id = (int)$this->input->post('student_id');
        $year       = (int)get_session_id();

        $traits_json = $this->input->post('traits');
        if ($traits_json === null || $traits_json === '') {
            $raw  = $this->input->raw_input_stream;
            $body = json_decode($raw, true);
            $traits_json = $body['traits'] ?? '{}';
            $student_id  = (int)($body['student_id'] ?? $student_id);
        }

        $traits = json_decode($traits_json, true);
        if (!is_array($traits)) $traits = [];

        $this->report_model->save_traits($student_id, $year, $traits);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['status' => 'ok']));
    }

    public function get_traits()
    {
        $student_id = (int)$this->input->get('student_id');
        $year       = (int)get_session_id();

        $this->load->model('report_model');
        $traits = $this->report_model->get_traits($student_id, $year);

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($traits ?: []));
    }

    private function _upload_signature($field)
    {
        $config = [
            'upload_path'   => './uploads/signatures/',
            'allowed_types' => 'jpg|jpeg|png',
            'max_size'      => 2048,
            'encrypt_name'  => true,
        ];
        $this->load->library('upload', $config);
        if ($this->upload->do_upload($field)) {
            return $this->upload->data('file_name');
        }
        return null;
    }

    private function my_student_ids(int $session_id): array
    {
        $teacher_id = is_loggedin() ? (int)get_loggedin_user_id() : 0;
        if ($teacher_id <= 0) return [];

        $ids  = [];
        $push = function(array $rows) use (&$ids) {
            foreach ($rows as $r) { $ids[(int)$r['student_id']] = true; }
        };

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
