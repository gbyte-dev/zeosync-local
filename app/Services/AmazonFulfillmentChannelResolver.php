<?php

namespace App\Services;

use App\Models\ProductMarketplaceMapping;
use Illuminate\Support\Facades\Log;

class AmazonFulfillmentChannelResolver
{
    /**
     * Resolve the authoritative fulfillment channel for an Amazon SKU / operation.
     *
     * @param ProductMarketplaceMapping|null $mapping
     * @param int|null $expectedQuantity
     * @param array $listing Amazon GET listing response
     * @return array [
     *     'channel'            => ?string,
     *     'source'             => string, // 'persisted_mapping', 'quantity_match', 'unresolved', 'ambiguous'
     *     'matching_channels'  => array,
     *     'available_channels' => array,
     * ]
     */
    public function resolve(
        ?ProductMarketplaceMapping $mapping,
        ?int $expectedQuantity,
        array $listing
    ): array {
        $sku = $mapping?->amazon_sku ?? 'UNKNOWN';

        // 1. PRIORITY 1: Persisted authoritative channel from mapping
        if (!empty($mapping?->fulfillment_channel_code)) {
            $persistedChannel = (string) $mapping->fulfillment_channel_code;
            $available = $this->extractChannelsSummary($listing);

            Log::info('[AMAZON_CHANNEL_RESOLUTION]', [
                'amazon_sku'        => $sku,
                'expected_quantity' => $expectedQuantity,
                'persisted_channel' => $persistedChannel,
                'resolved_channel'  => $persistedChannel,
                'source'            => 'persisted_mapping',
                'available_channels'=> $available,
            ]);

            return [
                'channel'            => $persistedChannel,
                'source'             => 'persisted_mapping',
                'matching_channels'  => [$persistedChannel],
                'available_channels' => $available,
            ];
        }

        // 2. PRIORITY 2: Fallback for existing/legacy products without persisted channel
        $normalizedChannels = $this->extractNormalizedChannels($listing);
        $availableSummary = $this->extractChannelsSummary($listing);

        if ($expectedQuantity === null) {
            $uniqueCodes = array_values(array_unique(array_filter(array_column($normalizedChannels, 'fulfillment_channel_code'))));
            if (count($uniqueCodes) === 1) {
                $singleChannel = (string) $uniqueCodes[0];
                Log::info('[AMAZON_CHANNEL_RESOLUTION]', [
                    'amazon_sku'        => $sku,
                    'expected_quantity' => $expectedQuantity,
                    'persisted_channel' => null,
                    'resolved_channel'  => $singleChannel,
                    'source'            => 'quantity_match',
                    'available_channels'=> $availableSummary,
                ]);

                return [
                    'channel'            => $singleChannel,
                    'source'             => 'quantity_match',
                    'matching_channels'  => [$singleChannel],
                    'available_channels' => $availableSummary,
                ];
            }

            Log::warning('[AMAZON_CHANNEL_RESOLUTION]', [
                'amazon_sku'        => $sku,
                'expected_quantity' => $expectedQuantity,
                'persisted_channel' => null,
                'resolved_channel'  => null,
                'source'            => 'unresolved',
                'available_channels'=> $availableSummary,
            ]);

            return [
                'channel'            => null,
                'source'             => 'unresolved',
                'matching_channels'  => [],
                'available_channels' => $availableSummary,
            ];
        }

        // Match against expected quantity
        $matching = [];
        foreach ($normalizedChannels as $entry) {
            $code = $entry['fulfillment_channel_code'] ?? null;
            $qty = $entry['quantity'] ?? null;
            if ($code !== null && $qty !== null && (int) $qty === (int) $expectedQuantity) {
                $matching[] = (string) $code;
            }
        }
        $matching = array_values(array_unique($matching));

        // 3. Exactly ONE channel matches expected quantity -> RESOLVE & PERSIST
        if (count($matching) === 1) {
            $resolvedChannel = $matching[0];

            Log::info('[AMAZON_CHANNEL_RESOLUTION]', [
                'amazon_sku'        => $sku,
                'expected_quantity' => $expectedQuantity,
                'persisted_channel' => null,
                'resolved_channel'  => $resolvedChannel,
                'source'            => 'quantity_match',
                'available_channels'=> $availableSummary,
            ]);

            // Safely backfill mapping if available
            if ($mapping && empty($mapping->fulfillment_channel_code)) {
                try {
                    $mapping->update(['fulfillment_channel_code' => $resolvedChannel]);
                } catch (\Throwable) {
                    // Non-fatal if DB write fails
                }
            }

            return [
                'channel'            => $resolvedChannel,
                'source'             => 'quantity_match',
                'matching_channels'  => $matching,
                'available_channels' => $availableSummary,
            ];
        }

        // 4. ZERO channels match -> UNRESOLVED (mismatch / continue retry)
        if (count($matching) === 0) {
            $distinct = array_values(array_unique(array_filter(array_column($normalizedChannels, 'fulfillment_channel_code'))));
            $reportingChannel = count($distinct) === 1 ? $distinct[0] : null;

            Log::info('[AMAZON_CHANNEL_RESOLUTION]', [
                'amazon_sku'        => $sku,
                'expected_quantity' => $expectedQuantity,
                'persisted_channel' => null,
                'resolved_channel'  => $reportingChannel,
                'source'            => 'unresolved',
                'available_channels'=> $availableSummary,
            ]);

            return [
                'channel'            => $reportingChannel,
                'source'             => 'unresolved',
                'matching_channels'  => [],
                'available_channels' => $availableSummary,
            ];
        }

        // 5. MORE THAN ONE channel matches -> AMBIGUOUS (Do NOT guess, Do NOT use [0], Do NOT default)
        Log::warning('[AMAZON_CHANNEL_RESOLUTION]', [
            'amazon_sku'        => $sku,
            'expected_quantity' => $expectedQuantity,
            'persisted_channel' => null,
            'resolved_channel'  => null,
            'source'            => 'ambiguous',
            'matching_channels' => $matching,
            'available_channels'=> $availableSummary,
        ]);

        return [
            'channel'            => null,
            'source'             => 'ambiguous',
            'matching_channels'  => $matching,
            'available_channels' => $availableSummary,
        ];
    }

    /**
     * Extract normalized list of channel objects from any Amazon GET listing format.
     */
    public function extractNormalizedChannels(array $listing): array
    {
        $channels = [];

        // 1. Top-level fulfillmentAvailability
        if (!empty($listing['fulfillmentAvailability']) && is_array($listing['fulfillmentAvailability'])) {
            foreach ($listing['fulfillmentAvailability'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $code = $entry['fulfillmentChannelCode'] ?? $entry['fulfillment_channel_code'] ?? 'DEFAULT';
                $qty = $entry['quantity'] ?? null;
                $channels[] = [
                    'fulfillment_channel_code' => (string) $code,
                    'quantity'                 => $qty !== null ? (int) $qty : null,
                ];
            }
        }

        // 2. Attributes fulfillment_availability
        if (!empty($listing['attributes']['fulfillment_availability']) && is_array($listing['attributes']['fulfillment_availability'])) {
            foreach ($listing['attributes']['fulfillment_availability'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $code = $entry['fulfillment_channel_code'] ?? $entry['fulfillmentChannelCode'] ?? 'DEFAULT';
                $qty = $entry['quantity'] ?? null;
                $channels[] = [
                    'fulfillment_channel_code' => (string) $code,
                    'quantity'                 => $qty !== null ? (int) $qty : null,
                ];
            }
        }

        return $channels;
    }

    /**
     * Summary strings for logging.
     */
    public function extractChannelsSummary(array $listing): array
    {
        return array_map(
            fn ($c) => ($c['fulfillment_channel_code'] ?? 'null') . '=' . ($c['quantity'] ?? 'null'),
            $this->extractNormalizedChannels($listing)
        );
    }
}
