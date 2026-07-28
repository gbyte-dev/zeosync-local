<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;

class ImageController extends Controller
{
    public function index(Request $request)
    {
        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            return back()->with('error', 'Shop not found.');
        }

        $images = Image::where('shop_id', $shop->id)
            ->latest()
            ->get();

        return view('image-upload', compact('images'));
    }

    public function store(Request $request)
    {

        $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240', // 10 MB
                'dimensions:min_width=1000,min_height=1000,max_width=10000,max_height=10000',
            ],
        ], [
            'image.required'   => 'Please select an image.',
            'image.image'      => 'The uploaded file must be an image.',
            'image.mimes'      => 'Only JPG, JPEG, PNG and WEBP images are allowed.',
            'image.max'        => 'Image size must not exceed 10 MB.',
            'image.dimensions' => 'Image dimensions must be between 1000×1000 and 10000×10000 pixels.',
        ]);
        $file = $request->file('image');

        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        $destinationPath = public_path('uploads/images');

        if (!file_exists($destinationPath)) {

            mkdir($destinationPath, 0777, true);
        }

        $file->move($destinationPath, $fileName);

        $path = 'uploads/images/' . $fileName;

        $shop = $request->attributes->get('active_shop_model');

        if (!$shop) {
            return back()->with('error', 'Shop not found.');
        }

        Image::create([
            'shop_id' => $shop->id,
            'image'   => $path,
        ]);

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
}
