<?php

namespace Tests\Feature;

use App\Models\ContactInquiry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ContactInquiryXssProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('admin_settings')) {
            Schema::create('admin_settings', function (Blueprint $table) {
                $table->id();
                $table->string('option_key')->unique();
                $table->text('option_value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('notification_key')->nullable();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contact_inquiries')) {
            Schema::create('contact_inquiries', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('subject');
                $table->text('message');
                $table->string('enquiry_type', 50)->default('general_enquiry');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }

        View::share('errors', new \Illuminate\Support\ViewErrorBag());
        View::share('unreadCount', 0);
        View::share('adminNotifications', collect());
        View::share('adminUnreadCount', 0);
    }

    public function test_contact_inquiry_payloads_are_stored_verbatim_and_rendered_safely()
    {
        $payload = [
            'name' => '<script>alert("XSS")</script>John',
            'email' => 'hacker@example.com',
            'subject' => '<script>document.addEventListener("DOMContentLoaded", function () { alert("Mandatory message."); });</script>',
            'message' => "Hello Team,\n<img src=x onerror=alert('XSS')>\nLine 2",
            'enquiry_type' => 'enterprise_plan_enquiry',
        ];

        $contact = ContactInquiry::create($payload);

        // Verify verbatim storage (no destructive tag stripping)
        $this->assertEquals($payload['subject'], $contact->fresh()->subject);
        $this->assertEquals($payload['message'], $contact->fresh()->message);

        // Verify Admin Detail View renders escaped HTML
        $viewShow = $this->view('admin.contact_inquiries.show', ['contact' => $contact]);
        $viewShow->assertSee('&lt;script&gt;document.addEventListener', false);
        $viewShow->assertDontSee('<script>document.addEventListener', false);
        $viewShow->assertSee('&lt;img src=x onerror=alert(&#039;XSS&#039;)&gt;', false);
        $viewShow->assertDontSee('<img src=x onerror=', false);

        // Verify Admin List View renders escaped HTML
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator([$contact], 1, 20, 1);
        $viewIndex = $this->view('admin.contact_inquiries.index', ['contacts' => $paginator]);
        $viewIndex->assertSee('&lt;script&gt;document.addEventListener', false);
        $viewIndex->assertDontSee('<script>document.addEventListener', false);

        // Verify Email view renders escaped HTML with preserved line breaks
        $viewEmail = $this->view('emails.contact-thank-you', ['contact' => $contact]);
        $viewEmail->assertSee('&lt;img src=x onerror=alert(&#039;XSS&#039;)&gt;', false);
        $viewEmail->assertDontSee('<img src=x onerror=', false);
        $viewEmail->assertSee('<br />', false);
    }
}
