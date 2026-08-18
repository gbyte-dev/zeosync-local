@extends('layouts.guest')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <h5 class="mb-4">Terms & Conditions</h5>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Acceptance of Terms</h6>
                    <p class="card-text text-muted">
                        By accessing or using {{getAppName()}}'s services, you agree to be bound by these Terms & Conditions. 
                        If you do not agree to these terms, please do not use our services. We may update these terms from time to time, 
                        and your continued use of the service after changes become effective constitutes your acceptance of those changes.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Shopify Authorization</h6>
                    <p class="card-text text-muted">
                        {{getAppName()}} connects with Shopify through Shopify's APIs and requires merchants to authorize the permissions necessary 
                        to provide the service. By connecting a Shopify store, you confirm that you have the authority to authorize {{getAppName()}} 
                        to access the store, related data, and account information needed to perform synchronization, inventory, order, and product-related 
                        services on your behalf.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Service Description</h6>
                    <p class="card-text text-muted">
                        {{getAppName()}} provides a synchronization platform that integrates Amazon and Shopify stores. Our services include 
                        product listing synchronization, inventory management, order processing, and related features. We strive to maintain 
                        high service availability, but we do not guarantee uninterrupted access to our platform or the availability of any third-party 
                        integrations at all times.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Third-Party Services</h6>
                    <p class="card-text text-muted">
                        {{getAppName()}} integrates with third-party platforms including Shopify and Amazon. Your use of those platforms remains 
                        subject to their respective terms, policies, and requirements. {{getAppName()}} is not responsible for interruptions, API 
                        changes, outages, service limitations, or policy changes made by third-party platforms. We do not control those services 
                        and cannot guarantee their continued availability or compatibility.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Subscription and Billing</h6>
                    <p class="card-text text-muted">
                        If {{getAppName()}} offers paid plans, the applicable subscription plan, billing frequency, fees, taxes, and renewal terms 
                        will be disclosed before purchase. Charges may be billed on a recurring monthly, annual, or other agreed schedule, and 
                        billing generally occurs at the start of each billing period unless otherwise stated in the plan details. If applicable, 
                        fees are processed through Shopify's billing system in accordance with Shopify's requirements.
                    </p>
                    <ul class="text-muted">
                        <li>Subscription plans, pricing, and billing frequency will be clearly stated before activation.</li>
                        <li>Charges occur automatically at the start of each subscription period unless a different schedule is agreed.</li>
                        <li>You may cancel your subscription in accordance with the applicable plan terms and billing policy.</li>
                        <li>Upgrades and downgrades may be applied immediately or at the next billing cycle, as stated in the plan details.</li>
                        <li>Refunds are subject to the subscription plan and applicable refund policy. We do not guarantee refunds for partially used periods unless required by law or stated in writing.</li>
                        <li>If your subscription expires or is not renewed, access to paid features may be suspended or terminated in accordance with the plan terms.</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Cancellation and Termination</h6>
                    <p class="card-text text-muted">
                        You may cancel your subscription at any time according to the plan terms and the cancellation procedure available in the service. 
                        If a merchant uninstalls {{getAppName()}} or disconnects their Shopify store, access to the service may end immediately, and any 
                        active subscription will be handled according to the applicable billing and cancellation terms. {{getAppName()}} may suspend or terminate 
                        access if the account is inactive, if payment is not received, or if the user violates these Terms. Upon termination, we may disable 
                        access to the service, stop future processing, and retain or delete information in accordance with applicable law and our Privacy Policy.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">User Responsibilities</h6>
                    <p class="card-text text-muted">
                        Users are responsible for maintaining the confidentiality of their account credentials and API keys. You agree to:
                    </p>
                    <ul class="text-muted">
                        <li>Provide accurate and complete information when using our services</li>
                        <li>Maintain the security of your account and promptly notify us of any unauthorized access</li>
                        <li>Comply with all applicable laws and regulations</li>
                        <li>Not use our services for any unlawful or prohibited activities</li>
                        <li>Respect intellectual property rights</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Privacy and Data Protection</h6>
                    <p class="card-text text-muted">
                        Your use of {{getAppName()}} is also subject to our <a href="{{ route('privacy') }}">Privacy Policy</a>, which explains how we collect, 
                        use, store, retain, and delete information processed through the Service. By using the service, you acknowledge that your data may be 
                        processed in connection with Shopify and Amazon integrations as necessary to provide the service.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Intellectual Property</h6>
                    <p class="card-text text-muted">
                        {{getAppName()}} retains ownership of its software, website, branding, logo, user interface, documentation, and other intellectual 
                        property used in connection with the Service. Merchants retain ownership of their own business data, product information, Shopify store 
                        content, Amazon data, and other information they provide or generate through their accounts. You may not copy, reproduce, distribute, 
                        or otherwise exploit our proprietary materials without our prior written consent.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Changes to Terms</h6>
                    <p class="card-text text-muted">
                        We may revise these Terms from time to time. Material changes will be communicated to you through the Service, by email, or by 
                        other reasonable notice. Changes become effective on the date stated in the notice or, if no date is stated, within a reasonable 
                        period after notice is provided. If you do not agree to a change, you may stop using the Service before the effective date. Your 
                        continued use of the Service after the effective date indicates your agreement to the updated Terms.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Limitation of Liability</h6>
                    <p class="card-text text-muted">
                        {{getAppName()}} shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your 
                        use or inability to use the service. We are not responsible for any data loss, business interruption, or lost profits arising from 
                        the use of our platform, including interruptions caused by third-party services, API changes, or outages outside our control.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Governing Law</h6>
                    <p class="card-text text-muted">
                        These Terms are governed by the laws of the United States, without regard to conflict-of-laws principles. Any dispute arising under 
                        these Terms shall be subject to the exclusive jurisdiction of the courts located in California, United States.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Contact Information</h6>
                    <p class="card-text text-muted">
                        For questions or concerns regarding these Terms & Conditions, please contact us at legal@zeosync.app. We will respond to your inquiry 
                        within a reasonable timeframe.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection