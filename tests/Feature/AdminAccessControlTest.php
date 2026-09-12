<?php

use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AdminSetting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (!Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admins')) {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('super_admin');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('admin_notifications')) {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('shopify_domain')->nullable();
            $table->text('access_token')->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('amazon_schemas')) {
        Schema::create('amazon_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_marketplace_mappings')) {
        Schema::create('product_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('categories')) {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('category')->nullable();
            $table->string('slug')->nullable();
            $table->string('marketplaceIds')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('status')->default('Active');
            $table->tinyInteger('self_added')->default(0);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('notification_settings')) {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key')->nullable();
            $table->string('name')->nullable();
            $table->boolean('email_enabled')->default(1);
            $table->boolean('in_app_enabled')->default(1);
            $table->boolean('mail_enabled')->default(1);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('plans')) {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('products')) {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('title')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('product_sync_logs')) {
        Schema::create('product_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shopify_orders')) {
        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shop_subscriptions')) {
        Schema::create('shop_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
});

/*
|--------------------------------------------------------------------------
| A. Guest -> Existing Admin Route
|--------------------------------------------------------------------------
*/
it('A1: redirects guest requesting /admin/dashboard to admin login without rendering admin layout', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertStatus(302);
    $response->assertRedirect(route('admin.login'));
    
    // Follow redirect to ensure the landing login page does not contain admin layout/sidebar
    $followResponse = $this->followRedirects($response);
    $followResponse->assertStatus(200);
    $followResponse->assertDontSee('desktop-sidebar', false);
    $followResponse->assertDontSee('sidebar-brand', false);
    $followResponse->assertDontSee('adminUnreadBadge', false);
});

it('A2: redirects guest requesting /admin to admin login', function () {
    $response = $this->get('/admin');

    $response->assertStatus(302);
    $response->assertRedirect(route('admin.login'));
});

it('A3: returns 401 when guest makes a JSON request to an existing admin route', function () {
    $response = $this->getJson('/admin/dashboard');

    $response->assertStatus(401);
    $response->assertDontSee('desktop-sidebar', false);
});

/*
|--------------------------------------------------------------------------
| B. Guest -> Unknown Admin Route
|--------------------------------------------------------------------------
*/
it('B1: redirects guest requesting unknown admin route /admin/non-existing-route to admin login', function () {
    $response = $this->get('/admin/non-existing-route');

    $response->assertStatus(302);
    $response->assertRedirect(route('admin.login'));

    $followResponse = $this->followRedirects($response);
    $followResponse->assertStatus(200);
    $followResponse->assertDontSee('desktop-sidebar', false);
    $followResponse->assertDontSee('admin-layout', false);
    $followResponse->assertDontSee('adminUnreadBadge', false);
});

it('B2: returns 401 when guest makes a JSON request to unknown admin route', function () {
    $response = $this->getJson('/admin/non-existing-route');

    $response->assertStatus(401);
    $response->assertDontSee('desktop-sidebar', false);
});

/*
|--------------------------------------------------------------------------
| C. Authenticated Normal User -> Existing Admin Route
|--------------------------------------------------------------------------
*/
it('C1: rejects logged-in normal user requesting /admin/dashboard with 403 Forbidden without admin layout', function () {
    $user = User::forceCreate([
        'name' => 'Normal Customer',
        'email' => 'customer@example.com',
        'password' => 'secret123',
    ]);

    $response = $this->actingAs($user, 'web')->get('/admin/dashboard');

    $response->assertStatus(403);
    $response->assertDontSee('desktop-sidebar', false);
    $response->assertDontSee('sidebar-brand', false);
    $response->assertDontSee('adminUnreadBadge', false);
    $response->assertDontSee('Admin Dashboard', false);
});

it('C2: rejects shop-authenticated user requesting /admin/shops with 403 Forbidden', function () {
    $shop = Shop::forceCreate([
        'shop' => 'my-store.myshopify.com',
        'is_active' => 1,
    ]);

    $response = $this->withSession([
        'active_shop' => $shop->shop,
        'active_shop_id' => $shop->id,
    ])->get('/admin/shops');

    $response->assertStatus(403);
    $response->assertDontSee('desktop-sidebar', false);
    $response->assertDontSee('admin-layout', false);
});

/*
|--------------------------------------------------------------------------
| D. Authenticated Normal User -> Unknown Admin Route
|--------------------------------------------------------------------------
*/
it('D1: rejects logged-in normal user requesting /admin/non-existing-route with 403 Forbidden without admin 404/layout', function () {
    $user = User::forceCreate([
        'name' => 'Store Owner',
        'email' => 'owner@example.com',
        'password' => 'secret123',
    ]);

    $response = $this->actingAs($user, 'web')->get('/admin/non-existing-route');

    $response->assertStatus(403);
    $response->assertDontSee('desktop-sidebar', false);
    $response->assertDontSee('admin-layout', false);
    $response->assertDontSee('adminUnreadBadge', false);
});

it('D2: rejects shop session user requesting /admin/invalid-sub-path with 403 Forbidden', function () {
    $response = $this->withSession([
        'active_shop' => 'store.myshopify.com',
    ])->get('/admin/nested/unknown/route');

    $response->assertStatus(403);
    $response->assertDontSee('desktop-sidebar', false);
    $response->assertDontSee('admin-layout', false);
});

/*
|--------------------------------------------------------------------------
| E. Authorized Admin -> Existing Admin Route
|--------------------------------------------------------------------------
*/
it('E1: allows authorized admin to access /admin/dashboard with 200 OK and admin layout', function () {
    $admin = Admin::forceCreate([
        'name' => 'Super Administrator',
        'email' => 'superadmin@example.com',
        'password' => 'adminpass123',
        'role' => 'super_admin',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('admin-layout', false);
    $response->assertSee('desktop-sidebar', false);
    $response->assertSee('Dashboard', false);
    $response->assertSee('Super Administrator', false);
});

/*
|--------------------------------------------------------------------------
| F. Authorized Admin -> Unknown Admin Route
|--------------------------------------------------------------------------
*/
it('F1: renders 404 page inside admin layout for authorized admin requesting /admin/non-existing-route', function () {
    $admin = Admin::forceCreate([
        'name' => 'Super Administrator',
        'email' => 'admin2@example.com',
        'password' => 'adminpass123',
        'role' => 'super_admin',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/non-existing-route');

    $response->assertStatus(404);
    $response->assertSee('404', false);
    $response->assertSee('admin-layout', false);
    $response->assertSee('desktop-sidebar', false);
});

/*
|--------------------------------------------------------------------------
| G. Guest -> Admin Login
|--------------------------------------------------------------------------
*/
it('G1: allows guest to view admin login page', function () {
    $response = $this->get('/admin/login');

    $response->assertStatus(200);
    $response->assertSee('Admin Login', false);
    $response->assertDontSee('desktop-sidebar', false);
});

/*
|--------------------------------------------------------------------------
| H. Authorized Admin -> Admin Login (redirects to dashboard)
|--------------------------------------------------------------------------
*/
it('H1: redirects logged-in admin from /admin/login to /admin/dashboard', function () {
    $admin = Admin::forceCreate([
        'name' => 'Active Admin',
        'email' => 'activeadmin@example.com',
        'password' => 'adminpass123',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/login');

    $response->assertStatus(302);
    $response->assertRedirect('/admin/dashboard');
});

/*
|--------------------------------------------------------------------------
| I. Valid Sub-Page Access for Authorized Admin (Regression Protection)
|--------------------------------------------------------------------------
*/
it('I1: allows authorized admin to access /admin/notification with 200 OK and admin layout', function () {
    $admin = Admin::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin_notif@example.com',
        'password' => 'adminpass123',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/notification');

    $response->assertStatus(200);
    $response->assertSee('admin-layout', false);
    $response->assertSee('desktop-sidebar', false);
    $response->assertSee('Notification', false);
});

it('I2: allows authorized admin to access /admin/import-categories with valid response', function () {
    $admin = Admin::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin_cat@example.com',
        'password' => 'adminpass123',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/import-categories');

    // Should return 200 (not 404)
    $response->assertStatus(200);
});

it('I3: allows authorized admin to access /admin/search-categories with valid response/redirect', function () {
    $admin = Admin::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin_search@example.com',
        'password' => 'adminpass123',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/search-categories?category=NonExistent');

    // Controller handles search (redirects back with message or 200, not router 404)
    expect($response->status())->toBeIn([200, 302]);
    expect($response->status())->not->toBe(404);
});

it('I4: allows authorized admin to access /admin/shops/{id} with 200 OK and shop details view', function () {
    $admin = Admin::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin_shop@example.com',
        'password' => 'adminpass123',
    ]);

    $shop = Shop::create([
        'shop' => 'inspection-store.myshopify.com',
        'is_active' => 1,
    ]);

    $response = $this->actingAs($admin, 'admin')->get("/admin/shops/{$shop->id}");

    $response->assertStatus(200);
    $response->assertSee('admin-layout', false);
    $response->assertSee('inspection-store.myshopify.com', false);
});

it('I5: allows authorized admin to create category via POST /admin/create-category', function () {
    $admin = Admin::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin_cat_create@example.com',
        'password' => 'adminpass123',
    ]);

    $response = $this->actingAs($admin, 'admin')->post('/admin/create-category', [
        'name' => 'Electronics New',
        'category' => 'Electronics New',
        'slug' => 'electronics-new-' . uniqid(),
        'status' => 'Active',
    ]);

    $response->assertStatus(302);
    expect(\App\Models\Category::where('name', 'Electronics New')->exists())->toBeTrue();
});

it('I6: guest accessing /admin/notification or /admin/shops/{id} redirects to login without layout', function () {
    $shop = Shop::create([
        'shop' => 'guest-shop.myshopify.com',
        'is_active' => 1,
    ]);

    $response1 = $this->get('/admin/notification');
    $response1->assertStatus(302);
    $response1->assertRedirect(route('admin.login'));

    $response2 = $this->get("/admin/shops/{$shop->id}");
    $response2->assertStatus(302);
    $response2->assertRedirect(route('admin.login'));
});

it('I7: normal user accessing /admin/notification or /admin/shops/{id} gets 403 Forbidden without layout', function () {
    $user = User::forceCreate([
        'name' => 'Normal User',
        'email' => 'normal_notif@example.com',
        'password' => 'secret123',
    ]);

    $shop = Shop::create([
        'shop' => 'user-shop.myshopify.com',
        'is_active' => 1,
    ]);

    $response1 = $this->actingAs($user, 'web')->get('/admin/notification');
    $response1->assertStatus(403);
    $response1->assertDontSee('desktop-sidebar', false);

    $response2 = $this->actingAs($user, 'web')->get("/admin/shops/{$shop->id}");
    $response2->assertStatus(403);
    $response2->assertDontSee('desktop-sidebar', false);
});

it('I8: route shadowing test: truly non-existent admin routes still return 404 for authorized admins', function () {
    $admin = Admin::forceCreate([
        'name' => 'Admin User',
        'email' => 'admin_404_check@example.com',
        'password' => 'adminpass123',
    ]);

    $response = $this->actingAs($admin, 'admin')->get('/admin/truly-non-existent-wildcard-page-xyz');

    $response->assertStatus(404);
    $response->assertSee('404', false);
    $response->assertSee('admin-layout', false);
});
