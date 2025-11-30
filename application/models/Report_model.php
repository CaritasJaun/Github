<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Report_model extends CI_Model
{
    /* -------------------------------------------------------
     * Comments
     * ----------------------------------------------------- */
    public function get_comments($student_id, $year)
    {
        return $this->db
            ->where('student_id', $student_id)
            ->where('year', $year)
            ->get('report_comments')
            ->row_array();
    }

    public function save_comments($student_id, $year, $teacher_comment, $principal_comment)
    {
        $exists = $this->get_comments($student_id, $year);

        $data = [
            'student_id'        => $student_id,
            'year'              => $year,
            'teacher_comment'   => $teacher_comment,
            'principal_comment' => $principal_comment
        ];

        if ($exists) {
            $this->db->where('student_id', $student_id)
                     ->where('year', $year)
                     ->update('report_comments', $data);
        } else {
            $this->db->insert('report_comments', $data);
        }
    }

    /* -------------------------------------------------------
     * Subject PACE scores (S% / M% support)
     * ----------------------------------------------------- */
    public function get_student_subject_pace_scores($student_id, $session_id = null)
    {
        $table = 'student_assign_paces';

        $hasPaceNumber = $this->db->field_exists('pace_number', $table);
        $hasPaceNo     = $this->db->field_exists('pace_no', $table);
        $paceCol       = $hasPaceNumber ? 'sap.pace_number' : ($hasPaceNo ? 'sap.pace_no' : 'NULL');

        $hasFirst      = $this->db->field_exists('first_attempt_score', $table);
        $hasSecond     = $this->db->field_exists('second_attempt_score', $table);
        $hasSupervisor = $this->db->field_exists('supervisor_score', $table);
        $hasModerator  = $this->db->field_exists('moderator_score', $table);

        // S%: prefer explicit supervisor_score; else best attempt; else NULL
        if ($hasSupervisor) {
            $sCol = 'sap.supervisor_score';
        } elseif ($hasFirst || $hasSecond) {
            $sCol = 'GREATEST(IFNULL(sap.first_attempt_score,0), IFNULL(sap.second_attempt_score,0))';
        } else {
            $sCol = 'NULL';
        }

        // M%: only if exists
        $mCol = $hasModerator ? 'sap.moderator_score' : 'NULL';

        $this->db->select([
            'subject.id AS subject_id',
            'subject.name AS subject_name',
            'subject.subject_code AS subject_code',   // <<< include code
            $paceCol . ' AS pace_number',
            "CASE
                WHEN sap.term IN ('Q1','Q2','Q3','Q4') THEN sap.term
                WHEN sap.term IN (1,2,3,4)             THEN CONCAT('Q', sap.term)
                ELSE sap.term
             END AS quarter",
            $sCol . ' AS s_score',
            $mCol . ' AS m_score',
            $sCol . ' AS percentage', // used for averages/rollups
        ], false);

        $this->db->from($table . ' AS sap');
        $this->db->join('subject', 'subject.id = sap.subject_id', 'inner');
        $this->db->where('sap.student_id', $student_id);

        if (!empty($session_id)) {
            $this->db->where('sap.session_id', $session_id);
        }

        // include rows with any score; build conditions only for existing cols
        $scoreConds = [];
        if ($hasFirst)      $scoreConds[] = 'sap.first_attempt_score IS NOT NULL';
        if ($hasSecond)     $scoreConds[] = 'sap.second_attempt_score IS NOT NULL';
        if ($hasSupervisor) $scoreConds[] = 'sap.supervisor_score IS NOT NULL';
        if ($hasModerator)  $scoreConds[] = 'sap.moderator_score IS NOT NULL';
        if (!empty($scoreConds)) {
            $this->db->where('(' . implode(' OR ', $scoreConds) . ')', null, false);
        }

        if ($this->db->field_exists('is_deleted', $table)) {
            $this->db->where('sap.is_deleted', 0);
        }

        // <<< enforce subject sequence (Math=1, English=2, ...)
        $this->db->order_by('subject.subject_code', 'ASC');
        if ($paceCol !== 'NULL') $this->db->order_by($paceCol, 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_subject_yearly_average($student_id, $subject_id, $year)
    {
        $this->db->select('AVG(percentage) as yearly_avg');
        $this->db->from('student_pace_assign');
        $this->db->where('student_id', $student_id);
        $this->db->where('subject_id', $subject_id);
        $this->db->where('status', 'completed');

        $row = $this->db->get()->row();
        return $row->yearly_avg ?? 0;
    }

    /* -------------------------------------------------------
     * Optional: SPC paces (legacy helper)
     * ----------------------------------------------------- */
    public function get_spc_paces($student_id, $session_id = null)
    {
        $this->db->select([
            'subject.id AS subject_id',
            'subject.name AS subject_name',
            'subject.subject_code AS subject_code',   // keep code available
            'COALESCE(sap.pace_number, sap.pace_no) AS pace_number',
            "CASE
                WHEN sap.term IN ('Q1','Q2','Q3','Q4') THEN sap.term
                WHEN sap.term IN (1,2,3,4) THEN CONCAT('Q', sap.term)
                ELSE sap.term
             END AS quarter",
            'GREATEST(IFNULL(sap.first_attempt_score,0), IFNULL(sap.second_attempt_score,0)) AS percentage',
        ], false);

        $this->db->from('student_assign_paces AS sap');
        $this->db->join('subject', 'subject.id = sap.subject_id', 'inner');
        $this->db->where('sap.student_id', $student_id);

        if (!empty($session_id)) {
            $this->db->where('sap.session_id', $session_id);
        }

        $this->db->group_start()
                 ->where('sap.first_attempt_score IS NOT NULL', null, false)
                 ->or_where('sap.second_attempt_score IS NOT NULL', null, false)
                 ->group_end();

        if ($this->db->field_exists('is_deleted', 'student_assign_paces')) {
            $this->db->where('sap.is_deleted', 0);
        }

        $this->db->order_by('subject.subject_code', 'ASC'); // enforce
        $this->db->order_by('sap.pace_number', 'ASC');

        return $this->db->get()->result_array();
    }

    /* ====================== SPC -> REPORT helpers (unchanged) ====================== */
    private function normalize_term_key($term)
    {
        $t = strtoupper(trim((string)$term));
        if (in_array($t, ['Q1','Q2','Q3','Q4'], true)) return strtolower($t);
        if (in_array($t, ['1','2','3','4'], true))     return 'q' . $t;
        return '';
    }

    public function get_spc_reading_program($student_id, $session_id)
    {
        $out = [
            'q1' => ['wpm'=>'', 'comprehension'=>'', 'score'=>''],
            'q2' => ['wpm'=>'', 'comprehension'=>'', 'score'=>''],
            'q3' => ['wpm'=>'', 'comprehension'=>'', 'score'=>''],
            'q4' => ['wpm'=>'', 'comprehension'=>'', 'score'=>''],
        ];

        $tables = ['spc_reading_program', 'reading_program', 'report_reading_program'];
        $table  = null;
        foreach ($tables as $t) {
            if ($this->db->table_exists($t)) { $table = $t; break; }
        }
        if (!$table) return $out;

        $this->db->where('student_id', $student_id);
        if ($this->db->field_exists('session_id', $table)) {
            $this->db->where('session_id', $session_id);
        } elseif ($this->db->field_exists('year', $table)) {
            $this->db->where('year', $session_id);
        }

        $rows = $this->db->get($table)->result_array();

        foreach ($rows as $r) {
            $k = $this->normalize_term_key($r['term'] ?? '');
            if (!$k) continue;

            $wpm  = $r['wpm'] ?? $r['words_per_min'] ?? $r['words_per_minute'] ?? '';
            $comp = $r['comprehension'] ?? $r['comp'] ?? $r['comp_score'] ?? '';
            $score= $r['percent'] ?? $r['percentage'] ?? $r['score'] ?? '';

            $out[$k]['wpm']            = ($wpm   === null ? '' : (string)$wpm);
            $out[$k]['comprehension']  = ($comp  === null ? '' : (string)$comp);
            $out[$k]['score']          = ($score === null ? '' : (string)$score);
        }

        return $out;
    }

    public function get_spc_general_assignments($student_id, $session_id)
    {
        $blank = ['q1'=>'','q2'=>'','q3'=>'','q4'=>''];
        $out   = ['row1'=>$blank, 'row2'=>$blank, 'row3'=>$blank];

        $tables = ['spc_general_assignments','general_assignments','report_general_assignments'];
        $table  = null;
        foreach ($tables as $t) {
            if ($this->db->table_exists($t)) { $table = $t; break; }
        }
        if (!$table) return $out;

        $this->db->where('student_id', $student_id);
        if ($this->db->field_exists('session_id', $table)) {
            $this->db->where('session_id', $session_id);
        } elseif ($this->db->field_exists('year', $table)) {
            $this->db->where('year', $session_id);
        }
        $this->db->order_by($this->db->field_exists('position',$table)?'position':
                            ($this->db->field_exists('row_index',$table)?'row_index':'id'), 'asc');

        $rows = $this->db->get($table)->result_array();

        $bucket = [];
        foreach ($rows as $r) {
            if (isset($r['q1']) || isset($r['q2']) || isset($r['q3']) || isset($r['q4'])) {
                $bucket[] = [
                    'q1' => (string)($r['q1'] ?? ''),
                    'q2' => (string)($r['q2'] ?? ''),
                    'q3' => (string)($r['q3'] ?? ''),
                    'q4' => (string)($r['q4'] ?? ''),
                ];
                continue;
            }

            $idx = (int)($r['row_no'] ?? $r['row_index'] ?? $r['slot'] ?? 0);
            if ($idx < 1 || $idx > 3) continue;

            $k = $this->normalize_term_key($r['term'] ?? '');
            if (!$k) continue;

            if (!isset($bucket[$idx])) $bucket[$idx] = $blank;

            $pct = $r['percent'] ?? $r['percentage'] ?? $r['score'] ?? $r['mark'] ?? '';
            $bucket[$idx][$k] = (string)$pct;
        }

        for ($i=1; $i<=3; $i++) {
            $out['row'.$i] = array_merge($blank, $bucket[$i] ?? ($bucket[$i-1] ?? $blank));
        }

        return $out;
    }

    public function get_reading_program($student_id, $session_id)
    {
        $table = null;
        foreach (['spc_reading_program','reading_program','reading_programme'] as $cand) {
            if ($this->db->table_exists($cand)) { $table = $cand; break; }
        }
        if (!$table) return [];

        $this->db->where('student_id', (int)$student_id);
        if ($this->db->field_exists('session_id', $table)) {
            $this->db->where('session_id', (int)$session_id);
        } elseif ($this->db->field_exists('year', $table)) {
            $this->db->where('year', (int)$session_id);
        }

        $rows = $this->db->get($table)->result_array();
        $out  = [];
        foreach ($rows as $r) {
            $t = strtoupper($r['term']);
            $k = 'q' . substr($t, 1);
            $out[$k] = [
                'wpm'     => (string)($r['wpm'] ?? ''),
                'percent' => (string)($r['percent'] ?? ($r['percentage'] ?? ($r['score'] ?? ''))),
                'comp'    => (string)($r['comprehension'] ?? ($r['comp'] ?? ($r['comp_score'] ?? ''))),
            ];
        }
        return $out;
    }

    public function get_traits($student_id, $year)
    {
        $row = $this->db->where('student_id', $student_id)
                        ->where('year', $year)
                        ->get('report_traits')
                        ->row_array();
        if (!$row) return [];
        $data = json_decode($row['traits_json'] ?? '{}', true);
        return is_array($data) ? $data : [];
    }

    public function save_traits($student_id, $year, array $traits)
    {
        $payload = [
            'traits_json' => json_encode($traits, JSON_UNESCAPED_UNICODE),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $exists = $this->db->where('student_id', $student_id)
                           ->where('year', $year)
                           ->get('report_traits')->row_array();

        if ($exists) {
            $this->db->where('student_id', $student_id)
                     ->where('year', $year)
                     ->update('report_traits', $payload);
        } else {
            $payload['student_id'] = (int)$student_id;
            $payload['year']       = (int)$year;
            $payload['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('report_traits', $payload);
        }
        return true;
    }

    public function get_days_absent_by_term($student_id)
    {
        $counts = [1=>0,2=>0,3=>0,4=>0];
        if (!$student_id) return ['q1'=>0,'q2'=>0,'q3'=>0,'q4'=>0];

        $rows = $this->db->select('term_id, attendance_status, goals_json')
            ->from('monitor_goal_check')
            ->where('student_id', $student_id)
            ->get()->result_array();

        foreach ($rows as $r) {
            $term = (int)($r['term_id'] ?? 0);
            if ($term < 1 || $term > 4) continue;

            $isAbsent = false;

            $att = strtoupper(trim((string)($r['attendance_status'] ?? '')));
            if ($att === 'A' || preg_match('/\bA\b/', $att)) {
                $isAbsent = true;
            }

            if (!$isAbsent) {
                $gj = (string)($r['goals_json'] ?? '');
                if ($gj !== '') {
                    if (preg_match('/"att"\s*:\s*"A"/i', $gj)) {
                        $isAbsent = true;
                    } else {
                        $decoded = json_decode($gj, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            array_walk_recursive($decoded, function($v, $k) use (&$isAbsent) {
                                if (strtolower((string)$k) === 'att' && strtoupper((string)$v) === 'A') {
                                    $isAbsent = true;
                                }
                            });
                        }
                    }
                }
            }

            if ($isAbsent) $counts[$term] += 1;
        }

        return ['q1'=>$counts[1],'q2'=>$counts[2],'q3'=>$counts[3],'q4'=>$counts[4]];
    }

    public function get_scripture_notes_by_term($student_id)
    {
        $branch_id = get_loggedin_branch_id();

        $rows = $this->db->select('term_id, scripture_note, date')
            ->from('monitor_goal_check')
            ->where('student_id', $student_id)
            ->where('branch_id', $branch_id)
            ->where('scripture_note IS NOT NULL', null, false)
            ->where('scripture_note <>', '')
            ->order_by('date', 'ASC')
            ->get()->result_array();

        $notes = [1 => [], 2 => [], 3 => [], 4 => []];

        foreach ($rows as $r) {
            $t = (int)($r['term_id'] ?? 0);
            if ($t < 1 || $t > 4) continue;

            $val = trim((string)$r['scripture_note']);
            if ($val === '') continue;

            $key = mb_strtolower($val, 'UTF-8');
            if (!isset($notes[$t]['__keys'])) $notes[$t]['__keys'] = [];
            if (!isset($notes[$t]['__keys'][$key])) {
                $notes[$t]['__keys'][$key] = true;
                $notes[$t][] = $val;
            }
        }

        foreach ($notes as $k => &$arr) {
            if (isset($arr['__keys'])) unset($arr['__keys']);
            $arr = array_values($arr);
        }
        unset($arr);

        return $notes;
    }

    public function get_student_grade_label($student_id, $session_id)
    {
        $branch_id = get_loggedin_branch_id();

        $row = $this->db->select('c.name, c.name_numeric')
            ->from('enroll e')
            ->join('class c', 'c.id = e.class_id', 'left')
            ->where('e.student_id', (int)$student_id)
            ->where('e.session_id', (int)$session_id)
            ->where('e.branch_id', (int)$branch_id)
            ->limit(1)
            ->get()->row();

        if (!$row) return '';

        if (!empty($row->name)) return (string)$row->name;
        if (isset($row->name_numeric) && $row->name_numeric !== '') {
            return 'Gr ' . (string)$row->name_numeric;
        }
        return '';
    }
/* === PROGRESS REPORT WORKFLOW (add to Report_model) ===================== */

public function get_workflow($student_id, $term, $year)
{
    $row = $this->db->get_where('progress_report_workflow', [
        'student_id' => (int)$student_id,
        'term'       => (int)$term,
        'year'       => (int)$year,
    ])->row_array();

    if (!$row) {
        $this->db->insert('progress_report_workflow', [
            'student_id' => (int)$student_id,
            'term'       => (int)$term,
            'year'       => (int)$year,
            'status'     => 'draft',
        ]);
        $row = $this->db->get_where('progress_report_workflow', [
            'student_id' => (int)$student_id,
            'term'       => (int)$term,
            'year'       => (int)$year,
        ])->row_array();
    }
    return $row;
}

public function update_workflow($student_id, $term, $year, $data)
{
    $this->db->where([
        'student_id' => (int)$student_id,
        'term'       => (int)$term,
        'year'       => (int)$year,
    ])->update('progress_report_workflow', $data);
    return $this->db->affected_rows() >= 0;
}

}
