@extends('layouts.guest')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="text-center mb-5">
                <h5 class="mb-3">Pricing Plans</h5>
                <p class="text-muted">Simple, transparent pricing to fit your business needs. All plans include core synchronization features.</p>
            </div>

            <div class="row g-4">
                @forelse ($plans as $plan)
                    @php
                        $month_price = $plan->prices['EVERY_30_DAYS'] ?? 0;
                        $yearly_price = $plan->prices['ANNUAL'] ?? 0;
                        $isHighlighted = $plan->is_highlighted;
                        $isEnterprise = $plan->is_enterprise;
                        $isTrial = $plan->is_trial;
                    @endphp

                    <div class="col-md-4">
                        <div class="card h-100 {{ $isHighlighted ? 'border-primary' : '' }}">
                            @if ($isHighlighted)
                                <div class="card-header bg-primary text-white text-center">
                                    <strong>{{ $plan->badge ?: 'Most Popular' }}</strong>
                                </div>
                            @endif
                            <div class="card-body">
                                <h6 class="card-title">
                                    {{ $plan->name }}
                                    @if ($plan->badge && !$isHighlighted)
                                        <span class="badge bg-primary ms-1">{{ $plan->badge }}</span>
                                    @endif
                                </h6>

                                @if ($isEnterprise)
                                    <h3 class="text-primary mb-3">Custom</h3>
                                @elseif ($isTrial)
                                    <h3 class="text-success mb-3">Free</h3>
                                @else
                                    <h3 class="text-primary mb-3">
                                        @if ($month_price != 0)
                                            ${{ number_format((float) $month_price, 0) }}<span class="text-muted fs-6">/month</span>
                                        @elseif ($yearly_price != 0)
                                            ${{ number_format((float) $yearly_price, 0) }}<span class="text-muted fs-6">/year</span>
                                        @endif
                                    </h3>
                                    @if ($month_price != 0 && $yearly_price != 0)
                                        <p class="text-muted small mb-3">or ${{ number_format((float) $yearly_price, 0) }}/year</p>
                                    @endif
                                @endif

                                <p class="card-text text-muted">{{ $plan->description }}</p>
                                <hr>
                                <ul class="list-unstyled text-muted">
                                    @forelse (($plan->features ?? []) as $feature)
                                        <li class="mb-2">✓ {{ $feature }}</li>
                                    @empty
                                        <li class="mb-2 text-muted">Contact us for feature details</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <a href="{{ route('contact') }}" class="btn {{ $isHighlighted ? 'btn-primary' : 'btn-outline-primary' }} w-100">{{ $plan->contact_button_text ?: 'Contact Sales' }}</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            No plans are currently available. Please check back soon.
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="card mt-5">
                <div class="card-body">
                    <h6 class="card-title text-center mb-3">All Plans Include</h6>
                    <div class="row text-center">
                        <div class="col-md-3 mb-3 mb-md-0">
                            <p class="text-muted mb-0">Real-time Sync</p>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <p class="text-muted mb-0">Secure API Connections</p>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <p class="text-muted mb-0">99.9% Uptime SLA</p>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted mb-0">Cancel Anytime</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <p class="text-muted">
                    Questions about pricing? <a href="{{ route('contact') }}">Contact our sales team</a> for a personalized quote.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection