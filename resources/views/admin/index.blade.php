@extends('admin.layout.app')

@section('title', 'Dashboard')

@section('content')

@php
    $stats = is_array($stats ?? null) ? $stats : [];
    $summaryCards = $summaryCards ?? [
        ['label' => 'Active Shops', 'value' => $stats['shops'] ?? 0, 'icon' => '🏪', 'color' => 'primary', 'hint' => 'No active shops'],
        ['label' => 'Categories', 'value' => $stats['categories'] ?? 0, 'icon' => '🗂️', 'color' => 'success', 'hint' => 'Updated from your catalog'],
        ['label' => 'Total Syncs', 'value' => $stats['syncs'] ?? 0, 'icon' => '🔄', 'color' => 'warning', 'hint' => 'Growing steadily'],
        ['label' => 'Live Jobs', 'value' => 0, 'icon' => '⚡', 'color' => 'info', 'hint' => 'Based on current activity'],
    ];

    $weeklyBars = $weeklyBars ?? [68, 82, 74, 91, 88, 96];
    $healthItems = $healthItems ?? [
        ['name' => 'Inventory Sync', 'value' => 92, 'color' => 'success'],
        ['name' => 'Product Mapping', 'value' => 84, 'color' => 'primary'],
        ['name' => 'Webhook Delivery', 'value' => 76, 'color' => 'warning'],
    ];

    $todaySummary = $todaySummary ?? [
        ['label' => 'Completed', 'value' => 0, 'color' => 'text-success'],
        ['label' => 'In Progress', 'value' => 0, 'color' => 'text-primary'],
        ['label' => 'Failed', 'value' => 0, 'color' => 'text-warning'],
        ['label' => 'Avg. Runtime', 'value' => '0.0 min', 'color' => 'text-muted'],
    ];
@endphp

<style>
    .dashboard-card {
        border: 0;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 12px 35px rgba(15, 23, 42, .08);
    }

    .dashboard-card .card-body {
        padding: 1.25rem 1.3rem;
    }

    .dashboard-icon {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
</style>

<div class="container-fluid px-0">
    <div class="row g-4 mb-4">
        @foreach ($summaryCards as $card)
            <div class="col-xl-3 col-md-6">
                <div class="dashboard-card card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted small mb-1">{{ $card['label'] }}</p>
                                <h3 class="fw-bold mb-0">{{ $card['value'] }}</h3>
                            </div>
                            <div class="dashboard-icon bg-{{ $card['color'] }} bg-opacity-10 text-{{ $card['color'] }}">
                                {{ $card['icon'] }}
                            </div>
                        </div>
                        <div class="mt-3 small text-muted">{{ $card['hint'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="dashboard-card card h-100">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-semibold">Sync Performance</h5>
                        <p class="text-muted small mb-0">Weekly activity overview</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">+14% this week</span>
                </div>
                <div class="card-body">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <svg viewBox="0 0 260 140" class="w-100" style="height: 180px;">
                                <path d="M10 110 C35 90, 55 70, 80 78 S125 95, 150 70 S205 40, 250 30" fill="none" stroke="#2563eb" stroke-width="4" stroke-linecap="round" />
                                <path d="M10 110 C35 90, 55 70, 80 78 S125 95, 150 70 S205 40, 250 30 L250 120 L10 120 Z" fill="rgba(37,99,235,0.12)" />
                            </svg>
                        </div>
                        <div class="col-lg-5">
                            <div class="d-flex align-items-end gap-2" style="height: 120px;">
                                @foreach ($weeklyBars as $bar)
                                    <div class="flex-fill rounded-top" style="height: {{ $bar }}%; background: linear-gradient(180deg, #93c5fd, #2563eb);"></div>
                                @endforeach
                            </div>
                            <div class="d-flex justify-content-between text-muted small mt-2">
                                <span>Mon</span>
                                <span>Tue</span>
                                <span>Wed</span>
                                <span>Thu</span>
                                <span>Fri</span>
                                <span>Sat</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="dashboard-card card h-100">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0 fw-semibold">System Health</h5>
                    <p class="text-muted small mb-0">Current sync quality</p>
                </div>
                <div class="card-body">
                    @foreach ($healthItems as $item)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small fw-semibold">{{ $item['name'] }}</span>
                                <span class="small text-muted">{{ $item['value'] }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-{{ $item['color'] }}" style="width: {{ $item['value'] }}%"></div>
                            </div>
                        </div>
                    @endforeach

                    <div class="alert alert-light border mt-3 mb-0">
                        <div class="fw-semibold">Next recommended action</div>
                        <div class="small text-muted">Review 3 products with low mapping confidence.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="dashboard-card card">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0 fw-semibold">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @forelse ($recentActivities as $activity)
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">{{ $activity['title'] }}</div>
                                    <div class="small text-muted">{{ $activity['message'] }} • {{ $activity['time'] }}</div>
                                </div>
                                <span class="badge {{ $activity['badge_class'] }}">{{ $activity['badge_text'] }}</span>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-muted">No recent activity yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="dashboard-card card h-100">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0 fw-semibold">Today’s Summary</h5>
                </div>
                <div class="card-body">
                    @foreach ($todaySummary as $item)
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ $item['label'] }}</span>
                            <span class="fw-semibold {{ $item['color'] }}">{{ $item['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@endsection