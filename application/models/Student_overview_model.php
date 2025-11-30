<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Student_overview_model extends CI_Model
{
    private $table = 'student_profile_overview';

    public function get_or_create(int $student_id, int $session_id)
    {
        $row = $this->db->get_where($this->table, [
            'student_id' => $student_id,
            'session_id' => $session_id
        ])->row_array();

        if (!$row) {
            $this->db->insert($this->table, [
                'student_id' => $student_id,
                'session_id' => $session_id
            ]);
            $row = $this->db->get_where($this->table, [
                'student_id' => $student_id,
                'session_id' => $session_id
            ])->row_array();
        }

        // attach computed metrics
        return array_merge($row, $this->compute_metrics($student_id, $session_id));
    }

    public function save_field(int $student_id, int $session_id, string $field, string $value, int $user_id)
    {
        // whitelist fields
        $allowed = ['pain_points','merits','involvement','achievements'];
        if (!in_array($field, $allowed, true)) return false;

        $exists = $this->db->get_where($this->table, [
            'student_id' => $student_id,
            'session_id' => $session_id
        ])->row_array();

        $data = [$field => $value, 'updated_by' => $user_id];
        if ($exists) {
            $this->db->where('student_id', $student_id)
                     ->where('session_id', $session_id)
                     ->update($this->table, $data);
        } else {
            $this->db->insert($this->table, array_merge([
                'student_id' => $student_id,
                'session_id' => $session_id,
            ], $data));
        }
        return $this->db->affected_rows() >= 0;
    }

   private function compute_metrics(int $student_id, int $session_id, int $term = 0): array
{
    // Assigned
    $this->db->from('student_assign_paces')
             ->where('student_id', $student_id)
             ->where('session_id', $session_id);
    if ($term > 0) $this->db->where('term', $term);
    $assigned = (int)$this->db->count_all_results();

    // Build optional term clause for raw SQL
    $termClause = ($term > 0) ? " AND term=" . (int)$term . " " : "";

    // Completed: best score >= 80
    $sqlCompleted = "
        SELECT COUNT(*) AS c
        FROM student_assign_paces
        WHERE student_id=? AND session_id=?
          {$termClause}
          AND GREATEST(COALESCE(first_attempt_score,0), COALESCE(second_attempt_score,0)) >= 80";
    $completed = (int)$this->db->query($sqlCompleted, [$student_id, $session_id])->row()->c;

    // Below 80: attempted (>0) but best < 80
    $sqlBelow = "
        SELECT COUNT(*) AS c
        FROM student_assign_paces
        WHERE student_id=? AND session_id=?
          {$termClause}
          AND GREATEST(COALESCE(first_attempt_score,0), COALESCE(second_attempt_score,0)) > 0
          AND GREATEST(COALESCE(first_attempt_score,0), COALESCE(second_attempt_score,0)) < 80";
    $below80 = (int)$this->db->query($sqlBelow, [$student_id, $session_id])->row()->c;

    $progress = $assigned > 0 ? round(($completed / $assigned) * 100, 1) : 0.0;

    return [
        'assigned_total'   => $assigned,
        'completed_total'  => $completed,
        'below80_total'    => $below80,
        'progress_percent' => $progress,
        'risk_flag'        => ($below80 >= 3) ? 'risk' : (($below80 > 0) ? 'watch' : 'ok'),
    ];
}

}
