<div class="modal fade" id="customPlanDetailsModal" tabindex="-1" aria-labelledby="customPlanDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header bg-light">
                <div class="flex-grow-1">
                    <h5 class="modal-title fw-bold mb-0" id="customPlanDetailsModalLabel">
                        {{ $customPlan->name }}
                    </h5>
                    <small class="text-muted">Complete Custom Enterprise Plan Details</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($shop->subscription)
                        <span class="badge bg-{{ $shop->subscription->status === 'active' ? 'success' : ($shop->subscription->status === 'cancelled' ? 'danger' : 'warning') }}">
                            {{ ucfirst($shop->subscription->status === 'active' ? 'Active' : ($shop->subscription->status === 'cancelled' ? 'Cancelled' : 'Pending')) }}
                        </span>
                    @else
                        <span class="badge bg-secondary">Not Assigned</span>
                    @endif
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body bg-light p-4">
                @php
                    $features = !empty($customPlan->features) ? (is_array($customPlan->features) ? $customPlan->features : json_decode($customPlan->features, true)) : [];
                    $prices = is_array($customPlan->prices) ? $customPlan->prices : json_decode($customPlan->prices, true);
                    $stripePriceIds = is_array($customPlan->stripe_price_ids) ? $customPlan->stripe_price_ids : json_decode($customPlan->stripe_price_ids, true);
                @endphp

                <div class="row g-3">
                    <!-- Plan Information -->
                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-white fw-semibold">
                                📋 Plan Information
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-borderless mb-0 small">
                                    <tr>
                                        <th class="w-40 text-muted fw-semibold ps-4">Plan Name</th>
                                        <td class="fw-semibold">{{ $customPlan->name }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-semibold ps-4">Billing</th>
                                        <td>
                                            @if(!empty($prices['EVERY_30_DAYS']))
                                                <span class="badge bg-primary">Monthly</span>
                                            @elseif(!empty($prices['ANNUAL']))
                                                <span class="badge bg-info">Yearly</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-semibold ps-4">Price</th>
                                        <td class="fw-bold text-primary">
                                            @if(!empty($prices['EVERY_30_DAYS']))
                                                ${{ number_format($prices['EVERY_30_DAYS'], 2) }}/Month
                                            @elseif(!empty($prices['ANNUAL']))
                                                ${{ number_format($prices['ANNUAL'], 2) }}/Year
                                            @else
                                                <span class="text-muted fw-normal">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-semibold ps-4">Product Limit</th>
                                        <td><strong>{{ $customPlan->product_limit == 0 ? 'Unlimited' : number_format($customPlan->product_limit) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-semibold ps-4">Sync Limit</th>
                                        <td><strong>{{ $customPlan->sync_limit == 0 ? 'Unlimited' : number_format($customPlan->sync_limit) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-semibold ps-4">Trial</th>
                                        <td>
                                            @if($customPlan->is_trial)
                                                <span class="text-success">Yes</span>
                                                @if($customPlan->trial_days)
                                                    <span class="text-muted">({{ $customPlan->trial_days }} days)</span>
                                                @endif
                                            @else
                                                <span class="text-muted">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-semibold ps-4">Enterprise</th>
                                        <td>
                                            @if($customPlan->is_enterprise)
                                                <i class="bi bi-check-circle-fill text-success"></i> Yes
                                            @else
                                                <span class="text-muted">No</span>
                                            @endif
                                        </td>
                                    </tr>
                           
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Features -->
                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-white fw-semibold">
                                ✨ Plan Features
                            </div>
                            <div class="card-body">
                                @forelse($features as $feature)
                                    <div class="d-flex align-items-center gap-2 p-2 mb-2 bg-white border rounded">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        <span class="small">{{ $feature }}</span>
                                    </div>
                                @empty
                                    <p class="text-muted text-center my-4"><i class="bi bi-inbox"></i> No Features Available</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- AI Features -->
                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-white fw-semibold">
                                🤖 AI Features
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-borderless mb-0 small">
                                    <tr>
                                        <th class="w-40 text-muted fw-semibold ps-4">AI AutoFill</th>
                                        <td>
                                            @if($customPlan->ai_autofill)
                                                <span class="badge bg-success">Enabled</span>
                                            @else
                                                <span class="text-muted">Disabled</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-semibold ps-4">AI Single Field</th>
                                        <td>
                                            @if($customPlan->ai_single_field)
                                                <span class="badge bg-success">Enabled</span>
                                            @else
                                                <span class="text-muted">Disabled</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Stripe Billing -->
                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header bg-white fw-semibold">
                                💳 Stripe Billing
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-borderless mb-0 small">
                                    <tr>
                                        <th class="w-40 text-muted fw-semibold ps-4">Monthly Price ID</th>
                                        <td>
                                            @if(!empty($stripePriceIds['EVERY_30_DAYS']))
                                                <code class="text-primary small">{{ $stripePriceIds['EVERY_30_DAYS'] }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-semibold ps-4">Yearly Price ID</th>
                                        <td>
                                            @if(!empty($stripePriceIds['ANNUAL']))
                                                <code class="text-primary small">{{ $stripePriceIds['ANNUAL'] }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <!-- <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-semibold">
                                📝 Description
                            </div>
                            <div class="card-body">
                                {!! $customPlan->description ?: '<p class="text-muted fst-italic mb-0">No Description</p>' !!}
                            </div>
                        </div>
                    </div> -->
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer bg-white border-top py-3">
                <small class="text-muted">
                    <strong>Created:</strong> {{ optional($customPlan->created_at)->format('d M Y h:i A') }}
                    <span class="mx-2">|</span>
                    <strong>Updated:</strong> {{ optional($customPlan->updated_at)->format('d M Y h:i A') }}
                </small>
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>