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

        if (!Schema::hasTable('notification_settings')) {
            Schema::create('notification_settings', function (Blueprint $table) {
                $table->id();
                $table->string('notification_key')->unique();
                $table->boolean('email_enabled')->default(true);
                $table->boolean('database_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_notification_settings')) {
            Schema::create('user_notification_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shop_id')->nullable();
                $table->string('notification_key')->nullable();
                $table->boolean('email_enabled')->default(true);
                $table->boolean('database_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('shops')) {
            Schema::create('shops', function (Blueprint $table) {
                $table->id();
                $table->string('shop')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mail_templates')) {
            Schema::create('mail_templates', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('subject')->nullable();
                $table->text('body')->nullable();
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

    public function test_name_symbol_validation()
    {
        $validNames = [
            'Brijesh Verma',
            'test-store-ojmx7jqk',
            'John-Doe',
            "O'Connor",
            'René François',
            'Store-123',
            'Alpha Store 99',
        ];

        $nameRule = ['name' => ['required', 'string', 'max:100', 'regex:/^(?=.*[\pL\pN])[\pL\pN\s\'-]+$/u']];

        foreach ($validNames as $name) {
            $validator = \Illuminate\Support\Facades\Validator::make(['name' => $name], $nameRule);
            $this->assertTrue($validator->passes(), "Expected valid name '{$name}' to pass validation.");
        }

        $invalidNames = [
            '<script>alert(1)</script>',
            'John@Doe',
            'Name#123',
            'User$Admin',
            'Test_Name',
            '---',
            "'''",
            '   ',
            "Robert'); DROP TABLE Students;--",
        ];

        foreach ($invalidNames as $name) {
            $validator = \Illuminate\Support\Facades\Validator::make(['name' => $name], $nameRule);
            $this->assertTrue($validator->fails(), "Expected invalid name '{$name}' to fail validation.");
        }
    }

    public function test_email_symbol_validation()
    {
        $validEmails = [
            'brijeshverma7814@gmail.com',
            'john@example.com',
            'john.doe@example.com',
            'john+test@example.co.in',
            'john+support@example.co.in',
            'john.doe+test@example.co.in',
            'user_name@sub.domain.org',
        ];

        foreach ($validEmails as $email) {
            $validator = \Illuminate\Support\Facades\Validator::make(
                ['email' => $email],
                ['email' => ['required', 'string', 'email:rfc', 'max:255']]
            );
            $this->assertTrue($validator->passes(), "Expected valid email '{$email}' to pass validation.");
        }

        $invalidEmails = [
            'johnexample.com',
            'john@',
            '@example.com',
            'plainaddress',
            '#@%^%#$@#$@#.com',
        ];

        foreach ($invalidEmails as $email) {
            $validator = \Illuminate\Support\Facades\Validator::make(
                ['email' => $email],
                ['email' => ['required', 'string', 'email:rfc', 'max:255']]
            );
            $this->assertTrue($validator->fails(), "Expected invalid email '{$email}' to fail validation.");
        }
    }

    public function test_subject_symbol_validation()
    {
        $validSubjects = [
            'need custom plan',
            'Need higher product and sync limits',
            'General Enquiry',
            'Question about pricing - plan_1, ok?',
            'Urgent Help Needed! Please check.',
            'Order 12345 update - question.',
            'Inquiry - Is this available? Yes!',
            'Need help with website!',
        ];

        $subjectRule = ['subject' => ['required', 'string', 'max:255', 'regex:/^(?=.*[\pL\pN])[\pL\pN\s.,!?_-]+$/u']];

        // Valid subject rules: letters, numbers, spaces, . , ? ! _ -
        foreach ($validSubjects as $subject) {
            $validator = \Illuminate\Support\Facades\Validator::make(['subject' => $subject], $subjectRule);
            $this->assertTrue($validator->passes(), "Expected valid subject '{$subject}' to pass validation.");
        }

        $invalidSubjects = [
            '<script>alert("XSS")</script>',
            'Subject with <HTML> tags',
            'Subject with "quotes"',
            'Subject with {brackets}',
            'Subject with @ symbol',
            'Subject with $ dollar',
            'Subject with # hash',
            'Subject with / slash',
            'Subject with = equals',
            '---',
            '...',
            '   ',
        ];

        foreach ($invalidSubjects as $subject) {
            $validator = \Illuminate\Support\Facades\Validator::make(['subject' => $subject], $subjectRule);
            $this->assertTrue($validator->fails(), "Expected invalid subject '{$subject}' to fail validation.");
        }
    }

    public function test_message_anti_xss_validation()
    {
        $validMessages = [
            'i need more sync and product limit',
            'I need more sync and product limit.',
            'I am facing an issue with C++ and PHP.',
            'Hello team, we need help connecting our Shopify store to Amazon.',
            "Line 1\nLine 2\nCheck: https://example.com/api?param=1&other=2",
            'Special readable punctuation: (test), [sample], #123, $500, 20% discount!',
        ];

        $rule = [
            'message' => [
                'required',
                'string',
                'max:5000',
                'regex:/[\pL\pN]/u',
                'not_regex:/<[^>]*>|<script|javascript\s*:|vbscript\s*:|on\w+\s*=|on\w+\/|<\?php|<\?|<\%|\?>|\%>/i',
            ],
        ];

        foreach ($validMessages as $msg) {
            $validator = \Illuminate\Support\Facades\Validator::make(['message' => $msg], $rule);
            $this->assertTrue($validator->passes(), "Expected message '{$msg}' to pass validation.");
        }

        $dangerousMessages = [
            '<SCRIPT>alert(1)</SCRIPT>',
            '<ScRiPt>alert(1)</ScRiPt>',
            '<img src=x onerror=alert(1)>',
            '<svg/onload=alert(1)>',
            '<a href="javascript:alert(1)">',
            '<div onclick="alert(1)">',
            '<?php echo "test"; ?>',
            '<?= "test" ?>',
            '<% malicious code %>',
            'javascript:alert(1)',
            'javascript : alert(1)',
            '<svg onload=alert("XSS")>',
            '"><script>alert("XSS")</script>',
            '<script src="https://evil.com/xss.js"></script>',
            'onerror = alert(1)',
            'onclick=alert(1)',
            '<? echo "test"; ?>',
            '<% unclosed tag',
            '???!!!***',
            '$$$###@@@',
        ];

        foreach ($dangerousMessages as $msg) {
            $validator = \Illuminate\Support\Facades\Validator::make(['message' => $msg], $rule);
            $this->assertTrue($validator->fails(), "Expected dangerous message '{$msg}' to fail validation.");
        }
    }

    public function test_enterprise_plan_contact_form_submits_successfully_with_hyphenated_store_name()
    {
        $payload = [
            'name' => 'test-store-ojmx7jqk',
            'email' => 'brijeshverma7814@gmail.com',
            'subject' => 'need custom plan',
            'message' => 'i need more sync and product limit',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ];

        $response = $this->withHeaders([
            'Origin' => 'https://zeosync.app',
        ])->post(route('contact.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('contact_inquiries', [
            'name' => 'test-store-ojmx7jqk',
            'email' => 'brijeshverma7814@gmail.com',
            'subject' => 'need custom plan',
            'message' => 'i need more sync and product limit',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);
    }

    public function test_server_side_contact_controller_rejects_malicious_http_payloads()
    {
        $dangerousPayloads = [
            [
                'name' => '<script>alert(1)</script>',
                'email' => 'test@example.com',
                'subject' => 'Help',
                'message' => 'Valid message text',
            ],
            [
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'subject' => '<img src=x onerror=alert(1)>',
                'message' => 'Valid message text',
            ],
            [
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'subject' => 'Need Assistance',
                'message' => '<svg/onload=alert(1)>',
            ],
            [
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'subject' => 'Need Assistance',
                'message' => '<?= "test" ?>',
            ],
            [
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'subject' => 'Need Assistance',
                'message' => '<% malicious code %>',
            ],
        ];

        foreach ($dangerousPayloads as $payload) {
            $response = $this->withHeaders([
                'Origin' => 'https://zeosync.app',
            ])->post(route('contact.store'), $payload);

            $response->assertSessionHasErrors();
        }
    }
}
