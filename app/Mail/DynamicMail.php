<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DynamicMail extends Mailable
{
    public $template;
    public $data;

    public function __construct($template, $data = [])
    {
        $this->template = $template;
        $this->data = $data;
    }

    public function build()
    {
        $body = $this->template->body;

        // 🔥 Variable replace
        foreach ($this->data as $key => $value) {
            $body = str_replace('{'.$key.'}', $value, $body);
        }

        return $this->subject($this->template->subject)
                    ->html($body);
    }
}