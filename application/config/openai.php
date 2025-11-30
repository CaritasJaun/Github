<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * OpenAI Assistants v2 config
 */
$config['openai_api_key']   = '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';          // <-- your Project API key
$config['openai_assistant'] = 'asst_oazb5lLc5oAcxSwgiSLwSsrP';   // <-- your Assistant ID
$config['openai_base']      = 'https://api.openai.com/v1';
$config['openai_poll_secs'] = 25;    // max time to poll a run before returning
$config['openai_org']       = '';    // optional: org or project headers if you use them
