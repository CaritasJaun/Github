<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Monitor_goal_check extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!in_array(get_loggedin_role_id(), [1, 3])) {
            access_denied();
        }

        $this->load->model('monitor_goal_check_model');
        $this->load->model('student_model');
    }

    public function index()
    {
        $this->data['title'] = "Monitor Goal Check";
        $this->data['sub_page'] = 'monitor_goal_check/index';
        $this->data['main_menu'] = true;

        if (get_loggedin_role_id() == 3) {
            $this->data['students'] = $this->student_model->get_student_list_by_teacher(get_loggedin_user_id());
        } else {
            $this->data['students'] = $this->student_model->get_all_students_basic(get_loggedin_branch_id());
        }

        $this->load->view('layout/index', $this->data);
    }

    public function load_goal_check_table()
    {
        $student_id = $this->input->post('student_id');
        $term_id    = (int) $this->input->post('term_id');

        if (!$student_id || $term_id < 1 || $term_id > 4) {
            echo '<div class="alert alert-warning text-center">Missing student or term selection.</div>';
            return;
        }

        $branch_id = get_loggedin_branch_id();

        $term_start = $this->db->select('start_date')
            ->where('term_id', $term_id)
            ->where('branch_id', $branch_id)
            ->get('term_dates')
            ->row('start_date');

        if (!$term_start) {
            echo '<div class="alert alert-danger text-center">Term start date not set for this campus. Please set it in Admin Panel.</div>';
            return;
        }

        // ⇩ SUBJECTS: only Mandatory + student-selected Optionals assigned to the class/section
        $subjects = $this->monitor_goal_check_model->get_student_subjects($student_id, $branch_id, get_session_id());

        // Fallback: ensure abbreviation exists
        foreach ($subjects as &$sub) {
            if (empty($sub['abbreviation'])) {
                $sub['abbreviation'] = $sub['subject_code'];
            }
        }
        unset($sub);

        // Build 11 weeks × 5 days (Mon–Fri) from term_start
        $dt = new DateTime($term_start);
        $week_dates = [];
        for ($w = 0; $w < 11; $w++) {
            for ($d = 0; $d < 5; $d++) {
                $week_dates[] = $dt->format('Y-m-d');
                $dt->modify('+1 day');
            }
            $dt->modify('+2 days');
        }

        $goal_data = $this->monitor_goal_check_model->get_by_term($student_id, $term_id);

        // ✅ STRUCTURE FOR VIEW
        $saved_data = [];
        $saved_week_notes = [];
        $saved_date_notes = [];
        $saved_scripture_notes = [];

        foreach ($goal_data as $row) {
            if (!isset($row['date']) || !isset($row['week_no'])) {
                continue; // skip bad rows
            }

            $date = (new DateTime($row['date']))->format('Y-m-d');
            $week = $row['week_no'];

            if (!empty($row['week_note'])) $saved_week_notes[$week] = $row['week_note'];
            if (!empty($row['scripture_note'])) $saved_scripture_notes[$week] = $row['scripture_note'];
            if (!empty($row['date_note'])) $saved_date_notes[$date] = $row['date_note'];

            $saved_data[$date]['__ATT__'] = ['attendance_status' => $row['attendance_status']];
            $saved_data[$date]['__DMT__'] = ['demerit' => $row['demerit']];  // note appended below (if any)
            $saved_data[$date]['__MRT__'] = ['merit'   => $row['merit']];    // note appended below (if any)

            // ⇩ NEW: expose DB column total_pages as pseudo-subject __TP__
            if (isset($row['total_pages']) && $row['total_pages'] !== null && $row['total_pages'] !== '') {
                $saved_data[$date]['__TP__'] = [
                    'goal' => (string)$row['total_pages'],
                    'note' => '',
                ];
            }

            if (!empty($row['goals_json'])) {
                    $goals = json_decode($row['goals_json'], true);
                    if (is_array($goals)) {
                        // normal subject goals/notes
                        foreach ($goals as $subject => $entry) {
                            if ($subject === '__DMT_NOTES__' || $subject === '__MRT_NOTES__' || $subject === '__DMT_NOTE__' || $subject === '__MRT_NOTE__') {
                                continue; // handled below
                            }
                            $saved_data[$date][$subject] = [
                                'goal' => isset($entry['goal']) ? $entry['goal'] : '',
                                'note' => isset($entry['note']) ? $entry['note'] : ''
                            ];
                        }
                
                        // ==== NEW: surface reserved merit/demerit notes as ARRAYS ====
                        // Back-compat: if legacy single note exists, convert to single-element array.
                        $dmtNotes = [];
                        if (isset($goals['__DMT_NOTES__']) && is_array($goals['__DMT_NOTES__'])) {
                            $dmtNotes = $goals['__DMT_NOTES__'];
                        } elseif (!empty($goals['__DMT_NOTE__'])) {
                            $dmtNotes = [ (string)$goals['__DMT_NOTE__'] ];
                        }
                
                        $mrtNotes = [];
                        if (isset($goals['__MRT_NOTES__']) && is_array($goals['__MRT_NOTES__'])) {
                            $mrtNotes = $goals['__MRT_NOTES__'];
                        } elseif (!empty($goals['__MRT_NOTE__'])) {
                            $mrtNotes = [ (string)$goals['__MRT_NOTE__'] ];
                        }
                
                        if ($dmtNotes) { $saved_data[$date]['__DMT__']['notes'] = $dmtNotes; }
                        if ($mrtNotes) { $saved_data[$date]['__MRT__']['notes'] = $mrtNotes; }
                    }
                }
            }

        $data = [
            'week_dates' => $week_dates,
            'subjects'   => $subjects,
            'goal_data'  => $goal_data,
            'saved_data' => $saved_data,
            'saved_week_notes' => $saved_week_notes,
            'saved_date_notes' => $saved_date_notes,
            'saved_scripture_notes' => $saved_scripture_notes,
            'term_start' => $term_start,
            'student_id' => $student_id,
            'term_id'    => $term_id
        ];

        $this->load->view('monitor_goal_check/partial_goal_matrix_table', $data);
    }

    public function set_term_dates()
    {
        $this->data['title'] = 'Set Term Dates';
        $this->data['sub_page'] = 'monitor_goal_check/set_term_dates';
        $this->data['main_menu'] = 'monitor_goal_check';

        $term_dates = $this->monitor_goal_check_model->get_all_term_dates();

        foreach ($term_dates as &$term) {
            $start_date = !empty($term['start_date']) ? new DateTime($term['start_date']) : null;
            $end_date = !empty($term['end_date']) ? new DateTime($term['end_date']) : null;

            if (!$end_date && $start_date) {
                $end_date = (clone $start_date)->modify('+10 weeks');
            }

            $term['start_week'] = $start_date ? $start_date->format("W") : 'N/A';
            $term['end_week'] = $end_date ? $end_date->format("W") : 'N/A';
            $term['total_weeks'] = ($start_date && $end_date) ? ceil($start_date->diff($end_date)->days / 7) : 'N/A';

            $term['start_date_obj'] = $start_date;
            $term['end_date_obj'] = $end_date;
        }

        $this->data['term_dates'] = $term_dates;
        $this->load->view('layout/index', $this->data);
    }

    public function save_term_date()
    {
        $term_id = (int) $this->input->post('term_id');
        $start_date = $this->input->post('start_date');
        $branch_id = get_loggedin_branch_id();

        if ($term_id >= 1 && $term_id <= 4 && $start_date) {
            $start = new DateTime($start_date);
            $end = clone $start;
            $end->modify('+10 weeks');

            $data = [
                'branch_id'   => $branch_id,
                'term_id'     => $term_id,
                'start_date'  => $start->format('Y-m-d'),
                'end_date'    => $end->format('Y-m-d')
            ];

            $this->db->replace('term_dates', $data);
            set_alert('success', 'Start and end dates saved.');
        } else {
            set_alert('error', 'Invalid input.');
        }

        redirect(base_url('monitor_goal_check/set_term_dates'));
    }

    public function update_term_end_date()
    {
        $this->db->where('term_id', $this->input->post('term_id'));
        $this->db->update('term_dates', [
            'end_date' => $this->input->post('end_date')
        ]);
        set_alert('success', 'End date updated successfully.');
        redirect(base_url('monitor_goal_check/set_term_dates'));
    }

    public function save_entry()
    {
        $student_id   = $this->input->post('student_id');
        $term_id      = $this->input->post('term_id');
        $week_no      = $this->input->post('week_no') ?? $this->input->post('week');
        $date         = $this->input->post('date');
        $day          = $this->input->post('day');
        $subject_code = $this->input->post('subject_code');
        $goal         = $this->input->post('goal');
        $att          = $this->input->post('attendance_status');
        $dmt          = $this->input->post('demerit');
        $mrt          = $this->input->post('merit');
        $notes        = $this->input->post('notes');

        if (!$student_id || !$term_id || !$week_no || !$date || !$day) {
            echo json_encode(['status' => 'fail', 'reason' => 'Missing required data']);
            return;
        }

        // Load existing row if available
        $existing = $this->db->get_where('monitor_goal_check', [
            'student_id' => $student_id,
            'term_id'    => $term_id,
            'date'       => $date
        ])->row_array();

        // Base data
        $data = [
            'student_id'        => $student_id,
            'term_id'           => $term_id,
            'branch_id'         => get_loggedin_branch_id(),
            'week_no'           => $week_no,
            'date'              => $date,
            'day_of_week'       => $day,
            'attendance_status' => $existing['attendance_status'] ?? '',
            'demerit'           => $existing['demerit'] ?? 0,
            'merit'             => $existing['merit'] ?? 0,
        ];

        // Only overwrite fields that are present in the request
        if ($att !== null) $data['attendance_status'] = $att;
        if ($dmt !== null && $dmt !== '') $data['demerit'] = $dmt;
        if ($mrt !== null && $mrt !== '') $data['merit'] = $mrt;

        // ⇩ SPECIAL CASE: Total Pages column, posted as pseudo subject "__TP__"
        if ($subject_code === '__TP__') {
            $data['total_pages'] = (int)($goal === '' || $goal === null ? 0 : $goal);
            // preserve existing goals_json if any
            if ($existing && isset($existing['goals_json'])) {
                $data['goals_json'] = $existing['goals_json'];
            }
            if ($existing) {
                $this->db->where('id', $existing['id'])->update('monitor_goal_check', $data);
            } else {
                $this->db->insert('monitor_goal_check', $data);
            }
            echo json_encode(['status' => 'success']);
            return;
        }

        // Load or update goals_json (for normal subjects only)
        $goals_json = isset($existing['goals_json']) && $existing['goals_json']
            ? json_decode($existing['goals_json'], true)
            : [];

            // -- Demerit/Merit notes (arrays) kept in goals_json under reserved keys
            $dmt_notes_json = $this->input->post('dmt_notes'); // JSON string or null
            $mrt_notes_json = $this->input->post('mrt_notes'); // JSON string or null
            
            if ($dmt_notes_json !== null) {
                $arr = json_decode($dmt_notes_json, true);
                if (is_array($arr)) {
                    $goals_json['__DMT_NOTES__'] = array_values(array_filter(array_map('strval', $arr), static function($s){ return $s !== ''; }));
                } else {
                    unset($goals_json['__DMT_NOTES__']);
                }
                // remove legacy single key if present
                unset($goals_json['__DMT_NOTE__']);
            }
            
            if ($mrt_notes_json !== null) {
                $arr = json_decode($mrt_notes_json, true);
                if (is_array($arr)) {
                    $goals_json['__MRT_NOTES__'] = array_values(array_filter(array_map('strval', $arr), static function($s){ return $s !== ''; }));
                } else {
                    unset($goals_json['__MRT_NOTES__']);
                }
                unset($goals_json['__MRT_NOTE__']);
            }

        if (!empty($subject_code)) {
            $entry = [];
            if ($goal !== null) $entry['goal'] = $goal;
            if (!empty($notes)) $entry['note'] = $notes;
            $goals_json[$subject_code] = $entry;
        }

        $data['goals_json'] = json_encode($goals_json);

        // Save to DB
        if ($existing) {
            $this->db->where('id', $existing['id']);
            $this->db->update('monitor_goal_check', $data);
        } else {
            $this->db->insert('monitor_goal_check', $data);
        }

        echo json_encode(['status' => 'success']);
    }

    public function save_meta_note()
    {
        $student_id = $this->input->post('student_id');
        $term_id    = (int)$this->input->post('term_id');
        $week_no    = (int)$this->input->post('week_no');
        $date       = $this->input->post('date');        // Y-m-d
        $type       = $this->input->post('note_type');   // 'week' | 'date' | 'scripture'
        $value      = $this->input->post('note_value');  // allow empty string to CLEAR
        $branch_id  = get_loggedin_branch_id();

        // allow empty string, just not NULL
        if (!$student_id || !$term_id || !$date || !$week_no || !$type || $value === null) {
            echo json_encode(['status' => 'fail', 'reason' => 'Missing data']);
            return;
        }

        $field_map = [
            'week'      => 'week_note',
            'date'      => 'date_note',
            'scripture' => 'scripture_note',
        ];
        if (!isset($field_map[$type])) {
            echo json_encode(['status' => 'fail', 'reason' => 'Invalid note type']);
            return;
        }
        $field = $field_map[$type];

        // Upsert scoped by branch
        $existing = $this->db->get_where('monitor_goal_check', [
            'student_id' => $student_id,
            'term_id'    => $term_id,
            'branch_id'  => $branch_id,
            'date'       => $date,
        ])->row_array();

        if ($existing) {
            $this->db->where('id', $existing['id'])->update('monitor_goal_check', [
                'week_no' => $week_no, // keep week in sync
                $field    => $value,
            ]);
        } else {
            $this->db->insert('monitor_goal_check', [
                'student_id' => $student_id,
                'term_id'    => $term_id,
                'branch_id'  => $branch_id,
                'week_no'    => $week_no,
                'date'       => $date,
                $field       => $value,
            ]);
        }

        echo json_encode(['status' => 'success']);
    }

    public function optionals_modal()
    {
        if (!$this->input->is_ajax_request()) { show_404(); }
        $student_id = (int)$this->input->get('student_id');
        if ($student_id <= 0) { show_error('Invalid student', 400); }

        $data['student_id'] = $student_id;
        $data['subjects']   = $this->monitor_goal_check_model->get_available_optionals_for_student($student_id);
        $this->load->view('monitor_goal_check/partials/optional_subjects_modal', $data);
    }

    public function save_optionals()
    {
        $this->output->set_content_type('application/json');

        // Allow Super Admin / Admin / Teacher (adjust to your roles)
        if (!in_array((int)$this->session->userdata('loggedin_role_id'), [1,2,3])) {
            echo json_encode([
                'success' => false,
                'message' => 'Access denied',
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
            ]);
            return;
        }

        $student_id  = (int)$this->input->post('student_id');
        $subject_ids = $this->input->post('subject_ids'); // array
        $subject_ids = is_array($subject_ids) ? array_map('intval', $subject_ids) : [];

        $ok = $this->monitor_goal_check_model->set_student_optionals($student_id, $subject_ids);

        echo json_encode([
            'success' => (bool)$ok,
            'message' => $ok ? 'Saved' : 'Failed',
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash()
        ]);
    }
}
