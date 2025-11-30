<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Ai extends CI_Controller
{
    public function __construct() {
        parent::__construct();
        $this->load->library('Openai_client');
        $this->output->set_content_type('application/json; charset=utf-8');
    }

    private function out($arr, $code = 200) {
        $this->output->set_status_header($code)->set_output(json_encode($arr));
    }
    
    // High-risk tasks that should be gated by a Yes/No precheck
private $flows = [
  'assign_paces' => [
    'intent'     => '/\b(assign|allocate|give|add|set)\b.*\bpace/i',
    'precheck_q' => "Has Finance/Admin **issued** the PACE(s) and confirmed stock? (Yes/No)",
    'if_no'      => "Ask Finance/Admin to issue the PACE(s). After that, assign in SPC: Dashboard ▸ Learning Centre ▸ SPC.",
    'steps'      =>
      "1) Dashboard ▸ Learning Centre ▸ **SPC**\n".
      "2) Select **student**\n3) Choose **subject** ▸ pick **PACE #**\n4) Save",
    'post'       =>
      "Checks: prev PACE ≥80% (or retake ≥80%), subject assigned, teacher allocated, stock exists.",
  ],
  // Add more tasks here (mark_scores, create_event, etc.)
];

    /** Context-specific guardrails and handoffs */
    private function context_rules(string $context, string $role_name): array
    {
        $ctx = strtolower(trim($context));

        // Normalize a few common variants
       if (in_array($ctx, ['teacher_profile','teacher/profile','teacher/view','teacher'])) {
    return [
        "Context: Teacher Profile screen.",
        "Scope: Teacher tasks only — learning-centre work, PACE requests, assigning PACEs in SPC, goal checks, recording scores.",
        "Never describe invoicing, payments, or finance screens for this user.",
        // Conversation flow for assign PACEs
        "When the user asks about assigning PACEs, follow this flow:",
        "1) Prerequisite check: Ask exactly — 'Has Finance/Admin ordered and issued this PACE and confirmed stock? (Yes/No)' and WAIT for the answer before giving steps.",
        "2) If No: explain that Finance/Admin must create the invoice and issue the PACE; advise the teacher to request it. Add a one-line handoff: 'Finance/Admin issues the PACE; once issued and stock received, you’ll assign it in SPC.'",
        "3) If Yes: provide short, precise steps to assign via SPC using ▸ breadcrumbs, e.g.: Dashboard ▸ Learning Centre ▸ Supervisor’s Progress Card ▸ select Student ▸ choose Subject ▸ select PACE # ▸ Save.",
        "4) Post-check items after steps: previous PACE passed (≥80% or second attempt ≥80%), subject is on this student’s profile, the teacher is allocated to the class, and stock exists.",
        // Troubleshooting mode
        "Troubleshooting trigger words: not working, can’t, error, fail, stuck. In these cases FIRST ask: 'Tell me what you tried so far (screens visited, student, subject, PACE #, and any error text).' Then give a concise checklist: (a) invoice issued/stock available by Finance/Admin, (b) previous PACE pass ≥80% or retake ≥80%, (c) subject assigned to student, (d) teacher allocated to class, (e) reload/try again. End with the next action (assign or request Finance).",
        "Be explicit about the handoff: 'Finance/Admin handles invoicing; you’ll assign once it’s issued.'"
    ];
}

        // Default: light hint only
        return [
            "Context: {$context}. Keep answers within the visible capabilities for a {$role_name}. If a requested step needs extra permissions, say which role handles it and how to request access."
        ];
    }

    /** Optional: last-resort cleanup for any leaked file refs */
    private function sanitize_answer($text) {
        $text  = (string)$text;
        $lines = preg_split("/\r\n|\r|\n/", $text);
        $keep  = [];
        foreach ($lines as $ln) {
            if (preg_match('/\b(See:|Source:|File:)\b/i', $ln)) continue;
            if (preg_match('/\.(md|pdf|docx?|xlsx|csv)\b/i', $ln)) continue;
            if (preg_match('#https?://#i', $ln)) continue;
            if (preg_match('#[\\/\\\\][^\\s]+#', $ln)) continue;
            $keep[] = $ln;
        }
        $clean = trim(implode("\n", $keep));
        return $clean === '' ? '(no answer)' : preg_replace('/\n{3,}/', "\n\n", $clean);
    }

    // FAB posts here
    public function assist()
{
    $msg     = trim((string)($this->input->post('message') ?? ''));
    $context = trim((string)($this->input->post('context') ?? ''));

    if ($msg === '') return $this->out(['answer' => 'Please type a message first.']);

    // Staff-only
    $role_id = (int)$this->session->userdata('loggedin_role_id');
    if (!in_array($role_id, [1,2,3,6], true)) {
        return $this->out(['answer' => "EduAssist AI is available to staff only."]);
    }
    $role_name = [1=>'Super Admin',2=>'Admin',3=>'Teacher',6=>'Principal'][$role_id] ?? 'User';

    // -------- Generic gate (flow precheck) --------
    // 1) If user message matches a flow intent, ask precheck and hold state
    foreach ($this->flows as $slug => $flow) {
        if (preg_match($flow['intent'], strtolower($msg))) {
            $this->session->set_userdata('ai_flow', ['slug'=>$slug,'status'=>'awaiting_precheck']);
            return $this->out(['answer'=>$flow['precheck_q']]);
        }
    }
    // 2) If we’re awaiting a precheck reply, branch on Yes/No
    $flow = $this->session->userdata('ai_flow');
    if (($flow['status'] ?? '') === 'awaiting_precheck') {
        $def = $this->flows[$flow['slug']];
        if (preg_match('/^\s*y(es)?\b/i', $msg)) {
            $this->session->unset_userdata('ai_flow');
            return $this->out(['answer'=>$def['steps']."\n\n".$def['post']."\n\nWhich student/subject?"]);
        } elseif (preg_match('/^\s*n(o)?\b/i', $msg)) {
            $this->session->unset_userdata('ai_flow');
            return $this->out(['answer'=>$def['if_no']."\n\nWant a message to send to Finance?"]);
        }
        return $this->out(['answer'=>"Please reply **Yes** or **No** so I can guide next."]);
    }
    // -------- end gate --------

    // Run-level guardrails (role + context)
    $rules = array_merge([
        "You are EduAssist Coach embedded in the EduAssist SaaS.",
        "Audience: {$role_name}. Use South Africa context and ACE model terminology where helpful.",
        "Rules:",
        "- Never reveal internal file names, paths, or citations.",
        "- Give clear, step-by-step guidance using ▸ breadcrumbs (e.g., Dashboard ▸ PACE Management ▸ Assign PACEs).",
        "- Be concise (3–6 bullets or ≤120 words).",
        "- If asked for test answers or to bypass learning, refuse and guide study steps instead.",
        "- If a task needs elevated permissions, say which role handles it and how to request access.",
        "- Always end with one clear question that moves the task forward (e.g., a Yes/No to the prerequisite, or 'Which student/subject/PACE #?')."
    ], $this->context_rules($context, $role_name));

    // Call the client (supports both 1-arg and 2-arg versions)
    try {
        $ref = new ReflectionMethod($this->openai_client, 'ask_assistant');
        $res = ($ref->getNumberOfParameters() >= 2)
            ? $this->openai_client->ask_assistant($msg, implode("\n", $rules))
            : $this->openai_client->ask_assistant($msg);
    } catch (Throwable $e) {
        return $this->out(['answer' => 'Sorry, the Assistant is not available right now.', 'raw' => ['error'=>$e->getMessage()]]);
    }

    if (isset($res['error'])) {
        return $this->out(['answer' => 'Sorry, I could not process that right now.', 'raw' => $res]);
    }

    $res['answer'] = $this->sanitize_answer($res['answer'] ?? '');
    return $this->out($res);
}
}