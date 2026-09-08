<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ContactThankYouMail;
use App\Models\ContactInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Admin;
use App\Models\MailTemplate;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\UserNotificationService;
use App\Models\Shop;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'subject'       => 'required|string|max:255',
            'message'       => 'required|string|max:2000',
            'enquiry_type'  => 'nullable|string|max:50',
        ]);

        $data['enquiry_type'] = $request->input(
            'enquiry_type',
            'general_enquiry'
        );

        $ip = $request->ip();

        if (empty($ip)) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'email' => 'Your network address could not be verified. Please try again later.',
                ]);
        }

        $ipHash = hash('sha256', $ip);
        $rateLimitKey = 'contact_enquiry_ip:' . $ipHash;
        $lockKey = 'contact_enquiry_lock:' . $ipHash;

        $lock = Cache::lock($lockKey, 10);

        try {
            if (! $lock->get()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'email' => 'You have already submitted an enquiry recently. Please try again later.',
                    ]);
            }

            if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'email' => 'You have already submitted an enquiry recently. Please try again later.',
                    ]);
            }

            RateLimiter::hit($rateLimitKey, 86400);

            $contact = ContactInquiry::create($data);
        try {

            $admin = Admin::where('role', 'admin')->first();

            if ($admin) {

                $template = MailTemplate::where(
                    'slug',
                    'admin-contact-enquiry'
                )->first();

                if ($template) {

                    app(EmailService::class)->sendDynamicEmailTo(

                        $template,

                        [
                            'name'          => $contact->name,
                            'email'         => $contact->email,
                            'subject'       => $contact->subject,
                            'message'       => $contact->message,
                            'enquiry_type'  => ucwords(str_replace('_', ' ', $contact->enquiry_type)),
                        ],

                        $admin->email

                    );
                }
            }
        } catch (\Exception $e) {

            logger()->error('Admin contact enquiry email failed', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Mail::to($contact->email)->send(new ContactThankYouMail($contact));
        } catch (\Exception $e) {
            // Keep the submission even if email fails
            logger()->error('Contact thank-you email failed: ' . $e->getMessage());
        }

        NotificationService::send(

            'contact_enquiry',

            $contact->enquiry_type == 'enterprise_plan_enquiry'
                ? 'New Enterprise Plan Enquiry'
                : 'New Contact Enquiry',

            "{$contact->name} submitted a new enquiry."

        );

        $shop = Shop::where('email', $contact->email)->first();

        if ($shop) {

            UserNotificationService::send(

                $shop->id,

                'contact_enquiry',

                $contact->enquiry_type === 'enterprise_plan_enquiry'
                    ? 'Enterprise Plan Enquiry Submitted'
                    : 'Contact Enquiry Submitted',

                'Your enquiry has been submitted successfully. Our team will contact you shortly.'

            );
        }
                return redirect()->back()->with('success', 'Thank you for your message. Our team will connect with you shortly.');
        } finally {
            $lock->release();
        }
    }

    public function adminIndex(Request $request)
    {
        $query = ContactInquiry::query();

        if ($request->filled('enquiry_type')) {
            $query->where('enquiry_type', $request->enquiry_type);
        }

        $contacts = $query->latest()->paginate(20);

        return view('admin.contact_inquiries.index', compact('contacts'));
    }

    public function adminShow(ContactInquiry $contact)
    {
        if (!$contact->is_read) {
            $contact->update(['is_read' => true]);
        }

        return view('admin.contact_inquiries.show', compact('contact'));
    }

    public function adminMarkRead(ContactInquiry $contact)
    {
        // Mark as read (idempotent)
        if (!$contact->is_read) {
            $contact->update(['is_read' => true]);
        }

        return redirect()->back()->with('success', 'Contact request marked as read.');
    }

    public function adminDestroy(ContactInquiry $contact)
    {
        $contact->delete();

        return redirect()->route('admin.contact-requests')->with('success', 'Contact request deleted.');
    }

    public function adminMarkAllRead()
    {
        \App\Models\ContactInquiry::query()->where('is_read', false)->update(['is_read' => true]);

        return redirect()->route('admin.contact-requests')->with('success', 'All contact requests marked as read.');
    }

    public function adminDestroyAll()
    {
        // Consider using soft deletes if preservation is required.
        \App\Models\ContactInquiry::query()->delete();

        return redirect()->route('admin.contact-requests')->with('success', 'All contact requests deleted.');
    }
}
