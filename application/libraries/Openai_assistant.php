<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Openai_assistant
{
    protected $CI;
    protected $apiKey;
    protected $assistantId;
    protected $apiBase;
    protected $timeout;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->config->load('openai', TRUE);
        $cfg = $this->CI->config->item('openai');

        $this->apiKey      = $cfg['openai_api_key'];
        $this->assistantId = $cfg['openai_assistant_id'];
        $this->apiBase     = rtrim($cfg['openai_api_base'], '/');
        $this->timeout     = (int)$cfg['openai_timeout'];
    }

    public function ask($userMessage, $threadId = null)
    {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'OPENAI_API_KEY not set'];
        }
        if (empty($this->assistantId)) {
            return ['ok' => false, 'error' => 'assistant_id not configured'];
        }

        // 1) Ensure a thread
        if (!$threadId) {
            $threadId = $this->createThread();
            if (!$threadId) return ['ok' => false, 'error' => 'Failed to create thread'];
        }

        // 2) Add user message
        $ok = $this->addMessage($threadId, $userMessage);
        if (!$ok) return ['ok' => false, 'error' => 'Failed to add message'];

        // 3) Run the assistant (uses its attached File Search/vector store only)
        $runId = $this->createRun($threadId);
        if (!$runId) return ['ok' => false, 'error' => 'Failed to create run'];

        // 4) Poll until complete
        $status = $this->waitForRun($threadId, $runId);
        if ($status !== 'completed') {
            return ['ok' => false, 'error' => 'Run status: ' . $status];
        }

        // 5) Fetch the latest assistant message
        $answer = $this->getLastAssistantMessage($threadId);

        return ['ok' => true, 'thread_id' => $threadId, 'answer' => $answer];
    }

    /* ---------------------- low-level helpers ---------------------- */

    protected function createThread()
    {
        $res = $this->http('POST', '/threads', []);
        return $res && !empty($res['id']) ? $res['id'] : null;
    }

    protected function addMessage($threadId, $content)
    {
        $payload = [
            'role'    => 'user',
            'content' => $content,
        ];
        $res = $this->http('POST', "/threads/{$threadId}/messages", $payload);
        return !empty($res['id']);
    }

    protected function createRun($threadId)
    {
        $payload = [
            'assistant_id' => $this->assistantId,
            // No tools specified here — the Assistant already has File Search attached.
        ];
        $res = $this->http('POST', "/threads/{$threadId}/runs", $payload);
        return $res && !empty($res['id']) ? $res['id'] : null;
    }

    protected function waitForRun($threadId, $runId, $maxWait = null)
    {
        $maxWait  = $maxWait ?: $this->timeout;
        $elapsed  = 0;
        $interval = 1000; // 1s

        while ($elapsed < ($maxWait * 1000)) {
            $res = $this->http('GET', "/threads/{$threadId}/runs/{$runId}");
            $status = $res['status'] ?? 'unknown';

            if (in_array($status, ['completed','failed','cancelled','expired'], true)) {
                return $status;
            }

            usleep($interval * 1000);
            $elapsed += $interval;
        }

        return 'timeout';
    }

    protected function getLastAssistantMessage($threadId)
    {
        $res = $this->http('GET', "/threads/{$threadId}/messages?order=desc&limit=10");
        if (empty($res['data'])) return '';

        foreach ($res['data'] as $msg) {
            if (($msg['role'] ?? '') === 'assistant') {
                // Extract plain text parts
                $out = [];
                foreach (($msg['content'] ?? []) as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        $out[] = $part['text']['value'] ?? '';
                    }
                }
                return trim(implode("\n\n", array_filter($out)));
            }
        }
        return '';
    }

    protected function http($method, $path, $payload = null)
    {
        $url = $this->apiBase . $path;

        $ch = curl_init();
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->timeout,
        ];

        if (in_array($method, ['POST','PUT','PATCH'], true)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?: []);
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) return null;

        $json = json_decode($body, true);
        if ($code >= 400) return null;
        return $json;
    }
}
