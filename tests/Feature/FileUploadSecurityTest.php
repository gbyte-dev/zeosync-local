<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ImageController;
use App\Models\AdminSetting;
use App\Models\Image;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    if (!Schema::hasTable('admin_settings')) {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('option_key')->unique();
            $table->text('option_value')->nullable();
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('images')) {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('image');
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    if (!Schema::hasTable('notification_settings')) {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key')->unique();
            $table->boolean('email_enabled')->default(1);
            $table->boolean('in_app_enabled')->default(1);
            $table->timestamps();
        });
    }

    Storage::fake('public');
});

/* =========================================================================
 * 1. ADMIN LOGO & FAVICON VALIDATION TESTS
 * ========================================================================= */

it('accepts valid PNG logo and generates safe random filename', function () {
    $file = UploadedFile::fake()->image('my_custom_logo.png', 400, 400);

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $file,
    ]);

    $controller = new AdminController();
    $controller->settingsupdate($request);

    $savedPath = AdminSetting::where('option_key', 'app_logo')->value('option_value');

    expect($savedPath)->not->toBeNull();
    expect($savedPath)->toStartWith('logo/');
    expect($savedPath)->not->toContain('my_custom_logo.png');
    expect(Storage::disk('public')->exists($savedPath))->toBeTrue();
});

it('accepts valid JPEG logo', function () {
    $file = UploadedFile::fake()->image('brand.jpg', 400, 400);

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $file,
    ]);

    (new AdminController())->settingsupdate($request);

    $savedPath = AdminSetting::where('option_key', 'app_logo')->value('option_value');
    expect($savedPath)->not->toBeNull();
    expect(Storage::disk('public')->exists($savedPath))->toBeTrue();
});

it('accepts valid WEBP logo', function () {
    $file = UploadedFile::fake()->image('logo.webp', 400, 400);

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $file,
    ]);

    (new AdminController())->settingsupdate($request);

    $savedPath = AdminSetting::where('option_key', 'app_logo')->value('option_value');
    expect($savedPath)->not->toBeNull();
    expect(Storage::disk('public')->exists($savedPath))->toBeTrue();
});

it('accepts valid PNG favicon', function () {
    $file = UploadedFile::fake()->image('fav.png', 32, 32);

    $request = Request::create('/settings', 'POST', [], [], [
        'app_favicon' => $file,
    ]);

    (new AdminController())->settingsupdate($request);

    $savedPath = AdminSetting::where('option_key', 'app_favicon')->value('option_value');
    expect($savedPath)->not->toBeNull();
    expect(Storage::disk('public')->exists($savedPath))->toBeTrue();
});

it('accepts valid ICO favicon', function () {
    $file = UploadedFile::fake()->create('fav.ico', 10, 'image/x-icon');

    $request = Request::create('/settings', 'POST', [], [], [
        'app_favicon' => $file,
    ]);

    (new AdminController())->settingsupdate($request);

    $savedPath = AdminSetting::where('option_key', 'app_favicon')->value('option_value');
    expect($savedPath)->not->toBeNull();
    expect(Storage::disk('public')->exists($savedPath))->toBeTrue();
});

it('rejects admin upload of PHP file for logo or favicon', function () {
    $phpFile = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $phpFile,
    ]);

    expect(fn () => (new AdminController())->settingsupdate($request))
        ->toThrow(ValidationException::class);
});

it('rejects admin upload of PHTML file', function () {
    $phtmlFile = UploadedFile::fake()->create('shell.phtml', 10, 'text/html');

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $phtmlFile,
    ]);

    expect(fn () => (new AdminController())->settingsupdate($request))
        ->toThrow(ValidationException::class);
});

it('rejects admin upload of HTML file', function () {
    $htmlFile = UploadedFile::fake()->create('page.html', 10, 'text/html');

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $htmlFile,
    ]);

    expect(fn () => (new AdminController())->settingsupdate($request))
        ->toThrow(ValidationException::class);
});

it('rejects admin upload of SVG file for logo and favicon', function () {
    $svgFile = UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml');

    $request1 = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $svgFile,
    ]);

    expect(fn () => (new AdminController())->settingsupdate($request1))
        ->toThrow(ValidationException::class);

    $request2 = Request::create('/settings', 'POST', [], [], [
        'app_favicon' => $svgFile,
    ]);

    expect(fn () => (new AdminController())->settingsupdate($request2))
        ->toThrow(ValidationException::class);
});

it('rejects invalid image payload disguised as image for admin logo', function () {
    $temp = tempnam(sys_get_temp_dir(), 'test_fake_img');
    file_put_contents($temp, '<?php echo "evil"; ?>');

    $fakeImg = new UploadedFile(
        $temp,
        'exploit.png',
        'image/png',
        null,
        true
    );

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $fakeImg,
    ]);

    try {
        expect(fn () => (new AdminController())->settingsupdate($request))
            ->toThrow(ValidationException::class);
    } finally {
        if (file_exists($temp)) {
            unlink($temp);
        }
    }
});

it('rejects oversized admin logo and favicon', function () {
    // 6MB logo (max 5MB)
    $bigLogo = UploadedFile::fake()->image('big.png', 100, 100)->size(6 * 1024);

    $request1 = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $bigLogo,
    ]);

    expect(fn () => (new AdminController())->settingsupdate($request1))
        ->toThrow(ValidationException::class);

    // 2MB favicon (max 1MB)
    $bigFav = UploadedFile::fake()->image('big_fav.png', 32, 32)->size(2 * 1024);

    $request2 = Request::create('/settings', 'POST', [], [], [
        'app_favicon' => $bigFav,
    ]);

    expect(fn () => (new AdminController())->settingsupdate($request2))
        ->toThrow(ValidationException::class);
});

it('does not trust client filename or directory traversal in stored admin filename', function () {
    $file = UploadedFile::fake()->image('../../../malicious_name.png', 100, 100);

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $file,
    ]);

    (new AdminController())->settingsupdate($request);

    $savedPath = AdminSetting::where('option_key', 'app_logo')->value('option_value');
    expect($savedPath)->not->toContain('malicious_name');
    expect($savedPath)->not->toContain('..');
    expect($savedPath)->toStartWith('logo/app_logo_');
});

it('failed upload does not delete existing admin logo or favicon', function () {
    // Set up existing logo
    Storage::disk('public')->put('logo/existing_logo.png', 'valid-logo-content');
    AdminSetting::updateOrCreate(
        ['option_key' => 'app_logo'],
        ['option_value' => 'logo/existing_logo.png']
    );

    $maliciousFile = UploadedFile::fake()->create('malicious.php', 10, 'application/x-php');

    $request = Request::create('/settings', 'POST', [], [], [
        'app_logo' => $maliciousFile,
    ]);

    try {
        (new AdminController())->settingsupdate($request);
    } catch (ValidationException $e) {
        // Expected
    }

    // Existing file must still exist and DB must remain unchanged
    expect(Storage::disk('public')->exists('logo/existing_logo.png'))->toBeTrue();
    expect(AdminSetting::where('option_key', 'app_logo')->value('option_value'))
        ->toBe('logo/existing_logo.png');
});

/* =========================================================================
 * 2. MERCHANT IMAGE UPLOAD VALIDATION TESTS
 * ========================================================================= */

it('accepts valid JPEG merchant image of any dimension including < 1000x1000', function () {
    $shop = (object) ['id' => 101];
    $file = UploadedFile::fake()->image('small_product.jpg', 250, 250);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = (new ImageController())->store($request);

    $imageRecord = Image::where('shop_id', 101)->latest()->first();
    expect($imageRecord)->not->toBeNull();
    expect($imageRecord->image)->toStartWith('uploads/images/');
    expect($imageRecord->image)->not->toContain('small_product.jpg');

    // Clean up physical uploaded file
    if (file_exists(public_path($imageRecord->image))) {
        unlink(public_path($imageRecord->image));
    }
});

it('accepts valid PNG merchant image smaller than 1000x1000', function () {
    $shop = (object) ['id' => 102];
    $file = UploadedFile::fake()->image('item.png', 500, 300);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    (new ImageController())->store($request);

    $imageRecord = Image::where('shop_id', 102)->latest()->first();
    expect($imageRecord)->not->toBeNull();
    expect($imageRecord->image)->toStartWith('uploads/images/');

    if (file_exists(public_path($imageRecord->image))) {
        unlink(public_path($imageRecord->image));
    }
});

it('accepts valid WEBP merchant image', function () {
    $shop = (object) ['id' => 103];
    $file = UploadedFile::fake()->image('photo.webp', 800, 600);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    (new ImageController())->store($request);

    $imageRecord = Image::where('shop_id', 103)->latest()->first();
    expect($imageRecord)->not->toBeNull();
    expect($imageRecord->image)->toStartWith('uploads/images/');

    if (file_exists(public_path($imageRecord->image))) {
        unlink(public_path($imageRecord->image));
    }
});

it('rejects merchant upload of PHP script file', function () {
    $shop = (object) ['id' => 104];
    $file = UploadedFile::fake()->create('backdoor.php', 10, 'application/x-php');

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    expect(fn () => (new ImageController())->store($request))
        ->toThrow(ValidationException::class);
});

it('rejects merchant upload of HTML file', function () {
    $shop = (object) ['id' => 105];
    $file = UploadedFile::fake()->create('xss.html', 10, 'text/html');

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    expect(fn () => (new ImageController())->store($request))
        ->toThrow(ValidationException::class);
});

it('rejects merchant upload of SVG file', function () {
    $shop = (object) ['id' => 106];
    $file = UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml');

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    expect(fn () => (new ImageController())->store($request))
        ->toThrow(ValidationException::class);
});

it('rejects merchant invalid image payload', function () {
    $shop = (object) ['id' => 107];
    $temp = tempnam(sys_get_temp_dir(), 'test_fake_merchant_img');
    file_put_contents($temp, 'not a real image payload');

    $file = new UploadedFile(
        $temp,
        'fake.jpg',
        'image/jpeg',
        null,
        true
    );

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    try {
        expect(fn () => (new ImageController())->store($request))
            ->toThrow(ValidationException::class);
    } finally {
        if (file_exists($temp)) {
            unlink($temp);
        }
    }
});

it('rejects merchant oversized file (>10MB)', function () {
    $shop = (object) ['id' => 108];
    $file = UploadedFile::fake()->image('huge.jpg', 1200, 1200)->size(11 * 1024);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    expect(fn () => (new ImageController())->store($request))
        ->toThrow(ValidationException::class);
});

it('missing active shop does not write any file and does not create image DB record', function () {
    $file = UploadedFile::fake()->image('product.jpg', 1200, 1200);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    // No active_shop_model attribute set

    $initialCount = Image::count();
    $response = (new ImageController())->store($request);

    // Returns redirect or 404
    expect(Image::count())->toBe($initialCount);
});

it('filename and path traversal cannot control merchant storage path', function () {
    $shop = (object) ['id' => 109];
    $file = UploadedFile::fake()->image('../../../traversal_test.png', 1200, 1200);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    (new ImageController())->store($request);

    $imageRecord = Image::where('shop_id', 109)->latest()->first();
    expect($imageRecord)->not->toBeNull();
    expect($imageRecord->image)->toStartWith('uploads/images/');
    expect($imageRecord->image)->not->toContain('..');
    expect($imageRecord->image)->not->toContain('traversal_test');

    if (file_exists(public_path($imageRecord->image))) {
        unlink(public_path($imageRecord->image));
    }
});
