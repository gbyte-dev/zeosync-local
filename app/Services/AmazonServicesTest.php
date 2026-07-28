<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SellingPartnerApi\Seller\SellerConnector;
use SellingPartnerApi\Enums\Endpoint;
use SellingPartnerApi\SellingPartnerApi;
use Illuminate\Support\Facades\LOG;
use App\Models\ProductSyncLog;
use App\Models\AdminSetting;
use SellingPartnerApi\Seller\ListingsItemsV20210801\Dto\ListingsItemPutRequest;
use SellingPartnerApi\Seller\ProductTypeDefinitionsV20200901\Requests\GetDefinitionsProductType;

class AmazonServiceTest
{

    /**
     * Amazon SP-API Listing Payloads
     * Product Types: WRITING_PAPER | WRITING_INSTRUMENT | WRITING_BOARD | WRENCH
     *
     * Follows the same structure as the monitor payload pattern.
     * All attribute arrays follow Amazon's SP-API JSON schema format.
     * Always call the Product Type Definitions API to validate your
     * payload before submission:
     *   GET /definitions/2020-09-01/productTypes/{PRODUCT_TYPE}
     *       ?marketplaceIds=ATVPDKIKX0DER&requirements=LISTING
     *
     * Common required fields across most product types:
     *   item_name, brand, bullet_point, product_description,
     *   country_of_origin, condition_type, list_price,
     *   fulfillment_availability, main_product_image_locator,
     *   item_package_weight, item_package_dimensions,
     *   supplier_declared_dg_hz_regulation
     */

    const MKT = 'ATVPDKIKX0DER';
    const LANG = 'en_US';

    // ─────────────────────────────────────────────────────────────────────────────
    // 1. WRITING_PAPER  (notebooks, ruled pads, printer paper, etc.)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Full standalone payload for WRITING_PAPER product type.
     * item_type_keyword examples: "college-ruled-paper", "graph-paper",
     *   "printer-paper", "spiral-notebooks"
     */
    public function writingPaperFullPayload($product, $amazon): array
    {
        $variants   = $this->parseJsonField($product->variants);
        $images     = $this->parseJsonField($product->images);
        $variant    = $variants[0] ?? [];

        $price = (float)($variant['price'] ?? $product->price ?? 9.99);
        if ($price <= 1) $price = 9.99;

        $qty   = (int)($variant['inventory_quantity'] ?? 10);
        $image = $images[0]['src'] ?? 'https://via.placeholder.com/500';

        $bulletPoints = $this->parseJsonField($amazon->bullet_points);
        $searchTerms  = $this->parseJsonField($amazon->search_terms);

        $payload = [
            // ── Identity ──────────────────────────────────────────────────────
            "item_name" => [[
                "value"        => substr(trim($amazon->amazon_title), 0, 200),
                "language_tag" => LANG,
                "marketplace_id" => MKT,
            ]],
            "brand" => [[
                "value"          => "ZEBRONICS",      // replace per product
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "manufacturer" => [[
                "value"          => "ZEBRONICS",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "model_number" => [[
                "value"          => "ZEB-NB80",
                "marketplace_id" => MKT,
            ]],

            // ── Classification ────────────────────────────────────────────────
            "item_type_keyword" => [[
                "value"          => "college-ruled-paper",
                "marketplace_id" => MKT,
            ]],

            // ── GTIN Exemption ────────────────────────────────────────────────
            "supplier_declared_has_product_identifier_exemption" => [[
                "value"          => true,
                "marketplace_id" => MKT,
            ]],

            // ── Content / Copy ────────────────────────────────────────────────
            "product_description" => [[
                "value"          => strip_tags($product->description ?? 'Quality writing paper for everyday use.'),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($pt) => [
                    "value"          => substr($pt, 0, 500),
                    "language_tag"   => LANG,
                    "marketplace_id" => MKT,
                ])->values()->toArray(),
            "generic_keyword" => [[
                "value"          => substr(implode(' ', $searchTerms), 0, 250),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Paper-specific attributes ──────────────────────────────────────
            // Ruling style: "college_ruled" | "wide_ruled" | "blank" | "graph"
            "ruling" => [[
                "value"          => "college_ruled",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Number of sheets / pages
            "number_of_pages" => [[
                "value"          => 200,
                "marketplace_id" => MKT,
            ]],
            // Sheet size — common: "letter" | "legal" | "a4"
            "sheet_size" => [[
                "value"          => "letter",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Paper weight (gsm)
            "paper_weight" => [[
                "value" => 75,
                "unit"  => "grams_per_square_meter",
                "marketplace_id" => MKT,
            ]],
            // Binding type: "spiral" | "glued" | "hardcover" | "loose_leaf"
            "binding_type" => [[
                "value"          => "spiral",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Cover material: "cardboard" | "plastic" | "leather"
            "cover_material" => [[
                "value"          => "cardboard",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "color" => [[
                "value"          => "White",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "material" => [[
                "value"          => "Paper",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Quantity / Packaging ──────────────────────────────────────────
            "number_of_items" => [[
                "value"          => 1,
                "marketplace_id" => MKT,
            ]],
            "item_package_quantity" => [[
                "value"          => 1,
                "marketplace_id" => MKT,
            ]],

            // ── Physical dimensions ───────────────────────────────────────────
            "item_dimensions" => [[
                "length" => ["value" => 11.0, "unit" => "inches"],
                "width"  => ["value" => 8.5,  "unit" => "inches"],
                "height" => ["value" => 0.5,  "unit" => "inches"],
                "marketplace_id" => MKT,
            ]],
            "item_weight" => [[
                "value"          => 0.5,
                "unit"           => "pounds",
                "marketplace_id" => MKT,
            ]],
            "item_package_dimensions" => [[
                "length" => ["value" => 11.5, "unit" => "inches"],
                "width"  => ["value" => 9.0,  "unit" => "inches"],
                "height" => ["value" => 1.0,  "unit" => "inches"],
                "marketplace_id" => MKT,
            ]],
            "item_package_weight" => [[
                "value"          => 0.6,
                "unit"           => "pounds",
                "marketplace_id" => MKT,
            ]],

            // ── Compliance ────────────────────────────────────────────────────
            "country_of_origin" => [[
                "value"          => "IN",
                "marketplace_id" => MKT,
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value"          => "not_applicable",
                "marketplace_id" => MKT,
            ]],
            "batteries_required" => [[
                "value"          => false,
                "marketplace_id" => MKT,
            ]],
            "batteries_included" => [[
                "value"          => false,
                "marketplace_id" => MKT,
            ]],

            // ── Offer ─────────────────────────────────────────────────────────
            "condition_type" => [[
                "value"          => "new_new",
                "marketplace_id" => MKT,
            ]],
            "list_price" => [[
                "value"          => $price,
                "currency"       => "USD",
                "marketplace_id" => MKT,
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity"                 => $qty,
            ]],

            // ── Images ────────────────────────────────────────────────────────
            "main_product_image_locator" => [[
                "media_location"  => $image,
                "marketplace_id"  => MKT,
            ]],
        ];

        // Extra images (up to 8)
        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) continue;
            $payload["other_product_image_locator_{$index}"] = [[
                "media_location"  => $img['src'],
                "marketplace_id"  => MKT,
            ]];
        }

        return $payload;
    }


    // ─────────────────────────────────────────────────────────────────────────────
    // 2. WRITING_INSTRUMENT  (pens, pencils, markers, highlighters, etc.)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Full standalone payload for WRITING_INSTRUMENT product type.
     * item_type_keyword examples: "ballpoint-pens", "gel-pens",
     *   "mechanical-pencils", "permanent-markers", "highlighters"
     */
    public function writingInstrumentFullPayload($product, $amazon): array
    {
        $variants   = $this->parseJsonField($product->variants);
        $images     = $this->parseJsonField($product->images);
        $variant    = $variants[0] ?? [];

        $price = (float)($variant['price'] ?? $product->price ?? 4.99);
        if ($price <= 1) $price = 4.99;

        $qty   = (int)($variant['inventory_quantity'] ?? 10);
        $image = $images[0]['src'] ?? 'https://via.placeholder.com/500';

        $bulletPoints = $this->parseJsonField($amazon->bullet_points);
        $searchTerms  = $this->parseJsonField($amazon->search_terms);

        $payload = [
            // ── Identity ──────────────────────────────────────────────────────
            "item_name" => [[
                "value"          => substr(trim($amazon->amazon_title), 0, 200),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "brand" => [[
                "value"          => "ZEBRONICS",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "manufacturer" => [[
                "value"          => "ZEBRONICS",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "model_number" => [[
                "value"          => "ZEB-PEN01",
                "marketplace_id" => MKT,
            ]],
            "part_number" => [[
                "value"          => "ZEB-PEN01",
                "marketplace_id" => MKT,
            ]],

            // ── Classification ────────────────────────────────────────────────
            "item_type_keyword" => [[
                "value"          => "ballpoint-pens",
                "marketplace_id" => MKT,
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value"          => true,
                "marketplace_id" => MKT,
            ]],

            // ── Content / Copy ────────────────────────────────────────────────
            "product_description" => [[
                "value"          => strip_tags($product->description ?? 'Smooth-writing ballpoint pen for everyday use.'),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($pt) => [
                    "value"          => substr($pt, 0, 500),
                    "language_tag"   => LANG,
                    "marketplace_id" => MKT,
                ])->values()->toArray(),
            "generic_keyword" => [[
                "value"          => substr(implode(' ', $searchTerms), 0, 250),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Instrument-specific attributes ────────────────────────────────
            // Ink/lead color
            "ink_color" => [[
                "value"          => "Blue",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Point type: "fine" | "medium" | "broad" | "extra_fine"
            "point_type" => [[
                "value"          => "medium",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Tip size in mm (for gel/ballpoint)
            "tip_size" => [[
                "value"          => 1.0,
                "unit"           => "millimeters",
                "marketplace_id" => MKT,
            ]],
            // Barrel/body color
            "color" => [[
                "value"          => "Black",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Barrel material
            "material" => [[
                "value"          => "Plastic",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Grip type: "rubberized" | "plastic" | "metal"
            "grip_type" => [[
                "value"          => "rubberized",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Retractable: true | false
            "is_retractable" => [[
                "value"          => true,
                "marketplace_id" => MKT,
            ]],
            // Number of pens in the set / pack count
            "number_of_items" => [[
                "value"          => 10,     // e.g. 10-pack
                "marketplace_id" => MKT,
            ]],
            "item_package_quantity" => [[
                "value"          => 1,
                "marketplace_id" => MKT,
            ]],

            // ── Physical ──────────────────────────────────────────────────────
            "item_dimensions" => [[
                "length" => ["value" => 5.5,  "unit" => "inches"],
                "width"  => ["value" => 0.4,  "unit" => "inches"],
                "height" => ["value" => 0.4,  "unit" => "inches"],
                "marketplace_id" => MKT,
            ]],
            "item_weight" => [[
                "value"          => 0.05,
                "unit"           => "pounds",
                "marketplace_id" => MKT,
            ]],
            "item_package_dimensions" => [[
                "length" => ["value" => 7.0,  "unit" => "inches"],
                "width"  => ["value" => 3.0,  "unit" => "inches"],
                "height" => ["value" => 1.0,  "unit" => "inches"],
                "marketplace_id" => MKT,
            ]],
            "item_package_weight" => [[
                "value"          => 0.2,
                "unit"           => "pounds",
                "marketplace_id" => MKT,
            ]],

            // ── Compliance ────────────────────────────────────────────────────
            "country_of_origin" => [[
                "value"          => "IN",
                "marketplace_id" => MKT,
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value"          => "not_applicable",
                "marketplace_id" => MKT,
            ]],
            "batteries_required" => [[
                "value"          => false,
                "marketplace_id" => MKT,
            ]],
            "batteries_included" => [[
                "value"          => false,
                "marketplace_id" => MKT,
            ]],

            // ── Offer ─────────────────────────────────────────────────────────
            "condition_type" => [[
                "value"          => "new_new",
                "marketplace_id" => MKT,
            ]],
            "list_price" => [[
                "value"          => $price,
                "currency"       => "USD",
                "marketplace_id" => MKT,
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity"                 => $qty,
            ]],

            // ── Images ────────────────────────────────────────────────────────
            "main_product_image_locator" => [[
                "media_location"  => $image,
                "marketplace_id"  => MKT,
            ]],
        ];

        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) continue;
            $payload["other_product_image_locator_{$index}"] = [[
                "media_location"  => $img['src'],
                "marketplace_id"  => MKT,
            ]];
        }

        return $payload;
    }


    // ─────────────────────────────────────────────────────────────────────────────
    // 3. WRITING_BOARD  (whiteboards, chalkboards, cork boards, etc.)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Full standalone payload for WRITING_BOARD product type.
     * item_type_keyword examples: "dry-erase-boards", "chalkboards",
     *   "cork-boards", "magnetic-whiteboards"
     */
    public function writingBoardFullPayload($product, $amazon): array
    {
        $variants   = $this->parseJsonField($product->variants);
        $images     = $this->parseJsonField($product->images);
        $variant    = $variants[0] ?? [];

        $price = (float)($variant['price'] ?? $product->price ?? 29.99);
        if ($price <= 1) $price = 29.99;

        $qty   = (int)($variant['inventory_quantity'] ?? 10);
        $image = $images[0]['src'] ?? 'https://via.placeholder.com/500';

        $bulletPoints = $this->parseJsonField($amazon->bullet_points);
        $searchTerms  = $this->parseJsonField($amazon->search_terms);

        $payload = [
            // ── Identity ──────────────────────────────────────────────────────
            "item_name" => [[
                "value"          => substr(trim($amazon->amazon_title), 0, 200),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "brand" => [[
                "value"          => "ZEBRONICS",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "manufacturer" => [[
                "value"          => "ZEBRONICS",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "model_number" => [[
                "value"          => "ZEB-WB36",
                "marketplace_id" => MKT,
            ]],
            "part_number" => [[
                "value"          => "ZEB-WB36",
                "marketplace_id" => MKT,
            ]],

            // ── Classification ────────────────────────────────────────────────
            "item_type_keyword" => [[
                "value"          => "dry-erase-boards",
                "marketplace_id" => MKT,
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value"          => true,
                "marketplace_id" => MKT,
            ]],

            // ── Content / Copy ────────────────────────────────────────────────
            "product_description" => [[
                "value"          => strip_tags($product->description ?? 'Magnetic dry-erase whiteboard for office and classroom use.'),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($pt) => [
                    "value"          => substr($pt, 0, 500),
                    "language_tag"   => LANG,
                    "marketplace_id" => MKT,
                ])->values()->toArray(),
            "generic_keyword" => [[
                "value"          => substr(implode(' ', $searchTerms), 0, 250),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Board-specific attributes ──────────────────────────────────────
            // Board type: "dry_erase" | "chalkboard" | "cork" | "magnetic"
            "board_type" => [[
                "value"          => "dry_erase",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Surface material
            "surface_description" => [[
                "value"          => "Melamine",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Frame material
            "frame_material" => [[
                "value"          => "Aluminum",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Is it magnetic?
            "is_magnetic" => [[
                "value"          => true,
                "marketplace_id" => MKT,
            ]],
            // Mounting type: "wall_mount" | "freestanding" | "easel"
            "mounting_type" => [[
                "value"          => "wall_mount",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Orientation: "landscape" | "portrait" | "both"
            "orientation" => [[
                "value"          => "landscape",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Board size
            "size" => [[
                "value"          => "36 x 24 Inch",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "color" => [[
                "value"          => "White",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "material" => [[
                "value"          => "Aluminum, Melamine",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Style: "modern" | "classic"
            "style" => [[
                "value"          => "Modern",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Included components
            "included_components" => [[
                "value"          => "Board, Dry Eraser, 3 Markers, Mounting Kit",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "number_of_items" => [[
                "value"          => 1,
                "marketplace_id" => MKT,
            ]],
            "item_package_quantity" => [[
                "value"          => 1,
                "marketplace_id" => MKT,
            ]],

            // ── Physical ──────────────────────────────────────────────────────
            "item_dimensions" => [[
                "length" => ["value" => 36.0, "unit" => "inches"],
                "width"  => ["value" => 24.0, "unit" => "inches"],
                "height" => ["value" => 1.5,  "unit" => "inches"],
                "marketplace_id" => MKT,
            ]],
            "item_weight" => [[
                "value"          => 8.0,
                "unit"           => "pounds",
                "marketplace_id" => MKT,
            ]],
            "item_package_dimensions" => [[
                "length" => ["value" => 38.0, "unit" => "inches"],
                "width"  => ["value" => 26.0, "unit" => "inches"],
                "height" => ["value" => 3.0,  "unit" => "inches"],
                "marketplace_id" => MKT,
            ]],
            "item_package_weight" => [[
                "value"          => 9.5,
                "unit"           => "pounds",
                "marketplace_id" => MKT,
            ]],

            // ── Warranty ──────────────────────────────────────────────────────
            "warranty_description" => [[
                "value"          => "1 Year Manufacturer Warranty",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Compliance ────────────────────────────────────────────────────
            "country_of_origin" => [[
                "value"          => "IN",
                "marketplace_id" => MKT,
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value"          => "not_applicable",
                "marketplace_id" => MKT,
            ]],
            "batteries_required" => [[
                "value"          => false,
                "marketplace_id" => MKT,
            ]],
            "batteries_included" => [[
                "value"          => false,
                "marketplace_id" => MKT,
            ]],

            // ── Offer ─────────────────────────────────────────────────────────
            "condition_type" => [[
                "value"          => "new_new",
                "marketplace_id" => MKT,
            ]],
            "list_price" => [[
                "value"          => $price,
                "currency"       => "USD",
                "marketplace_id" => MKT,
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity"                 => $qty,
            ]],

            // ── Images ────────────────────────────────────────────────────────
            "main_product_image_locator" => [[
                "media_location"  => $image,
                "marketplace_id"  => MKT,
            ]],
        ];

        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) continue;
            $payload["other_product_image_locator_{$index}"] = [[
                "media_location"  => $img['src'],
                "marketplace_id"  => MKT,
            ]];
        }

        return $payload;
    }


    // ─────────────────────────────────────────────────────────────────────────────
    // 4. WRENCH  (hand tools — combination, socket, torque, Allen, pipe wrenches)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Full standalone payload for WRENCH product type.
     * item_type_keyword examples: "combination-wrenches", "socket-wrenches",
     *   "torque-wrenches", "pipe-wrenches", "allen-wrenches"
     */
    public function wrenchFullPayload($product, $amazon): array
    {
        $variants   = $this->parseJsonField($product->variants);
        $images     = $this->parseJsonField($product->images);
        $variant    = $variants[0] ?? [];

        $price = (float)($variant['price'] ?? $product->price ?? 19.99);
        if ($price <= 1) $price = 19.99;

        $qty   = (int)($variant['inventory_quantity'] ?? 10);
        $image = $images[0]['src'] ?? 'https://via.placeholder.com/500';

        $bulletPoints = $this->parseJsonField($amazon->bullet_points);
        $searchTerms  = $this->parseJsonField($amazon->search_terms);

        $payload = [
            // ── Identity ──────────────────────────────────────────────────────
            "item_name" => [[
                "value"          => substr(trim($amazon->amazon_title), 0, 200),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "brand" => [[
                "value"          => "ZEBRONICS",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "manufacturer" => [[
                "value"          => "ZEBRONICS",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "model_number" => [[
                "value"          => "ZEB-WR12",
                "marketplace_id" => MKT,
            ]],
            "part_number" => [[
                "value"          => "ZEB-WR12",
                "marketplace_id" => MKT,
            ]],

            // ── Classification ────────────────────────────────────────────────
            "item_type_keyword" => [[
                "value"          => "combination-wrenches",
                "marketplace_id" => MKT,
            ]],
            "supplier_declared_has_product_identifier_exemption" => [[
                "value"          => true,
                "marketplace_id" => MKT,
            ]],

            // ── Content / Copy ────────────────────────────────────────────────
            "product_description" => [[
                "value"          => strip_tags($product->description ?? 'Heavy-duty combination wrench for professional and home use.'),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "bullet_point" => collect($bulletPoints)
                ->map(fn($pt) => [
                    "value"          => substr($pt, 0, 500),
                    "language_tag"   => LANG,
                    "marketplace_id" => MKT,
                ])->values()->toArray(),
            "generic_keyword" => [[
                "value"          => substr(implode(' ', $searchTerms), 0, 250),
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Wrench-specific attributes ─────────────────────────────────────
            // Wrench type: "combination" | "socket" | "torque" | "pipe" | "allen"
            "wrench_type" => [[
                "value"          => "combination",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Drive size (for socket wrenches): "1/4 inch" | "3/8 inch" | "1/2 inch"
            "drive_size" => [[
                "value"          => "3/8",
                "unit"           => "inches",
                "marketplace_id" => MKT,
            ]],
            // Measurement system: "metric" | "sae" | "both"
            "measurement_system" => [[
                "value"          => "metric",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Wrench size / jaw opening
            "size" => [[
                "value"          => "12mm",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Number of pieces in the set
            "number_of_pieces" => [[
                "value"          => 1,
                "marketplace_id" => MKT,
            ]],
            "number_of_items" => [[
                "value"          => 1,
                "marketplace_id" => MKT,
            ]],
            "item_package_quantity" => [[
                "value"          => 1,
                "marketplace_id" => MKT,
            ]],
            // Material: "chrome_vanadium_steel" | "carbon_steel" | "stainless_steel"
            "material" => [[
                "value"          => "Chrome Vanadium Steel",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Finish: "chrome" | "matte" | "black_oxide" | "polished"
            "finish_type" => [[
                "value"          => "chrome",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            "color" => [[
                "value"          => "Silver",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Handle type: "fixed" | "ratcheting" | "flex_head"
            "handle_type" => [[
                "value"          => "fixed",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Compatible fastener types
            "compatible_devices" => [[
                "value"          => "Nuts, Bolts, Hex Fasteners",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],
            // Included components
            "included_components" => [[
                "value"          => "1 x Combination Wrench",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Physical ──────────────────────────────────────────────────────
            "item_dimensions" => [[
                "length" => ["value" => 7.5,  "unit" => "inches"],
                "width"  => ["value" => 1.0,  "unit" => "inches"],
                "height" => ["value" => 0.5,  "unit" => "inches"],
                "marketplace_id" => MKT,
            ]],
            "item_weight" => [[
                "value"          => 0.3,
                "unit"           => "pounds",
                "marketplace_id" => MKT,
            ]],
            "item_package_dimensions" => [[
                "length" => ["value" => 9.0,  "unit" => "inches"],
                "width"  => ["value" => 2.0,  "unit" => "inches"],
                "height" => ["value" => 1.0,  "unit" => "inches"],
                "marketplace_id" => MKT,
            ]],
            "item_package_weight" => [[
                "value"          => 0.4,
                "unit"           => "pounds",
                "marketplace_id" => MKT,
            ]],

            // ── Warranty ──────────────────────────────────────────────────────
            "warranty_description" => [[
                "value"          => "Lifetime Manufacturer Warranty",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Compliance / Safety ───────────────────────────────────────────
            "country_of_origin" => [[
                "value"          => "IN",
                "marketplace_id" => MKT,
            ]],
            "supplier_declared_dg_hz_regulation" => [[
                "value"          => "not_applicable",
                "marketplace_id" => MKT,
            ]],
            "batteries_required" => [[
                "value"          => false,
                "marketplace_id" => MKT,
            ]],
            "batteries_included" => [[
                "value"          => false,
                "marketplace_id" => MKT,
            ]],
            // Tools may need safety standards
            "safety_warning" => [[
                "value"          => "Keep out of reach of children. Use appropriate safety equipment.",
                "language_tag"   => LANG,
                "marketplace_id" => MKT,
            ]],

            // ── Offer ─────────────────────────────────────────────────────────
            "condition_type" => [[
                "value"          => "new_new",
                "marketplace_id" => MKT,
            ]],
            "list_price" => [[
                "value"          => $price,
                "currency"       => "USD",
                "marketplace_id" => MKT,
            ]],
            "fulfillment_availability" => [[
                "fulfillment_channel_code" => "DEFAULT",
                "quantity"                 => $qty,
            ]],

            // ── Images ────────────────────────────────────────────────────────
            "main_product_image_locator" => [[
                "media_location"  => $image,
                "marketplace_id"  => MKT,
            ]],
        ];

        foreach ($images as $index => $img) {
            if ($index === 0 || $index > 8) continue;
            $payload["other_product_image_locator_{$index}"] = [[
                "media_location"  => $img['src'],
                "marketplace_id"  => MKT,
            ]];
        }

        return $payload;
    }


    // ─────────────────────────────────────────────────────────────────────────────
    // VARIATION DISPATCHER
    // Use this to route to the correct payload builder by product type.
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Route to the correct full-payload builder based on product type string.
     * Add more types here as you expand the catalogue.
     */
    public function getPayloadByType(string $type, $product, $amazon): array
    {
        return match (strtoupper($type)) {
            'WRITING_PAPER'      => $this->writingPaperFullPayload($product, $amazon),
            'WRITING_INSTRUMENT' => $this->writingInstrumentFullPayload($product, $amazon),
            'WRITING_BOARD'      => $this->writingBoardFullPayload($product, $amazon),
            'WRENCH'             => $this->wrenchFullPayload($product, $amazon),
            'COMPUTER_MONITOR'   => $this->monitorFullPayload($product, $amazon),
            default              => throw new \InvalidArgumentException("Unsupported product type: {$type}"),
        };
    }

    /**
     * Generic variant sync that works for all product types above.
     * Variation theme defaults to COLOR; adjust per category if needed
     * (e.g. WRITING_INSTRUMENT might vary on INK_COLOR or TIP_SIZE).
     */
    public function genericVariantPayload(
        $shop,
        $product,
        $amazon,
        string $productType,
        string $variationTheme = 'COLOR'
    ) {
        try {
            $isAccepted = false;
            $variants   = $this->parseJsonField($product->variants);
            $images     = $this->parseJsonField($product->images);

            if (empty($variants)) {
                LOG::error("NO VARIANTS FOUND [{$productType}]", ['product_id' => $product->id]);
                return false;
            }

            $parentSku = DB::table('amazon_products')
                ->where('product_id', $product->id)
                ->value('sku');

            if (!$parentSku) {
                throw new \Exception('Parent SKU not found');
            }

            // Build & clean parent payload
            $parentPayload = $this->getPayloadByType($productType, $product, $amazon);
            unset(
                $parentPayload['list_price'],
                $parentPayload['fulfillment_availability'],
                $parentPayload['main_product_image_locator']
            );
            foreach (array_keys($parentPayload) as $key) {
                if (str_contains($key, 'other_product_image_locator')) {
                    unset($parentPayload[$key]);
                }
            }
            $parentPayload['variation_theme'] = [[
                "name"           => $variationTheme,
                "marketplace_id" => MKT,
            ]];
            $parentPayload['parentage_level'] = [[
                "value"          => "parent",
                "marketplace_id" => MKT,
            ]];

            $parentResponse = $this->putListing($shop, $parentSku, $parentPayload, $product);
            $parentBody     = method_exists($parentResponse, 'dto') ? $parentResponse->dto() : null;
            $isAccepted     = ($parentBody->status ?? null) === 'ACCEPTED';
            LOG::info("{$productType} PARENT RESPONSE", [
                'sku'    => $parentSku,
                'status' => $parentBody->status ?? null,
                'issues' => $parentBody->issues ?? [],
            ]);

            // Children
            foreach ($variants as $variant) {
                $sku   = trim($variant['sku'] ?? (strtoupper($productType) . '-' . $variant['id']));
                $price = (float)($variant['price'] ?? $product->price ?? 9.99);
                if ($price <= 1) $price = 9.99;
                $qty   = (int)($variant['inventory_quantity'] ?? 0);

                // option1 maps to the variation theme dimension (COLOR, SIZE, etc.)
                $optionValue = trim($variant['option1'] ?? 'Default');

                $childPayload = $this->getPayloadByType($productType, $product, $amazon);
                // Strip images from child; re-assign per-variant image below
                unset($childPayload['main_product_image_locator']);
                foreach (array_keys($childPayload) as $key) {
                    if (str_contains($key, 'other_product_image_locator')) {
                        unset($childPayload[$key]);
                    }
                }

                // Per-variant image
                foreach ($images as $img) {
                    if (!empty($img['variant_ids']) && in_array($variant['id'], $img['variant_ids'])) {
                        $childPayload['main_product_image_locator'] = [[
                            "media_location"  => $img['src'],
                            "marketplace_id"  => MKT,
                        ]];
                        break;
                    }
                }

                // Price & inventory
                $childPayload['list_price'] = [[
                    "value"          => $price,
                    "currency"       => "USD",
                    "marketplace_id" => MKT,
                ]];
                $childPayload['fulfillment_availability'] = [[
                    "fulfillment_channel_code" => "DEFAULT",
                    "quantity"                 => $qty,
                ]];

                // Variation dimension — map theme name to attribute key
                $themeAttribute = match (strtoupper($variationTheme)) {
                    'COLOR'    => 'color',
                    'SIZE'     => 'size',
                    'INK_COLOR' => 'ink_color',
                    default    => 'color',
                };
                $childPayload[$themeAttribute] = [[
                    "value"          => ucfirst($optionValue),
                    "language_tag"   => LANG,
                    "marketplace_id" => MKT,
                ]];

                $childPayload['variation_theme'] = [[
                    "name"           => $variationTheme,
                    "marketplace_id" => MKT,
                ]];
                $childPayload['parentage_level'] = [[
                    "value"          => "child",
                    "marketplace_id" => MKT,
                ]];
                $childPayload['child_parent_sku_relationship'] = [[
                    "parent_sku"             => $parentSku,
                    "child_relationship_type" => "variation",
                    "marketplace_id"         => MKT,
                ]];

                $childResponse = $this->putListing($shop, $sku, $childPayload, $product);
                $childBody     = method_exists($childResponse, 'dto') ? $childResponse->dto() : null;
                if (($childBody->status ?? null) !== 'ACCEPTED') {
                    $isAccepted = false;
                }
                LOG::info("{$productType} CHILD RESPONSE", [
                    'sku'    => $sku,
                    'status' => $childBody->status ?? null,
                    'issues' => $childBody->issues ?? [],
                ]);
            }

            $product->update([
                'synced_to_amazon' => $isAccepted ? 1 : 0,
                'needs_resync'     => $isAccepted ? 0 : 1,
            ]);

            ProductSyncLog::create([
                'product_id' => $product->id,
                'shop_id'    => $shop->id,
                'platform'   => 'amazon',
                'status'     => 'success',
                'message'    => "Amazon {$productType} variation sync successful",
                'type'       => 'product',
            ]);

            return response()->json([
                'success' => true,
                'message' => "Amazon {$productType} variation sync successful",
            ]);

        } catch (\Throwable $e) {
            LOG::error("{$productType} VARIANT UPLOAD FAILED", [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ]);
            ProductSyncLog::create([
                'product_id'    => $product->id,
                'shop_id'       => $shop->id,
                'platform'      => 'amazon',
                'status'        => 'error',
                'error_message' => $e->getMessage(),
                'type'          => 'product',
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function parseJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

}