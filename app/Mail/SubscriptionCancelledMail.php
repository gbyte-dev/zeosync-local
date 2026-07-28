<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class SubscriptionCancelledMail extends Mailable
{
    public $subscription;

    public function __construct($subscription)
    {
        $this->subscription = $subscription;
    }

    public function build()
    {
        return $this->subject('Subscription Cancelled')
            ->view('emails.subscription_cancelled');
    }
}