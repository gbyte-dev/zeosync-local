<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class AmazonSmartFormController extends Controller
{
    public function index()
    {
        return view('amazon.smart-form');
    }
    public function fetch(Request $request)
    {
        $productType = $request->product_type;
        $fields = [];
        if ($productType == 'SHIRT') {
            $fields = [
                'item_name',
                'brand',
                'manufacturer',
                'product_description',
                'color',
                'fit_type',
                'shirt_size',
                'fabric_type',
                'style'
            ];
        }
        if ($productType == 'HEADPHONES') {
            $fields = [
                'item_name',
                'brand',
                'manufacturer',
                'product_description',
                'color',
                'connectivity_technology',
                'headphones_form_factor',
                'batteries_required',
                // required fields
                'merchant_suggested_asin',
                'merchant_shipping_group',
                'package_length',
                'package_width',
                'package_height',
                'item_package_weight',
                'battery',
                'num_batteries',
                'has_multiple_battery_powered_components',
                'includes_rechargable_battery'
            ];
        }
        return response()->json([
            'success' => true,
            'fields' => $fields
        ]);
    }
}
