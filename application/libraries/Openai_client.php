<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Openai_client {
    private $CI, $key, $assistant, $base, $pollSecs, $org;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->config('openai');
        $this->key       = getenv('OPENAI_API_KEY') ?: $this->CI->config->item('openai_api_key');
        $this->assistant = $this->CI->config->item('openai_assistant');
        $this->base      = $this->CI->config->item('openai_base') ?: 'https://api.openai.com/v1';
        $this->pollSecs  = (int) $this->CI->config->item('openai_poll_secs') ?: 25;
        $this->org       = (string) $this->CI->config->item('openai_org');
    }

    private function headers() {
        $h = [
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];
        if ($this->org) $h[] = 'OpenAI-Organization: ' . $this->org; // or OpenAI-Project
        return $h;
    }

    private function req($method, $path, $payload = null) {
        if (!$this->key) return ['error' => 'Missing OpenAI API key'];
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_TIMEOUT        => 60,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
        $out = curl_exec($ch);
        if ($out === false) { $err = curl_error($ch); curl_close($ch); return ['error' => $err]; }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode($out, true);
        return ($code >= 200 && $code < 300) ? $json : ['error' => $out, 'status' => $code];
    }

    /** Thread helpers (we keep thread_id in session per user) */
    public function get_or_create_thread_id() {
        $tid = $this->CI->session->userdata('openai_thread_id');
        if ($tid) return $tid;
        $r = $this->req('POST', '/threads', []);
        if (isset($r['id'])) {
            $tid = $r['id'];
            $this->CI->session->set_userdata('openai_thread_id', $tid);
            return $tid;
        }
        return null;
    }

    public function add_message($thread_id, $content) {
        return $this->req('POST', "/threads/{$thread_id}/messages", [
            'role'    => 'user',
            'content' => $content,
        ]);
    }

    public function run_assistant($thread_id, $instructions = '') {
        return $this->req('POST', "/threads/{$thread_id}/runs", [
            'assistant_id' => $this->assistant,
            'instructions' => $instructions,
        ]);
    }

    public function get_run($thread_id, $run_id) {
        return $this->req('GET', "/threads/{$thread_id}/runs/{$run_id}");
    }

    public function list_messages($thread_id, $after = null) {
        $path = "/threads/{$thread_id}/messages?limit=10";
        if ($after) $path .= '&after=' . urlencode($after);
        return $this->req('GET', $path);
    }

    /** Sync ask: add message -> create run -> poll -> fetch latest assistant reply */
    public function ask_assistant($user_text) {
        if (!$this->assistant) return ['error' => 'Missing assistant id'];
        $thread_id = $this->get_or_create_thread_id();
        if (!$thread_id) return ['error' => 'Could not create thread'];

        // Add the user message
        $m = $this->add_message($thread_id, $user_text);
        if (isset($m['error'])) return $m;

        // Start a run
        $run = $this->run_assistant($thread_id);
        if (isset($run['error'])) return $run;

        $run_id = $run['id'];

        // Poll until completed or timed out
        $deadline = time() + $this->pollSecs;
        $last_status = '';
        while (time() < $deadline) {
            $g = $this->get_run($thread_id, $run_id);
            if (isset($g['error'])) return $g;
            $last_status = $g['status'];
            if (in_array($g['status'], ['completed', 'failed', 'cancelled', 'expired'])) break;
            usleep(600000); // 0.6s
        }

        if ($last_status !== 'completed') {
            return ['error' => 'Run not completed', 'status' => $last_status];
        }

        // Fetch latest assistant message
        $msgs = $this->list_messages($thread_id);
        if (isset($msgs['error'])) return $msgs;

        foreach ($msgs['data'] as $msg) {
            if ($msg['role'] === 'assistant' && !empty($msg['content'][0]['text']['value'])) {
                return ['answer' => $msg['content'][0]['text']['value'], 'raw' => $msg];
            }
        }
        return ['answer' => '(no assistant message found)'];
    }
}
