<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Monitor_goal_check_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        // We’ll load Pace_model on demand inside methods; no eager load required.
    }

    public function get_by_term($student_id, $term_id)
    {
        $branch_id = get_loggedin_branch_id();
        return $this->db
            ->where('student_id', $student_id)
            ->where('term_id', $term_id)
            ->where('branch_id', $branch_id)
            ->order_by('date', 'asc')
            ->get('monitor_goal_check')
            ->result_array();
    }

    public function get_all_term_dates()
    {
        $branch_id = get_loggedin_branch_id();
        return $this->db
            ->where('branch_id', $branch_id)
            ->order_by('term_id', 'asc')
            ->get('term_dates')
            ->result_array();
    }

    public function save_or_update_combined($data)
    {
        $student_id = $data['student_id'];
        $term_id    = $data['term_id'];
        $date       = $data['date'];
        $week_no    = $data['week_no'];

        $entry = [
            'student_id'         => $student_id,
            'term_id'            => $term_id,
            'date'               => $date,
            'week_no'            => $week_no,
            'attendance_status'  => $data['attendance_status'] ?? null,
            'demerit'            => $data['demerit'] ?? 0,
            'merit'              => $data['merit'] ?? 0,
            'goals_json'         => isset($data['goals_json']) ? json_encode($data['goals_json']) : null,
            'week_note'          => $data['week_note'] ?? null,
            'date_note'          => $data['date_note'] ?? null,
            'scripture_note'     => $data['scripture_note'] ?? null,
            'branch_id'          => get_loggedin_branch_id(),
        ];

        // only set if provided, so unrelated saves don’t overwrite it
        if (array_key_exists('total_pages', $data)) {
            $entry['total_pages'] = (int)$data['total_pages'];
        }

        $existing = $this->db->get_where('monitor_goal_check', [
            'student_id' => $student_id,
            'term_id'    => $term_id,
            'date'       => $date,
        ])->row();

        if ($existing) {
            $this->db->where('id', $existing->id);
            return $this->db->update('monitor_goal_check', $entry);
        } else {
            return $this->db->insert('monitor_goal_check', $entry);
        }
    }

    /**
     * SUBJECTS FOR MGC GRID (PACE-first)
     * ----------------------------------
     * We first try to derive the subject list from student_assign_paces
     * for this student/term/session (statuses: assigned/issued/completed/redo/ordered/paid).
     * If none exist (new student, new term), we fall back to:
     *  - Mandatory subjects assigned to the class/section
     *  - Optional subjects assigned AND selected by the student
     *
     * Signature kept backward-compatible; $term is optional.
     */
    public function get_student_subjects($student_id, $branch_id = null, $session_id = null, $term = null)
    {
        $branch_id  = $branch_id  ?: (int)get_loggedin_branch_id();
        $session_id = $session_id ?: (int)get_session_id();
        $student_id = (int)$student_id;

        // ---------- 1) Try derive from PACEs ----------
        if ($this->db->table_exists('student_assign_paces')) {
            // Normalize term filter (accept 1..4 or Q1..Q4)
            $termValues = [];
            if ($term !== null && $term !== '') {
                $t = strtoupper((string)$term);
                if (in_array($t, ['Q1','Q2','Q3','Q4'], true)) {
                    $termValues = [$t, substr($t,1)]; // ['Q1','1']
                } else {
                    $t = preg_replace('/\s+/', '', $t);
                    $termValues = [$t, 'Q'.$t];        // ['1','Q1']
                }
            }

            $this->db->reset_query();
            $this->db->select('DISTINCT subject_id', false)
                     ->from('student_assign_paces')
                     ->where('student_id', $student_id)
                     ->where_in('status', ['assigned','issued','completed','redo','ordered','paid']);

            if ($session_id > 0 && $this->db->field_exists('session_id','student_assign_paces')) {
                $this->db->where('session_id', $session_id);
            }

            if (!empty($termValues)) {
                $this->db->group_start();
                if ($this->db->field_exists('term','student_assign_paces'))    $this->db->or_where_in('term',    $termValues);
                if ($this->db->field_exists('terms','student_assign_paces'))   $this->db->or_where_in('terms',   $termValues);
                if ($this->db->field_exists('quarter','student_assign_paces')) $this->db->or_where_in('quarter', $termValues);
                $this->db->group_end();
            }

            $paceSubjectIds = array_map('intval', array_column($this->db->get()->result_array(), 'subject_id'));

            if (!empty($paceSubjectIds)) {
                // Fetch those subjects (do NOT over-filter by branch to avoid accidental drops)
                $this->db->reset_query();
                $this->db->select('id, name, abbreviation, subject_code, subject_type, subject_author')
                         ->from('subject')
                         ->where_in('id', $paceSubjectIds)
                         ->order_by('(subject_code IS NULL)', 'asc', false)
                         ->order_by('CAST(subject_code AS UNSIGNED)', 'asc', false)
                         ->order_by('name', 'asc');

                $subjects = $this->db->get()->result_array();

                // Add placeholders for any subject ids missing from the subject table
                $have = [];
                foreach ($subjects as $s) $have[(int)$s['id']] = true;
                foreach ($paceSubjectIds as $sid) {
                    if (empty($have[$sid])) {
                        $subjects[] = [
                            'id'             => (int)$sid,
                            'name'           => 'Subject '.$sid,
                            'abbreviation'   => '',
                            'subject_code'   => null,
                            'subject_type'   => 'Mandatory',
                            'subject_author' => 'ACE',
                        ];
                    }
                }

                // Abbreviation fallback
                foreach ($subjects as &$sub) {
                    if (empty($sub['abbreviation'])) {
                        $sub['abbreviation'] = isset($sub['subject_code']) && $sub['subject_code'] !== null
                            ? (string)$sub['subject_code']
                            : (isset($sub['name']) ? (string)$sub['name'] : '');
                    }
                }
                unset($sub);

                return $subjects;
            }
        }

        // ---------- 2) Fallback: class/section + optional selections ----------
        $enroll = $this->db->select('class_id, section_id')
            ->from('enroll')
            ->where('student_id', $student_id)
            ->where('session_id', $session_id)
            ->get()->row_array();

        if (!$enroll) {
            return [];
        }

        $class_id   = (int)$enroll['class_id'];
        $section_id = (int)$enroll['section_id'];

        $this->db->select('s.id, s.name, s.abbreviation, s.subject_code, s.subject_type');
        $this->db->from('subject AS s');

        // Assigned to class/section in this session/branch
        $this->db->join(
            'subject_assign AS sa',
            "sa.subject_id = s.id
             AND sa.class_id = {$class_id}
             AND sa.section_id = {$section_id}
             AND sa.session_id = {$session_id}
             AND sa.branch_id = {$branch_id}",
            'left',
            false
        );

        // Student's optional selections
        $this->db->join(
            'student_optional_subjects AS sos',
            "sos.subject_id = s.id
             AND sos.student_id = {$student_id}
             AND sos.session_id = {$session_id}
             AND sos.branch_id = {$branch_id}",
            'left',
            false
        );

        $this->db->where('s.branch_id', $branch_id);
        $this->db->where("
            (
              (s.subject_type = 'Mandatory' AND sa.id IS NOT NULL)
              OR
              (s.subject_type <> 'Mandatory' AND sa.id IS NOT NULL AND sos.id IS NOT NULL)
            )
        ", null, false);

        $this->db->order_by('s.subject_code', 'ASC');
        $subjects = $this->db->get()->result_array();

        foreach ($subjects as &$sub) {
            if (empty($sub['abbreviation'])) {
                $sub['abbreviation'] = (string)$sub['subject_code'];
            }
        }
        unset($sub);

        return $subjects;
    }

    // Returns only OPTIONAL subjects assigned to the student's class/section,
    // annotated with whether the student has already selected them.
    public function get_available_optionals_for_student($student_id, $branch_id = null, $session_id = null)
    {
        $branch_id  = $branch_id  ?: get_loggedin_branch_id();
        $session_id = $session_id ?: get_session_id();

        $enroll = $this->db->select('class_id, section_id')
            ->from('enroll')
            ->where('student_id', (int)$student_id)
            ->where('session_id', (int)$session_id)
            ->get()->row_array();
        if (!$enroll) return [];

        $class_id   = (int)$enroll['class_id'];
        $section_id = (int)$enroll['section_id'];

        $this->db->select("s.id, s.name, s.abbreviation, s.subject_code, (sos.id IS NOT NULL) AS selected", false);
        $this->db->from('subject s');
        $this->db->join(
            'subject_assign sa',
            "sa.subject_id = s.id
             AND sa.class_id = {$class_id}
             AND sa.section_id = {$section_id}
             AND sa.session_id = {$session_id}
             AND sa.branch_id = {$branch_id}",
            'inner',
            false
        );
        $this->db->join(
            'student_optional_subjects sos',
            "sos.subject_id = s.id
             AND sos.student_id = {$student_id}
             AND sos.session_id = {$session_id}
             AND sos.branch_id = {$branch_id}",
            'left',
            false
        );
        $this->db->where('s.branch_id', $branch_id);
        $this->db->where("s.subject_type <> 'Mandatory'");
        $this->db->order_by('s.subject_code', 'ASC');

        return $this->db->get()->result_array();
    }

    // Replaces a student's optional selections with the provided subject_id list.
    public function set_student_optionals($student_id, $subject_ids, $branch_id = null, $session_id = null)
    {
        $branch_id  = $branch_id  ?: get_loggedin_branch_id();
        $session_id = $session_id ?: get_session_id();

        $this->db->trans_start();

        $this->db->where([
            'student_id' => (int)$student_id,
            'branch_id'  => (int)$branch_id,
            'session_id' => (int)$session_id,
        ])->delete('student_optional_subjects');

        if (!empty($subject_ids) && is_array($subject_ids)) {
            $rows = [];
            foreach ($subject_ids as $sid) {
                $rows[] = [
                    'branch_id'  => (int)$branch_id,
                    'session_id' => (int)$session_id,
                    'student_id' => (int)$student_id,
                    'subject_id' => (int)$sid,
                ];
            }
            if ($rows) {
                $this->db->insert_batch('student_optional_subjects', $rows);
            }
        }

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    // Insert-only (merge) optional subjects for a student based on $subject_ids.
    // Does NOT delete existing selections.
    public function add_student_optionals($student_id, $subject_ids, $branch_id = null, $session_id = null)
    {
        if (empty($subject_ids)) return true;

        $branch_id  = $branch_id  ?: get_loggedin_branch_id();
        $session_id = $session_id ?: get_session_id();

        $subject_ids = array_unique(array_map('intval', $subject_ids));

        $this->db->trans_start();
        foreach ($subject_ids as $sid) {
            // insert-if-not-exists
            $exists = $this->db->get_where('student_optional_subjects', [
                'branch_id'  => $branch_id,
                'session_id' => $session_id,
                'student_id' => (int)$student_id,
                'subject_id' => (int)$sid,
            ])->row_array();

            if (!$exists) {
                $this->db->insert('student_optional_subjects', [
                    'branch_id'  => $branch_id,
                    'session_id' => $session_id,
                    'student_id' => (int)$student_id,
                    'subject_id' => (int)$sid,
                ]);
            }
        }
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    // Stamp today’s "current PACE" so MGC grid surfaces it immediately
    public function set_assigned_pace(int $student_id, int $subject_id, int $pace_no, string $dateYmd): void
    {
        $table  = 'monitor_goal_check';
        $fields = array_flip($this->db->list_fields($table)); // schema-aware

        // ensure a row for today
        $row = $this->db->where('student_id', $student_id)
                        ->where('date', $dateYmd)
                        ->get($table)->row_array();

        if (!$row) {
            // required columns
            $insert = [
                'student_id' => $student_id,
                'date'       => $dateYmd,
                'goals_json' => json_encode(new stdClass()),
            ];

            // optional columns — ONLY set if they exist
            if (isset($fields['branch_id']))      $insert['branch_id']  = (int) get_loggedin_branch_id();

            $termNum = $this->infer_term_from_date($dateYmd); // 1..4
            if (isset($fields['term']))           $insert['term']    = $termNum;
            elseif (isset($fields['term_id']))    $insert['term_id'] = $termNum;

            $now = date('Y-m-d H:i:s');
            if (isset($fields['created_at']))     $insert['created_at'] = $now;
            if (isset($fields['updated_at']))     $insert['updated_at'] = $now;

            $this->db->insert($table, $insert);

            // re-read
            $row = $this->db->where('student_id', $student_id)
                            ->where('date', $dateYmd)
                            ->get($table)->row_array();
            if (!$row) return;
        }

        // update goals_json with the current pace marker
        $goals = json_decode($row['goals_json'] ?: '{}', true);
        if (!isset($goals['current_paces'])) $goals['current_paces'] = [];
        $goals['current_paces'][(string)$subject_id] = (int)$pace_no;

        $upd = ['goals_json' => json_encode($goals)];
        if (isset($fields['updated_at'])) $upd['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', (int)$row['id'])->update($table, $upd);
    }

    private function infer_term_from_date(string $dateYmd): int
    {
        $m = (int)date('n', strtotime($dateYmd));
        if ($m <= 3) return 1;
        if ($m <= 6) return 2;
        if ($m <= 9) return 3;
        return 4;
    }
}
