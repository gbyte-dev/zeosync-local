@extends('layouts.guest')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <h5 class="mb-4">Privacy Policy</h5>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Shopify Data We Process</h6>
                    <p class="card-text text-muted">
                        When you install and authorize {{getAppName()}} and connect a Shopify store, we may receive information from your Shopify store through Shopify's APIs, including:
                    </p>
                    <ul class="text-muted">
                        <li>Store name, domain, and account metadata</li>
                        <li>Product data such as titles, variants, SKUs, pricing, and inventory levels</li>
                        <li>Order data including order numbers, statuses, line items, totals, taxes, and fulfillment details</li>
                        <li>Customer information accessed only where necessary for supported order-sync functionality, including customer name, email address, phone number, billing address, shipping address, and order history</li>
                        <li>API credentials, access tokens, and related integration data required to authenticate and maintain the connection</li>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">How We Use Your Information</h6>
                    <table class="table table-sm table-bordered text-muted mb-0">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Data</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Store information</td>
                                <td>Identify and connect the merchant's store and maintain the integration</td>
                            </tr>
                            <tr>
                                <td>Product data</td>
                                <td>Synchronize products, variants, pricing, and listings between Shopify and Amazon</td>
                            </tr>
                            <tr>
                                <td>Inventory</td>
                                <td>Sync stock levels and inventory updates between connected platforms</td>
                            </tr>
                            <tr>
                                <td>Order data</td>
                                <td>Process and synchronize orders, fulfillment information, and order status updates</td>
                            </tr>
                            <tr>
                                <td>Customer data</td>
                                <td>Only where required for supported functionality such as order processing, customer contact details, and syncing related order information</td>
                            </tr>
                            <tr>
                                <td>API credentials and tokens</td>
                                <td>Authenticate with connected platforms and maintain the service connection</td>
                            </tr>
                            <tr>
                                <td>Usage data</td>
                                <td>Monitor service performance, troubleshoot issues, and improve functionality</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Shopify Authorization</h6>
                    <p class="card-text text-muted">
                        {{getAppName()}} uses Shopify's APIs to provide its functionality. When you install the app, Shopify asks you to authorize the permissions required for the service. We only request and use the permissions necessary to connect the store and provide the synchronization features described in our service. By connecting a Shopify store, you confirm that you have the authority to authorize {{getAppName()}} to access the store and the data needed to provide the service.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Data Retention</h6>
                    <p class="card-text text-muted">
                        We retain information for as long as necessary to provide the Service, maintain the integration, process orders, support billing and account management, and comply with applicable legal, accounting, and security obligations. When a merchant disconnects, uninstalls, or cancels the service, we delete or anonymize data that is no longer required for the Service, subject to applicable law, contractual obligations, and temporary backup retention periods. Backup copies may be retained for a limited period for data recovery and security purposes.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Shopify Privacy Requests and Compliance</h6>
                    <p class="card-text text-muted">
                        We respond to applicable privacy and data requests under Shopify's requirements and applicable law. This includes handling Shopify compliance webhooks such as <strong>customers/data_request</strong>, <strong>customers/redact</strong>, and <strong>shop/redact</strong> where applicable to the merchant's account and data.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Third-Party Service Providers</h6>
                    <p class="card-text text-muted">
                        {{getAppName()}} works with third-party providers required to operate and support the Service. These may include Shopify, Amazon, Stripe, hosting and database providers, and email or support service providers. We do not sell personal data. We may share data only as necessary to provide the Service, process payments, maintain infrastructure, or comply with legal obligations.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">International Data Transfers</h6>
                    <p class="card-text text-muted">
                        Data may be processed and stored on servers or systems located in jurisdictions outside the merchant's home country, including where our hosting, database, and support providers are located. We use reasonable administrative, technical, and organizational safeguards, including access controls, secure hosting, encryption in transit, and restricted internal access, to help protect data while it is processed internationally.
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title">Data Security</h6>
                    <p class="card-text text-muted">
                        We implement reasonable security measures to protect personal and business data, including encrypted communication where supported, secure API connections, limited access controls, monitoring, and routine review of technical and operational safeguards. We do not sell personal data and only share data with trusted service providers when necessary to operate the Service.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Your Rights</h6>
                    <p class="card-text text-muted">
                        You may request access to, correction of, or deletion of your data, subject to applicable law and the retention requirements described above. You can also revoke API access and disconnect your store from the Service through the app or account settings. For privacy-related inquiries, please contact us at privacy@zeosync.app.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection