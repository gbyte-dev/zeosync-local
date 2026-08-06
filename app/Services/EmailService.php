<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\EmailDataHelper;

class EmailService
{
    public function sendDynamicEmail($template, $customer = null): void
    {
        try {

            //   STEP 1: Build variables
            $data = EmailDataHelper::build([
                'customer' => $customer,
            ]);

            //   STEP 2: Replace variables
            $subject = $this->replaceVariables($template->subject ?? '', $data);
            $body = $this->replaceVariables($template->body ?? '', $data);
            //   FINAL BODY WITH HEADER + VARIABLES
            $finalBody = $this->replaceVariables(
                $this->wrapWithLayout($body),
                $data
            );

            //   STEP 3: Get email
            $email = $customer?->email;

            if (!$email) {
                Log::warning('EmailService: No email found');
                return;
            }

            //   STEP 4: Send email (HTML)
            Mail::send([], [], function ($message) use ($email, $subject, $finalBody) {
                $message->to($email)
                    ->subject($subject)
                    ->html($finalBody);
            });

            //  SUCCESS LOG
            Log::info('Email sent successfully', [
                'email' => $email,
                'subject' => $subject
            ]);
        } catch (\Exception $e) {

            //  ERROR LOG
            Log::error('Email sending failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendDynamicEmailTo($template, array $variables, string $email): void
    {
        try {

            // Existing helper variables
            $data = EmailDataHelper::build($variables);

            // Merge custom variables
            $data = array_merge($data, $variables);

            // Replace variables
            $subject = $this->replaceVariables($template->subject ?? '', $data);

            $body = $this->replaceVariables($template->body ?? '', $data);

            $finalBody = $this->replaceVariables(
                $this->wrapWithLayout($body),
                $data
            );

            Mail::send([], [], function ($message) use ($email, $subject, $finalBody) {

                $message->to($email)
                    ->subject($subject)
                    ->html($finalBody);
            });
        } catch (\Exception $e) {

            \Log::error('Dynamic email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Replace {{variables}}
    private function replaceVariables(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', trim($value), $content);
        }

        return $content;
    }


    private function wrapWithLayout($content)
    {
        return "
            <div style='margin:auto; padding:0; background:#f4f6f8; font-family:Helvetica, Arial, sans-serif;max-width:800px;'>

                <!-- HEADER -->
                <div style='background:linear-gradient(90deg,#2563eb,#1e40af); padding:18px 20px; text-align:center; color:#fff;'>
                    <h1 style='margin:0; font-size:18px; font-weight:700;' >" . config('app.name', env('APP_NAME', 'AmazonSync')) . "</h1>
                </div>

                <!-- CARD -->
                <div style=' margin:24px auto; background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 6px 18px rgba(2,6,23,0.08);'>
                    
                    <!-- BODY -->
                    <div style='padding:24px; color:#0f172a; font-size:15px; line-height:1.6;'>
                        " . $content . "
                    </div>

                </div>

                <!-- FOOTER -->
                <div style='text-align:center; padding:18px; color:#94a3b8; font-size:12px;'>

                    <p style='margin:0 0 8px;'>Need help? Contact us at</p>
                    <p style='margin:0; font-weight:600; color:#2563eb;'>{support_email}</p>

                    <p style='margin-top:10px;'>
                        © " . date('Y') . " " . config('app.name', env('APP_NAME', 'AmazonSync')) . ". All rights reserved.
                    </p>

                </div>

            </div> ";
    }
}
