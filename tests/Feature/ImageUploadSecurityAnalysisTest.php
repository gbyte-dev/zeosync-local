<?php

use App\Http\Controllers\ImageController;
use App\Models\Image;
use App\Models\Shop;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    if (!Schema::hasTable('shops')) {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop')->unique();
            $table->string('email')->nullable();
            $table->text('access_token')->nullable();
            $table->boolean('is_active')->default(1);
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

    // Clean up test images directory
    $uploadDir = public_path('uploads/images');
    if (file_exists($uploadDir)) {
        $files = glob($uploadDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && (str_contains($file, 'test_') || str_contains($file, '_'))) {
                // remove test artifacts
            }
        }
    }
});

/* =========================================================================
 * 1. TENANT AUTHORIZATION TESTS
 * ========================================================================= */

it('Test A: Valid upload succeeds for authenticated Shop A and is assigned to Shop A', function () {
    $shopA = Shop::create([
        'shop' => 'shop-a.myshopify.com',
        'email' => 'shopa@example.com',
        'is_active' => 1,
    ]);

    $file = UploadedFile::fake()->image('valid_product.jpg', 600, 600);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shopA);

    $response = (new ImageController())->store($request);

    $imageRecord = Image::where('shop_id', $shopA->id)->latest()->first();

    expect($imageRecord)->not->toBeNull();
    expect($imageRecord->shop_id)->toBe($shopA->id);
    expect($imageRecord->image)->toStartWith('uploads/images/');
    expect(file_exists(public_path($imageRecord->image)))->toBeTrue();

    // Clean up
    if (file_exists(public_path($imageRecord->image))) {
        unlink(public_path($imageRecord->image));
    }
});

it('Test B: Cross-tenant isolation prevents Shop A from viewing or deleting Shop B images', function () {
    $shopA = Shop::create([
        'shop' => 'tenant-a.myshopify.com',
        'email' => 'a@example.com',
        'is_active' => 1,
    ]);

    $shopB = Shop::create([
        'shop' => 'tenant-b.myshopify.com',
        'email' => 'b@example.com',
        'is_active' => 1,
    ]);

    // Shop B owns an image
    $imageB = Image::create([
        'shop_id' => $shopB->id,
        'image'   => 'uploads/images/shop_b_private.jpg',
    ]);

    // Shop A requests forSelection
    $requestA = Request::create('/image-picker-images', 'GET');
    $requestA->attributes->set('active_shop_model', $shopA);

    $selectionResponse = (new ImageController())->forSelection($requestA);
    $data = $selectionResponse->getData(true);

    expect($data['success'])->toBeTrue();
    // Shop A must see 0 images (Shop B's image is not accessible)
    expect($data['images'])->toBeEmpty();

    // Shop A requests index
    $indexRequest = Request::create('/image-upload', 'GET');
    $indexRequest->attributes->set('active_shop_model', $shopA);
    $viewResponse = (new ImageController())->index($indexRequest);
    $viewImages = $viewResponse->getData()['images'];
    expect($viewImages)->toBeEmpty();

    // Shop A attempts to delete Shop B's image
    $deleteRequest = Request::create('/image-upload/' . $imageB->id, 'DELETE');
    $deleteRequest->attributes->set('active_shop_model', $shopA);

    expect(fn () => (new ImageController())->destroy($deleteRequest, $imageB->id))
        ->toThrow(ModelNotFoundException::class);

    // Verify Shop B image still exists in DB
    expect(Image::where('id', $imageB->id)->exists())->toBeTrue();
});

/* =========================================================================
 * 2. MALICIOUS FILENAME TESTS
 * ========================================================================= */

it('rejects path traversal filename ../../../shell.php and prevents arbitrary file write', function () {
    $shop = Shop::create([
        'shop' => 'traversal-test.myshopify.com',
        'email' => 't@example.com',
        'is_active' => 1,
    ]);

    $file = UploadedFile::fake()->create('../../../shell.php', 10, 'application/x-php');

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    expect(fn () => (new ImageController())->store($request))
        ->toThrow(ValidationException::class);

    // Verify shell.php was not written anywhere
    expect(file_exists(public_path('shell.php')))->toBeFalse();
    expect(file_exists(base_path('shell.php')))->toBeFalse();
});

it('sanitizes double extension image.php.jpg to server-generated .jpg without executing or preserving .php', function () {
    $shop = Shop::create([
        'shop' => 'double-ext.myshopify.com',
        'email' => 'de@example.com',
        'is_active' => 1,
    ]);

    // Create a real valid JPEG image named image.php.jpg
    $file = UploadedFile::fake()->image('image.php.jpg', 400, 400);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = (new ImageController())->store($request);

    $record = Image::where('shop_id', $shop->id)->latest()->first();
    expect($record)->not->toBeNull();

    // Server must discard original filename and double extension
    expect($record->image)->not->toContain('image.php.jpg');
    expect($record->image)->not->toContain('.php');
    expect($record->image)->toMatch('/uploads\/images\/\d+_[a-f0-9]{16}\.jpg/');
    expect(file_exists(public_path($record->image)))->toBeTrue();

    if (file_exists(public_path($record->image))) {
        unlink(public_path($record->image));
    }
});

it('rejects executable trailing extension image.jpg.php', function () {
    $shop = Shop::create([
        'shop' => 'trailing-php.myshopify.com',
        'email' => 'tp@example.com',
        'is_active' => 1,
    ]);

    $file = UploadedFile::fake()->create('image.jpg.php', 10, 'application/x-php');

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    expect(fn () => (new ImageController())->store($request))
        ->toThrow(ValidationException::class);
});

it('sanitizes XSS filename test<script>.jpg and does not reflect script tag in stored filename', function () {
    $shop = Shop::create([
        'shop' => 'xss-filename.myshopify.com',
        'email' => 'xss@example.com',
        'is_active' => 1,
    ]);

    $file = UploadedFile::fake()->image('test<script>.jpg', 400, 400);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    $response = (new ImageController())->store($request);

    $record = Image::where('shop_id', $shop->id)->latest()->first();
    expect($record)->not->toBeNull();
    expect($record->image)->not->toContain('<script>');
    expect($record->image)->not->toContain('test');
    expect($record->image)->toStartWith('uploads/images/');

    if (file_exists(public_path($record->image))) {
        unlink(public_path($record->image));
    }
});

/* =========================================================================
 * 3. MIME / CONTENT MISMATCH TESTS
 * ========================================================================= */

it('rejects fake image fake.jpg containing executable PHP script content', function () {
    $shop = Shop::create([
        'shop' => 'mime-mismatch.myshopify.com',
        'email' => 'mime@example.com',
        'is_active' => 1,
    ]);

    // Create a file named fake.jpg but whose content is raw PHP code
    $temp = tempnam(sys_get_temp_dir(), 'fake_img_');
    file_put_contents($temp, '<?php echo "malicious"; ?>');

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

    // Ensure no record was created in database
    expect(Image::where('shop_id', $shop->id)->count())->toBe(0);
});

/* =========================================================================
 * 4. EXECUTABLE UPLOAD PREVENTION TESTS
 * ========================================================================= */

it('strictly rejects any .php, .phtml, .php5, or .phar file from becoming an uploaded resource', function () {
    $shop = Shop::create([
        'shop' => 'exec-prevent.myshopify.com',
        'email' => 'exec@example.com',
        'is_active' => 1,
    ]);

    $extensions = ['shell.php', 'exploit.phtml', 'script.php5', 'bundle.phar'];

    foreach ($extensions as $filename) {
        $file = UploadedFile::fake()->create($filename, 10, 'application/x-php');

        $request = Request::create('/image-upload', 'POST', [], [], [
            'image' => $file,
        ]);
        $request->attributes->set('active_shop_model', $shop);

        expect(fn () => (new ImageController())->store($request))
            ->toThrow(ValidationException::class);
    }

    expect(Image::where('shop_id', $shop->id)->count())->toBe(0);
});

/* =========================================================================
 * 5. SERVER-GENERATED FILENAME & STORAGE VERIFICATION
 * ========================================================================= */

it('guarantees stored filename != user-provided filename and follows server-generated format', function () {
    $shop = Shop::create([
        'shop' => 'server-filename.myshopify.com',
        'email' => 'sf@example.com',
        'is_active' => 1,
    ]);

    $originalName = 'my_secret_camera_upload_12345.png';
    $file = UploadedFile::fake()->image($originalName, 500, 500);

    $request = Request::create('/image-upload', 'POST', [], [], [
        'image' => $file,
    ]);
    $request->attributes->set('active_shop_model', $shop);

    (new ImageController())->store($request);

    $record = Image::where('shop_id', $shop->id)->latest()->first();

    expect($record)->not->toBeNull();
    $storedFilename = basename($record->image);

    // 1. Stored filename != original user filename
    expect($storedFilename)->not->toBe($originalName);
    expect($storedFilename)->not->toContain('my_secret_camera_upload');

    // 2. Format: <timestamp>_<16-hex-chars>.<extension>
    expect($storedFilename)->toMatch('/^\d+_[a-f0-9]{16}\.png$/');

    // 3. Extension is strictly from allowed set
    $extension = pathinfo($storedFilename, PATHINFO_EXTENSION);
    expect(in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true))->toBeTrue();

    // 4. Stored within public/uploads/images/
    expect($record->image)->toStartWith('uploads/images/');
    expect(file_exists(public_path($record->image)))->toBeTrue();

    if (file_exists(public_path($record->image))) {
        unlink(public_path($record->image));
    }
});
