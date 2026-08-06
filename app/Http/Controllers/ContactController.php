<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ContactThankYouMail;
use App\Models\ContactInquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
}
