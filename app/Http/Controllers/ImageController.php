<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;

class ImageController extends Controller
{
    public function index(Request $request)
    {
	//  phpinfo();
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            return back()->with('error', 'Shop not found.');
        }

        $images = Image::where('shop_id', $shop->id)->latest()->get();

        return view('image-upload', compact('images'));
    }

    public function forSelection(Request $request)
    {
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Shop not found.',
            ], 404);
        }

        $images = Image::where('shop_id', $shop->id)
            ->latest()
            ->get()
            ->map(function ($image) {
                return [
                    'id' => $image->id,
                    'name' => basename($image->image),
                    'path' => $image->image,
                    'url' => asset($image->image),
                ];
            });

        return response()->json([
            'success' => true,
            'images' => $images,
        ]);
    }

    public function store(Request $request)
    {
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found.',
                ], 404);
            }

            return back()->with('error', 'Shop not found.');
        }

        $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:10240', // 10 MB
            ],
        ], [
            'image.required'   => 'Please select an image.',
            'image.file'       => 'The uploaded item must be a valid file.',
            'image.image'      => 'The uploaded file must be an image.',
            'image.mimes'      => 'Only JPG, JPEG, PNG and WEBP images are allowed.',
            'image.mimetypes'  => 'Only JPG, JPEG, PNG and WEBP images are allowed.',
            'image.max'        => 'Image size must not exceed 10 MB.',
        ]);

        $file = $request->file('image');
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $guessed = strtolower((string) $file->guessExtension());
        $extension = in_array($guessed, $allowed, true) ? $guessed : 'jpg';
        $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destinationPath = public_path('uploads/images');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $fileName);
        $path = 'uploads/images/' . $fileName;

        $image = Image::create([
            'shop_id' => $shop->id,
            'image'   => $path,
        ]);

       // $this->resizeAndPadImage(public_path($path));

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully.',
                'image' => [
                    'id' => $image->id,
                    'name' => basename($image->image),
                    'path' => $image->image,
                    'url' => asset($image->image),
                ],
            ]);
        }

        return back()->with('success', 'Image uploaded successfully.');
    }


    public function destroy(Request $request, $id)
    {
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            return back()->with('error', 'Shop not found.');
        }

        $image = Image::where('id', $id)
            ->where('shop_id', $shop->id)
            ->firstOrFail();

        // Delete physical file
        if (!empty($image->image)) {
            $filePath = public_path($image->image);

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Delete database record
        $image->delete();

        return back()->with('success', 'Image deleted successfully.');
    }

   private function resizeAndPadImage(string $sourcePath): bool
    {
        $imageInfo = getimagesize($sourcePath);

        if (!$imageInfo) { return false;  }

        [$width, $height] = $imageInfo;
        $mime = $imageInfo['mime'];

        // Already 1000x1000 or larger — no changes
        if ($width >= 1000 && $height >= 1000) {
            return true;
        }

        // Load source image
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;

            case 'image/png':
                $source = imagecreatefrompng($sourcePath);
                break;

            case 'image/webp':
                $source = imagecreatefromwebp($sourcePath);
                break;

            default:
                return false;
        }

        if (!$source) {  return false;   }

        // Calculate proportional upscale
        $scale = max(1000 / $width, 1000 / $height);

        $newWidth  = (int) ceil($width * $scale);
        $newHeight = (int) ceil($height * $scale);

        // Resize
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            $transparent = imagecolorallocatealpha( $resized, 255, 255, 255, 127 );
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled( $resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width,
            $height
        );

        // Create final 1000x1000 canvas
        $canvas = imagecreatetruecolor(1000, 1000);

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);

            $transparent = imagecolorallocatealpha( $canvas, 255, 255,
                255,  127 );

            imagefill($canvas, 0, 0, $transparent);
        } else {
            // White background for JPEG
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
        }

        // Center the resized image
        $x = (int) (($newWidth - 1000) / 2);
        $y = (int) (($newHeight - 1000) / 2);

        imagecopy( $canvas, $resized,  -$x, -$y, 0,  0, $newWidth, $newHeight
        );

        // Create temporary file in the same directory
        $directory = dirname($sourcePath);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

        $tempPath = $directory . '/tmp_' . uniqid() . '.' . $extension;

        // Save processed image to temporary file
        switch ($mime) {
            case 'image/jpeg':
                $result = imagejpeg($canvas, $tempPath, 90);
                break;

            case 'image/png':
                $result = imagepng($canvas, $tempPath, 6);
                break;

            case 'image/webp':
                $result = imagewebp($canvas, $tempPath, 90);
                break;

            default:
                $result = false;
        }

        imagedestroy($source);
        imagedestroy($resized);
        imagedestroy($canvas);

        if (!$result || !file_exists($tempPath)) {
            return false;
        }

        // Delete original image
        if (!unlink($sourcePath)) {
            unlink($tempPath);
            return false;
        }

        // Move processed image to original path
        if (!rename($tempPath, $sourcePath)) {
            return false;
        }

        return true;
    }
}
