<?php

use App\Models\Admin;
use App\Models\AdminSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    } else {
        DB::table('admin_settings')->truncate();
    }

    if (!Schema::hasTable('notification_settings')) {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('notification_key')->unique();
            $table->text('description')->nullable();
            $table->boolean('email_enabled')->default(0);
            $table->boolean('in_app_enabled')->default(0);
            $table->timestamps();
        });
    } else {
        DB::table('notification_settings')->truncate();
    }

    if (!Schema::hasTable('contact_inquiries')) {
        Schema::create('contact_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    } else {
        DB::table('contact_inquiries')->truncate();
    }

    if (!Schema::hasTable('admin_notifications')) {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    } else {
        DB::table('admin_notifications')->truncate();
    }

    if (!Schema::hasTable('admins')) {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('super_admin');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    AdminSetting::forget('privacy_contact_email');
    Cache::flush();
});

it('public privacy page displays default fallback privacy@zeosync.app when no email is configured', function () {
    $response = $this->get(route('privacy'));

    $response->assertStatus(200);
    $response->assertSee('For privacy-related inquiries, please contact us at <a href="mailto:privacy@zeosync.app">privacy@zeosync.app</a>.', false);
});

it('getPrivacyContactEmail helper falls back safely on empty or invalid email', function () {
    expect(getPrivacyContactEmail())->toBe('privacy@zeosync.app');

    AdminSetting::updateOrCreate(['option_key' => 'privacy_contact_email'], ['option_value' => '']);
    expect(getPrivacyContactEmail())->toBe('privacy@zeosync.app');

    AdminSetting::updateOrCreate(['option_key' => 'privacy_contact_email'], ['option_value' => '   ']);
    expect(getPrivacyContactEmail())->toBe('privacy@zeosync.app');

    AdminSetting::updateOrCreate(['option_key' => 'privacy_contact_email'], ['option_value' => 'not-an-email']);
    expect(getPrivacyContactEmail())->toBe('privacy@zeosync.app');
});

it('unauthenticated guest cannot update privacy contact email setting', function () {
    $response = $this->post(route('admin.settings.update'), [
        'privacy_contact_email' => 'hacker@example.com',
    ]);

    $response->assertRedirect(route('admin.login'));

    expect(AdminSetting::where('option_key', 'privacy_contact_email')->first())->toBeNull();
});

it('authenticated admin can view the Privacy Contact Email field on admin settings page', function () {
    $admin = Admin::create([
        'name'     => 'Test Admin',
        'email'    => 'admin@zeosync.com',
        'password' => bcrypt('password123'),
        'role'     => 'super_admin',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.settings'));

    $response->assertStatus(200);
    $response->assertSee('Privacy Contact Email');
    $response->assertSee('name="privacy_contact_email"', false);
});

it('authenticated admin can update and save privacy contact email successfully', function () {
    $admin = Admin::create([
        'name'     => 'Test Admin',
        'email'    => 'admin@zeosync.com',
        'password' => bcrypt('password123'),
        'role'     => 'super_admin',
    ]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.settings.update'), [
        'app_name'              => 'ZeoSync',
        'privacy_contact_email' => 'legal-dpo@company.com',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success', 'Settings updated successfully.');

    // Assert persisted in database
    $dbRecord = AdminSetting::where('option_key', 'privacy_contact_email')->first();
    expect($dbRecord)->not->toBeNull()
        ->and($dbRecord->option_value)->toBe('legal-dpo@company.com');

    // Assert helper retrieves updated email
    expect(getPrivacyContactEmail())->toBe('legal-dpo@company.com');

    // Assert public Privacy Policy page displays the updated email as a mailto link
    $privacyResponse = $this->get(route('privacy'));
    $privacyResponse->assertStatus(200);
    $privacyResponse->assertSee('For privacy-related inquiries, please contact us at <a href="mailto:legal-dpo@company.com">legal-dpo@company.com</a>.', false);
});

it('server-side validation rejects invalid privacy contact email', function () {
    $admin = Admin::create([
        'name'     => 'Test Admin',
        'email'    => 'admin@zeosync.com',
        'password' => bcrypt('password123'),
        'role'     => 'super_admin',
    ]);

    // Pre-seed a valid value
    AdminSetting::updateOrCreate(['option_key' => 'privacy_contact_email'], ['option_value' => 'existing@zeosync.app']);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.settings.update'), [
        'privacy_contact_email' => 'invalid-email-string',
    ]);

    $response->assertSessionHasErrors(['privacy_contact_email']);

    // Assert database value was NOT overwritten with invalid email
    $dbRecord = AdminSetting::where('option_key', 'privacy_contact_email')->first();
    expect($dbRecord->option_value)->toBe('existing@zeosync.app');
});

it('preserves saved privacy contact email after admin logout and re-login', function () {
    $admin = Admin::create([
        'name'     => 'Test Admin',
        'email'    => 'admin@zeosync.com',
        'password' => bcrypt('password123'),
        'role'     => 'super_admin',
    ]);

    // Admin logs in and sets email
    $this->actingAs($admin, 'admin')->post(route('admin.settings.update'), [
        'privacy_contact_email' => 'compliance@partner.com',
    ]);

    // Admin logs out
    $this->post(route('admin.logout'));

    // Cache clear simulation (e.g. app restart)
    Cache::flush();

    // Verify still in database and rendered on public page
    expect(AdminSetting::where('option_key', 'privacy_contact_email')->value('option_value'))->toBe('compliance@partner.com');
    expect(getPrivacyContactEmail())->toBe('compliance@partner.com');

    $privacyResponse = $this->get(route('privacy'));
    $privacyResponse->assertStatus(200);
    $privacyResponse->assertSee('mailto:compliance@partner.com');
    $privacyResponse->assertSee('compliance@partner.com');

    // Admin logs back in and visits settings page
    $settingsResponse = $this->actingAs($admin, 'admin')->get(route('admin.settings'));
    $settingsResponse->assertStatus(200);
    $settingsResponse->assertSee('compliance@partner.com');
});
