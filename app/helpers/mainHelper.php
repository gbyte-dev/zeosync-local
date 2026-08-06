<?php

use App\Models\AdminSetting;
use App\Services\StripeService;
use Stripe\StripeClient;
use App\Models\Category;
use App\Models\ProductMarketplaceMapping;
use App\Models\AllProduct;
use App\Models\Plan;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        return cache()->remember("setting_{$key}", 3600, function () use ($key, $default) {
            return \App\Models\AdminSetting::where('option_key', $key)
                ->value('option_value') ?? $default;
        });
    }
}

if (!function_exists('getMailSettings')) {
function getMailSettings(){
    $settings = DB::table('admin_settings')->pluck('option_value', 'option_key')->toArray();

    config([
        'mail.mailers.smtp.host' => $settings['SMTP_host']??env('MAIL_HOST', ''),
        'mail.mailers.smtp.port' => $settings['SMTP_port']??env('MAIL_PORT', ''),
        'mail.mailers.smtp.username' => $settings['SMTP_username']??env('MAIL_USERNAME', ''),
        'mail.mailers.smtp.password' => $settings['SMTP_password']??env('MAIL_PASSWORD', ''),
        'mail.mailers.smtp.encryption' => $settings['SMTP_encryption']??env('MAIL_ENCRYPTION', ''),
        'mail.from.address' => $settings['from_email']??env('MAIL_FROM_ADDRESS', ''),
        'mail.from.name' => $settings['from_name']??env('MAIL_FROM_NAME', ''),
    ]);
}
}
/**
 * Get Stripe Service instance
 */
function stripe(): StripeService
{
    return app(StripeService::class);
}

/**
 * Get Stripe Client instance
 */
function stripeClient(): StripeClient
{
    return app(StripeClient::class);
}

/**
 * Get Stripe publishable key
 */
function stripePublishableKey(): string
{
    return \App\Providers\StripeServiceProvider::getPublishableKey();
}

/**
 * Create Stripe checkout and send payment link via email
 * 
 * @param \App\Models\Shop $shop
 * @param array $paymentData
 * @param string $toEmail
 * @param string|null $templateName
 * @return array
 */
function sendPaymentLink(\App\Models\Shop $shop, array $paymentData, string $toEmail, ?string $templateName = null): array
{
    return stripe()->createCheckoutAndSendEmail($shop, $paymentData, $toEmail, $templateName);
}

function getCategorires($parent_id=null){

    if(empty($parent_id)){
        $datacat = Category::where(['parent_id' => $parent_id ])->get();
    }
    if(is_numeric($parent_id)){
        $datacat = Category::where(['parent_id' => $parent_id ])->get();
    }
    if(is_string($parent_id)){
        $datacat = Category::where(['category' => $parent_id ])->get();
    }
   
    return $datacat;
}

function getCategoryData($catid,$field=null){
    $datacat = Category::where(['id' => $catid ])->first();
    if($field){
        return ($datacat->$field)??'';
    }
    return $datacat;
}

function checkIsProductSynced($product_id,$type){
   $mapped_id = false;
    if($type=='amazon'){
        $mapping = ProductMarketplaceMapping::where('amazon_parent_sku',$product_id)->first();
        if($mapping){
            $mapped_id = $mapping->shopify_product_id??'';
        }

    }else{
        $mapping = ProductMarketplaceMapping::where('shopify_product_id',$product_id)->first();
        if($mapping){
            $mapped_id = $mapping->amazon_sku??'';
        }
    }

    return $mapped_id;

}

function getSubCategorires($parent_id,$status='Active'){

    $datacat = Category::where(['parent_id' => $parent_id,'status' => $status ])->get();
    return $datacat->count();
}

function resolveUnit(string $rawUnit, array $map): ?string
{
    $key = strtolower(trim($rawUnit));
    return $map[$key] ?? null;
}

if (!function_exists('getPlanName')) {
    function getPlanName($plan_id){
        $plan = Plan::where('id',$plan_id)->first();
        if($plan ){
        return   $plan->name; 
        }
        return $plan;
    }
}

if (!function_exists('getAllPlan')) {
    function getAllPlan(){
        $plan = Plan::where('is_active',1)->get();
        if($plan ){
        return   $plan; 
        }
        return [];
    }
}

if (!function_exists('checkAmazonConnected')) {
    function checkAmazonConnected($shop=null){
        if(!$shop){
            $shop = session('active_shop');
        }
        if(!$shop){
            return false;
        }
        
        $shopdata = Shop::where('shop', $shop)->first();
        if($shopdata && ($shopdata->amazon_refresh_token != null && !empty($shopdata->amazon_refresh_token) ) ){
        return   true; 
        }
        return false;
    }
}

if (!function_exists('getLogo')) {
    function getLogo(){
        $settings = DB::table('admin_settings')->where('option_key', 'app_logo')->first();

        if ($settings && !empty($settings->option_value)) {
            $logoPath = $settings->option_value;
            $path = public_path($logoPath);
            $exists = \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath);

            if (file_exists($path)) {
                return asset($logoPath);
            } elseif ($exists) {
                return asset('storage/' . $logoPath);
            }
        }

        return asset('logo/logoamazonysync.png');
    }
}


if (!function_exists('getFavicon')) {
    function getFavicon(){
        $settings = DB::table('admin_settings')->where('option_key', 'app_favicon')->first();

        if ($settings && !empty($settings->option_value)) {
            $faviconPath = $settings->option_value;
            $path = public_path($faviconPath);
            $exists = \Illuminate\Support\Facades\Storage::disk('public')->exists($faviconPath);

            if (file_exists($path)) {
                return asset($faviconPath);
            } elseif ($exists) {
                return asset('storage/' . $faviconPath);
            }
        }

        return asset('logo/favamzsync.png');
    }
}

if (!function_exists('getAppName')) {
    function getAppName(){
        $settings = DB::table('admin_settings')->where('option_key', 'app_name')->first();

        if ($settings && !empty($settings->app_name)) {
           return $settings->app_name;
        }

        return 'Zeosync';
    }
}

