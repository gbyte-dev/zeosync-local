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

            $htmlData = $this->escapeHtmlVariables($data);

            //   STEP 2: Replace variables
            $subject = str_replace(["\r", "\n"], '', $this->replaceVariables($template->subject ?? '', $data));
            $body = $this->replaceVariables($template->body ?? '', $htmlData);
            //   FINAL BODY WITH HEADER + VARIABLES
            $finalBody = $this->replaceVariables(
                $this->wrapWithLayout($body),
                $htmlData
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

            $htmlData = $this->escapeHtmlVariables($data);

            // Replace variables
            $subject = str_replace(["\r", "\n"], '', $this->replaceVariables($template->subject ?? '', $data));

            $body = $this->replaceVariables($template->body ?? '', $htmlData);

            $finalBody = $this->replaceVariables(
                $this->wrapWithLayout($body),
                $htmlData
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
            if (is_scalar($value) || $value === null) {
                $content = str_replace('{' . $key . '}', trim((string) $value), $content);
            }
        }

        return $content;
    }

    private function escapeHtmlVariables(array $data): array
    {
        $escaped = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                if ($key === 'message') {
                    $escaped[$key] = nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
                } else {
                    $escaped[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            } else {
                $escaped[$key] = $value;
            }
        }

        return $escaped;
    }


    private function wrapWithLayout($content)
    {
        return "
            <div style='margin:auto; padding:0; background:#f4f6f8; font-family:Helvetica, Arial, sans-serif;max-width:800px;'>

                <!-- HEADER -->
                <div style='background:linear-gradient(90deg,#2563eb,#1e40af); padding:18px 20px; text-align:center; color:#fff;'>
                    <h1 style='margin:0; font-size:18px; font-weight:700;' >" . config('app.name', env('APP_NAME', 'Zeosync')) . "</h1>
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
                        © " . date('Y') . " " . config('app.name', env('APP_NAME', 'Zeosync')) . ". All rights reserved.
                    </p>

                </div>

            </div> ";
    }
}
