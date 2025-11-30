<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Spc extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Spc_model');
        $this->load->model('student_model');
        $this->load->model('Pace_model');
        $this->load->model('Pace_order_workflow_model', 'pow');

    }

    // -------------------------------------------------------------------------
    // SUPERVISOR PROGRESS CARD
    // -------------------------------------------------------------------------
    public function index()
    {
        $data = [];

        $role_id    = get_loggedin_role_id();
        $teacher_id = get_loggedin_user_id();

        // Teacher sees only their students; others see all
        if ($role_id == 3) {
            $data['student_list'] = $this->student_model->get_students_for_teacher($teacher_id);
        } else {
            $data['student_list'] = $this->Spc_model->get_all_students();
        }

        $student_id         = $this->input->get('student_id');
        $data['student_id'] = $student_id;
        $session_id         = get_session_id();

        // read & normalize term (Q1..Q4)
        $term = (int)$this->input->get('term');
        if ($term <= 0 || $term > 4) { $term = 1; }
        $data['term'] = $term;

        if (!empty($student_id)) {
            $data['student'] = $this->Spc_model->get_student($student_id);

            // --- All PACE rows for the student (with legacy fallback) ---
            $all = $this->Pace_model->get_student_paces($student_id, $term, $session_id);
            if (empty($all)) {
                $all = $this->Pace_model->get_student_paces($student_id, $term, 0);
            }

            // --- Normalize each row so the view code has consistent keys ---
            foreach ($all as &$r) {
                // pace_number <- pace_number | pace_no | book_number
                if (!isset($r['pace_number']) || $r['pace_number'] === '' || $r['pace_number'] === null) {
                    if (isset($r['pace_no']))       $r['pace_number'] = $r['pace_no'];
                    elseif (isset($r['book_number'])) $r['pace_number'] = $r['book_number'];
                }
                $r['pace_number'] = (int)($r['pace_number'] ?? 0);

                // slot_index <- slot_index | slot
                if (empty($r['slot_index']) && !empty($r['slot'])) {
                    $r['slot_index'] = (int)$r['slot'];
                }
                $r['slot_index'] = (int)($r['slot_index'] ?? 0);

                // status normalize (string), keep original but compare case-insensitive later
                if (isset($r['status']) && is_string($r['status'])) {
                    $r['_status_uc'] = strtoupper($r['status']);
                } else {
                    $r['_status_uc'] = '';
                }

                // term normalize (Q1..Q4) from possible columns
                if (empty($r['term']) && !empty($r['terms']))   $r['term'] = $r['terms'];
                if (empty($r['term']) && !empty($r['quarter'])) $r['term'] = $r['quarter'];
                if (is_numeric($r['term'] ?? null)) $r['term'] = 'Q'.(int)$r['term'];
                $r['term'] = (string)($r['term'] ?? '');
            }
           unset($r);

// Show once handed to learner and keep completed / redo
$data['paces'] = array_values(array_filter($all, function ($p) {
    $st = $p['_status_uc'] ?? '';
    return in_array($st, ['ASSIGNED','ISSUED','COMPLETED','REDO'], true);
}));

/**
 * Sort strictly by: subject → pace_number (numeric) → slot_index (tiebreaker)
 * then normalize slot_index per subject to 1..N so the SPC grid columns are sequential.
 */

// 1) Sort by subject, then numeric PACE number
usort($data['paces'], function ($a, $b) {
    $sa = (int)($a['subject_id'] ?? 0);
    $sb = (int)($b['subject_id'] ?? 0);
    if ($sa !== $sb) return $sa - $sb;

    $pa = (int)($a['pace_number'] ?? 0);
    $pb = (int)($b['pace_number'] ?? 0);
    if ($pa !== $pb) return $pa - $pb;

    $la = (int)($a['slot_index'] ?? 0);
    $lb = (int)($b['slot_index'] ?? 0);
    return $la - $lb;
});

// 2) Renumber slot_index per subject to 1..N (left→right in SPC)
if (!empty($data['paces'])) {
    $grouped = [];
    foreach ($data['paces'] as $row) {
        $sid = (int)($row['subject_id'] ?? 0);
        $grouped[$sid][] = $row;
    }
    $normalized = [];
    foreach ($grouped as $sid => $rows) {
        $col = 1;
        foreach ($rows as $r) {
            $r['slot_index'] = $col++;  // force sequential columns by PACE number
            $normalized[] = $r;
        }
    }
    $data['paces'] = $normalized;
}

// ── SUBJECTS FOR SPC (derive from existing PACEs first; fallback to class map) ──

// 1) Collect subject IDs present in the fetched PACE rows ($all)
$subjectIds = array();
foreach ((array)$all as $pRow) {
    $sid = isset($pRow['subject_id']) ? (int)$pRow['subject_id'] : 0;
    if ($sid > 0) $subjectIds[$sid] = true;
}
$subjectIds = array_keys($subjectIds);

$subjects = array();

// 2) If PACEs exist, fetch those subjects (no strict filters)
if (!empty($subjectIds)) {
    $this->db->select('id, name, abbreviation, subject_code, subject_type, subject_author');
    $this->db->from('subject');
    $this->db->where_in('id', $subjectIds);
    // report order: subject_code numeric → name
    $this->db->order_by("FIELD(subject_type, 'Mandatory','Optional')", '', false);
    $this->db->order_by('id', 'asc');
    $subjects = $this->db->get()->result_array();

    // placeholders for any IDs missing from subject table
    $found = array();
    foreach ($subjects as $s) $found[(int)$s['id']] = true;
    foreach ($subjectIds as $sid) {
        if (empty($found[$sid])) {
            $subjects[] = array(
                'id'             => (int)$sid,
                'name'           => 'Subject '.$sid,
                'abbreviation'   => '',
                'subject_code'   => null,
                'subject_type'   => 'Mandatory',
                'subject_author' => 'ACE',
            );
        }
    }
}

// 3) If still empty (student has no PACEs yet), fallback to class/section assignment (lightly)
if (empty($subjects)) {
    $branch_id = !empty($data['student']['branch_id']) ? (int)$data['student']['branch_id'] : (int)get_loggedin_branch_id();
    $enroll = $this->db->select('class_id, section_id')
        ->from('enroll')
        ->where('student_id', (int)$student_id)
        ->where('session_id', (int)$session_id)
        ->get()->row_array();

    $class_id   = (int)($enroll['class_id']   ?? 0);
    $section_id = (int)($enroll['section_id'] ?? 0);

    $this->db->select('s.id, s.name, s.abbreviation, s.subject_code, s.subject_type, s.subject_author');
    $this->db->from('subject AS s');
    $this->db->join('subject_assign AS sa', 'sa.subject_id = s.id', 'left');

    if ($class_id)   $this->db->where('sa.class_id',   $class_id);
    if ($section_id) $this->db->where('sa.section_id', $section_id);
    if ($this->db->field_exists('session_id','subject_assign')) $this->db->where('sa.session_id', $session_id);
    if ($this->db->field_exists('branch_id','subject_assign'))  $this->db->where('sa.branch_id',  $branch_id);

    // keep campus scoping if your data uses it
    if ($this->db->field_exists('branch_id','subject')) $this->db->where('s.branch_id', $branch_id);

    $this->db->order_by('(s.subject_code IS NULL)', 'asc', false);
    $this->db->order_by('CAST(s.subject_code AS UNSIGNED)', 'asc', false);
    $this->db->order_by('s.name', 'asc');
    $subjects = $this->db->get()->result_array();
}

// 4) Fallback abbreviations
foreach ($subjects as &$sub) {
    if (empty($sub['abbreviation'])) {
        $sub['abbreviation'] = isset($sub['subject_code']) && $sub['subject_code'] !== null
            ? (string)$sub['subject_code']
            : (isset($sub['name']) ? (string)$sub['name'] : '');
    }
}
unset($sub);

$data['subjects'] = $subjects;

// 5) Filter PACEs to just these subjects (defensive)
$aceIds = array_map('intval', array_column($subjects, 'id'));
$data['paces'] = array_values(array_filter($data['paces'], function ($p) use ($aceIds) {
    $sid = isset($p['subject_id']) ? (int)$p['subject_id'] : 0;
    return in_array($sid, $aceIds, true);
}));

// 6) Group PACEs by subject for the SPC grid
$grouped = array();
foreach ($subjects as $subj) {
    $sid = isset($subj['id']) ? (int)$subj['id'] : 0;
    if ($sid > 0) $grouped[$sid] = array();
}
foreach ($data['paces'] as $p) {
    $sid = isset($p['subject_id']) ? (int)$p['subject_id'] : 0;
    if ($sid > 0) $grouped[$sid][] = $p;
}
$data['grouped'] = $grouped;


            // ---------------- Prefill Reading Programme & General Assignments ----------------
            // Reading Programme
            [$rpTable, $rc] = $this->_pick_rp_table();
            $rp = ['Q1'=>[], 'Q2'=>[], 'Q3'=>[], 'Q4'=>[]];
            if ($rpTable) {
                $this->db->where($rc['student'], $student_id);
                if ($rc['session']) $this->db->where($rc['session'], $session_id);
                $this->db->where_in($rc['term'], ['Q1','Q2','Q3','Q4']);
                $rows = $this->db->get($rpTable)->result_array();

                foreach ($rows as $r) {
                    $t = strtoupper((string)($r[$rc['term']] ?? ''));
                    if (!in_array($t, ['Q1','Q2','Q3','Q4'], true)) continue;
                    $rp[$t] = [
                        'title'         => (string)($rc['title']   ? ($r[$rc['title']] ?? '')   : ''),
                        'wpm'           => $rc['wpm']    && array_key_exists($rc['wpm'],$r)     && $r[$rc['wpm']]     !== null ? (string)(int)$r[$rc['wpm']]     : '',
                        'percent'       => $rc['percent']&& array_key_exists($rc['percent'],$r) && $r[$rc['percent']] !== null ? (string)(0 +  $r[$rc['percent']]) : '',
                        'comprehension' => $rc['comp']   && array_key_exists($rc['comp'],$r)    && $r[$rc['comp']]    !== null ? (string)(0 +  $r[$rc['comp']])   : '',
                    ];
                }
            }
            $data['rp'] = $rp;

            // General Assignments
            $ga = ['Q1'=>[], 'Q2'=>[], 'Q3'=>[], 'Q4'=>[]];
            $gm = $this->_ga_table_map();
            if ($gm) {
                $table = $gm['table'];
                $c     = $gm['col'];

                $this->db->where('student_id', $student_id);
                if ($c['session']) $this->db->where($c['session'], $session_id);
                $this->db->where_in('term', ['Q1','Q2','Q3','Q4']);
                $this->db->order_by($c['row'], 'asc');
                $gaRows = $this->db->get($table)->result_array();

                foreach ($gaRows as $r) {
                    $t = strtoupper((string)($r['term'] ?? ''));
                    if (!in_array($t, ['Q1','Q2','Q3','Q4'], true)) continue;

                    $i = (int)$r[$c['row']];
                    if ($i < 1 || $i > 7) continue;

                    $dateVal = '';
                    if ($c['date'] && array_key_exists($c['date'], $r)) {
                        $dateVal = $this->_fmt_date_for_input($r[$c['date']]);
                    }

                    $pctVal = '';
                    if ($c['percent'] && array_key_exists($c['percent'], $r) && $r[$c['percent']] !== null) {
                        $pctVal = (string)(0 + $r[$c['percent']]);
                    }

                    $ga[$t][$i] = [
                        'date'    => $dateVal,
                        'item'    => (string)($r['item'] ?? ''),
                        'percent' => $pctVal,
                    ];
                }
            }
            $data['ga'] = $ga;

        } else {
            $data['student']      = [];
            $data['paces']        = [];
            $data['subjects']     = [];
            $data['pace_options'] = [];
            $data['grouped']      = [];
            $data['rp']           = ['Q1'=>[], 'Q2'=>[], 'Q3'=>[], 'Q4'=>[]];
            $data['ga']           = ['Q1'=>[], 'Q2'=>[], 'Q3'=>[], 'Q4'=>[]];
        }

        $this->data               = array_merge($this->data, $data);
        $this->data['main_menu']  = true;
        $this->data['title']      = 'Supervisor Progress Card';
        $this->data['sub_page']   = 'spc/index';
        $this->load->view('layout/index', $this->data);
    }

    // -------------------------------------------------------------------------
    // AJAX: update a score field (SPC only records scores). Only when status=assigned
    // -------------------------------------------------------------------------
  public function update_field()
{
    // Inputs
    $id          = (int)($this->input->post('id') ?? 0);
    $field_in    = trim((string)$this->input->post('field', true));
    $value       = $this->input->post('value');

    // Fallback keys if view didn’t post id
    $student_id  = (int)($this->input->post('student_id') ?? 0);
    $subject_id  = (int)($this->input->post('subject_id') ?? 0);
    $slot_index  = (int)($this->input->post('slot') ?? $this->input->post('slot_index') ?? 0);
    $session_id  = (int)get_session_id();

    // ▼ NEW: reusable composite guard
    $compositeWhere = [];
    if ($student_id > 0) $compositeWhere['student_id'] = $student_id;
    if ($subject_id > 0) $compositeWhere['subject_id'] = $subject_id;
    if ($slot_index > 0) $compositeWhere['slot_index'] = $slot_index;
    if ($session_id > 0) $compositeWhere['session_id'] = $session_id;

    // Map field aliases to real columns
    $map = [
        'first_attempt_score'  => 'first_attempt_score',
        'second_attempt_score' => 'second_attempt_score',
        'moderator_score'      => 'moderator_score',
        's1'                   => 'first_attempt_score',
        'score_1'              => 'first_attempt_score',
        'first'                => 'first_attempt_score',
        'first_attempt'        => 'first_attempt_score',
        '1st'                  => 'first_attempt_score',
        's2'                   => 'second_attempt_score',
        'score_2'              => 'second_attempt_score',
        'second'               => 'second_attempt_score',
        'second_attempt'       => 'second_attempt_score',
        '2nd'                  => 'second_attempt_score',
        'm'                    => 'moderator_score',
        'moderator'            => 'moderator_score',
    ];
    
    $field_db = $map[$field_in] ?? '';
    if ($field_db === '') {
        return $this->_json(false, 'SPC only edits scores. Use the Assignment tab.');
    }

    // semantic flags (use these for logic branches)
    $isFirst  = in_array($field_in, ['first_attempt_score','s1','score_1','first','first_attempt','1st'], true);
    $isSecond = in_array($field_in, ['second_attempt_score','s2','score_2','second','second_attempt','2nd'], true);

    $field = $map[$field_in] ?? '';
    if ($field === '') {
        return $this->_json(false, 'SPC only edits scores. Use the Assignment tab.');
    }

    // Resolve the row
    if ($id <= 0 && $student_id && $subject_id && $slot_index) {
        $row = $this->db->get_where('student_assign_paces', [
            'student_id' => $student_id,
            'subject_id' => $subject_id,
            'slot_index' => $slot_index,
            'session_id' => $session_id,
        ])->row_array();
        if ($row) $id = (int)$row['id'];
    }
    if ($id <= 0) return $this->_json(false, 'Invalid row id.');

    // --- CHANGED: guard fetch by id + composite; fallback to composite-only
    $whereFetch = ['id' => $id] + $compositeWhere;
    $row = $this->db->get_where('student_assign_paces', $whereFetch)->row_array();
    if (!$row && !empty($compositeWhere)) {
        $row = $this->db->get_where('student_assign_paces', $compositeWhere)->row_array();
        if ($row) $id = (int)$row['id'];
    }
    if (!$row) return $this->_json(false, 'Row not found.');

    // --- Ensure REDO order workflow is available --------------------------
    $this->load->model('Pace_order_workflow_model', 'pow');

    // Role guard for moderator_score
    $can_moderate = false;
    if (function_exists('is_superadmin_loggedin') && is_superadmin_loggedin()) $can_moderate = true;
    if (function_exists('is_admin_loggedin')      && is_admin_loggedin())      $can_moderate = true;
    $role_raw  = (string)($this->session->userdata('role') ?? '');
    $role_norm = strtolower(str_replace([' ', '-'], '_', trim($role_raw)));
    $role_id   = (int)($this->session->userdata('role_id') ?? $this->session->userdata('loggedin_role_id') ?? 0);
    if (in_array($role_norm, ['superadmin','super_admin','admin','moderator'], true)) $can_moderate = true;
    if (in_array($role_id, [1,2], true)) $can_moderate = true;

    $prev_status = strtoupper((string)($row['status'] ?? ''));
    $slot        = (int)($row['slot_index'] ?? $row['slot'] ?? 0);

    if ($field === 'moderator_score' && !$can_moderate) {
        return $this->_json(false, 'Only admin / super admin / moderator can edit Moderator score');
    }

    // --- REDO gating -------------------------------------------------------
    $redo_is_issued = false;
if ($field_in !== 'moderator_score' && $prev_status === 'REDO') {
    if (!$isSecond) {
        return $this->_json(false, 'Redo required. No further scoring allowed on this PACE.');
    }
    // 2nd attempt only after redo has been re-assigned/issued
    if (method_exists($this->pow, 'is_redo_issued')) {
        $redo_is_issued = (bool)$this->pow->is_redo_issued($id);
    }
    if (!$redo_is_issued) {
        return $this->_json(false, 'Redo PACE must be assigned/issued before capturing the 2nd attempt.');
    }
}

    // --- Allow scoring only while ASSIGNED/ISSUED or valid REDO-S2 --------
    $allow_due_to_redo = ($prev_status === 'REDO' && $isSecond && $redo_is_issued);
    if ($field_in !== 'moderator_score' && !$allow_due_to_redo && !in_array($prev_status, ['ASSIGNED','ISSUED'], true)) {
        return $this->_json(false, 'You can record scores only when status is ASSIGNED or ISSUED.');
    }

    // --- Previous PACE must be completed ----------------------------------
    if ($field !== 'moderator_score' && $slot > 1) {
        $completed_exists = $this->db->where([
            'student_id' => $row['student_id'],
            'subject_id' => $row['subject_id'],
            'session_id' => $row['session_id'],
            'slot_index' => $slot - 1,
            'status'     => 'completed',
        ])->count_all_results('student_assign_paces') > 0;
        if (!$completed_exists) {
            return $this->_json(false, 'Complete the previous PACE before scoring this one.');
        }
    }

    // --- Validate value ----------------------------------------------------
    if ($value === '' || !is_numeric($value)) return $this->_json(false, 'Score must be numeric');
    $value = (int)$value;
    if ($value < 0 || $value > 100) return $this->_json(false, 'Score must be between 0 and 100');

    // --- Prevent non-admin overwrite --------------------------------------
    $role_norm = strtolower(str_replace([' ', '-'], '_', trim((string)$this->session->userdata('role'))));
    $role_id   = (int)($this->session->userdata('role_id') ?? $this->session->userdata('loggedin_role_id') ?? 0);
    $is_admin_like = in_array($role_norm, ['superadmin','super_admin','admin'], true) || in_array($role_id, [1,2], true);

    if ($isFirst) {
        $s1_existing = $row['first_attempt_score'] ?? $row['score_1'] ?? null;
        if ($s1_existing !== null && $s1_existing !== '' && !$is_admin_like) {
            return $this->_json(false, '1st attempt already recorded');
        }
    }
    if ($isSecond) {
        $s1_existing = $row['first_attempt_score'] ?? $row['score_1'] ?? null;
        if ($s1_existing === null || $s1_existing === '') {
            return $this->_json(false, 'Enter 1st attempt before 2nd attempt');
        }
        if ((int)$s1_existing >= 80) {
            return $this->_json(false, '2nd attempt allowed only if 1st < 80');
        }
        if (strtoupper((string)$row['status']) === 'REDO'
            && method_exists($this->pow, 'is_redo_issued')
            && !$this->pow->is_redo_issued($id)) {
            $this->redo_issue_warning = true;
        }
    }

    // --- Persist the attempt ----------------------------------------------
    // CHANGED: update guarded by id + composite; fallback composite-only when 0 affected
    $this->db->where('id', $id);
    foreach ($compositeWhere as $k => $v) { $this->db->where($k, $v); }
    $this->db->update('student_assign_paces', [$field_db => $value]);

    $affected = $this->db->affected_rows();

    if ($affected === 0 && !empty($compositeWhere)) {
        $this->db->where($compositeWhere)->update('student_assign_paces', [$field_db => $value]);
        $affected = $this->db->affected_rows();
        if ($affected > 0) {
            // ensure $id matches the row we just updated
            $tmp = $this->db->select('id')->get_where('student_assign_paces', $compositeWhere)->row_array();
            if (!empty($tmp['id'])) $id = (int)$tmp['id'];
        }
    }

    $unlock_second = (
        $isFirst
        && is_numeric($value) && (int)$value < 80
        && (empty($row['second_attempt_score'] ?? $row['score_2'] ?? null))
    );

    // --- Recompute status --------------------------------------------------
    // CHANGED: re-fetch guarded, with composite fallback
    $whereFetch2 = ['id' => $id] + $compositeWhere;
    $row = $this->db->get_where('student_assign_paces', $whereFetch2)->row_array();
    if (!$row && !empty($compositeWhere)) {
        $row = $this->db->get_where('student_assign_paces', $compositeWhere)->row_array();
    }

    $first  = null; $second = null;
    if (array_key_exists('first_attempt_score', $row))  $first  = ($row['first_attempt_score']  === '' ? null : (int)$row['first_attempt_score']);
    if (array_key_exists('second_attempt_score', $row)) $second = ($row['second_attempt_score'] === '' ? null : (int)$row['second_attempt_score']);
    if ($first === null  && array_key_exists('score_1', $row)) $first  = ($row['score_1'] === '' ? null : (int)$row['score_1']);
    if ($second === null && array_key_exists('score_2', $row)) $second = ($row['score_2'] === '' ? null : (int)$row['score_2']);

    $final   = null; 
    $final_attempt = null;
    $new_status   = strtoupper((string)($row['status'] ?? ''));
    $completed_at = $row['completed_at'] ?? null;

    if ($field !== 'moderator_score') {
        $new_status   = 'assigned';
        $completed_at = null;

        if ($first !== null && $second === null) {
            $final = $first; $final_attempt = 'first';
            if ($first >= 80) {
                $new_status   = 'completed';
                $completed_at = date('Y-m-d H:i:s');
            } else {
                $new_status = 'redo';
                try { $this->pow->create_redo_from_sap($id, false); } catch (\Throwable $e) {
                    log_message('error', 'REDO order create (S1<80) SAP '.$id.' : '.$e->getMessage());
                }
            }
        } elseif ($first !== null && $second !== null) {
            $final = max($first, $second);
            $final_attempt = ($second >= $first) ? 'second' : 'first';
            $new_status = ($final >= 80) ? 'completed' : 'redo';
            if ($new_status === 'completed') {
                $completed_at = date('Y-m-d H:i:s');
            } else {
                try { $this->pow->create_redo_from_sap($id, false); } catch (\Throwable $e) {
                    log_message('error', 'REDO order create (S2<80) SAP '.$id.' : '.$e->getMessage());
                }
            }
        }

        $upd = ['status' => $new_status, 'completed_at' => $completed_at];
        if ($final !== null) {
            $upd['final_score'] = $final; 
            $upd['final_attempt'] = $final_attempt;
        }
        if ($this->db->field_exists('redo', 'student_assign_paces'))
            $upd['redo'] = ($new_status === 'redo') ? 1 : 0;

        // CHANGED: status update guarded; fallback composite-only
        $this->db->where('id', $id);
        foreach ($compositeWhere as $k => $v) { $this->db->where($k, $v); }
        $this->db->update('student_assign_paces', $upd);

        if ($this->db->affected_rows() === 0 && !empty($compositeWhere)) {
            $this->db->where($compositeWhere)->update('student_assign_paces', $upd);
        }

        // CHANGED: final re-fetch guarded
        $whereFetch3 = ['id' => $id] + $compositeWhere;
        $row = $this->db->get_where('student_assign_paces', $whereFetch3)->row_array();
        if (!$row && !empty($compositeWhere)) {
            $row = $this->db->get_where('student_assign_paces', $compositeWhere)->row_array() ?: [];
        }
    }

    // --- Response ----------------------------------------------------------
    $locked = (isset($row['status']) && strtolower($row['status']) === 'redo');

    return $this->_json(true, null, [
        'id'                   => $id,
        'status'               => $row['status'] ?? null,
        'final_score'          => $row['final_score'] ?? null,
        'final_attempt'        => $row['final_attempt'] ?? null,
        'term'                 => $row['term'] ?? '',
        'first_attempt_score'  => ($row['first_attempt_score']  ?? $row['score_1'] ?? 0),
        'second_attempt_score' => ($row['second_attempt_score'] ?? $row['score_2'] ?? 0),
        'moderator_score'      => $row['moderator_score'] ?? 0,
        'locked'               => $locked,
        'unlock_second'        => $unlock_second,
        $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
    ]);
}

    // -------------------------------------------------------------------------
    // Save Reading Programme line (title|wpm|percent|comprehension) for a term
    // -------------------------------------------------------------------------
    public function update_rp()
    {
        $student_id = (int)$this->input->post('student_id');
        $term       = $this->_norm_term($this->input->post('term'));
        $field      = strtolower((string)$this->input->post('field', true)); // title|wpm|percent|percentage|comprehension|comp
        $value      = $this->input->post('value', true);
        $session_id = get_session_id();

        $compositeWhere = [];
        if ($student_id > 0) $compositeWhere['student_id'] = $student_id;
        if ($subject_id > 0) $compositeWhere['subject_id'] = $subject_id;
        if ($slot_index > 0) $compositeWhere['slot_index'] = $slot_index;
        if ($session_id > 0) $compositeWhere['session_id'] = $session_id;


        if (!$student_id || !$term) return $this->_json(false, 'Invalid request');
        if ($field === 'percentage') $field = 'percent';
        if ($field === 'comp')       $field = 'comprehension';
        if (!in_array($field, ['title','wpm','percent','comprehension'], true)) {
            return $this->_json(false, 'Invalid field');
        }

        // Table + column mapping
        [$table, $cOrErr] = $this->_pick_rp_table();
        if (!$table) return $this->_json(false, $cOrErr);
        $c = $cOrErr;

        // Numeric guards
        if (in_array($field, ['wpm','percent','comprehension'], true)) {
            if ($value === '' || !is_numeric($value)) return $this->_json(false, 'Numeric value required');
            $v = (float)$value;
            if ($field === 'wpm') $v = max(0, min(1000, (int)$v));
            else                  $v = max(0, min(100,  $v));
            $value = $v;
        }

        // WHERE
        $where = [ $c['student'] => $student_id, $c['term'] => $term ];
        if ($c['session']) $where[$c['session']] = $session_id;

        $row = $this->db->get_where($table, $where)->row_array();

        // Field -> real column
        $colMap = [
            'title'         => $c['title'],
            'wpm'           => $c['wpm'],
            'percent'       => $c['percent'],
            'comprehension' => $c['comp'],
        ];
        $realCol = $colMap[$field];
        if (!$realCol) return $this->_json(false, "Column for '$field' not found in $table");

        $data = [ $realCol => $value, 'updated_at' => date('Y-m-d H:i:s') ];

        if ($row) {
            $this->db->where('id', $row[$c['id']])->update($table, $data);
        } else {
            $data = array_merge($data, $where, ['created_at' => date('Y-m-d H:i:s')]);
            $this->db->insert($table, $data);
        }

        $err = $this->db->error();
        return $err['code'] ? $this->_json(false, 'DB error: ' . $err['message']) : $this->_json(true);
    }

    // -------------------------------------------------------------------------
    // Save General Assignment cell (date|item|percent) for a specific term/row
    // -------------------------------------------------------------------------
    public function update_ga()
    {
        // Accept multiple param name variants robustly
        $student_id = (int)($this->input->post('student_id') ?? $this->input->post('sid') ?? 0);
        $term       = $this->_norm_term($this->input->post('term'));
        $row_index  = (int)($this->input->post('row') ?? $this->input->post('row_index') ?? $this->input->post('r') ?? 0);
        $field      = strtolower(trim((string)($this->input->post('field') ?? ''))); // date|item|percent|percentage|%
        $value      = $this->input->post('value', true);
        $session_id = get_session_id();

        if ($field === 'percentage' || $field === '%') $field = 'percent';

        // Basic validation
        if (!$student_id || !$term || $row_index < 1 || $row_index > 7) {
            return $this->_json(false, 'Invalid request');
        }
        if (!in_array($field, ['date','item','percent'], true)) {
            return $this->_json(false, 'Invalid field');
        }

        // Resolve table / columns
        $map = $this->_ga_table_map();
        if (!$map) {
            return $this->_json(false, "GA table is missing required columns (need student_id, term, item, percent/percentage and row_index/row_no/position)");
        }
        $table   = $map['table'];
        $c       = $map['col'];
        $colDate = $c['date'];      // 'date' or 'date_txt' (may be null)
        $colPct  = $c['percent'];   // 'percent' or 'percentage'
        $colRow  = $c['row'];       // row_index / row_no / position
        $colSes  = $c['session'];   // session_id / year

        // Normalize value
        if ($field === 'percent') {
            if ($value === '' || !is_numeric($value)) return $this->_json(false, 'Numeric % required');
            $value = max(0, min(100, (float)$value));
        } elseif ($field === 'date') {
            if (!$colDate) return $this->_json(false, 'Date column not found on GA table');
            $value = trim((string)$value);
            if ($value !== '') {
                $ts = strtotime(str_replace('/', '-', $value));
                if ($ts !== false) {
                    $value = ($colDate === 'date') ? date('Y-m-d', $ts) : date('Y/m/d', $ts);
                }
            }
        }

        // WHERE for upsert
        $where = ['student_id' => $student_id, 'term' => $term, $colRow => $row_index];
        if ($colSes) $where[$colSes] = $session_id;

        $row = $this->db->where($where)->get($table)->row_array();

        // Column to update for this field
        $updateCol = ($field === 'percent') ? $colPct : (($field === 'date') ? $colDate : 'item');

        if ($row) {
            $this->db->where('id', $row['id'])
                     ->update($table, [$updateCol => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $insert = $where + [
                $updateCol   => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($updateCol !== 'item' && $this->db->field_exists('item', $table)) {
                $insert['item'] = '';
            }
            $this->db->insert($table, $insert);
        }

        return $this->_json(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    // normalize "1|2|3|4|Q1|Q2|Q3|Q4" → Q1..Q4
    private function _norm_term($t)
    {
        $t = strtoupper(trim((string)$t));
        if ($t === '1') return 'Q1';
        if ($t === '2') return 'Q2';
        if ($t === '3') return 'Q3';
        if ($t === '4') return 'Q4';
        return in_array($t, ['Q1','Q2','Q3','Q4'], true) ? $t : '';
    }

    // JSON responder
    function _json($ok, $msg = null, $extra = [])
    {
        if ($ok) {
            $out = array_merge(['success' => true, 'ok' => true], $extra);
        } else {
            $m   = ($msg ?: 'Save failed');
            $out = ['success' => false, 'ok' => false, 'error' => $m, 'msg' => $m] + $extra;
        }

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
        }

        $this->output->set_status_header(200)
                     ->set_content_type('application/json', 'utf-8')
                     ->set_output(json_encode($out));
        exit;
    }

    // Auto-detect GA table + columns
    private function _ga_table_map()
    {
        $candidates = ['spc_general_assignments','general_assignments'];
        $table = null;
        foreach ($candidates as $t) {
            if ($this->db->table_exists($t)) { $table = $t; break; }
        }
        if (!$table) return null;

        $cols = [
            'date'    => $this->db->field_exists('date', $table) ? 'date'
                         : ($this->db->field_exists('date_txt', $table) ? 'date_txt' : null),
            'percent' => $this->db->field_exists('percent', $table) ? 'percent'
                         : ($this->db->field_exists('percentage', $table) ? 'percentage' : null),
            'row'     => $this->db->field_exists('row_index', $table) ? 'row_index'
                         : ($this->db->field_exists('row_no', $table) ? 'row_no'
                         : ($this->db->field_exists('position', $table) ? 'position' : null)),
            'session' => $this->db->field_exists('session_id', $table) ? 'session_id'
                         : ($this->db->field_exists('year', $table) ? 'year' : null),
        ];

        $required_ok = $this->db->field_exists('student_id',$table)
                    && $this->db->field_exists('term',$table)
                    && $this->db->field_exists('item',$table)
                    && ($cols['percent'] !== null)
                    && ($cols['row'] !== null);
        if (!$required_ok) return null;

        return ['table'=>$table,'col'=>$cols];
    }

    // Auto-detect Reading Programme table + columns
    private function _pick_rp_table()
    {
        $candidates = ['spc_reading_program', 'report_reading_program'];
        $table = null;
        foreach ($candidates as $t) {
            if ($this->db->table_exists($t)) { $table = $t; break; }
        }
        if (!$table) return [null, 'No reading program table (spc_reading_program/report_reading_program)'];

        $cols = [
            'id'      => $this->db->field_exists('id', $table) ? 'id' : null,
            'student' => $this->db->field_exists('student_id', $table) ? 'student_id' : null,
            'term'    => $this->db->field_exists('term', $table) ? 'term' : null,
            'title'   => $this->db->field_exists('title', $table) ? 'title' : null,
            'wpm'     => $this->db->field_exists('wpm', $table) ? 'wpm' : null,
            'percent' => $this->db->field_exists('percent', $table) ? 'percent'
                         : ($this->db->field_exists('percentage', $table) ? 'percentage' : null),
            'comp'    => $this->db->field_exists('comprehension', $table) ? 'comprehension'
                         : ($this->db->field_exists('comp', $table) ? 'comp'
                         : ($this->db->field_exists('comp_score', $table) ? 'comp_score' : null)),
            'session' => $this->db->field_exists('session_id', $table) ? 'session_id'
                         : ($this->db->field_exists('year', $table) ? 'year' : null),
        ];

        if (!$cols['student'] || !$cols['term'] || !$cols['id']) {
            return [null, "Reading table '$table' missing required columns (id, student_id, term)"];
        }
        return [$table, $cols];
    }

    // Utility: compute next slot index for a subject
    public function get_next_slot($student_id, $subject_id, $session_id)
    {
        $row = $this->db->select('COALESCE(MAX(slot_index),0) AS max_slot', false)
            ->from('student_assign_paces')
            ->where('student_id', $student_id)
            ->where('subject_id', $subject_id)
            ->where('session_id', $session_id)
            ->get()->row_array();

        return (int)$row['max_slot'] + 1; // 1..12
    }

    private function _fmt_date_for_input($v)
    {
        $v = (string)$v;
        if ($v === '') return '';
        $ts = strtotime(str_replace('/', '-', $v));
        return $ts ? date('Y-m-d', $ts) : '';
    }

    // Upsert Reading Programme (accepts normal POST and AJAX)
    public function save_reading_program()
    {
        $student_id = (int)$this->input->post('student_id');
        $term       = $this->_norm_term($this->input->post('term'));
        if (!$student_id || !$term) {
            return $this->_json(false, 'Missing student_id or term');
        }

        $vals = [];
        foreach (['title','wpm','percent','comprehension','comp'] as $k) {
            $v = $this->input->post($k, true);
            if ($v !== null) { $vals[$k] = $v; }
        }

        $ok = $this->Spc_model->upsert_reading_program(
            $student_id,
            get_session_id(),
            $term,
            $vals
        );

        return $this->output->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => $ok ? 'ok' : 'error',
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
            ]));
    }

    private function _detect_order_tables()
    {
        $batchCandidates = ['pace_orders_batches','orders_batches','order_batches'];
        $itemCandidates  = ['pace_orders','orders','order_paces','pace_order_items'];

        $batch = null; foreach ($batchCandidates as $t) if ($this->db->table_exists($t)) { $batch = $t; break; }
        $item  = null; foreach ($itemCandidates  as $t) if ($this->db->table_exists($t))  { $item  = $t; break; }

        if (!$batch || !$item) return null;

        $batch_fk = $this->db->field_exists('batch_id', $item) ? 'batch_id'
                  : ($this->db->field_exists('orders_batch_id', $item) ? 'orders_batch_id'
                  : ($this->db->field_exists('invoice_id', $item) ? 'invoice_id' : 'batch_id'));

        return ['batch'=>$batch, 'item'=>$item, 'batch_fk'=>$batch_fk];
    }

    // Create a NEW order batch & invoice line for a REDO PACE (schema-aware).
    private function _redo_create_new_invoice(array $sap)
    {
        if (empty($sap['id'])) return;

        $invTable   = $this->db->table_exists('invoice')              ? 'invoice'
                    : ($this->db->table_exists('invoices')            ? 'invoices'
                    : ($this->db->table_exists('hs_academy_invoices') ? 'hs_academy_invoices' : null));
        $itemsTable = $this->db->table_exists('invoice_items')        ? 'invoice_items'
                    : ($this->db->table_exists('hs_academy_invoice_items') ? 'hs_academy_invoice_items' : null);

        if (!$invTable || !$itemsTable) { log_message('error','SPC REDO: invoice tables missing'); return; }

        $now     = date('Y-m-d H:i:s');
        $branch  = (int)($sap['branch_id'] ?? get_loggedin_branch_id());
        $session = (int)($sap['session_id'] ?? get_session_id());

        // Flag SAP as redo & bump attempt
        $this->db->where('id', (int)$sap['id'])->update('student_assign_paces', [
            'is_redo'        => 1,
            'attempt_number' => max(2, (int)($sap['attempt_number'] ?? 1) + 1),
        ]);

        // --- Invoice header (status=redo) ---
        $inv = [
            'branch_id'  => $branch,
            'student_id' => (int)$sap['student_id'],
            'session_id' => $session,
            'status'     => $this->_inv_status_value($invTable, 'redo'),
            'total'      => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($this->db->field_exists('is_redo', $invTable)) $inv['is_redo'] = 1;

        $invFields = $this->db->list_fields($invTable);
        $this->db->insert($invTable, array_intersect_key($inv, array_flip($invFields)));
        $err = $this->db->error(); if ($err['code']) { log_message('error','SPC REDO header insert: '.$err['code'].' '.$err['message']); return; }
        $invoice_id = (int)$this->db->insert_id();
        if ($invoice_id <= 0) { log_message('error','SPC REDO: failed to insert invoice header'); return; }

        // --- Invoice item ---
        $fields = $this->db->list_fields($itemsTable);

        $paceCol = in_array('pace_number', $fields, true) ? 'pace_number'
                : (in_array('pace_no', $fields, true) ? 'pace_no'
                : (in_array('book_number', $fields, true) ? 'book_number' : null));
        $qtyCol  = in_array('qty', $fields, true) ? 'qty' : (in_array('quantity', $fields, true) ? 'quantity' : 'qty');
        $upCol   = in_array('unit_price', $fields, true) ? 'unit_price' : (in_array('price', $fields, true) ? 'price' : 'unit_price');
        $ltCol   = in_array('line_total', $fields, true) ? 'line_total' : (in_array('total', $fields, true) ? 'total' : 'line_total');

        $noteCol = null; foreach (['description','notes','note','label','item_name','title'] as $c) { if (in_array($c, $fields, true)) { $noteCol = $c; break; } }

        $payload = [
            'invoice_id' => $invoice_id,
            'sap_id'     => (int)$sap['id'],
            'subject_id' => (int)$sap['subject_id'],
            $qtyCol      => 1,
            $upCol       => 0,
            $ltCol       => 0,
            'created_at' => $now,
        ];
        if ($paceCol) $payload[$paceCol] = (int)$sap['pace_number'];
        if ($this->db->field_exists('is_redo', $itemsTable)) $payload['is_redo'] = 1;
        if ($noteCol) {
            $subject = $this->db->select('name')->get_where('subject', ['id' => (int)$sap['subject_id']])->row('name');
            $payload[$noteCol] = trim(($subject ?: 'Subject').' PACE '.(int)$sap['pace_number'].' [REDO]');
        }

        $this->db->insert($itemsTable, array_intersect_key($payload, array_flip($fields)));
        $err = $this->db->error(); if ($err['code']) { log_message('error','SPC REDO item insert: '.$err['code'].' '.$err['message']); }

        // --- One per-invoice notification (de-dup by receiver + invoice_id in URL) ---
        if ($this->db->table_exists('notifications')) {
            $receivers = $this->db->select('lc.user_id')
                ->from('login_credential lc')->join('staff s','s.id=lc.user_id','left')
                ->where_in('lc.role', [1,2,8])->where('lc.active',1)->where('s.branch_id',$branch)
                ->get()->result_array();

            $count = (int)$this->db->where('invoice_id',$invoice_id)->count_all_results($itemsTable);
            $url   = site_url('pace/order?invoice_id='.$invoice_id);
            $msg   = "Student #{$sap['student_id']} ordered {$count} PACE(s) in this invoice.";
            $now   = date('Y-m-d H:i:s');

            foreach ($receivers as $r) {
                $uid = (int)$r['user_id'];
                $existing = $this->db->where('receiver_id',$uid)->like('url','invoice_id='.$invoice_id,'both')
                    ->where('is_read',0)->order_by('id','DESC')->limit(1)->get('notifications')->row_array();

                if ($existing) {
                    $upd = ['message'=>$msg]; if ($this->db->field_exists('updated_at','notifications')) $upd['updated_at']=$now;
                    $this->db->where('id',(int)$existing['id'])->update('notifications',$upd);
                } else {
                    $this->db->insert('notifications', [
                        'receiver_id'=>$uid,'title'=>'PACE Order Batch','message'=>$msg,'url'=>$url,
                        'branch_id'=>$branch,'created_at'=>$now,'is_read'=>0,
                    ]);
                }
            }
        }
    }

    // AJAX: save elective name for a student/subject
    public function save_elective_alias()
    {
        $student_id = (int)$this->input->post('student_id');
        $subject_id = (int)$this->input->post('subject_id');
        $name       = (string)$this->input->post('name', true);

        if (!$student_id || !$subject_id) {
            return $this->_json(false, 'Invalid request', [
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
            ]);
        }

        $ok = $this->Spc_model->save_elective_alias(
            $student_id, $subject_id, $name, (int)get_session_id()
        );

        $err = $this->db->error();
        if (!$ok && $err && !empty($err['message'])) {
            log_message('error', 'SPC elective alias save failed: '.$err['code'].' '.$err['message']);
            return $this->_json(false, 'DB error: '.$err['message'], [
                $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
            ]);
        }

        return $this->_json(true, null, [
            $this->security->get_csrf_token_name() => $this->security->get_csrf_hash(),
        ]);
    }
// QUICK DIAGNOSTIC: /spc/diag?student_id=123&term=1
public function diag()
{
    $student_id = (int)$this->input->get('student_id');
    $term_in    = $this->input->get('term');
    $session_id = (int)get_session_id();

    $slotExists      = $this->db->field_exists('slot', 'student_assign_paces');
    $slotIndexExists = $this->db->field_exists('slot_index', 'student_assign_paces');

    // counts per status for this student
    $byStatus = $this->db->select('COALESCE(UPPER(status), "NULL") AS status, COUNT(*) AS cnt', false)
        ->from('student_assign_paces')
        ->where('student_id', $student_id)
        ->group_by('status')
        ->get()->result_array();

    // sample of rows we expect to show on SPC
    $this->db->reset_query();
    $sel = 'id, subject_id, pace_number, status';
    if ($slotExists)      $sel .= ', slot';
    if ($slotIndexExists) $sel .= ', slot_index';

    $sample = $this->db->select($sel)
        ->from('student_assign_paces')
        ->where('student_id', $student_id)
        ->where_in('status', ['assigned','issued','completed','redo'])
        ->order_by('subject_id','asc')
        ->order_by($slotIndexExists ? 'slot_index' : ($slotExists ? 'slot' : 'id'),'asc', false)
        ->limit(20)
        ->get()->result_array();

    $out = [
        'ok'          => true,
        'student_id'  => $student_id,
        'term_param'  => $term_in,
        'session_id'  => $session_id,
        'table'       => 'student_assign_paces',
        'columns'     => ['slot' => $slotExists, 'slot_index' => $slotIndexExists],
        'by_status'   => $byStatus,
        'sample_rows' => $sample,
    ];

    $this->output->set_content_type('application/json')
                 ->set_output(json_encode($out, JSON_PRETTY_PRINT));
}

  private function _inv_status_value($table, $label) {
    $row = $this->db->query("SHOW COLUMNS FROM {$table} LIKE 'status'")->row_array();
    $isInt = $row && stripos($row['Type'] ?? '', 'int') !== false;
    $map = ['draft'=>0,'paid'=>1,'issued'=>2,'billed'=>3,'redo'=>4];
    return $isInt ? ($map[$label] ?? 0) : $label;
}  
}
