<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Diag_mail extends CI_Controller {
    public function send() {
        $this->load->library('email');

        // Use your cPanel mailbox (what you showed in UI)
        $config = [
            'protocol'    => 'smtp',
            'smtp_host'   => 'cp62.domains.co.za', // try 'mail.eduassistance.co.za' if this fails
            'smtp_user'   => 'schoolmanage@eduassistance.co.za',
            'smtp_pass'   => 'School@1234Manage',
            'smtp_port'   => 465,   // alt: 587 with 'tls'
            'smtp_crypto' => 'ssl', // alt: 'tls' with port 587
            'mailtype'    => 'html',
            'charset'     => 'utf-8',
            'newline'     => "\r\n",
            'crlf'        => "\r\n",
            'smtp_timeout'=> 15,
        ];
        $this->email->initialize($config);

        $this->email->from('schoolmanage@eduassistance.co.za', 'EduAssist Mail Diag');
        $this->email->to($this->input->get('to') ?: 'jauncrc@gmail.com');
        $this->email->subject('SMTP Test from EduAssist');
        $this->email->message('SMTP OK @ ' . date('c'));

        if ($this->email->send(false)) {
            echo "SENT";
        } else {
            echo nl2br($this->email->print_debugger(['headers']));
        }
    }
}
