@extends('layouts.zeosync')

@section('title', 'Contact — Zeosync')
@section('meta_description', 'Bring your channels, your catalog, and the parts that slow you down. Get in touch with the Zeosync team.')
@section('preview-banner', 'Website preview  . Proposed launch plans & illustrative product experience')

@section('content')

    <section class="pagehead wrap">
        <p class="eyebrow">FIND YOUR FIT</p>
        <h1>Let&rsquo;s talk about<br><em>your store.</em></h1>
        <p class="lead">Bring your channels, your catalog, and the parts that slow you down. Start with a clear picture of what you need.</p>
    </section>

    <section class="wrap">
        <div class="cards" style="grid-template-columns: repeat(2, 1fr);">
            <article class="card" \>
                
                <h3>Email support</h3>
                <p>For general inquiries and support.</p>
                <a class="textlink" href="mailto:support@zeosync.app">support@zeosync.app</a>
            </article>
            <article class="card">
                <h3>Phone support</h3>
                <p>Mon&ndash;Fri, 9am&ndash;6pm EST.</p>
                <a class="textlink" href="tel:+1-555-123-4567">+1 (555) 123-4567</a>
            </article>
        </div>
    </section>

    <section class="wrap contactgrid">
        <div>
            <h2>A useful conversation<br>starts here.</h2>
            <p>Tell us about your marketplace, order volume, and the part of the workflow that&rsquo;s slowing you down. We&rsquo;ll get back to you as soon as possible.</p>

            <div class="contactnote">
                <b>Keep it simple. Keep it safe.</b>
                <p>Your marketplace, order volume, and main challenge are enough. Do not include passwords, API keys, customer records, or private order details.</p>
            </div>

        </div>

        <form action="{{ route('contact.store') }}" method="POST" id="contact-form">
            @csrf

            <h3>Your store, at a glance.</h3>
            @if (session('success'))
                <div class="contactnote success alert alert-success">
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="formrow">
                <div>
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" placeholder="John Doe" maxlength="200" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="john@example.com" maxlength="200" value="{{ old('email') }}" required>
                </div>
            </div>

            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" placeholder="How can we help?" maxlength="200" value="{{ old('subject') }}" required>

            <label for="store">Shopify store URL <span>(optional)</span></label>
            <input type="text" id="store" name="store" placeholder="your-store.myshopify.com" maxlength="200" value="{{ old('store') }}">

            <div class="formrow">
                <div>
                    <label for="marketplace">Amazon marketplace</label>
                    <select id="marketplace" name="marketplace">
                        @foreach (['United States', 'United Kingdom', 'Canada', 'European Union', 'Other / multiple regions'] as $option)
                            <option value="{{ $option }}" @selected(old('marketplace') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="plan">Plan to discuss</label>
                    <select id="plan" name="plan">
                        @foreach (['Not sure yet', 'Starter', 'Growth', 'Scale', 'Custom'] as $option)
                            <option value="{{ $option }}" @selected(old('plan') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label for="volume">Monthly Amazon orders</label>
            <select id="volume" name="volume">
                @foreach (['Under 100', '100–1,000', '1,001–5,000', 'More than 5,000', 'Not selling on Amazon yet'] as $option)
                    <option value="{{ $option }}" @selected(old('volume') === $option)>{{ $option }}</option>
                @endforeach
            </select>

            <label for="message">What would you like to improve?</label>
            <textarea id="message" name="message" rows="5" maxlength="2000" placeholder="For example: less time checking stock and importing Amazon orders." required>{{ old('message') }}</textarea>

            <button class="btn" type="submit">Send message <span aria-hidden="true">&#8599;</span></button>
            <p class="micro">Your message goes directly to the Zeosync team. No account connection, no payment required.</p>
        </form>
    </section>

    <section class="section wrap">
        <div class="sectionintro">
            <p class="eyebrow">KNOW WHAT YOU&rsquo;RE ASKING</p>
            <h2>Frequently asked questions.</h2>
        </div>

        <div class="faqs">
            <details open>
                <summary>How do I integrate my Amazon and Shopify stores?</summary>
                <p>Sign up for an account, connect your Amazon and Shopify stores using our secure integration wizard, and start syncing your products, inventory, and orders within minutes.</p>
            </details>
            <details>
                <summary>What platforms do you support?</summary>
                <p>Currently, we support Amazon Seller Central and Shopify. We&rsquo;re constantly working on adding more platforms to provide a comprehensive multi-channel selling solution.</p>
            </details>
            <details>
                <summary>Is my data secure?</summary>
                <p>We use industry-standard encryption and security measures to protect your data. All API connections are secure, and we never share your information with third parties.</p>
            </details>
            <details>
                <summary>How long does setup usually take?</summary>
                <p>Most stores are connected and syncing within a single session. The exact time depends on your catalog size and how many SKU mappings need to be reviewed before you go live.</p>
            </details>
            <details>
                <summary>Will this affect my live Amazon or Shopify listings?</summary>
                <p>Nothing changes on either channel until you confirm a sync. You&rsquo;ll be able to review products, inventory, and mappings before anything goes live.</p>
            </details>
            <details>
                <summary>Do you support multiple Amazon seller accounts?</summary>
                <p>Support for multiple accounts depends on your plan. Let us know your setup in the form above and we&rsquo;ll confirm what&rsquo;s included before you commit to anything.</p>
            </details>
            <details>
                <summary>What happens after I submit this form?</summary>
                <p>A member of the team will review your setup details and follow up by email, usually within one business day, to talk through next steps.</p>
            </details>
            <details>
                <summary>Can I ask questions before connecting my store?</summary>
                <p>Yes. Use the form above to share your marketplace, order volume, and main challenge, and we&rsquo;ll walk through fit and pricing before anything is connected.</p>
            </details>
        </div>
    </section>

@endsection
