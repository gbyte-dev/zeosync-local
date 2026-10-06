@extends('layouts.zeosync')
@push('styles')
<style nonce="{{ $cspNonce }}">
{!! file_get_contents(public_path('css/styles-zeosync.css')) !!}
</style>
@endpush
@section('preview-banner', 'Website preview  . Proposed launch plans & illustrative product experience')
@section('content')
        <section class="pagehead wrap">
            <p class="eyebrow">HOW IT WORKS</p>
            <h1>Connect Shopify<br>and Amazon with <em>one workflow.</em></h1>
            <p class="lead">Connect your Shopify store, link Amazon Seller Central, map your products, and manage your
                marketplace workflow from one place.</p>
        </section>
        <section class="wrap journey">
            <article>
                <div class="journeyno">01</div>
                <div>
                    <p class="eyebrow">CONNECT SHOPIFY</p>
                    <h2>Connect your Shopify store to ZeoSync.</h2>
                    <p>Start by connecting your Shopify store with ZeoSync. Your store becomes the source for the
                        products and variants you want to manage across your marketplace workflow.</p>
                    <div class="outcome"><b>What to confirm</b><span>Your Shopify store is connected and ready for the
                            next step.</span></div>
                </div>
            </article>
            <article>
                <div class="journeyno">02</div>
                <div>
                    <p class="eyebrow">CONNECT AMAZON</p>
                    <h2>Connect your Amazon Seller Central account.</h2>
                    <p>Connect Amazon Seller Central through ZeoSync, then authorize the account so ZeoSync can work
                        with your Amazon catalog, listings, inventory, and supported marketplace data.</p>
                    <div class="outcome"><b>What to confirm</b><span>Your Shopify and Amazon accounts are connected and
                            ready to manage.</span></div>
                </div>
            </article>
            <article>
                <div class="journeyno">03</div>
                <div>
                    <p class="eyebrow">MAP YOUR PRODUCTS</p>
                    <h2>Match Shopify products with Amazon listings.</h2>
                    <p>Map Shopify products and variants to their corresponding Amazon listings. For products that are
                        not already on Amazon, use the ZeoSync workflow to prepare and create the required Amazon
                        product information.</p>
                    <div class="outcome"><b>What to confirm</b><span>Each Shopify product or variant is linked to the
                            correct Amazon listing.</span></div>
                </div>
            </article>
            <article>
                <div class="journeyno">04</div>
                <div>
                    <p class="eyebrow">SYNC & MANAGE</p>
                    <h2>Keep your marketplace workflow under control.</h2>
                    <p>Use the ZeoSync dashboard to manage product mappings, inventory, Amazon connections, plans, and
                        supported synchronization workflows. Review your data and update mappings as your catalog
                        changes.</p>
                    <div class="outcome"><b>What to confirm</b><span>Your Shopify and Amazon operations stay organized
                            from one dashboard.</span></div>
                </div>
            </article>
        </section>
        <section class="section wrap">
            <div class="sectionintro">
                <p class="eyebrow">FROM CATALOG TO MARKETPLACE</p>
                <h2>Everything starts with your product catalog.</h2>
                <p>ZeoSync is built around the connection between your Shopify products and Amazon listings. The
                    workflow keeps product and variant relationships clear before synchronization begins.</p>
            </div>

            <div class="cards">
                <article class="card">
                    <span class="index">01</span>
                    <h3>Shopify as your starting point</h3>
                    <p>Bring your Shopify products and variants into the ZeoSync workflow so you can work from the
                        catalog you already manage.</p>
                </article>

                <article class="card">
                    <span class="index">02</span>
                    <h3>Variant-level mapping</h3>
                    <p>Map the correct Shopify variant to the corresponding Amazon listing or SKU instead of treating
                        every variant as one product.</p>
                </article>

                <article class="card">
                    <span class="index">03</span>
                    <h3>Amazon-ready product data</h3>
                    <p>When a product needs to be created on Amazon, ZeoSync provides the workflow for entering and
                        validating the required product information.</p>
                </article>
            </div>
        </section>

        <section class="darksection">
            <div class="wrap">
                <div class="split">
                    <div>
                        <p class="eyebrow">PRODUCT & INVENTORY WORKFLOW</p>
                        <h2>One place to understand what is happening.</h2>
                        <p class="large">Once products are connected, ZeoSync gives you a central workflow for managing
                            marketplace connections, mappings, inventory and supported synchronization tasks.</p>
                    </div>

                    <div class="textrows">
                        <p>
                            <b>Product mapping</b>
                            <span>Keep the Shopify product, Shopify variant and Amazon listing relationship
                                explicit.</span>
                        </p>
                        <p>
                            <b>Inventory visibility</b>
                            <span>Review Amazon inventory data and the quantity information available through the
                                connected marketplace.</span>
                        </p>
                        <p>
                            <b>Connection status</b>
                            <span>Keep your Amazon Seller Central connection available from the ZeoSync
                                dashboard.</span>
                        </p>
                        <p>
                            <b>Plan controls</b>
                            <span>Use the active ZeoSync plan and its product or sync limits as part of your
                                workflow.</span>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section wrap">
            <div class="sectionintro">
                <p class="eyebrow">WHEN SOMETHING CHANGES</p>
                <h2>Your catalog can change without breaking the workflow.</h2>
                <p>ZeoSync is designed around real catalog changes: new products, new variants, updated mappings and
                    marketplace data that needs to be reviewed.</p>
            </div>

            <div class="steps">
                <article>
                    <span>NEW PRODUCT</span>
                    <h3>Add it to Shopify first.</h3>
                    <p>Use your normal Shopify catalog workflow, then bring the product into ZeoSync when it needs to be
                        managed on Amazon.</p>
                </article>

                <article>
                    <span>NEW AMAZON LISTING</span>
                    <h3>Create or connect the listing.</h3>
                    <p>Map the Shopify product or variant to the appropriate Amazon listing, or use the Amazon product
                        workflow when a new listing is required.</p>
                </article>

                <article>
                    <span>PRODUCT UPDATE</span>
                    <h3>Review the mapping.</h3>
                    <p>When catalog structure changes, verify the affected product or variant mapping before continuing
                        with marketplace operations.</p>
                </article>
            </div>
        </section>

        <section class="lightsection">
            <div class="wrap">
                <div class="sectionintro">
                    <p class="eyebrow">THE ZEOSYNC DASHBOARD</p>
                    <h2>Built around the work you actually need to manage.</h2>
                    <p>The dashboard brings the main ZeoSync areas together so you do not have to manage every
                        marketplace task separately.</p>
                </div>

                <div class="cards">
                    <article class="card">
                        <span class="index">DASHBOARD</span>
                        <h3>See the current state</h3>
                        <p>Use the dashboard as the starting point for your connected store, marketplace workflow and
                            account activity.</p>
                    </article>

                    <article class="card">
                        <span class="index">AMAZON</span>
                        <h3>Manage the connection</h3>
                        <p>Connect Amazon Seller Central and keep the marketplace account available for supported
                            ZeoSync operations.</p>
                    </article>

                    <article class="card">
                        <span class="index">PLANS</span>
                        <h3>Know your limits</h3>
                        <p>Review the active plan and the limits that apply to products and supported synchronization
                            activity.</p>
                    </article>

                    <article class="card">
                        <span class="index">SUPPORT</span>
                        <h3>Get help when needed</h3>
                        <p>Use ZeoSync support resources when you need help understanding the workflow or resolving an
                            issue.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section wrap">
            <div class="sectionintro">
                <p class="eyebrow">A SIMPLE OPERATING MODEL</p>
                <h2>Connect once. Manage continuously.</h2>
                <p>The goal is not to add another complicated system to your business. ZeoSync gives your Shopify and
                    Amazon workflow a clear place to connect, map, review and manage.</p>
            </div>

            <div class="journey">
                <article>
                    <div class="journeyno">01</div>
                    <div>
                        <p class="eyebrow">CONNECT</p>
                        <h2>Bring Shopify and Amazon together.</h2>
                        <p>Connect the store and marketplace accounts required for your workflow.</p>
                    </div>
                </article>

                <article>
                    <div class="journeyno">02</div>
                    <div>
                        <p class="eyebrow">ORGANIZE</p>
                        <h2>Make product relationships clear.</h2>
                        <p>Map products and variants to the right Amazon listings so every marketplace relationship has
                            a clear reference.</p>
                    </div>
                </article>

                <article>
                    <div class="journeyno">03</div>
                    <div>
                        <p class="eyebrow">OPERATE</p>
                        <h2>Manage the workflow from ZeoSync.</h2>
                        <p>Review inventory, mappings, connections and supported synchronization activity from the
                            dashboard.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="closing wrap">
            <div>
                <p class="eyebrow">YOUR ZEOSYNC WORKFLOW</p>
                <h2>Shopify to Amazon.<br><em>One connected workflow.</em></h2>
            </div>
            <div>
                <p>Connect your store, map your products, and manage your Amazon workflow with ZeoSync.</p><a
                    class="btn " href="contact.php">Get started<span aria-hidden="true">↗</span></a>
            </div>
        </section>
@endsection