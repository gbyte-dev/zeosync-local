@extends('layouts.zeosync')

@section('title', 'Amazon & Shopify, working together — Zeosync')
@section('meta_description', 'Connect Amazon and Shopify with clearer product, inventory, order and returns workflows.')
@section('preview-banner', 'Website preview  . Proposed launch plans & illustrative product experience')

@section('content')
		<section class="hero wrap">
			<div class="herocopy">
				<p class="eyebrow"><span class="line"></span> AMAZON + SHOPIFY, TOGETHER</p>
				<h1>Two sales channels.<br><em>One less headache.</em></h1>
				<p class="lead">Keep products, inventory, and orders moving together. Zeosync brings Amazon and Shopify into a clearer workflow—so you can get back to growing your business.</p>
                <p class="lead">  Connect your store, automate product sync, and manage orders & returns — all in one place.</p>

				<div class="actions"> <form method="GET" action="{{ route('shopify.install') }}" class="d-flex justify-content-center">
                <div class="input-group" style="max-width: 450px;">

                    <input type="text" name="shop" class="form-control" placeholder="your-store-name" value="{{session('active_shop')}}" required>

                    <span class="input-group-text d-none d-flex">.myshopify.com</span>

                    <button class="btn btn-primary px-4">
                        Connect Store
                    </button>
                </div>
            </form>
            <p class="text-muted small mt-2 "> Example: demo-store.myshopify.com</p>

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
		</section>

@endsection
