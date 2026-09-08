<?php

use App\Models\ContactInquiry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('contact_enquiry_ip:' . hash('sha256', '198.51.100.1'));
    RateLimiter::clear('contact_enquiry_ip:' . hash('sha256', '198.51.100.2'));
    RateLimiter::clear('contact_enquiry_ip:' . hash('sha256', '127.0.0.1'));

    Mail::fake();

    if (\Illuminate\Support\Facades\Schema::hasTable('contact_inquiries')) {
        ContactInquiry::truncate();
    }
});

it('Test 1: First valid enquiry from IP A succeeds and creates ContactInquiry', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'Inquiry 1',
            'message'      => 'Hello from Alice',
            'enquiry_type' => 'general_enquiry',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(ContactInquiry::where('email', 'alice@example.com')->count())->toBe(1);
});

it('Test 2: Second enquiry from same IP A within 24h is rejected', function () {
    // 1st request from IP A
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'Inquiry 1',
            'message'      => 'Hello from Alice',
            'enquiry_type' => 'general_enquiry',
        ]);

    expect(ContactInquiry::count())->toBe(1);

    // 2nd request from same IP A within 24h
    $response2 = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice 2',
            'email'        => 'alice2@example.com',
            'subject'      => 'Inquiry 2',
            'message'      => 'Spam or second attempt',
            'enquiry_type' => 'general_enquiry',
        ]);

    $response2->assertRedirect();
    $response2->assertSessionHasErrors('email');

    // Must NOT create another database record
    expect(ContactInquiry::count())->toBe(1);
    expect(ContactInquiry::where('email', 'alice2@example.com')->exists())->toBeFalse();
});

it('Test 3: Rejected request does NOT create another ContactInquiry or send emails', function () {
    // 1st request
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'First Message',
            'message'      => 'Legitimate message',
            'enquiry_type' => 'general_enquiry',
        ]);

    Mail::assertSent(\App\Mail\ContactThankYouMail::class, 1);

    // 2nd request from same IP
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice Again',
            'email'        => 'alice-again@example.com',
            'subject'      => 'Second Message',
            'message'      => 'Abusive repeat message',
            'enquiry_type' => 'general_enquiry',
        ]);

    $response->assertSessionHasErrors('email');

    // Email count remains 1 (no new emails sent for blocked request)
    Mail::assertSent(\App\Mail\ContactThankYouMail::class, 1);
    expect(ContactInquiry::count())->toBe(1);
});

it('Test 4: Different IP B can submit independently within same 24 hours', function () {
    // 1st request from IP A
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'Alice Msg',
            'message'      => 'Alice inquiry',
            'enquiry_type' => 'general_enquiry',
        ]);

    expect(ContactInquiry::count())->toBe(1);

    // Request from IP B (should be allowed)
    $responseB = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.2'])
        ->post('/contact', [
            'name'         => 'Bob',
            'email'        => 'bob@example.com',
            'subject'      => 'Bob Msg',
            'message'      => 'Bob inquiry from another IP',
            'enquiry_type' => 'general_enquiry',
        ]);

    $responseB->assertRedirect();
    $responseB->assertSessionHas('success');

    expect(ContactInquiry::count())->toBe(2);
    expect(ContactInquiry::where('email', 'bob@example.com')->exists())->toBeTrue();
});

it('Test 5: Same IP A can submit again after 24h', function () {
    Carbon::setTestNow(now());

    // 1st request at T=0
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice',
            'email'        => 'alice@example.com',
            'subject'      => 'Day 1 Inquiry',
            'message'      => 'Day 1 text',
            'enquiry_type' => 'general_enquiry',
        ]);

    expect(ContactInquiry::count())->toBe(1);

    // Advance time by 24 hours and 1 second
    Carbon::setTestNow(now()->addSeconds(86401));

    // Request after 24h from same IP A
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->post('/contact', [
            'name'         => 'Alice Day 2',
            'email'        => 'alice.day2@example.com',
            'subject'      => 'Day 2 Inquiry',
            'message'      => 'Day 2 text after 24 hours',
            'enquiry_type' => 'general_enquiry',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(ContactInquiry::count())->toBe(2);
    expect(ContactInquiry::where('email', 'alice.day2@example.com')->exists())->toBeTrue();

    Carbon::setTestNow(); // Reset test time
});

it('Test 6: Invalid form submission does NOT consume the 24-hour allowance', function () {
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
            'subject'      => 'Valid Subject',
            'message'      => 'Valid message body',
            'enquiry_type' => 'general_enquiry',
        ]);

    $goodResponse->assertRedirect();
    $goodResponse->assertSessionHas('success');

    expect(ContactInquiry::count())->toBe(1);
});
