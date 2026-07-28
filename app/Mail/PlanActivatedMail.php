<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PlanActivatedMail extends Mailable
{
    public $shop;
    public $plan;

    public function __construct($shop, $plan)
    {
        $this->shop = $shop;
        $this->plan = $plan;
    }

    public function build()
    {
        return $this->subject('Plan Activated 🎉')
            ->view('emails.plan-activated')
            ->with([
                'shop' => $this->shop,
                'plan' => $this->plan
            ]);
    }
}