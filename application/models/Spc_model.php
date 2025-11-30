<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Spc_model extends CI_Model
{
    /* --------------------------------------------------------
       A.  list every student – for the <select> in the view
       -------------------------------------------------------- */
    public function get_all_students()
    {
        return $this->db->select('id, register_no, first_name, last_name')
                        ->order_by('first_name', 'asc')
                        ->get('student')
                        ->result_array();
    }

    /* --------------------------------------------------------
       B.  single student’s details
       -------------------------------------------------------- */
    public function get_student($student_id)
    {
        return $this->db
            ->select('student.*, enroll.class_id')
            ->from('student')
            ->join('enroll', 'enroll.student_id = student.id AND enroll.session_id = ' . (int)get_session_id(), 'left')
            ->where('student.id', $student_id)
            ->get()
            ->row_array();
    }


    /* --------------------------------------------------------
       D.  General Assignments (SPC)
       -------------------------------------------------------- */
    public function get_general_assignments($student_id, $session_id)
    {
        return $this->db
            ->where('student_id', $student_id)
            ->where('session_id', $session_id)
            ->where_in('term', ['Q1','Q2','Q3','Q4'])
            ->order_by('term','asc')
            ->order_by('row_index','asc')
            ->get('spc_general_assignments')
            ->result_array();
    }

    public function upsert_general_assignment($student_id, $session_id, $term, $row_index, $payload)
    {
        $exists = $this->db->where([
            'student_id' => $student_id,
            'session_id' => $session_id,
            'term'       => $term,
            'row_index'  => $row_index,
        ])->get('spc_general_assignments')->row_array();

        $payload['updated_at'] = date('Y-m-d H:i:s');

        if ($exists) {
            $this->db->where('id', $exists['id'])->update('spc_general_assignments', $payload);
            return $exists['id'];
        } else {
            $payload += [
                'student_id' => $student_id,
                'session_id' => $session_id,
                'term'       => $term,
                'row_index'  => $row_index,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $this->db->insert('spc_general_assignments', $payload);
            return $this->db->insert_id();
        }
    }

    /* --------------------------------------------------------
       E.  Reading Programme (SPC) - GET
       -------------------------------------------------------- */
    public function get_reading_program($student_id, $session_id)
    {
        $rows = $this->db->where('student_id', $student_id)
            ->where('session_id', $session_id)
            ->where_in('term', ['Q1','Q2','Q3','Q4'])
            ->get('spc_reading_program')
            ->result_array();

        $out = ['Q1'=>[], 'Q2'=>[], 'Q3'=>[], 'Q4'=>[]];
        foreach ($rows as $r) {
            $t = strtoupper($r['term']);
            $out[$t] = [
                'title'         => (string)($r['title'] ?? ''),
                'wpm'           => $r['wpm'] === null ? '' : (string)(int)$r['wpm'],
                'percent'       => $r['percent'] === null ? '' : (string)(0 + $r['percent']),
                'comprehension' => $r['comprehension'] === null ? '' : (string)(0 + $r['comprehension']),
            ];
        }
        return $out;
    }

    /* --------------------------------------------------------
       F.  Reading Programme (SPC) - UPSERT
           Unified signature (works with either payload style):
           - ['title','wpm','percent','comprehension']
           - or ['wpm','percent','comp']
       -------------------------------------------------------- */
    public function upsert_reading_program($student_id, $session_id, $term, $vals = [])
    {
        $term = strtoupper(trim($term)); // Q1..Q4

        // Always include identifiers + updated_at
        $data = [
            'student_id' => (int)$student_id,
            'session_id' => (int)$session_id,
            'term'       => $term,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Only set columns that were actually posted
        if (array_key_exists('title', $vals)) {
            $data['title'] = ($vals['title'] === '') ? null : (string)$vals['title'];
        }
        if (array_key_exists('wpm', $vals)) {
            $data['wpm'] = ($vals['wpm'] === '' || $vals['wpm'] === null) ? null : (int)$vals['wpm'];
        }
        if (array_key_exists('percent', $vals)) {
            $data['percent'] = ($vals['percent'] === '' || $vals['percent'] === null) ? null : (int)$vals['percent'];
        }
        if (array_key_exists('comprehension', $vals) || array_key_exists('comp', $vals)) {
            $v = array_key_exists('comprehension', $vals) ? $vals['comprehension'] : $vals['comp'];
            $data['comprehension'] = ($v === '' || $v === null) ? null : (int)$v;
        }

        // Upsert on (student_id, session_id, term)
        $row = $this->db->get_where('spc_reading_program', [
            'student_id' => (int)$student_id,
            'session_id' => (int)$session_id,
            'term'       => $term
        ])->row_array();

        if ($row) {
            $this->db->where('id', $row['id'])->update('spc_reading_program', $data);
            return $row['id'];
        } else {
            $data['created_at'] = $data['updated_at'];
            $this->db->insert('spc_reading_program', $data);
            return $this->db->insert_id();
        }
    }

    /* --------------------------------------------------------
       G.  Save %S attempt and AUTO status (redo/completed)
       -------------------------------------------------------- */
    public function save_score($assign_id, $attempt, $score)
    {
        $assign_id = (int) $assign_id;
        $attempt   = strtolower(trim($attempt));
        $field     = in_array($attempt, ['s2','2','second','second_attempt','score_2','2nd']) ? 'score_2' : 'score_1';
        $score     = (is_numeric($score) ? (int)$score : null);

        // Write the score for this attempt
        $this->db->where('id', $assign_id)->update('student_assign_paces', [$field => $score]);

        // Re-read row and apply status rules
        $row = $this->db->where('id', $assign_id)->get('student_assign_paces')->row_array();
        if (!$row) return false;

        $first  = isset($row['score_1']) ? (int)$row['score_1'] : null;
        $second = isset($row['score_2']) ? (int)$row['score_2'] : null;
        $status = isset($row['status']) ? $row['status'] : 'assigned';

        if ($first === null) {
            // no score yet; keep status as-is
        } elseif ($first < 80 && $second === null) {
            // trigger redo immediately after a failing first attempt
            $status = 'redo';
        } elseif ($second !== null) {
            // second attempt recorded; decide final state
            $status = ($second >= 80) ? 'completed' : 'redo';
        } else {
            // first attempt >= 80 and no second -> completed
            $status = 'completed';
        }

        $update = ['status' => $status];

        // Optional convenience flag if your table has it
        if ($this->db->field_exists('redo', 'student_assign_paces')) {
            $update['redo'] = ($status === 'redo') ? 1 : 0;
        }

        $this->db->where('id', $assign_id)->update('student_assign_paces', $update);
        return true;
    }

    /* --------------------------------------------------------
       H.  (Optional) generic router for saving cells
       -------------------------------------------------------- */
    public function save_cell($assign_id, $field, $value)
    {
        $f = strtolower(trim($field));
        if (in_array($f, ['s1','score_1','first','first_attempt'])) {
            return $this->save_score($assign_id, 's1', $value);
        }
        if (in_array($f, ['s2','score_2','second','second_attempt'])) {
            return $this->save_score($assign_id, 's2', $value);
        }
        // passthrough for any other column
        $this->db->where('id', (int)$assign_id)->update('student_assign_paces', [$field => $value]);
        return true;
    }

    // ─────────────────────────────────────────────────────────
    // Elective aliases per student (table-agnostic, safe upsert)
    // ─────────────────────────────────────────────────────────
    private function elective_table_name()
    {
        if ($this->db->table_exists('student_electives')) return 'student_electives';
        if ($this->db->table_exists('spc_student_electives')) return 'spc_student_electives';
        return null;
    }

    // Return [subject_id => alias string] for a student (+/- session filter if present)
    // (removed strict type hints for compatibility)
    public function get_elective_aliases($student_id, $session_id)
    {
        if (!$student_id || !$session_id) return [];
        if (!$this->db->table_exists('spc_elective_alias')) return [];

        $rows = $this->db->select('subject_id, alias_name')
            ->from('spc_elective_alias')
            ->where('student_id', (int)$student_id)
            ->where('session_id', (int)$session_id)
            ->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['subject_id']] = (string)$r['alias_name'];
        }
        return $out;
    }

    // (removed strict type hints for compatibility)
    public function save_elective_alias($student_id, $subject_id, $name, $session_id)
    {
        if (!$student_id || !$subject_id || !$session_id) return false;
        if (!$this->db->table_exists('spc_elective_alias')) return false;

        $name = trim((string)$name);
        $where = [
            'student_id' => (int)$student_id,
            'subject_id' => (int)$subject_id,
            'session_id' => (int)$session_id
        ];

        // empty => delete record (treat as clearing the alias)
        if ($name === '') {
            $this->db->where($where)->delete('spc_elective_alias');
            return $this->db->error()['code'] === 0;
        }

        $row = $this->db->get_where('spc_elective_alias', $where)->row_array();
        $now = date('Y-m-d H:i:s');

        if ($row) {
            $this->db->where('id', (int)$row['id'])
                     ->update('spc_elective_alias', ['alias_name' => $name, 'updated_at' => $now]);
            return $this->db->error()['code'] === 0;
        } else {
            $this->db->insert('spc_elective_alias', $where + [
                'alias_name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return $this->db->error()['code'] === 0 && $this->db->insert_id() > 0;
        }
    }
    
    // C. PACEs for that student (session + optional term + status filter)
public function get_student_paces($student_id, $term = null)
{
    $student_id = (int)$student_id;

    $this->db->reset_query();
    $this->db->from('student_assign_paces');
    $this->db->where('student_id', $student_id);
    $this->db->where('session_id', get_session_id());

    // Limit to the selected quarter (column name differs across installs)
    if ($term !== null) {
        $term = (int)$term;
        if     ($this->db->field_exists('term',    'student_assign_paces')) $this->db->where('term', $term);
        elseif ($this->db->field_exists('terms',   'student_assign_paces')) $this->db->where('terms', $term);
        elseif ($this->db->field_exists('quarter', 'student_assign_paces')) $this->db->where('quarter', $term);
    }

    // Show the rows SPC cares about
    if ($this->db->field_exists('status', 'student_assign_paces')) {
        $this->db->where_in('status', [
            'assigned','Assigned','ASSIGNED',
            'completed','Completed','COMPLETED',
            'redo','Redo','REDO',
            'issued','Issued','ISSUED'
        ]);
    }

    // Keep your original ordering (safe across CI/PHP versions)
    $this->db->order_by('subject_id ASC, slot_index ASC, pace_number ASC');

    $q = $this->db->get();
    return ($q === false) ? [] : $q->result_array();
}

}
