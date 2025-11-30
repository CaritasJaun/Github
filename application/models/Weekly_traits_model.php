<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Weekly_traits_model extends CI_Model
{
    // Single source of truth for trait keys & labels
    public function get_traits_definition(): array
    {
        return [
            'work' => [
                'label' => 'Work Habits',
                'items' => [
                    'follow_directions'          => 'Follows directions',
                    'works_independently'        => 'Works well independently',
                    'does_not_disturb_others'    => 'Does not disturb others',
                    'cares_for_materials'        => 'Takes care of materials',
                    'completes_work_required'    => 'Completes work required',
                    'attaches_completed_work'    => 'Attaches completed assignments',
                ],
            ],
            'social' => [
                'label' => 'Social Traits',
                'items' => [
                    'is_courteous'                => 'Is courteous',
                    'gets_along_with_others'      => 'Gets along well with others',
                    'exhibits_self_control'       => 'Exhibits self-control',
                    'respects_authority'          => 'Shows respect for authority',
                    'responds_to_correction'      => 'Responds well to correction',
                    'promotes_school_spirit'      => 'Promotes school spirit',
                ],
            ],
            'personal' => [
                'label' => 'Personal Traits',
                'items' => [
                    'establishes_goals'           => 'Ability to establish own goals',
                    'reaches_goals'               => 'Successfully reaches goals',
                    'displays_flexibility'        => 'Displays flexibility',
                    'shows_creativity'            => 'Shows creativity',
                    'overall_progress'            => 'Shows overall progress',
                    'attitude_to_computers'       => 'Attitude towards computer learning',
                ],
            ],
        ];
    }

    // Get all captured scores for ONE week (student+term+week)
    public function get_scores($student_id, $session_id, $term, $week_no): array
    {
        $rows = $this->db
            ->where('student_id', $student_id)
            ->where('session_id', $session_id)
            ->where('term', $term)
            ->where('week_no', $week_no)
            ->get('weekly_traits')
            ->result_array();

        $map = [];
        foreach ($rows as $r) $map[$r['trait_key']] = (int)$r['score'];
        return $map;
    }

    // Upsert single cell
    public function save_score(array $d): bool
    {
        $exists = $this->db->where([
            'session_id' => $d['session_id'],
            'student_id' => $d['student_id'],
            'term'       => $d['term'],
            'week_no'    => $d['week_no'],
            'trait_key'  => $d['trait_key'],
        ])->get('weekly_traits')->row_array();

        if ($exists) {
            $this->db->where('id', $exists['id'])->update('weekly_traits', [
                'branch_id'  => $d['branch_id'],
                'teacher_id' => $d['teacher_id'],
                'category'   => $d['category'],
                'score'      => $d['score'],
            ]);
        } else {
            $this->db->insert('weekly_traits', $d);
        }
        return $this->db->affected_rows() >= 0;
    }

    // Averages per term for entire year → used by Progress Report
    // Returns: [trait_key => [1=>avg,2=>avg,3=>avg,4=>avg]]
    public function get_term_averages($student_id, $session_id): array
    {
        $rows = $this->db->select('trait_key, term, AVG(score) AS avg_score', false)
            ->from('weekly_traits')
            ->where('student_id', $student_id)
            ->where('session_id', $session_id)
            ->group_by(['trait_key','term'])
            ->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $k = $r['trait_key'];
            $t = (int)$r['term'];
            $out[$k][$t] = round((float)$r['avg_score'], 1);
        }
        return $out;
    }
}
