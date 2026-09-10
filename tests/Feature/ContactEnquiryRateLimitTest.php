<?php

use App\Models\ContactInquiry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    RateLimiter::clear('contact_enquiry_ip:' . hash('sha256', '198.51.100.1'));
    RateLimiter::clear('contact_enquiry_ip:' . hash('sha256', '198.51.100.2'));
    RateLimiter::clear('contact_enquiry_ip:' . hash('sha256', '127.0.0.1'));

    Mail::fake();

    $this->withHeaders(['Origin' => 'https://zeosync.app']);

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
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
    } else {
        ContactInquiry::truncate();
    }

    if (!Schema::hasTable('admins')) {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->default('admin');
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

    if (!Schema::hasTable('admin_notifications')) {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('user_notifications')) {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
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
});

it('Test 1: First valid Custom Plan enquiry from IP A succeeds and creates ContactInquiry', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'Custom Plan Request',
            'message'      => 'Need higher limit for enterprise store',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(ContactInquiry::where('email', 'alice@example.com')->count())->toBe(1);
    expect(ContactInquiry::where('enquiry_type', 'enterprise_plan_enquiry')->exists())->toBeTrue();
});

it('Test 2: Second enquiry from same IP A within 12h is rejected', function () {
    // 1st request from IP A
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'Inquiry 1',
            'message'      => 'Hello from Alice',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    expect(ContactInquiry::count())->toBe(1);

    // 2nd request from same IP A within 12h (e.g. after 1 hour)
    Carbon::setTestNow(now()->addHours(1));

    $response2 = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice 2',
            'email'        => 'alice2@example.com',
            'subject'      => 'Inquiry 2',
            'message'      => 'Spam or second attempt',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $response2->assertRedirect();
    $response2->assertSessionHasErrors('email');

    // Must NOT create another database record
    expect(ContactInquiry::count())->toBe(1);
    expect(ContactInquiry::where('email', 'alice2@example.com')->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('Test 3: Rejected request does NOT create another ContactInquiry or send emails', function () {
    // 1st request
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'First Message',
            'message'      => 'Legitimate message',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    Mail::assertSent(\App\Mail\ContactThankYouMail::class, 1);

    // 2nd request from same IP
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice Again',
            'email'        => 'alice-again@example.com',
            'subject'      => 'Second Message',
            'message'      => 'Abusive repeat message',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $response->assertSessionHasErrors('email');

    // Email count remains 1 (no new emails sent for blocked request)
    Mail::assertSent(\App\Mail\ContactThankYouMail::class, 1);
    expect(ContactInquiry::count())->toBe(1);
});

it('Test 4: Different IP B can submit independently within same 12 hours', function () {
    // 1st request from IP A
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'Alice Msg',
            'message'      => 'Alice inquiry',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    expect(ContactInquiry::count())->toBe(1);

    // Request from IP B (should be allowed)
    $responseB = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])
        ->post('/contact', [
            'name'         => 'Bob',
            'email'        => 'bob@example.com',
            'subject'      => 'Bob Msg',
            'message'      => 'Bob inquiry from another IP',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $responseB->assertRedirect();
    $responseB->assertSessionHas('success');

    expect(ContactInquiry::count())->toBe(2);
    expect(ContactInquiry::where('email', 'bob@example.com')->exists())->toBeTrue();
});

it('Test 5: Same IP A can submit again after 12h (43,200 seconds)', function () {
    Carbon::setTestNow(now());

    // 1st request at T=0
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'Initial Custom Plan Request',
            'message'      => 'Initial text',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    expect(ContactInquiry::count())->toBe(1);

    // Advance time by 12 hours and 1 second (43,201 seconds)
    Carbon::setTestNow(now()->addSeconds(43201));

    // Request after 12h from same IP A
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice 12h Later',
            'email'        => 'alice.later@example.com',
            'subject'      => 'Follow-up Custom Plan Request',
            'message'      => 'Follow-up text after 12 hours',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(ContactInquiry::count())->toBe(2);
    expect(ContactInquiry::where('email', 'alice.later@example.com')->exists())->toBeTrue();

    Carbon::setTestNow(); // Reset test time
});

it('Test 6: Invalid form submission does NOT consume the 12-hour allowance', function () {
    // Malformed request (missing required name and subject)
    $badResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'email'   => 'invalid-email',
            'message' => 'short',
        ]);

    $badResponse->assertSessionHasErrors(['name', 'email', 'subject']);
    expect(ContactInquiry::count())->toBe(0);

    // Subsequent valid request from same IP must succeed
    $goodResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice Corrected',
            'email'        => 'alice@example.com',
            'subject'      => 'Valid Custom Plan Subject',
            'message'      => 'Valid message body',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $goodResponse->assertRedirect();
    $goodResponse->assertSessionHas('success');

    expect(ContactInquiry::count())->toBe(1);
});

it('Test 7: Simultaneous/locked request from the same IP is rejected by atomic lock', function () {
    $ip = '198.51.100.1';
    $lockKey = 'contact_enquiry_lock:' . hash('sha256', $ip);

    // Acquire lock to simulate an in-flight concurrent request
    $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);
    $lock->get();

    // Concurrent request arrives while lock is held
    $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/contact', [
            'name'         => 'Alice Concurrent',
            'email'        => 'alice.concurrent@example.com',
            'subject'      => 'Concurrent Message',
            'message'      => 'Trying concurrent submission',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
    expect(ContactInquiry::count())->toBe(0);

    // Release lock
    $lock->release();
});

it('Test 8: Unavailable or null client IP is rejected immediately without creating record or sending emails', function () {
    // Simulate a request where REMOTE_ADDR is empty/null
    $response = $this->withServerVariables(['REMOTE_ADDR' => ''])
        ->post('/contact', [
            'name'         => 'No IP User',
            'email'        => 'noip@example.com',
            'subject'      => 'No IP Subject',
            'message'      => 'Testing unavailable IP address',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    // Middleware returns 403 or controller returns redirect with error
    if ($response->status() === 403) {
        $response->assertStatus(403);
    } else {
        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    // Verify no DB record, no emails, and no rate-limit state created
    expect(ContactInquiry::count())->toBe(0);
    Mail::assertNothingSent();
});

it('Test 9: Blocked request before 12h does not reset or extend the 12-hour expiration timer', function () {
    Carbon::setTestNow(now());

    // 1st request at T=0
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'T0 Request',
            'message'      => 'T0 text',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    expect(ContactInquiry::count())->toBe(1);

    // Attempt at T=6 hours (blocked)
    Carbon::setTestNow(now()->addHours(6));

    $blockedResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'T6 Request',
            'message'      => 'T6 text',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $blockedResponse->assertSessionHasErrors('email');
    expect(ContactInquiry::count())->toBe(1);

    // Advance time to T=12 hours and 1 second from original T=0 (6 hours and 1 second after blocked attempt)
    Carbon::setTestNow(now()->addHours(6)->addSeconds(1));

    // This request must succeed because the original 12h window has expired (blocked request did not extend timer)
    $allowedResponse = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice Allowed',
            'email'        => 'alice.allowed@example.com',
            'subject'      => 'T12 Request',
            'message'      => 'T12 text',
            'enquiry_type' => 'enterprise_plan_enquiry',
        ]);

    $allowedResponse->assertRedirect();
    $allowedResponse->assertSessionHas('success');

    expect(ContactInquiry::count())->toBe(2);

    Carbon::setTestNow();
});
