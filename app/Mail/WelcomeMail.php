<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class WelcomeMail extends Mailable
{
    public $shop;

    public function __construct($shop)
    {
        $this->shop = $shop;
    }

    public function build()
    {
        return $this->subject('Welcome to Our App 🎉')
            ->view('emails.welcome')
            ->with([
                'shop' => $this->shop
            ]);
    }
}