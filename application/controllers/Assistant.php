<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Assistant extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('openai_assistant');
        $this->output->set_content_type('application/json');
    }

    // POST /assistant/ask
    // body: { "message": "...", "thread_id": "thread_..." (optional) }
    public function ask()
    {
        $payload   = json_decode($this->input->raw_input_stream ?: '{}', true);
        $message   = trim($payload['message'] ?? '');
        $thread_id = $payload['thread_id'] ?? null;

        if ($message === '') {
            return $this->output->set_status_header(400)
                ->set_output(json_encode(['ok' => false, 'error' => 'message is required']));
        }

        $res = $this->openai_assistant->ask($message, $thread_id);

        $status = $res['ok'] ? 200 : 500;
        return $this->output->set_status_header($status)
            ->set_output(json_encode($res));
    }
}
