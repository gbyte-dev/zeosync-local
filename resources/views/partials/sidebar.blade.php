@php
$currentShop = $activeShop ?? request('shop') ?? session('active_shop');
$params = $currentShop ? ['shop' => $currentShop] : [];
$orderSource = request('source');
$isOrdersSectionOpen = request()->is('orders') || in_array($orderSource, ['shopify', 'amazon'], true);
$shopifyOrdersUrl = url('/orders?') . http_build_query(array_filter([
'shop' => $currentShop,
'source' => 'shopify',
]));
$amazonOrdersUrl = url('/orders?') . http_build_query(array_filter([
'shop' => $currentShop,
'source' => 'amazon',
]));
@endphp

<style>
    .sidebar__logo {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px 16px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, .08);
        margin-bottom: 12px;
    }

    .sidebar__logo-img {
        width: 100%;
        max-width: 180px;
        height: auto;
        object-fit: contain;
        display: block;
    }
</style>
<s-app-nav>
    <s-link href="{{ route('dashboard').($currentShop ? '?shop='.$currentShop : '') }}" rel="home">Dashboard</s-link>
    <s-link href="{{ route('amazon.connect').($currentShop ? '?shop='.$currentShop : '') }}">Account Connected</s-link>
    <s-link href="{{ route('shopify.inventory.index').($currentShop ? '?shop='.$currentShop : '') }}">Inventory</s-link>
    <s-link href="{{ $amazonOrdersUrl }}">Amazon Orders</s-link>
    <s-link href="{{ route('shopify.logs').($currentShop ? '?shop='.$currentShop : '') }}">Sync History</s-link>
    <s-link href="{{ route('user.notification').($currentShop ? '?shop='.$currentShop : '') }}">Notifications</s-link>
    <s-link href="{{ route('settings.index').($currentShop ? '?shop='.$currentShop : '') }}">Settings</s-link>
    <s-link href="{{ route('shopify.plans').($currentShop ? '?shop='.$currentShop : '') }}">Plans / Billing</s-link>
    <s-link href="{{ route('shopify.support').($currentShop ? '?shop='.$currentShop : '') }}">Support</s-link>
</s-app-nav>

<nav id="sidebar" class="sidebar">
    <div class="sidebar__logo">
        <a href="{{ route('dashboard').($currentShop ? '?shop='.$currentShop : '') }}">
            <img src="{{ asset('logo/logoamazonysync.png') }}"
                alt="Logo"
                class="sidebar__logo-img">
        </a>
    </div>

    <!-- Dashboard -->
    <div class="sidebar__item">
        <a href="{{ route('dashboard').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2 sidebar__icon"></i>
            <span class="sidebar__text">Dashboard</span>
        </a>
    </div>

    <!-- Account Connected -->
    <div class="sidebar__item">
        <a href="{{ route('amazon.connect').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('amazon.connect') ? 'active' : '' }}">
            <i class="bi bi-link-45deg sidebar__icon"></i>
            <span class="sidebar__text">Account Connected</span>
        </a>
    </div>

    <!-- Products (Dropdown) -->
    <div class="sidebar__item">
        <a class="sidebar__link {{ (request()->routeIs('shopify.products*')|| request()->routeIs('user.product*')) ? 'active' : '' }}"
            data-bs-toggle="collapse"
            href="#productsMenu"
            role="button"
            aria-expanded="{{ request()->routeIs('shopify.products*') ? 'true' : 'false' }}"
            aria-controls="productsMenu">
            <i class="bi bi-box-seam sidebar__icon"></i>
            <span class="sidebar__text">Products</span>
            <i class="bi bi-chevron-down sidebar__chevron"></i>
        </a>

        <div class="collapse {{ request()->routeIs('shopify.products*') || request()->routeIs('user.product*') ? 'show' : '' }}"
            id="productsMenu">
            <div class="sidebar__submenu">
                <a href="{{ route('shopify.products').($currentShop ? '?shop='.$currentShop : '') }}"
                    class="sidebar__sublink {{ request()->routeIs('shopify.products') ? 'active' : '' }}">
                    <i class="bi bi-plus-circle sidebar__subicon"></i>
                    <span class="sidebar__text">Shopify Products</span>
                </a>
                <a href="{{ route('user.product.showProducts').($currentShop ? '?shop='.$currentShop : '') }}"
                    class="sidebar__sublink {{ request()->routeIs('user.product*') ? 'active' : '' }}">
                    <i class="bi bi-eye sidebar__subicon"></i>
                    <span class="sidebar__text">Amazon Products</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Image Upload -->
    <div class="sidebar__item">
        <a href="{{ route('shopify.imgupload').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('shopify.imgupload') ? 'active' : '' }}">
            <i class="bi bi-cloud-arrow-up sidebar__icon"></i>
            <span class="sidebar__text">Image Upload</span>
        </a>
    </div>

    <!-- Orders (Dropdown) -->
    <div class="sidebar__item">
        <a class="sidebar__link {{ request()->routeIs('orders*') ? 'active' : '' }}"
            data-bs-toggle="collapse"
            href="#ordersMenu"
            role="button"
            aria-expanded="{{ request()->routeIs('orders*') ? 'true' : 'false' }}"
            aria-controls="ordersMenu">
            <i class="bi bi-box-seam sidebar__icon"></i>
            <span class="sidebar__text">Orders</span>
            <i class="bi bi-chevron-down sidebar__chevron"></i>
        </a>

        <div class="collapse {{ request()->routeIs('orders*') ? 'show' : '' }}" id="ordersMenu">
            <div class="sidebar__submenu">
                <a href="{{ $shopifyOrdersUrl }}"
                    class="sidebar__sublink {{ request()->is('orders') && $orderSource === 'shopify' ? 'active' : '' }}">
                    <i class="bi bi-bag-check sidebar__subicon"></i>
                    <span class="sidebar__text">Shopify Orders</span>
                </a>
                <a href="{{ $amazonOrdersUrl }}"
                    class="sidebar__sublink {{ request()->is('orders') && $orderSource === 'amazon' ? 'active' : '' }}">
                    <i class="bi bi-cart-check sidebar__subicon"></i>
                    <span class="sidebar__text">Amazon Orders</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Inventory -->
    <div class="sidebar__item">
        <a href="{{ route('shopify.inventory.index').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('shopify.inventory.index') ? 'active' : '' }}">
            <i class="bi bi-boxes sidebar__icon"></i>
            <span class="sidebar__text">Inventory</span>
        </a>
    </div>

    <!-- AI Chat -->
    <!-- <div class="sidebar__item">
        <a href="{{ route('shopify.ai.chat').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('shopify.ai.chat') ? 'active' : '' }}">
            <i class="bi bi-chat-left-text sidebar__icon"></i>
            <span class="sidebar__text">AI Chat</span>
        </a>
    </div> -->

    <!-- Sync History -->
    <div class="sidebar__item">
        <a href="{{ route('shopify.logs').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('shopify.logs') ? 'active' : '' }}">
            <i class="bi bi-clock-history sidebar__icon"></i>
            <span class="sidebar__text">Sync History</span>
        </a>
    </div>

    <!-- Notifications -->
    <div class="sidebar__item">
        <a href="{{ route('user.notification').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('user.notification') ? 'active' : '' }}">
            <i class="bi bi-bell sidebar__icon"></i>
            <span class="sidebar__text">Notifications</span>
            @if(isset($userUnreadCount) && $userUnreadCount > 0)
            <span class="sidebar__badge">{{ $userUnreadCount > 9 ? '9+' : $userUnreadCount }}</span>
            @endif
        </a>
    </div>

    <!-- Settings -->
    <div class="sidebar__item">
        <a href="{{ route('settings.index').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('settings.index') ? 'active' : '' }}">
            <i class="bi bi-gear sidebar__icon"></i>
            <span class="sidebar__text">Settings</span>
        </a>
    </div>

    <!-- Plans / Billing -->
    <div class="sidebar__item">
        <a href="{{ route('shopify.plans').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('shopify.plans') ? 'active' : '' }}">
            <i class="bi bi-credit-card sidebar__icon"></i>
            <span class="sidebar__text">Plans / Billing</span>
        </a>
    </div>

    <!-- Support Docs -->
    <div class="sidebar__item">
        <a href="{{ route('shopify.support').($currentShop ? '?shop='.$currentShop : '') }}"
            class="sidebar__link {{ request()->routeIs('shopify.support') ? 'active' : '' }}">
            <i class="bi bi-life-preserver sidebar__icon"></i>
            <span class="sidebar__text">Support Docs</span>
        </a>
    </div>

    <div class="sidebar__item" id="logout-button">
        <a href="{{ route('site.logout') }}"
            class="sidebar__link {{ request()->routeIs('site.logout') ? 'active' : '' }}">
            <i class="bi bi-box-arrow-left sidebar__icon"></i>
            <span class="sidebar__text">Logout</span>
        </a>
    </div>

</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.self !== window.top) {
            document.getElementById('logout-button').style.display = 'none';
        }
    });
</script>