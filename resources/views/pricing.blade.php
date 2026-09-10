@extends('layouts.zeosync')

@section('title', 'Pricing — Zeosync')
@section('meta_description', 'Simple, transparent pricing to fit your business needs. All plans include core synchronization features.')
@section('preview-banner', 'Website preview &bull; Proposed launch plans & illustrative product experience')

@section('content')

    <section class="pagehead wrap">
        <p class="eyebrow">SIMPLE, TRANSPARENT PRICING</p>
        <h1>Your sales are yours.<br><em>Keep it that way.</em></h1>
        <p class="lead">Simple, transparent pricing to fit your business needs. All plans include core synchronization features.</p>
    </section>

    <section class="wrap">
        <p class="notice">USD &middot; Monthly and annual billing available &middot; Cancel anytime.</p>

        <div class="pricinggrid">
            @forelse ($plans as $plan)
                @php
                    $month_price = $plan->prices['EVERY_30_DAYS'] ?? 0;
                    $yearly_price = $plan->prices['ANNUAL'] ?? 0;
                    $isHighlighted = $plan->is_highlighted;
                    $isEnterprise = $plan->is_enterprise;
                    $isTrial = $plan->is_trial;
                @endphp

                <article class="pricecard {{ $isHighlighted ? 'featured' : '' }}">
                    <div class="planlabel">
                        {{ $plan->name }}
                        <span>{{ $isHighlighted ? ($plan->badge ?: 'MOST POPULAR') : '' }}</span>
                    </div>

                    @if ($plan->badge && !$isHighlighted)
                        <p class="planlimit"><b>{{ $plan->badge }}</b></p>
                    @endif

                    <p>{{ $plan->description }}</p>

                    <div class="price">
                        @if ($isEnterprise)
                            <strong>Custom</strong>
                        @elseif ($isTrial)
                            <strong>Free</strong>
                        @elseif ($month_price != 0)
                            <strong>${{ number_format((float) $month_price, 0) }}</strong>
                            <span>/ month</span>
                        @elseif ($yearly_price != 0)
                            <strong>${{ number_format((float) $yearly_price, 0) }}</strong>
                            <span>/ year</span>
                        @endif
                    </div>

                    @if (!$isEnterprise && !$isTrial && $month_price != 0 && $yearly_price != 0)
                        <p class="planlimit">or <b>${{ number_format((float) $yearly_price, 0) }}</b> / year</p>
                    @endif

                    @if ($isEnterprise)
                        <a href="{{ route('contact') }}" class="btn {{ $isHighlighted ? '' : 'secondary' }}">
                            {{ $plan->contact_button_text ?: 'Contact Sales' }}<span aria-hidden="true">&#8599;</span>
                        </a>
                    @else
                        <a href="{{ route('contact') }}?plan={{$plan->name}}" class="btn {{ $isHighlighted ? '' : 'secondary' }}">
                            Get Started<span aria-hidden="true">&#8599;</span>
                        </a>
                    @endif

                    <ul class="checklist">
                        @forelse (($plan->features ?? []) as $feature)
                            <li>{{ $feature }}</li>
                        @empty
                            <li>Contact us for feature details</li>
                        @endforelse
                    </ul>
                </article>
            @empty
                <div class="pricingnote">
                    <p>No plans are currently available. Please check back soon.</p>
                </div>
            @endforelse
        </div>

        <div class="pricingnote">
            <b>All plans include</b>
            <p>Real-time sync &middot; Secure API connections &middot; 99.9% uptime SLA &middot; Cancel anytime.</p>
        </div>
    </section>

    <section class="section wrap">
        <div class="sectionintro">
            <p class="eyebrow">STILL DECIDING?</p>
            <h2>Questions about pricing?</h2>
            <p><a href="{{ route('contact') }}">Contact our sales team</a> for a personalized quote.</p>
        </div>
    </section>

@endsection
