<?php

namespace App\Http\Controllers;

use App\Services\Email\EmailDataHelper;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function sendDynamicEmail($template, $customer = null)
    {
        try {

            $data = EmailDataHelper::build([
                'customer' => $customer,
            ]);

            $subject = $this->replaceVariables($template['subject'], $data);
            $body = $this->replaceVariables($template['body'], $data);

            $email = $customer->email ?? null;

            if (!$email) {
                \Log::warning('No email found');
                return;
            }

            Mail::send([], [], function ($message) use ($email, $subject, $body) {
                $message->to($email)
                    ->subject($subject)
                    ->html($body);
            });

            \Log::info('Dynamic email sent', ['email' => $email]);

        } catch (\Exception $e) {
            \Log::error('Dynamic email failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function replaceVariables($content, $data)
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }
}