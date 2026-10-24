@extends('layouts.zeosync')

@section('title', 'Amazon & Shopify, working together — Zeosync')
@section('meta_description', 'Connect Amazon and Shopify with clearer product, inventory, order and returns workflows.')
@section('preview-banner', 'Website preview  . Proposed launch plans & illustrative product experience')

@push('styles')
<style nonce="{{ $cspNonce }}">
    /* Hero section with +60px added height (~545px to 550px total height) */
    .hero {
        padding-top: 60px;
        padding-bottom: 62px;
        gap: 50px;
    }
    .hero h1 {
        font-size: clamp(2.45rem, 3.8vw, 3.35rem);
        line-height: 1.15;
        letter-spacing: -0.045em;
    }
    .hero .eyebrow {
        margin-bottom: 14px;
        font-size: 0.75rem;
    }
    .hero .lead {
        margin-top: 16px;
        font-size: 1rem;
        line-height: 1.62;
        max-width: 580px;
    }
    .hero .herocopy p + p {
        margin-top: 12px;
    }
    .hero .actions {
        margin-top: 20px;
    }
    .hero .btn {
        padding: 12px 22px;
        font-size: 0.9rem;
    }
    .hero .micro {
        margin-top: 14px;
        font-size: 0.8rem;
    }
    .hero .workspace {
        box-shadow: 0 18px 50px rgba(17, 28, 37, 0.1);
    }
    .hero .workspacebar {
        padding: 12px 20px;
        font-size: 0.8rem;
    }
    .hero .mini-brand {
        font-size: 0.84rem;
    }
    .hero .demo {
        font-size: 0.72rem;
    }
    .hero .workspacebody {
        padding: 16px 20px;
    }
    .hero .wsheading small {
        font-size: 0.7rem;
        letter-spacing: 0.08em;
    }
    .hero .wsheading h3 {
        margin-top: 4px;
        font-size: 1.16rem;
    }
    .hero .avatar {
        padding: 8px 10px;
        font-size: 0.72rem;
    }
    .hero .channels {
        margin: 15px 0;
        gap: 10px;
    }
    .hero .channels > div {
        padding: 9px 15px;
    }
    .hero .channels b {
        font-size: 1.08rem;
    }
    .hero .channels span:not(.connection) {
        font-size: 0.72rem;
    }
    .hero .connection {
        font-size: 1.35rem;
    }
    .hero .metrics {
        padding: 12px 14px;
        gap: 9px;
        border-radius: 8px;
    }
    .hero .metrics span {
        font-size: 0.78rem;
    }
    .hero .metrics strong {
        font-size: 1.45rem;
        margin: 2px 0;
    }
    .hero .metrics small {
        font-size: 0.7rem;
    }
    .hero .tabletitle {
        margin: 14px 0 8px;
        font-size: 0.78rem;
    }
    .hero .tabletitle span {
        font-size: 0.72rem;
    }
    .hero .activity {
        padding: 9.5px 0;
        gap: 10px;
    }
    .hero .activity b {
        font-size: 0.8rem;
    }
    .hero .activity small {
        font-size: 0.72rem;
    }
    .hero .activityicon {
        padding: 6px 9px;
        font-size: 1rem;
        border-radius: 6px;
    }
    .hero .tag {
        padding: 3px 8px;
        font-size: 0.72rem;
    }
    .hero .workspacefoot {
        padding: 8px 12px;
        font-size: 0.72rem;
    }

    @media (min-width: 1500px) {
        .hero {
            padding-top: 68px;
            padding-bottom: 70px;
        }
    }
    @media (max-width: 1050px) {
        .hero {
            gap: 30px;
        }
        .hero .workspacebody {
            padding: 14px 18px;
        }
    }
    @media (max-width: 780px) {
        .hero {
            padding-top: 42px;
            padding-bottom: 46px;
            gap: 34px;
        }
        .hero h1 {
            font-size: clamp(2.2rem, 7.5vw, 3rem);
        }
    }

    .feature-video-section {
        padding-top: 36px;
        padding-bottom: 64px;
        background: var(--paper);
    }
    .feature-video-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 48px;
        align-items: center;
    }
    .feature-video-copy {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .feature-video-section .eyebrow {
        color: #1d68a4;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        font-size: 0.75rem;
        letter-spacing: 0.14em;
        font-weight: 700;
    }
    .feature-video-section .eyebrow .line {
        background: #1d68a4;
        width: 25px;
        height: 2px;
        display: inline-block;
        vertical-align: middle;
        margin-right: 0;
    }
    .feature-video-copy h2 {
        color: var(--ink);
        font-size: clamp(1.85rem, 2.5vw, 2.5rem);
        line-height: 1.2;
        letter-spacing: -0.04em;
        margin-bottom: 12px;
        font-weight: 600;
    }
    .feature-video-copy .lead {
        color: var(--muted);
        font-size: 0.975rem;
        line-height: 1.65;
        margin-top: 0;
        margin-bottom: 22px;
        max-width: 520px;
    }
    .feature-highlights-2x2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .feature-highlight-card {
        background: #ffffff;
        border: 1px solid var(--line);
        border-radius: 9px;
        padding: 14px 14px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 94px;
        box-shadow: 0 2px 8px rgba(17, 28, 37, 0.03);
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .feature-highlight-card:hover {
        transform: translateY(-2px);
        border-color: #377fa4;
        box-shadow: 0 6px 16px rgba(17, 28, 37, 0.06);
    }
    .feature-highlight-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .feature-highlight-icon {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        background: #eef6fb;
        color: #1d68a4;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .feature-highlight-num {
        font-size: 0.7rem;
        font-weight: 700;
        color: #9eadb5;
        letter-spacing: 0.05em;
    }
    .feature-highlight-title {
        font-family: 'DM Sans', Arial, sans-serif;
        font-size: 0.85rem;
        line-height: 1.35;
        font-weight: 600;
        color: var(--ink);
        margin: 0;
    }
    .feature-video-media {
        display: flex;
        flex-direction: column;
        justify-content: center;
        width: 100%;
    }
    .feature-video-card {
        background: #ffffff;
        border: 1px solid #d2dce0;
        border-radius: 12px;
        box-shadow: 0 16px 40px -12px rgba(17, 28, 37, 0.12), 0 6px 16px -6px rgba(17, 28, 37, 0.05);
        overflow: hidden;
        width: 100%;
        transition: box-shadow 0.3s ease;
    }
    .feature-video-card:hover {
        box-shadow: 0 20px 48px -12px rgba(17, 28, 37, 0.15), 0 8px 20px -6px rgba(17, 28, 37, 0.07);
    }
    .feature-video-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 9px 15px;
        background: #f6f8f8;
        border-bottom: 1px solid var(--line);
        font-size: 0.75rem;
        color: var(--muted);
    }
    .feature-video-dots {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .feature-video-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #d2dce0;
        display: inline-block;
    }
    .feature-video-label {
        font-weight: 600;
        color: var(--ink);
        font-size: 0.725rem;
        letter-spacing: -0.01em;
    }
    .feature-video-badge {
        font-size: 0.65rem;
        font-weight: 600;
        color: #1d68a4;
        background: #eef6fb;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #d6e8f5;
    }
    .feature-video-viewport {
        position: relative;
        width: 100%;
        aspect-ratio: 16 / 9;
        background: #0f172a;
    }
    .feature-video-element {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #0f172a;
    }
    @media (max-width: 1100px) {
        .feature-video-grid {
            gap: 36px;
        }
    }
    @media (max-width: 960px) {
        .feature-video-grid {
            gap: 28px;
        }
        .feature-video-copy h2 {
            font-size: 1.85rem;
        }
        .feature-highlight-card {
            padding: 10px 12px;
            min-height: 84px;
        }
        .feature-highlight-title {
            font-size: 0.8rem;
        }
    }
    @media (max-width: 768px) {
        .feature-video-section {
            padding-top: 24px;
            padding-bottom: 48px;
        }
        .feature-video-grid {
            grid-template-columns: 1fr;
            gap: 28px;
        }
        .feature-video-copy .lead {
            max-width: 100%;
            margin-bottom: 20px;
        }
        .feature-video-media {
            max-width: 600px;
            margin: 0 auto;
        }
    }
    @media (max-width: 520px) {
        .feature-highlights-2x2 {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .feature-highlight-card {
            min-height: auto;
            padding: 12px 14px;
        }
        .feature-video-bar {
            padding: 8px 12px;
        }
    }
</style>
@endpush

@section('content')
		<section class="hero wrap">
			<div class="herocopy">
				<p class="eyebrow"><span class="line"></span> AMAZON + SHOPIFY, TOGETHER</p>
				<h1>Two sales channels.<br><em>One less headache.</em></h1>
				<p class="lead">Keep products, inventory, and orders moving together. Zeosync brings Amazon and Shopify into a clearer workflow—so you can get back to growing your business.</p>
                <p class="lead">  Connect your store, automate product sync, and manage orders & returns — all in one place.</p>

				<div class="actions"> 
					<a href="https://admin.shopify.com/?organization_id=211723053&no_redirect=true&redirect=/oauth/redirect_from_developer_dashboard?client_id%3Dcc04d2b21de39debde846f2b0f70a763" target="_blank" class="btn btn-primary px-4">
						Install on Shopify
					</a>

					{{-- Existing Connect Store UI temporarily disabled
					<form method="GET" action="{{ route('shopify.install') }}" class="d-flex justify-content-center">
						<div class="input-group" style="max-width: 450px;">

							<input type="text" name="shop" class="form-control" placeholder="your-store-name" value="{{session('active_shop')}}" required>

							<span class="input-group-text d-none d-flex">.myshopify.com</span>

							<button class="btn btn-primary px-4" id="connect_to_store">
								Connect Store
							</button>
						</div>
					</form>
					<p class="text-muted small mt-2 "> Example: demo-store.myshopify.com</p>
					--}}
        		</div>
				<p class="micro">Less switching tabs. Less repeating work. More room to sell.</p>
			</div>
			<div class="workspace" aria-label="Illustrative Zeosync dashboard">
				<div class="workspacebar"><span class="mini-brand">z / Workspace</span><span class="demo">Illustrative preview</span></div>
				<div class="workspacebody">
					<div class="wsheading">
						<div><small>YOUR COMMERCE, CONNECTED</small>
							<h3>A clearer day starts here.</h3></div><span class="avatar">AC</span></div>
					<div class="channels">
						<div><b>Amazon</b><span>Marketplace</span></div><span class="connection" aria-hidden="true">⇄</span>
						<div><b>Shopify</b><span>Online store</span></div>
					</div>
					<div class="metrics">
						<div><span>Products</span><strong>1,248</strong><small>Across your catalog</small></div>
						<div><span>Orders</span><strong>86</strong><small>In one view</small></div>
						<div><span>Returns</span><strong>04</strong><small>Ready to review</small></div>
					</div>
					<div class="tabletitle"><b>One view. Fewer loose ends.</b><span>Sample activity</span></div>
					<div class="activity"><span class="activityicon">↻</span>
						<div><b>Inventory updated</b><small>Canvas tote · Natural · 48 available</small></div><span class="tag">Synced</span></div>
					<div class="activity"><span class="activityicon">▤</span>
						<div><b>Amazon order received</b><small>Order #1048 · Ready for fulfillment</small></div><span class="tag">Ready</span></div>
					<div class="activity"><span class="activityicon">↩</span>
						<div><b>Return ready to review</b><small>Order #1032 · Check return details</small></div><span class="tag neutral">Review</span></div>
				</div>
				<div class="workspacefoot">Sample data. Product layout shown for illustration.</div>
			</div>
		</section>
		<section class="section wrap feature-video-section" id="product-tour" aria-labelledby="feature-video-heading">
			<div class="feature-video-grid">
				<div class="feature-video-copy">
					<p class="eyebrow"><span class="line"></span> PRODUCT TOUR</p>
					<h2 id="feature-video-heading">See ZeoSync in Action</h2>
					<p class="lead">Discover how ZeoSync connects Amazon and Shopify, simplifies product synchronization, and helps you manage your commerce workflow from one place.</p>

					<div class="feature-highlights-2x2" aria-label="Feature Highlights">
						<div class="feature-highlight-card">
							<div class="feature-highlight-header">
								<div class="feature-highlight-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M16 3h5v5"></path>
										<path d="M4 20L21 3"></path>
										<path d="M21 16v5h-5"></path>
										<path d="M15 15l6 6"></path>
										<path d="M4 4l5 5"></path>
									</svg>
								</div>
								<span class="feature-highlight-num">01</span>
							</div>
							<h3 class="feature-highlight-title">Seamless Amazon + Shopify Integration</h3>
						</div>

						<div class="feature-highlight-card">
							<div class="feature-highlight-header">
								<div class="feature-highlight-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M21.5 2v6h-6"></path>
										<path d="M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.19"></path>
									</svg>
								</div>
								<span class="feature-highlight-num">02</span>
							</div>
							<h3 class="feature-highlight-title">Automated Product Synchronization</h3>
						</div>

						<div class="feature-highlight-card">
							<div class="feature-highlight-header">
								<div class="feature-highlight-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
										<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
										<line x1="12" y1="22.08" x2="12" y2="12"></line>
									</svg>
								</div>
								<span class="feature-highlight-num">03</span>
							</div>
							<h3 class="feature-highlight-title">Centralized Inventory Management</h3>
						</div>

						<div class="feature-highlight-card">
							<div class="feature-highlight-header">
								<div class="feature-highlight-icon" aria-hidden="true">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M9 11l3 3L22 4"></path>
										<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
									</svg>
								</div>
								<span class="feature-highlight-num">04</span>
							</div>
							<h3 class="feature-highlight-title">Streamlined Order Workflows</h3>
						</div>
					</div>
				</div>

				<div class="feature-video-media">
					<div class="feature-video-card">
						<div class="feature-video-bar">
							<div class="feature-video-dots" aria-hidden="true">
								<span class="feature-video-dot"></span>
								<span class="feature-video-dot"></span>
								<span class="feature-video-dot"></span>
							</div>
							<span class="feature-video-label">ZeoSync &middot; Amazon + Shopify Walkthrough</span>
							<span class="feature-video-badge">1080p HD</span>
						</div>
						<div class="feature-video-viewport">
							<video
								id="zeosync-tour-video"
								class="feature-video-element"
								controls
								preload="none"
								playsinline
								width="1280"
								height="720"
								@if (file_exists(public_path('video/zeosync-feature-overview-poster.webp')))
									poster="{{ asset('video/zeosync-feature-overview-poster.webp') }}"
								@endif
							>
								<source
									src="{{ asset('video/zeosync-feature-overview.mp4') }}"
									type="video/mp4"
								>
								Your browser does not support HTML video.
							</video>
						</div>
					</div>
				</div>
			</div>
		</section>
		<section class="strip">
			<div class="wrap"><span>BUILT AROUND YOUR DAY</span><b>Product sync</b><b>Inventory alignment</b><b>Order management</b></div>
		</section>
		<section class="section wrap">
			<div class="sectionintro">
				<p class="eyebrow">THE WORK BETWEEN THE SALES</p>
				<h2>Your channels grew.<br>Your workload shouldn’t.</h2>
				<p>Every product change, new order, and return creates another detail to manage. Bring the essentials together before busy becomes overwhelming.</p>

			</div>
			<div class="cards">
				<article class="card"><span class="index">01</span>
					<h3>Keep your catalog connected.</h3>
					<p>Coordinate product information across Amazon and Shopify, with fewer repetitive updates between channels.</p>
				</article>
				<article class="card"><span class="index">02</span>
					<h3>Get a clearer order picture.</h3>
					<p>Bring Amazon orders into a central workflow so the next step is easier to see and manage.</p>
				</article>
				<article class="card"><span class="index">03</span>
					<h3>Close the loop on returns.</h3>
					<p>Keep return details and refund tracking closer to the rest of your operation.</p>
				</article>
			</div>
		</section>
		<section class="darksection">
			<div class="wrap split">
				<div>
					<p class="eyebrow">FOCUSED BY DESIGN</p>
					<h2>You sell.<br>We help connect<br>the moving parts.</h2></div>
				<div>
					<p class="large">Your store doesn’t need another layer of complexity. It needs a simpler way to keep two important channels working together.</p>
					<div class="textrows">
						<p><b>Start with the essentials.</b><span>Products, inventory, orders, and returns.</span></p>
						<p><b>Choose a plan you can understand.</b><span>Clear proposed monthly pricing. No percentage of sales.</span></p>
						<p><b>Check the fit before connecting.</b><span>Your marketplace, catalog, and fulfillment setup matter.</span></p>
					</div><a class="btn " href="{{ route('about') }}">See how it works<span aria-hidden="true">↗</span></a></div>
			</div>
		</section>
		<section class="section wrap">
			<div class="sectionintro">
				<p class="eyebrow">A STRAIGHTFORWARD START</p>
				<h2>From separate stores<br>to a shared workflow.</h2></div>
				<div class="steps">
					<article><span>01 / CONNECT</span>
						<h3>Start with your store.</h3>
						<p>Review your Shopify setup and Amazon selling requirements.</p>
					</article>
					<article><span>02 / ALIGN</span>
						<h3>Get the details right.</h3>
						<p>Confirm products, SKU mappings, and the information you want to sync.</p>
					</article>
					<article><span>03 / MANAGE</span>
						<h3>Make room for growth.</h3>
						<p>Manage orders and follow through on returns with a clearer view.</p>
					</article>
				</div>
			</div>
		</section>

@endsection