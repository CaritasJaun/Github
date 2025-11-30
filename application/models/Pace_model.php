<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Pace_model extends CI_Model
{
    /* ============================================================
     * STUDENTS / SUBJECTS
     * ============================================================ */

    /** Return ACTIVE students this user may see (arrays). */
    public function get_all_students()
    {
        $role   = (int) get_loggedin_role_id(); // 1 SA, 2 Admin, 3 Teacher, 4 Accountant, 6 Principal, 8 Receptionist...
        $userID = (int) get_loggedin_user_id();

        // Admin family → all active students
        if (in_array($role, [1,2,4,6,8], true)) {
            return $this->db->select('id, first_name, last_name')
                ->from('student')
                ->where('active', 1)
                ->order_by('first_name', 'asc')
                ->get()->result_array();
        }

        // Teachers → only their classes' students
        if ($role === 3) {
            $classIDs = $this->db->select('class_id')
                ->from('teacher_allocation')
                ->where('teacher_id', $userID)
                ->get()->result_array();

            if (!$classIDs) return [];

            return $this->db->select('stu.id, stu.first_name, stu.last_name')
                ->from('student AS stu')
                ->join('enroll AS en', 'en.student_id = stu.id', 'left')
                ->where_in('en.class_id', array_column($classIDs, 'class_id'))
                ->where('stu.active', 1)
                ->group_by('stu.id')
                ->order_by('stu.first_name', 'asc')
                ->get()->result_array();
        }

        return [];
    }

    /**
     * Enrolled subjects for a learner (by their current class) in **report order**.
     * Prefers subject_assign; falls back to full subject list if no mapping.
     */
    public function get_enrolled_subjects($student_id)
    {
        $student_id = (int) $student_id;

        // Most recent class mapping
        $class = $this->db->select('class_id')
            ->from('enroll')
            ->where('student_id', $student_id)
            ->order_by('id', 'desc')
            ->limit(1)->get()->row_array();

        if ($class && $this->db->table_exists('subject_assign')) {
            return $this->db->select('s.id, s.name, s.subject_code')
                ->from('subject_assign AS sa')
                ->join('subject AS s', 's.id = sa.subject_id', 'left')
                ->where('sa.class_id', (int)$class['class_id'])
                // report order: numeric subject_code, NULLs last, then name
                ->order_by('(s.subject_code IS NULL)', 'asc', false)
                ->order_by('CAST(s.subject_code AS UNSIGNED)', 'asc', false)
                ->order_by('s.name', 'asc')
                ->get()->result_array();
        }

        // Fallback → all subjects in report order
        return $this->get_all_subjects();
    }
/** Return the column name to use for the visual slot, or null if none. */
private function _slot_col()
{
    static $col = null;
    if ($col !== null) return $col;
    if ($this->db->field_exists('slot', 'student_assign_paces'))      $col = 'slot';
    elseif ($this->db->field_exists('slot_index', 'student_assign_paces')) $col = 'slot_index';
    else $col = null;
    return $col;
}

/** Put the slot value into whichever slot columns exist. */
private function _put_slot_cols(array &$arr, int $value): void
{
    if ($this->db->field_exists('slot', 'student_assign_paces'))      $arr['slot'] = $value;
    if ($this->db->field_exists('slot_index', 'student_assign_paces')) $arr['slot_index'] = $value;
}

/** Read current slot value from a row (0 if absent). */
private function _row_slot_val(array $row): int
{
    $c = $this->_slot_col();
    return ($c && isset($row[$c]) && $row[$c] !== null) ? (int)$row[$c] : 0;
}

    /** All subjects (arrays) in **report order**. */
    public function get_all_subjects()
    {
        return $this->db->select('id, name, subject_code')
            ->from('subject')
            ->order_by('(subject_code IS NULL)', 'asc', false)
            ->order_by('CAST(subject_code AS UNSIGNED)', 'asc', false)
            ->order_by('name', 'asc')
            ->get()->result_array();
    }

    /* ============================================================
     * ORDER PIPELINE / ASSIGNMENTS
     * ============================================================ */

    /**
     * All assignments for a student (optionally by term),
     * ordered by report subject order then the visual slot.
     * Includes a virtual slot backfill so legacy rows with slot_index=0 still render.
     */
   public function get_student_paces($student_id, $term = null, $session_id = null, $statuses = null)
{
    $student_id = (int)$student_id;
    if ($session_id === null) $session_id = (int)get_session_id();
    if ($statuses === null)   $statuses   = ['assigned','issued','redo','completed','ordered','paid'];

    // Accept Q1..Q4 or 1..4
    $termValues = [];
    if ($term !== null && $term !== '') {
        $t = strtoupper((string)$term);
        if (in_array($t, ['Q1','Q2','Q3','Q4'], true))      $termValues = [$t, substr($t,1)];
        else { $t = preg_replace('/\s+/', '', $t); $termValues = [$t, 'Q'.$t]; }
    }

    $slotCol = $this->_slot_col(); // 'slot' or 'slot_index' or null

    $run = function(bool $withSession) use ($student_id,$session_id,$termValues,$statuses,$slotCol)
    {
        $this->db->reset_query();
        $this->db->select('sap.*, sub.name AS subject_name, sub.subject_code')
                 ->from('student_assign_paces AS sap')
                 ->join('subject AS sub', 'sub.id = sap.subject_id', 'left')
                 ->where('sap.student_id', $student_id);

        if (!empty($statuses)) $this->db->where_in('sap.status', (array)$statuses);
        if ($withSession && $session_id > 0 && $this->db->field_exists('session_id','student_assign_paces')) {
            $this->db->where('sap.session_id', (int)$session_id);
        }

        if (!empty($termValues)) {
            $this->db->group_start();
            if ($this->db->field_exists('term', 'student_assign_paces'))    $this->db->or_where_in('sap.term', $termValues);
            if ($this->db->field_exists('terms', 'student_assign_paces'))   $this->db->or_where_in('sap.terms', $termValues);
            if ($this->db->field_exists('quarter', 'student_assign_paces')) $this->db->or_where_in('sap.quarter', $termValues);
            $this->db->group_end();
        }

        $this->db->order_by('(sub.subject_code IS NULL)', 'asc', false)
                 ->order_by('CAST(sub.subject_code AS UNSIGNED)', 'asc', false);
        if ($slotCol) $this->db->order_by("COALESCE(sap.$slotCol,0)", 'asc', false);

        $q = $this->db->get();
        return $q ? $q->result_array() : [];
    };

    $rows = $run(true);
    if (empty($rows)) $rows = $run(false);
    return $rows;
}


    /* ---------- Available PACE numbers ---------- */

    /** Admin view: all PACE numbers in subject not yet taken by this student. */
    public function get_available_paces_admin($student_id, $subject_id)
    {
        // Order numerically, not lexicographically
        $allNums = array_map('intval', array_column(
            $this->db->select('pace_number')
                ->from('subject_pace')
                ->where('subject_id', (int)$subject_id)
                ->order_by('CAST(pace_number AS UNSIGNED)', 'ASC', false)
                ->get()->result_array(),
            'pace_number'
        ));

        $taken = array_map('intval', array_column(
            $this->db->select('pace_number')
                ->from('student_assign_paces')
                ->where('student_id', (int)$student_id)
                ->where('subject_id', (int)$subject_id)
                ->get()->result_array(),
            'pace_number'
        ));

        return array_values(array_diff($allNums, $taken));
    }

    /** Teacher view: available PACE numbers filtered by learner grade. */
    public function get_available_paces_by_grade($student_id, $subject_id, $gradeNum)
    {
        $student_id = (int)$student_id;
        $subject_id = (int)$subject_id;
        $gradeNum   = (int)$gradeNum;

        // Also grab latest class_id in case the grade column stores class ids
        $classRow = $this->db->select('class_id')
            ->from('enroll')
            ->where('student_id', $student_id)
            ->order_by('id', 'DESC')->limit(1)
            ->get()->row_array();
        $classId = $classRow ? (int)$classRow['class_id'] : 0;

        // Build a tolerant grade match
        $this->db->select('pace_number')
            ->from('subject_pace')
            ->where('subject_id', $subject_id)
            ->group_start()
                ->where('grade', $gradeNum)
                ->or_where('grade', (string)$gradeNum)
                ->or_where('grade', 'Gr ' . $gradeNum)
                ->or_where('grade', 'Grade ' . $gradeNum)
                ->or_where('grade', $classId)
                ->or_where('grade', 'All')
                ->or_where('grade', 'ALL')
                ->or_where('grade', '*')
                ->or_where('grade', '')
                ->or_where('grade IS NULL', null, false)
            ->group_end()
            ->order_by('CAST(pace_number AS UNSIGNED)', 'ASC', false);

        $allNums = array_map('intval', array_column($this->db->get()->result_array(), 'pace_number'));

        // Remove numbers already taken
        $taken = array_map('intval', array_column(
            $this->db->select('pace_number')
                ->from('student_assign_paces')
                ->where('student_id', $student_id)
                ->where('subject_id', $subject_id)
                ->get()->result_array(),
            'pace_number'
        ));

        $available = array_values(array_diff($allNums, $taken));
        sort($available, SORT_NUMERIC);
        return $available;
    }

    /** Helper: next free visual slot (column) in SPC for that subject/year. */
    private function get_next_slot($student_id, $subject_id, $session_id)
{
    $slotCol = $this->_slot_col();
    if (!$slotCol) return 1; // if no slot column exists at all

    $row = $this->db->select("COALESCE(MAX($slotCol),0) AS max_slot", false)
        ->from('student_assign_paces')
        ->where('student_id', (int)$student_id)
        ->where('subject_id', (int)$subject_id)
        ->where('session_id', (int)$session_id)
        ->get()->row_array();

    return (int)($row['max_slot'] ?? 0) + 1;
}

    /**
     * Start pipeline rows as "assigned" (bulk assign / optional UI).
     * Ensures slots lock left→right; avoids duplicates.
     */
    public function save_pace_assignments($student_id, $subject_id, $term, $pace_nums)
{
    if (empty($pace_nums)) return;
    if (!is_array($pace_nums)) $pace_nums = [$pace_nums];

    $session = (int)get_session_id();
    $branch  = (int)get_loggedin_branch_id();
    $next    = $this->get_next_slot($student_id, $subject_id, $session);
    $slotCol = $this->_slot_col();

    foreach ($pace_nums as $num) {
        $num = (int)$num; if ($num <= 0) continue;

        $select = 'id, status';
        if ($slotCol) $select .= ", $slotCol";
        $existing = $this->db->select($select)
            ->from('student_assign_paces')
            ->where([
                'student_id'  => (int)$student_id,
                'subject_id'  => (int)$subject_id,
                'pace_number' => $num,
                'session_id'  => $session,
            ])->get()->row_array();

        if ($existing) {
            $upd = [];
            if ($existing['status'] !== 'assigned') $upd['status'] = 'assigned';

            $curr = $this->_row_slot_val($existing);
            if ($slotCol && $curr <= 0) {
                $this->_put_slot_cols($upd, $next);
                $next++;
            }
            if ($upd) $this->db->update('student_assign_paces', $upd, ['id' => (int)$existing['id']]);
            continue;
        }

        $row = [
            'branch_id'      => $branch,
            'student_id'     => (int)$student_id,
            'subject_id'     => (int)$subject_id,
            'pace_number'    => $num,
            'term'           => $term,
            'status'         => 'assigned',
            'assigned_date'  => date('Y-m-d'),
            'session_id'     => $session,
            'attempt_number' => 1,
        ];
        if ($slotCol) {
            $this->_put_slot_cols($row, $next);
            $next++;
        }

        $this->db->insert('student_assign_paces', $row);
    }
}

    /** Single row fetch (array). */
    public function get_single_assign($id)
    {
        return $this->db->select('sap.*, stu.first_name, stu.last_name')
            ->from('student_assign_paces AS sap')
            ->join('student AS stu', 'stu.id = sap.student_id', 'left')
            ->where('sap.id', (int)$id)
            ->get()->row_array();
    }

    /* ============================================================
     * SCORING
     * ============================================================ */

    /** Save test score with first/second attempt rules; returns bool. */
    public function save_test_score($assign_id, $score, $remarks = '')
    {
        $assign_id  = (int)$assign_id;
        $score      = (int)$score;
        $assignment = $this->db->get_where('student_assign_paces', ['id' => $assign_id])->row_array();
        if (!$assignment) return false;

        $update = [
            'remarks'     => $remarks,
            'scored_date' => date('Y-m-d'),
        ];

        $first  = $assignment['first_attempt_score'];
        $second = $assignment['second_attempt_score'];

        if (is_null($first)) {
            // First attempt
            $update['first_attempt_score'] = $score;
            $update['attempt_number']      = 1;
            $update['status']              = ($score >= 80) ? 'completed' : 'issued';

            if ($score >= 80) {
                $update['completed_at'] = date('Y-m-d H:i:s');

$currSlot = $this->_row_slot_val($assignment);
if ($currSlot === 0) {
    $slot = $this->get_next_slot((int)$assignment['student_id'], (int)$assignment['subject_id'], (int)$assignment['session_id']);
    $this->_put_slot_cols($update, $slot);
}
            }
        } elseif (is_null($second)) {
            // Second attempt
            $update['second_attempt_score'] = $score;
            $update['attempt_number']       = 2;

            $final  = max((int)$assignment['first_attempt_score'], $score);
            $status = ($final >= 80) ? 'completed' : 'redo';

            $update['status']        = $status;
            $update['final_score']   = $final;
            $update['final_attempt'] = ($score >= (int)$assignment['first_attempt_score']) ? 'second' : 'first';

            if ($status === 'completed') {
                $update['completed_at'] = date('Y-m-d H:i:s');

                $currSlot = $this->_row_slot_val($assignment);
if ($currSlot === 0) {
    $slot = $this->get_next_slot((int)$assignment['student_id'], (int)$assignment['subject_id'], (int)$assignment['session_id']);
    $this->_put_slot_cols($update, $slot);
}
            }
        } else {
            // > 2 attempts not handled here
            return false;
        }

        $this->db->where('id', $assign_id)->update('student_assign_paces', $update);
        return $this->db->affected_rows() > 0;
    }

    /** Bulk score save (same rules as above); returns bool. */
    public function save_score(array $row)
    {
        if (empty($row['id'])) return false;

        $assign_id  = (int)$row['id'];
        $score      = isset($row['score']) ? (int)$row['score'] : null;
        $remarks    = isset($row['remarks']) ? $row['remarks'] : '';
        $assignment = $this->db->get_where('student_assign_paces', ['id' => $assign_id])->row_array();
        if (!$assignment) return false;

        $update = [
            'remarks'     => $remarks,
            'scored_date' => date('Y-m-d'),
        ];

        $first  = $assignment['first_attempt_score'];
        $second = $assignment['second_attempt_score'];

        if (is_null($first)) {
            $update['first_attempt_score'] = $score;
            $update['attempt_number']      = 1;
            $update['status']              = ($score >= 80) ? 'completed' : 'issued';

            if ($score >= 80) {
                $update['completed_at'] = date('Y-m-d H:i:s');

               $currSlot = $this->_row_slot_val($assignment);
if ($currSlot === 0) {
    $slot = $this->get_next_slot((int)$assignment['student_id'], (int)$assignment['subject_id'], (int)$assignment['session_id']);
    $this->_put_slot_cols($update, $slot);
}
            }
        } elseif (is_null($second)) {
            $update['second_attempt_score'] = $score;
            $update['attempt_number']       = 2;

            $final = max((int)$assignment['first_attempt_score'], $score);
            if ($final >= 80) {
                $update['status']       = 'completed';
                $update['completed_at'] = date('Y-m-d H:i:s');

                $currSlot = $this->_row_slot_val($assignment);
if ($currSlot === 0) {
    $slot = $this->get_next_slot((int)$assignment['student_id'], (int)$assignment['subject_id'], (int)$assignment['session_id']);
    $this->_put_slot_cols($update, $slot);
}
            } else {
                $update['status'] = 'redo';
            }

            $update['final_score']   = $final;
            $update['final_attempt'] = ($score >= (int)$assignment['first_attempt_score']) ? 'second' : 'first';
        } else {
            return false;
        }

        $this->db->where('id', $assign_id)->update('student_assign_paces', $update);
        return $this->db->affected_rows() > 0;
    }

    /** PACEs pending scoring (issued/redo) – arrays. */
    public function get_pending_paces($student_id, $term = null)
    {
        $this->db->select('sap.*, sub.name AS subject_name, sub.subject_code')
            ->from('student_assign_paces AS sap')
            ->join('subject AS sub', 'sub.id = sap.subject_id', 'left')
            ->where('sap.student_id', (int)$student_id)
            ->where_in('sap.status', ['issued', 'redo']);

        if (!empty($term)) $this->db->where('sap.term', $term);

        $this->db
            ->order_by('(sub.subject_code IS NULL)', 'asc', false)
            ->order_by('CAST(sub.subject_code AS UNSIGNED)', 'asc', false)
            ->order_by('COALESCE(sap.slot, sap.slot_index)', 'asc', false);

        return $this->db->get()->result_array();
    }

    /* ============================================================
     * SINGLE ASSIGN (LEGACY HELPER)
     * ============================================================ */

    /** Ensure a specific PACE is assigned; lock slot if needed. */
    public function assign_single_pace($student_id, $subject_id, $term, $pace_no)
    {
        $session = (int) get_session_id();
        $branch  = (int) get_loggedin_branch_id();
        $pace_no = (int) $pace_no;

        // Does it already exist for this session?
        $row = $this->db->select('id, slot, slot_index, status')
            ->from('student_assign_paces')
            ->where([
                'student_id'  => (int)$student_id,
                'subject_id'  => (int)$subject_id,
                'pace_number' => $pace_no,
                'session_id'  => $session,
            ])->get()->row_array();

        // Next free slot
        $slot = $this->get_next_slot($student_id, $subject_id, $session);

        if ($row) {
            $current = (int)($row['slot'] ?: $row['slot_index']);
            $upd = [
                'status'        => 'assigned',
                'assigned_date' => date('Y-m-d'),
            ];
            if ($current <= 0) {
                $upd['slot'] = $slot;
                $upd['slot_index'] = $slot;
            } else {
                $slot = $current; // keep existing
            }
            $this->db->update('student_assign_paces', $upd, ['id' => (int)$row['id']]);
            return ['id' => (int)$row['id'], 'slot' => $slot];
        }

        // Fresh row
        $this->db->insert('student_assign_paces', [
            'student_id'     => (int)$student_id,
            'subject_id'     => (int)$subject_id,
            'pace_number'    => (int)$pace_no,
            'term'           => $term,
            'status'         => 'assigned',
            'assigned_date'  => date('Y-m-d'),
            'session_id'     => $session,
            'branch_id'      => $branch,
            'attempt_number' => 1,
            'slot'           => $slot,
            'slot_index'     => $slot,
        ]);

        return ['id' => (int)$this->db->insert_id(), 'slot' => $slot];
    }

    // NEW: quick stock lookup for a Subject PACE via its stock_code (SKU)
    public function has_stock_for_subject_pace($subject_pace_id, $qty = 1)
    {
        $sp = $this->db->select('sp.stock_code')
            ->from('subject_pace as sp')
            ->where('sp.id', (int)$subject_pace_id)
            ->get()->row_array();

        if (!$sp || empty($sp['stock_code'])) {
            return false; // no mapping set yet
        }

        // NOTE: change 'products' to 'product' if your table is singular.
        $product = $this->db->select('id, code, (qty) as on_hand')
            ->from('products')
            ->where('code', $sp['stock_code'])
            ->get()->row_array();

        return (int)($product['on_hand'] ?? 0) >= (int)$qty;
    }

    public function get_subject_paces_with_stock($subject_id, $grade)
    {
        return $this->db->select('sp.*, p.id AS product_id, p.code AS product_code, p.available_stock, p.sales_price')
            ->from('subject_pace AS sp')
            ->join('product AS p', 'p.id = sp.product_id', 'left')
            ->where('sp.subject_id', (int)$subject_id)
            ->where('sp.grade', (int)$grade)
            ->order_by('CAST(sp.pace_number AS UNSIGNED)', 'ASC', false)
            ->get()->result_array();
    }

    // === NEW: helpers for student-assigned subjects & order filtering ===

    /** Latest grade (class_id) for a student. */
    private function get_student_grade_id($student_id)
    {
        $row = $this->db->select('class_id')
            ->from('enroll')
            ->where('student_id', (int)$student_id)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()->row_array();

        return (int)($row['class_id'] ?? 0);
    }

    /**
     * Subject IDs explicitly assigned to a student by teacher/admin.
     * Supports either student_assigned_subjects.session_id OR .year
     * (both handled if present).
     */
    public function get_assigned_subject_ids_for_student($student_id)
    {
        if (!$this->db->table_exists('student_assigned_subjects')) return [];

        // Try current year first
        $this->db->select('subject_id')
                 ->from('student_assigned_subjects')
                 ->where('student_id', (int)$student_id);

        if ($this->db->field_exists('session_id', 'student_assigned_subjects')) {
            $this->db->where('session_id', (int)get_session_id());
        } elseif ($this->db->field_exists('year', 'student_assigned_subjects')) {
            $this->db->where('year', (int)date('Y'));
        }

        $rows = $this->db->get()->result_array();

        // If nothing for current year/session, return ANY year for this student.
        if (empty($rows)) {
            $rows = $this->db->select('DISTINCT subject_id', false)
                ->from('student_assigned_subjects')
                ->where('student_id', (int)$student_id)
                ->get()->result_array();
        }

        return array_map('intval', array_column($rows, 'subject_id'));
    }

    /**
     * Subjects to show on Order PACEs for a student:
     *  - If teacher/admin has assigned subjects, show only those.
     *  - Else fall back to class-enrolled subjects (get_enrolled_subjects).
     * Sorted by report order.
     */
    public function get_order_subjects_for_student($student_id)
    {
        $student_id = (int)$student_id;
        $grade_id   = $this->get_student_grade_id($student_id);
        if ($grade_id <= 0) return [];

        $assigned = $this->get_assigned_subject_ids_for_student($student_id);
        if (!empty($assigned)) {
            return $this->db->select('s.id, s.name, s.subject_code')
                ->from('subject_pace AS sp')
                ->join('subject AS s', 's.id = sp.subject_id', 'inner')
                ->where('sp.grade', $grade_id)
                ->where_in('s.id', $assigned)
                ->group_by('s.id')
                ->order_by('(s.subject_code IS NULL)', 'asc', false)
                ->order_by('CAST(s.subject_code AS UNSIGNED)', 'asc', false)
                ->order_by('s.name', 'asc')
                ->get()->result_array();
        }

        return $this->get_enrolled_subjects($student_id);
    }

    /** Convenience wrapper for the order UI to get available numbers using student's grade. */
    public function get_available_paces_for_order($student_id, $subject_id)
    {
        $grade_id = $this->get_student_grade_id((int)$student_id);
        if ($grade_id <= 0) return [];
        return $this->get_available_paces_by_grade((int)$student_id, (int)$subject_id, $grade_id);
    }

    // === NEW: Assigned subject IDs for a student (any year/session) ===
    public function get_assigned_subject_ids_for_student_any($student_id)
    {
        $student_id = (int)$student_id;
        if (!$this->db->table_exists('student_assigned_subjects')) {
            return [];
        }
        $rows = $this->db->select('DISTINCT subject_id', false)
            ->from('student_assigned_subjects')
            ->where('student_id', $student_id)
            ->get()->result_array();
        return array_map('intval', array_column($rows, 'subject_id'));
    }

    /* ============================================================
     * >>> NEW: Grade-tolerant helpers for Assign Subjects <<<
     * ============================================================ */

    /** Build tolerant tokens to match subject_pace.grade for a learner. */
    private function build_grade_tokens($student_id)
    {
        $en = $this->db->select('e.class_id, c.name_numeric')
            ->from('enroll e')
            ->join('class c','c.id = e.class_id','left')
            ->where('e.student_id',(int)$student_id)
            ->order_by('e.id','DESC')->limit(1)
            ->get()->row_array();

        $class_id = (int)($en['class_id'] ?? 0);
        $num      = (int)($en['name_numeric'] ?? 0);

        $tokens = [];
        if ($class_id > 0) $tokens[] = (string)$class_id;
        if ($num > 0) {
            $tokens[] = (string)$num;
            $tokens[] = 'Gr '.$num;
            $tokens[] = 'Grade '.$num;
        }
        // Common buckets
        array_push($tokens, 'All', 'ALL', '*', '');

        return [$tokens, $class_id, $num];
    }

    /** Mandatory subject IDs for learner (grade tolerant; with safe fallback). */
    public function get_mandatory_subject_ids_for_student($student_id)
    {
        list($tokens) = $this->build_grade_tokens($student_id);

        $this->db->select('DISTINCT s.id', false)
            ->from('subject_pace sp')
            ->join('subject s','s.id = sp.subject_id','inner')
            ->where('LOWER(s.subject_type)', 'mandatory');

        if (!empty($tokens)) {
            $this->db->group_start();
            foreach ($tokens as $t) {
                if ($t === '') {
                    $this->db->or_where('sp.grade', '');
                    $this->db->or_where('sp.grade IS NULL', null, false);
                } else {
                    $this->db->or_where('sp.grade', $t);
                }
            }
            $this->db->group_end();
        }

        $ids = array_map('intval', array_column($this->db->get()->result_array(), 'id'));

        // Fallback: all mandatory subjects (prevents empty UI)
        if (!$ids) {
            $ids = array_map('intval', array_column(
                $this->db->select('id')->from('subject')
                    ->where('LOWER(subject_type)', 'mandatory')
                    ->get()->result_array(), 'id'
            ));
        }

        return $ids;
    }

    /** Optional subjects (rows) for learner (grade tolerant; with safe fallback). */
    public function get_optional_subjects_for_student($student_id)
    {
        list($tokens) = $this->build_grade_tokens($student_id);

        $this->db->select('DISTINCT s.id, s.name', false)
            ->from('subject_pace sp')
            ->join('subject s','s.id = sp.subject_id','inner')
            ->where('LOWER(s.subject_type) <>', 'mandatory');

        if (!empty($tokens)) {
            $this->db->group_start();
            foreach ($tokens as $t) {
                if ($t === '') {
                    $this->db->or_where('sp.grade', '');
                    $this->db->or_where('sp.grade IS NULL', null, false);
                } else {
                    $this->db->or_where('sp.grade', $t);
                }
            }
            $this->db->group_end();
        }

        $rows = $this->db
            ->order_by('(s.subject_code IS NULL)','asc',false)
            ->order_by('CAST(s.subject_code AS UNSIGNED)','asc',false)
            ->order_by('s.name','asc')
            ->get()->result_array();

        if (!$rows) {
            $rows = $this->db->select('id, name')
                ->from('subject')
                ->where('LOWER(subject_type) <>', 'mandatory')
                ->order_by('(subject_code IS NULL)','asc',false)
                ->order_by('CAST(subject_code AS UNSIGNED)','asc',false)
                ->order_by('name','asc')
                ->get()->result_array();
        }

        return $rows;
    }

    /* --------------------------------------------------------
       Can we assign this PACE? Enforce previous pass ≥ 80%
       (accept first_attempt_score OR second_attempt_score OR final_score)
       -------------------------------------------------------- */
    public function can_assign_next(int $student_id, int $subject_id, int $pace_no): bool
    {
        // First PACE in strand → allow
        $prev = $this->db->select('first_attempt_score, second_attempt_score, final_score')
            ->from('student_assign_paces')
            ->where('student_id',  $student_id)
            ->where('subject_id',  $subject_id)
            ->where('pace_number <', (int)$pace_no)
            ->where('session_id', (int)get_session_id())
            ->order_by('pace_number', 'DESC')
            ->limit(1)
            ->get()->row_array();

        if (!$prev) return true;

        if (isset($prev['final_score']) && $prev['final_score'] !== null && $prev['final_score'] !== '') {
            return (float)$prev['final_score'] >= 80.0;
        }

        $fa = isset($prev['first_attempt_score'])  ? (float)$prev['first_attempt_score']  : -1;
        $sa = isset($prev['second_attempt_score']) ? (float)$prev['second_attempt_score'] : -1;

        return max($fa, $sa) >= 80.0;
    }

    /* --------------------------------------------------------
       Convenience: is this exact PACE row already ISSUED?
       (Use when legacy single-assign endpoint is used)
       -------------------------------------------------------- */
    public function is_issued_to_student(int $student_id, int $subject_id, int $pace_no): bool
    {
        return (bool) $this->db->select('id')
            ->from('student_assign_paces')
            ->where([
                'student_id'  => $student_id,
                'subject_id'  => $subject_id,
                'pace_number' => $pace_no,
                'status'      => 'issued',
                'session_id'  => (int)get_session_id(),
            ])
            ->limit(1)->get()->row_array();
    }

    // Subjects for the student's current class/section (teacher will therefore only see applicable subjects)
    public function get_student_subjects($student_id)
    {
        $session_id = (int) get_session_id();
        $branch_id  = (int) get_loggedin_branch_id();

        $enroll = $this->db->select('class_id, section_id')
            ->from('enroll')
            ->where(['student_id' => $student_id, 'session_id' => $session_id, 'branch_id' => $branch_id])
            ->get()->row_array();

        if (!$enroll) return [];

        $this->db->select('subject.id, subject.name');
        $this->db->from('subject_assign');
        $this->db->join('subject', 'subject.id = subject_assign.subject_id');
        $this->db->where('subject_assign.class_id', $enroll['class_id']);

        if ($this->db->field_exists('section_id','subject_assign') && !empty($enroll['section_id'])) {
            $this->db->where('subject_assign.section_id', $enroll['section_id']);
        }
        if ($this->db->field_exists('branch_id','subject')) {
            $this->db->where('subject.branch_id', $branch_id);
        }
        $this->db->order_by('subject.name', 'asc');

        return $this->db->get()->result_array();
    }

    // All PACE numbers per subject
    public function get_pace_numbers_for_subjects($subject_ids, $student_id = null)
    {
        $subject_ids = array_map('intval', (array)$subject_ids);
        if (empty($subject_ids)) return [];

        // latest enrollment → class/grade
        $en = $this->db->select('en.class_id, c.name AS class_name')
            ->from('enroll AS en')
            ->join('class AS c', 'c.id = en.class_id', 'left')
            ->where('en.student_id', (int)$student_id)
            ->order_by('en.id', 'desc')
            ->limit(1)
            ->get()->row_array();

        $class_id   = $en ? (int)$en['class_id'] : 0;
        $class_name = $en && isset($en['class_name']) ? (string)$en['class_name'] : '';
        $grade_num  = 0;
        if ($class_name !== '' && preg_match('/(\d{1,2})/', $class_name, $m)) {
            $grade_num = (int)$m[1]; // e.g. "Gr 9" -> 9
        }

        // Prefer canonical table
        if ($this->db->table_exists('subject_pace')) {
            $this->db->reset_query();
            $this->db->select('subject_id, pace_number')
                     ->from('subject_pace')
                     ->where_in('subject_id', $subject_ids);

            // apply any grade filter columns that exist
            if ($class_id && $this->db->field_exists('class_id', 'subject_pace')) {
                $this->db->where('class_id', $class_id);
            } elseif ($grade_num && $this->db->field_exists('grade', 'subject_pace')) {
                $this->db->where('grade', $grade_num);
            } elseif ($grade_num && $this->db->field_exists('grade_level', 'subject_pace')) {
                $this->db->where('grade_level', $grade_num);
            }

            $q = $this->db->order_by('subject_id', 'asc')
                          ->order_by('pace_number+0 ASC', null, false)
                          ->get();
            if ($q !== false) {
                $out = [];
                foreach ($q->result_array() as $r) {
                    $sid = (int)$r['subject_id'];
                    $pn  = (string)$r['pace_number'];
                    if (!isset($out[$sid])) $out[$sid] = [];
                    if (!in_array($pn, $out[$sid], true)) $out[$sid][] = $pn;
                }
                return $out;
            }
        }

        // Fallback: product table (optional grade columns)
        if ($this->db->table_exists('product')) {
            $this->db->reset_query();
            $this->db->select('subject_id, pace_number')
                     ->from('product')
                     ->where_in('subject_id', $subject_ids);

            if ($class_id && $this->db->field_exists('class_id', 'product')) {
                $this->db->where('class_id', $class_id);
            } elseif ($grade_num && $this->db->field_exists('grade', 'product')) {
                $this->db->where('grade', $grade_num);
            } elseif ($grade_num && $this->db->field_exists('grade_level', 'product')) {
                $this->db->where('grade_level', $grade_num);
            }

            $q = $this->db->order_by('subject_id', 'asc')
                          ->order_by('pace_number+0 ASC', null, false)
                          ->get();
            if ($q !== false) {
                $out = [];
                foreach ($q->result_array() as $r) {
                    $sid = (int)$r['subject_id'];
                    $pn  = (string)$r['pace_number'];
                    if (!isset($out[$sid])) $out[$sid] = [];
                    if (!in_array($pn, $out[$sid], true)) $out[$sid][] = $pn;
                }
                return $out;
            }
        }

        return [];
    }

    /* ==== BEGIN: issued-only by subject for a student (unique definition) == */
    // Pull ISSUED PACEs straight from student_assign_paces (scoped to selected term).
    // Do NOT filter by assigned_date — some rows may already have a timestamp but are still "issued".
    public function get_issued_pace_numbers_for_subjects($subject_ids, $student_id = 0, $term = null)
    {
        $subject_ids = array_map('intval', (array)$subject_ids);
        $student_id  = (int)$student_id;
        $term        = is_null($term) ? null : (int)$term;

        if (empty($subject_ids) || $student_id <= 0) {
            return [];
        }

        if (!$this->db->table_exists('student_assign_paces')) {
            return [];
        }

        $this->db->reset_query();
        $this->db->select('subject_id, pace_number')
                 ->from('student_assign_paces')
                 ->where('student_id', $student_id)
                 ->where_in('subject_id', $subject_ids)
                 ->where("UPPER(status) = 'ISSUED'", null, false);

        // Limit to selected term (support several possible column names)
        if (!is_null($term)) {
            if     ($this->db->field_exists('term',   'student_assign_paces')) $this->db->where('term', $term);
            elseif ($this->db->field_exists('terms',  'student_assign_paces')) $this->db->where('terms', $term);
            elseif ($this->db->field_exists('quarter','student_assign_paces')) $this->db->where('quarter', $term);
        }

        $this->db->order_by('subject_id', 'asc')
                 ->order_by('pace_number+0 ASC', null, false);

        $q = $this->db->get();
        if ($q === false) return [];

        $out = [];
        foreach ($q->result_array() as $r) {
            $sid = (int)$r['subject_id'];
            $pn  = (string)$r['pace_number'];
            if (!isset($out[$sid])) $out[$sid] = [];
            if (!in_array($pn, $out[$sid], true)) $out[$sid][] = $pn;
        }

        return $out;
    }

    /* ==== BEGIN: existing assignments for selected term (unique definition) == */
    public function get_existing_assignments($student_id, $term)
    {
        $student_id = (int)$student_id;
        $term       = (int)$term;

        if (!$this->db->table_exists('student_assign_paces')) return [];

        $this->db->reset_query();
        $this->db->select('subject_id, pace_number')
                 ->from('student_assign_paces')
                 ->where('student_id', $student_id);

        if     ($this->db->field_exists('term', 'student_assign_paces'))    $this->db->where('term', $term);
        elseif ($this->db->field_exists('quarter', 'student_assign_paces')) $this->db->where('quarter', $term);

        if ($this->db->field_exists('status', 'student_assign_paces')) {
            $this->db->where_in('status', ['assigned','Assigned','ASSIGNED','completed','Completed','COMPLETED']);
        }

        $q = $this->db->get();
        if ($q === false) return [];

        $map = [];
        foreach ($q->result_array() as $r) {
            $sid = (int)$r['subject_id'];
            $pn  = (string)$r['pace_number'];
            if (!isset($map[$sid])) $map[$sid] = [];
            $map[$sid][] = $pn;
        }
        return $map;
    }

    public function batch_assign_paces($student_id, $term, $items, $user_id, $branch_id)
    {
        $existing = $this->get_existing_assignments($student_id, $term);
        $now = date('Y-m-d H:i:s');
        $data = [];
        $paceCol = $this->paceCol('student_assign_paces');

        $session_id = (int) get_session_id();
        $student_id = (int) $student_id;
        $branch_id  = (int) $branch_id;

        // track next slot per subject to avoid querying inside loop repeatedly
        $nextBySubject = [];

        foreach ($items as $it) {
            $sid = isset($it['subject_id']) ? (int)$it['subject_id'] : 0;
            $pn  = isset($it['pace_no'])    ? (int)$it['pace_no']    : 0; // client sends "pace_no"
            if ($sid <= 0 || $pn <= 0) continue;

            // correct duplicate guard: $existing[$sid] is a flat list of pace_numbers
            if (!empty($existing[$sid]) && in_array((string)$pn, $existing[$sid], true)) continue;

            // compute next slot per subject/session
            if (!isset($nextBySubject[$sid])) {
                $nextBySubject[$sid] = $this->get_next_slot($student_id, $sid, $session_id);
            }
            $slot = $nextBySubject[$sid]++;

            $row = [
                'branch_id'      => $branch_id,
                'student_id'     => $student_id,
                'subject_id'     => $sid,
                'term'           => (int)$term,
                'status'         => 'assigned',
                'assigned_by'    => (int)$user_id,
                'assigned_date'  => date('Y-m-d'),
                'attempt_number' => 1,
                'session_id'     => $session_id,
                'slot'           => $slot,
                'slot_index'     => $slot,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
            $row[$paceCol] = $pn; // correct column name for pace number

            $data[] = $row;
        }

        if (!empty($data)) {
            $this->db->insert_batch('student_assign_paces', $data);
            return $this->db->affected_rows();
        }
        return 0;
    }

    private function paceCol($table = 'student_assign_paces')
    {
        $fields = $this->db->list_fields($table);
        return in_array('pace_number', $fields, true) ? 'pace_number' : 'pace_no';
    }

    public function get_paces_for_subject_and_class($subject_id, $class_id)
    {
        $subject_id = (int)$subject_id;
        $class_id   = (int)$class_id;

        // 1) pace_catalog(subject_id, class_id, pace_number)
        if ($this->db->table_exists('pace_catalog')) {
            $q = $this->db->select('pace_number')
                          ->from('pace_catalog')
                          ->where(['subject_id' => $subject_id, 'class_id' => $class_id])
                          ->order_by('pace_number', 'ASC')
                          ->get()->result_array();
            if (!empty($q)) return array_values(array_unique(array_map('intval', array_column($q, 'pace_number'))));
        }

        // 2) subject_pace_ranges(subject_id, class_id, pace_from, pace_to)
        if ($this->db->table_exists('subject_pace_ranges')) {
            $rows = $this->db->select('pace_from, pace_to')
                             ->from('subject_pace_ranges')
                             ->where(['subject_id' => $subject_id, 'class_id' => $class_id])
                             ->order_by('pace_from', 'ASC')->get()->result_array();
            if (!empty($rows)) {
                $nums = [];
                foreach ($rows as $r) {
                    $a = (int)$r['pace_from'];
                    $b = (int)$r['pace_to'];
                    if ($a > 0 && $b >= $a) {
                        for ($i = $a; $i <= $b; $i++) $nums[] = $i;
                    }
                }
                if (!empty($nums)) return array_values(array_unique($nums));
            }
        }

        // 3) pace_numbers(subject_id, class_id, pace_number) OR pace_numbers(subject_id, grade_id, pace_number)
        if ($this->db->table_exists('pace_numbers')) {
            // try class_id first
            $col = $this->db->field_exists('class_id', 'pace_numbers') ? 'class_id' :
                   ($this->db->field_exists('grade_id', 'pace_numbers') ? 'grade_id' : null);

            if ($col !== null) {
                $q = $this->db->select('pace_number')
                              ->from('pace_numbers')
                              ->where(['subject_id' => $subject_id, $col => $class_id])
                              ->order_by('pace_number', 'ASC')->get()->result_array();
                if (!empty($q)) return array_values(array_unique(array_map('intval', array_column($q, 'pace_number'))));
            }
        }

        // Fallback – return an empty list (view will still render)
        return [];
    }

    /**
     * Build options map: subject_id => [pace numbers...]
     * $rows is the subject list you already pass to the view.
     */
    public function build_planner_options_from_catalog($class_id, $rows)
    {
        $out = [];
        foreach ((array)$rows as $r) {
            $sid = (int)($r['subject_id'] ?? 0);
            if ($sid <= 0) continue;
            $out[$sid] = $this->get_paces_for_subject_and_class($sid, (int)$class_id);
        }
        return $out;
    }

    // Subjects to show in the Assign PACEs grid.
    public function get_subjects_for_assign_grid($student_id)
    {
        $student_id = (int)$student_id;
        $yearNow    = (int)date('Y');

        // 1) Try per-student subjects for the current year
        $rows = $this->db->select('s.id, s.name')
            ->from('student_assigned_subjects AS sas')
            ->join('subject AS s', 's.id = sas.subject_id', 'inner')
            ->where('sas.student_id', $student_id)
            ->where('sas.year', $yearNow)
            ->order_by('s.name', 'asc')
            ->get()->result_array();

        if (!empty($rows)) {
            return $rows;
        }

        // 2) If nothing for the current year, use the latest available year for this student
        $latest = $this->db->select('MAX(year) AS y')
            ->from('student_assigned_subjects')
            ->where('student_id', $student_id)
            ->get()->row();

        if (!empty($latest) && (int)$latest->y > 0) {
            $rows = $this->db->select('s.id, s.name')
                ->from('student_assigned_subjects AS sas')
                ->join('subject AS s', 's.id = sas.subject_id', 'inner')
                ->where('sas.student_id', $student_id)
                ->where('sas.year', (int)$latest->y)
                ->order_by('s.name', 'asc')
                ->get()->result_array();

            if (!empty($rows)) {
                return $rows;
            }
        }

        // 3) Fallback to latest class/section subject assignment
        $en = $this->db->select('en.class_id, en.section_id, st.branch_id')
            ->from('enroll AS en')
            ->join('student AS st', 'st.id = en.student_id', 'inner')
            ->where('en.student_id', $student_id)
            ->order_by('en.id', 'desc')
            ->limit(1)
            ->get()->row_array();

        if (!$en) {
            return [];
        }

        return $this->db->select('s.id, s.name')
            ->from('subject_assign AS sa')
            ->join('subject AS s', 's.id = sa.subject_id', 'inner')
            ->where('sa.class_id', $en['class_id'])
            ->where('sa.section_id', $en['section_id'])
            ->where('sa.branch_id', $en['branch_id'])
            ->order_by('s.name', 'asc')
            ->get()->result_array();
    }

    /**
     * Flip status from 'issued' -> 'assigned' for selected PACEs in one bulk update.
     * Each $items[] = ['subject_id'=>int, 'pace_no'=>int|string]  (or 'pace')
     * Returns number of rows actually updated (never negative).
     */
    public function mark_issued_as_assigned($student_id, $term, array $items)
    {
        if (!$this->db->table_exists('student_assign_paces')) return 0;

        $student_id = (int)$student_id;
        $term       = (int)$term;

        // Detect term column name once
        $termCol = null;
        if     ($this->db->field_exists('term',    'student_assign_paces')) $termCol = 'term';
        elseif ($this->db->field_exists('terms',   'student_assign_paces')) $termCol = 'terms';
        elseif ($this->db->field_exists('quarter', 'student_assign_paces')) $termCol = 'quarter';

        // Normalize payload -> subject_id => [pace_numbers...]
        $wanted = [];
        foreach ($items as $it) {
            $sid  = isset($it['subject_id']) ? (int)$it['subject_id'] : 0;
            $pace = isset($it['pace_no']) ? (string)$it['pace_no'] : (isset($it['pace']) ? (string)$it['pace'] : '');
            if ($sid > 0 && $pace !== '') {
                if (!isset($wanted[$sid])) $wanted[$sid] = [];
                $wanted[$sid][] = $pace;
            }
        }
        if (empty($wanted)) return 0;

        // 1) Find all matching "issued" rows by ID
        $this->db->reset_query();
        $this->db->select('id')
                 ->from('student_assign_paces')
                 ->where('student_id', $student_id)
                 ->where("UPPER(status) = 'ISSUED'", null, false);

        if ($termCol) $this->db->where($termCol, $term);

        // Build (subject_id, pace_number) OR chain
        $this->db->group_start();
        foreach ($wanted as $sid => $paces) {
            $paces = array_values(array_unique(array_map('strval', $paces)));
            $this->db->or_group_start()
                     ->where('subject_id', (int)$sid)
                     ->where_in('pace_number', $paces)
                     ->group_end();
        }
        $this->db->group_end();

        $ids = array_map('intval', array_column($this->db->get()->result_array(), 'id'));
        if (empty($ids)) return 0;

        // 2) Bulk update by primary key
        $this->db->reset_query();
        $this->db->where_in('id', $ids);
        $this->db->set('status', 'assigned');
        if ($this->db->field_exists('assigned_date', 'student_assign_paces')) {
            $this->db->set('assigned_date', 'NOW()', false);
        }
        $this->db->update('student_assign_paces');

        $aff = (int)$this->db->affected_rows();
        return ($aff > 0) ? $aff : 0;
    }
}
