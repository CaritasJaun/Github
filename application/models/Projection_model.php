<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Projection_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('application_model');
    }

    /* ----------------------------------------------------------------------
     * SUBJECTS the student has for a given year (main source for planner).
     * Table: student_assigned_subjects (id, student_id, subject_id, year, ...)
     * Fallback: infer from student_assign_paces for the active session.
     * --------------------------------------------------------------------*/
    public function get_student_subjects($student_id, $session_id = 0, $year = null)
    {
        $student_id = (int)$student_id;
        $year       = ($year !== null) ? (int)$year : (int)date('Y');

        // Primary: explicit subject assignment for the given year
        $rows = $this->db->select('s.id, s.name')
            ->from('student_assigned_subjects sas')
            ->join('subject s', 's.id = sas.subject_id', 'inner')
            ->where('sas.student_id', $student_id)
            ->where('sas.year', $year)
            ->order_by('s.name', 'ASC')
            ->get()->result_array();

        if (!empty($rows)) {
            return $rows;
        }

        // Fallback (rare): infer from PACEs assigned in the session
        if ($session_id) {
            return $this->db->select('s.id, s.name')
                ->from('student_assign_paces sap')
                ->join('subject s', 's.id = sap.subject_id', 'inner')
                ->where('sap.student_id', $student_id)
                ->where('sap.session_id', (int)$session_id)
                ->group_by('s.id')
                ->order_by('s.name', 'ASC')
                ->get()->result_array();
        }

        return [];
    }

    /* ----------------------------------------------------------------------
     * CHART HELPERS
     * --------------------------------------------------------------------*/
    public function committed_count($student_id, $subject_id, $year)
    {
        // Count of PACEs “committed” for the year (approve flag can be added if you use it)
        return (int)$this->db->select('COUNT(*) AS c', false)
            ->from('student_pace_projection')
            ->where([
                'student_id' => (int)$student_id,
                'subject_id' => (int)$subject_id,
                'year'       => (int)$year,
            ])
            // ->where('status', 'approved')
            ->get()->row()->c;
    }

    public function actual_completed_count($student_id, $subject_id, $session_id)
    {
        return (int)$this->db->select('COUNT(*) AS c', false)
            ->from('student_assign_paces')
            ->where([
                'student_id' => (int)$student_id,
                'subject_id' => (int)$subject_id,
                'session_id' => (int)$session_id,
                'status'     => 'completed',
            ])
            ->get()->row()->c;
    }

    /* ----------------------------------------------------------------------
     * ROWS FOR THE “Projection Planner” TABLE IN PROFILE VIEW
     * - Returns one row per assigned subject (for the given year)
     * - Includes any saved projection (target_finish, pacelist_json, status)
     * --------------------------------------------------------------------*/
    public function get_student_projection_rows($branch_id, $student_id, $session_id, $class_id, $year)
    {
        $subjects = $this->get_student_subjects($student_id, $session_id, $year);
        if (empty($subjects)) return [];

        $out = [];
        foreach ($subjects as $s) {
            $sid = (int)$s['id'];

            // Fetch existing projection (if any)
            $row = $this->db->get_where('student_pace_projection', [
                'branch_id'  => (int)$branch_id,
                'student_id' => (int)$student_id,
                'subject_id' => $sid,
                'year'       => (int)$year,
            ])->row_array();

            // Normalize payload for the view
            $pacelist = [];
            if (!empty($row['pacelist_json'])) {
                $tmp = json_decode($row['pacelist_json'], true);
                if (is_array($tmp)) {
                    $pacelist = array_values(array_unique(array_map('intval', $tmp)));
                    sort($pacelist);
                }
            }

            $out[] = [
                'subject_id'    => $sid,
                'subject_name'  => $s['name'],
                'target_finish' => $row['target_finish'] ?? '',
                'pacelist'      => $pacelist,              // array of numbers already chosen (0..12 items)
                'status'        => $row['status'] ?? 'pending',
            ];
        }
        return $out;
    }

    /**
     * Data for the “Projection vs Actual” chart.
     * Subjects limited to what the student actually has this session/year.
     */
    public function get_vs_actual_chart_data(int $branch_id, int $student_id, int $year, ?int $session_id = null): array
    {
        if ($session_id === null) {
            $session_id = (int)get_session_id();
        }

        $labels    = [];
        $committed = [];
        $actual    = [];

        $subjects = $this->get_student_subjects($student_id, $session_id, $year);

        foreach ($subjects as $sub) {
            $sid        = (int)$sub['id'];
            $labels[]   = (string)$sub['name'];
            $committed[] = $this->committed_count($student_id, $sid, $year);
            $actual[]    = $this->actual_completed_count($student_id, $sid, $session_id);
        }

        return [
            'labels'    => $labels,
            'committed' => $committed,
            'actual'    => $actual,
        ];
    }

    /* ----------------------------------------------------------------------
     * Utility: list of assigned subject ids for a student (current session/year)
     * --------------------------------------------------------------------*/
    public function get_assigned_subject_ids_for_student($student_id)
    {
        if (!$this->db->table_exists('student_assigned_subjects')) return [];

        // Try current session/year first
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

    /* ----------------------------------------------------------------------
     * Catalog helpers for planner options (GRADE-aware)
     * --------------------------------------------------------------------*/

    // Resolve numeric grade (1..12) from class_id: try 'grade' column; otherwise parse out of class.name
    private function resolve_grade_for_class(int $class_id): int
    {
        $grade = 0;

        if ($this->db->field_exists('grade', 'class')) {
            $row = $this->db->select('grade')->from('class')->where('id', $class_id)->get()->row_array();
            $grade = (int)($row['grade'] ?? 0);
        } else {
            $row = $this->db->select('name')->from('class')->where('id', $class_id)->get()->row_array();
            if (!empty($row['name']) && preg_match('/(\d{1,2})/', (string)$row['name'], $m)) {
                $grade = (int)$m[1];
            }
        }

        return $grade;
    }

    // Get pace options for a specific subject + grade (optionally by section)
    private function get_catalog_paces_for_subject_grade(int $subject_id, int $grade, ?int $section_id = null): array
    {
        // ---- 1) SUBJECT_PACE (your curriculum mapping)
        if ($this->db->table_exists('subject_pace')) {
            $qb = $this->db->select('pace_number')
                           ->from('subject_pace')
                           ->where('subject_id', $subject_id);

            if ($this->db->field_exists('grade', 'subject_pace') && $grade > 0) {
                $qb->where('grade', $grade);
            }
            if ($section_id && $this->db->field_exists('section_id', 'subject_pace')) {
                // allow rows that are generic (NULL) or specifically tied to the section
                $qb->group_start()
                       ->where('section_id', $section_id)
                       ->or_where('section_id IS NULL', null, false)
                   ->group_end();
            }

            $rows = $qb->order_by('pace_number', 'ASC')->get()->result_array();
            if (!empty($rows)) {
                $vals = array_map('intval', array_column($rows, 'pace_number'));
                $vals = array_values(array_unique($vals));
                sort($vals, SORT_NUMERIC);
                return $vals;
            }
        }

        // ---- 2) PRODUCT (if your schema encodes subject/grade in product)
        if ($this->db->table_exists('product')) {
            // Determine which column carries subject id in your install
            $subjectCol = null;
            if ($this->db->field_exists('subject_id', 'product')) {
                $subjectCol = 'subject_id';
            } elseif ($this->db->field_exists('category_id', 'product')) {
                // your screenshots show category_id aligns with subject_id
                $subjectCol = 'category_id';
            }

            if ($subjectCol !== null && $this->db->field_exists('pace_number', 'product')) {
                $qb = $this->db->select('pace_number')
                               ->from('product')
                               ->where($subjectCol, $subject_id)
                               ->where('pace_number >', 0);

                if ($this->db->field_exists('grade', 'product') && $grade > 0) {
                    $qb->where('grade', $grade);
                }

                $rows = $qb->order_by('pace_number', 'ASC')->get()->result_array();
                if (!empty($rows)) {
                    $vals = array_map('intval', array_column($rows, 'pace_number'));
                    $vals = array_values(array_unique($vals));
                    sort($vals, SORT_NUMERIC);
                    return $vals;
                }
            }
        }

        // ---- 3) LAST RESORT: historical assignments for the subject (school-wide)
        $rows = $this->db->select('DISTINCT pace_number', false)
                         ->from('student_assign_paces')
                         ->where('subject_id', $subject_id)
                         ->order_by('pace_number', 'ASC')
                         ->get()->result_array();

        $vals = array_map('intval', array_column($rows, 'pace_number'));
        $vals = array_values(array_unique($vals));
        sort($vals, SORT_NUMERIC);
        return $vals;
    }

    /**
     * Build planner dropdown options using the curriculum catalog (GRADE-aware).
     * Primary source: subject_pace (grade + subject). Optional filter by section_id.
     * Fallbacks: product (if it stores subject/grade), then historical assignments.
     *
     * @param int        $class_id
     * @param array      $proj_rows   rows from get_student_projection_rows()
     * @param int|null   $section_id
     * @return array     [subject_id => [pace_numbers...]]
     */
    public function build_planner_options_from_catalog(int $class_id, array $proj_rows, ?int $section_id = null): array
    {
        $out   = [];
        $grade = $this->resolve_grade_for_class($class_id);

        foreach ($proj_rows as $r) {
            $sid       = (int)$r['subject_id'];
            $out[$sid] = $this->get_catalog_paces_for_subject_grade($sid, $grade, $section_id);
        }

        return $out;
    }
    
    public function get_projection_block(int $student_id, int $year): array
    {
        $row = $this->db->get_where('student_projection', [
            'student_id' => $student_id,
            'year'       => $year
        ])->row_array();

        if (!$row) {
            return [
                'labels'    => [],
                'committed' => [],
                'actual'    => [],
            ];
        }

        return [
            'labels'    => $this->decode_json($row['labels_json']),
            'committed' => $this->decode_json($row['committed_json']),
            'actual'    => $this->decode_json($row['actual_json']),
        ];
    }

    /** Upsert projection block for a student/year */
    public function save_projection_block(int $student_id, int $year, array $labels, array $committed, array $actual): bool
    {
        // Normalize lengths (pad to longest with empty strings / nulls)
        $max = max(count($labels), count($committed), count($actual));
        $labels    = array_values(array_pad($labels, $max, ''));
        $committed = array_values(array_pad($committed, $max, null));
        $actual    = array_values(array_pad($actual, $max, null));

        $payload = [
            'student_id'     => $student_id,
            'year'           => $year,
            'labels_json'    => json_encode($labels, JSON_UNESCAPED_UNICODE),
            'committed_json' => json_encode($committed, JSON_UNESCAPED_UNICODE),
            'actual_json'    => json_encode($actual, JSON_UNESCAPED_UNICODE),
        ];

        // Upsert
        $exists = $this->db->select('id')
            ->from('student_projection')
            ->where('student_id', $student_id)
            ->where('year', $year)
            ->limit(1)
            ->get()->row_array();

        if ($exists) {
            $this->db->where('id', (int)$exists['id'])->update('student_projection', $payload);
        } else {
            $this->db->insert('student_projection', $payload);
        }
        return $this->db->affected_rows() >= 0;
    }

    /** Safe JSON decode -> array */
    private function decode_json($val): array
    {
        if ($val === null || $val === '' ) return [];
        $arr = json_decode($val, true);
        return is_array($arr) ? $arr : [];
    }
    
   /** Save subject×12-slot grid into committed_json for a student/year */
public function save_projection_grid(int $student_id, int $year, array $grid): bool
{
    $norm = [];
    foreach ($grid as $sid => $row) {
        $sid = (int)$sid;
        if ($sid <= 0) continue;
        $norm[$sid] = [];
        for ($i = 1; $i <= 12; $i++) {
            $k = 'p'.$i;
            $v = $row[$k] ?? '';
            $norm[$sid][$k] = ($v !== '' && is_numeric($v)) ? (int)$v : '';
        }
    }

    $exists = $this->db->select('id')
        ->from('student_projection')
        ->where('student_id', $student_id)
        ->where('year', $year)
        ->limit(1)->get()->row_array();

    $payload = ['committed_json' => json_encode($norm, JSON_UNESCAPED_UNICODE)];
    if ($exists) {
        $this->db->where('id', (int)$exists['id'])->update('student_projection', $payload);
    } else {
        $payload['student_id']  = $student_id;
        $payload['year']        = $year;
        $payload['labels_json'] = json_encode([], JSON_UNESCAPED_UNICODE);
        $payload['actual_json'] = json_encode([], JSON_UNESCAPED_UNICODE);
        $this->db->insert('student_projection', $payload);
    }
    return $this->db->affected_rows() >= 0;
}

/** Optional loader if/when you want to prefill from DB */
public function get_projection_grid(int $student_id, int $year): array
{
    $row = $this->db->get_where('student_projection', [
        'student_id' => $student_id,
        'year'       => $year
    ])->row_array();

    if (!$row) return [];
    $grid = json_decode($row['committed_json'] ?? '[]', true);
    return is_array($grid) ? $grid : [];
}

/** Save Pages Planner meta into meta_json for a student/year */
public function save_projection_meta(int $student_id, int $year, array $meta): bool
{
    // Only keep the two keys we care about
    $clean = [];
    if (isset($meta['avg_pages_per_pace']) && $meta['avg_pages_per_pace'] !== '') {
        $clean['avg_pages_per_pace'] = (int)$meta['avg_pages_per_pace'];
    }
    if (isset($meta['weeks_left']) && $meta['weeks_left'] !== '') {
        $clean['weeks_left'] = (int)$meta['weeks_left'];
    }

    // Nothing to save
    if ($clean === []) return true;

    $exists = $this->db->select('id, meta_json')
        ->from('student_projection')
        ->where('student_id', $student_id)
        ->where('year', $year)
        ->limit(1)->get()->row_array();

    if ($exists) {
        // Merge into existing meta_json
        $current = json_decode($exists['meta_json'] ?? '[]', true);
        if (!is_array($current)) $current = [];
        $merged = array_merge($current, $clean);
        $this->db->where('id', (int)$exists['id'])
                 ->update('student_projection', ['meta_json' => json_encode($merged, JSON_UNESCAPED_UNICODE)]);
    } else {
        $payload = [
            'student_id'  => $student_id,
            'year'        => $year,
            'labels_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'committed_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'actual_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'meta_json'   => json_encode($clean, JSON_UNESCAPED_UNICODE),
        ];
        $this->db->insert('student_projection', $payload);
    }
    return $this->db->affected_rows() >= 0;
}

/** Get Pages Planner meta for a student/year */
public function get_projection_meta(int $student_id, int $year): array
{
    $row = $this->db->get_where('student_projection', [
        'student_id' => $student_id,
        'year'       => $year
    ])->row_array();

    if (!$row) return [];
    $meta = json_decode($row['meta_json'] ?? '[]', true);
    return is_array($meta) ? $meta : [];
}

}

