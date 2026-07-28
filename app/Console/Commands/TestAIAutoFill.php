<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSchema;
use App\Services\AmazonSchemaFilterService;
use App\Services\AI\AIPromptBuilderService;
use app\Services\AIConfigurationService;
use App\Services\AI\AIListingService;
use Illuminate\Console\Command;

class TestAIAutoFill extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:test-a-i-auto-fill';

    /**
     * The console command description.
     */
    protected $description = 'Test AI Listing Pipeline';

    public function __construct(
        private readonly AmazonSchemaFilterService $filterService,
        private readonly AIPromptBuilderService $promptBuilder,
        private readonly AIListingService $listingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // STEP 1 - Load Product
        $product = Product::findOrFail(226);

        // STEP 2 - Load Product Schema
        $schema = ProductSchema::where(
            'product_type',
            $product->product_type
        )->firstOrFail();

        // STEP 3 - Load Amazon PTD Schema
        $resolvedSchema = $schema->schema_json;

        if (!is_array($resolvedSchema)) {
            $this->error('Resolved schema is not a valid array.');

            return self::FAILURE;
        }

        // STEP 4 - Classify Schema
        $classification = $this->filterService->classify($resolvedSchema);

        $this->info('✓ Schema classified');

        // STEP 5 - Build Prompt
        $prompts = $this->promptBuilder->buildPrompt(
            $product->toArray(),
            $classification,
            [],
            $product->product_type
        );

        $this->info('✓ Prompt generated');

        // STEP 6 - Call OpenAI
        $response = $this->listingService->generate(
            $prompts['system_prompt'],
            $prompts['user_prompt']
        );

        if (!$response['success']) {

            $this->error('AI Generation Failed');

            dd($response);

            return self::FAILURE;
        }

        $this->info('✓ AI Response Received');

        dd([
            'success' => $response['success'],

            'generated_fields' => array_keys($response['content'] ?? []),

            'generated_count' => count($response['content'] ?? []),

            'usage' => $response['usage'],

            'response' => $response['content'],
        ]);

        return self::SUCCESS;
    }
}