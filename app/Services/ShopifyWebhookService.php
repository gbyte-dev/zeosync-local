<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyWebhookService
{
    public function buildOrdersCreateWebhookUrl(): string
    {
        return rtrim($this->publicAppUrl(), '/')
            . route('shopify.webhooks.orders.create', [], false);
    }

    public function buildAppUninstalledWebhookUrl(): string
    {
        $url = rtrim($this->publicAppUrl(), '/')
            . route('shopify.webhooks.app.uninstalled', [], false);

        \Log::info('FINAL UNINSTALL URL', [
            'public_app_url' => $this->publicAppUrl(),
            'route' => route('shopify.webhooks.app.uninstalled', [], false),
            'final_url' => $url,
        ]);

        return $url;
    }

    public function ensureOrdersCreateWebhook(Shop $shop): void
    {
        \Log::info('INSIDE ORDERS WEBHOOK FUNCTION');
        $targetUrl = $this->buildOrdersCreateWebhookUrl();
        $existingWebhook = $this->findOrdersCreateWebhook($shop, $targetUrl);

        if ($existingWebhook) {
            return;
        }

        $response = $this->graphQl(
            $shop,
            <<<'GRAPHQL'
mutation WebhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
  webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
    webhookSubscription {
      id
      topic
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL,
            [
                'topic' => 'ORDERS_CREATE',
                'webhookSubscription' => [
                    'callbackUrl' => $targetUrl,
                ],
            ]
        );

        $errors = data_get($response, 'data.webhookSubscriptionCreate.userErrors', []);

        if (!empty($errors)) {
            $message = collect($errors)->pluck('message')->filter()->implode(' ');
            throw new RuntimeException($message !== '' ? $message : 'Unable to create Shopify orders webhook subscription.');
        }
    }

    public function ensureAppUninstalledWebhook(Shop $shop): void
    {
        \Log::info('INSIDE UNINSTALL WEBHOOK FUNCTION');
        $targetUrl = $this->buildAppUninstalledWebhookUrl();

        \Log::info('UNINSTALL WEBHOOK URL', ['url' => $targetUrl]);

        $response = $this->graphQl(
            $shop,
            <<<'GRAPHQL'
mutation webhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
  webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
    webhookSubscription {
      id
      topic
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL,
            [
                'topic' => 'APP_UNINSTALLED',
                'webhookSubscription' => [
                    'callbackUrl' => $targetUrl,
                ],
            ]
        );
    }
    public function isValidWebhook(string $payload, ?string $hmacHeader): bool
    {
        $hmacHeader = trim((string) $hmacHeader);

        if ($hmacHeader === '') {
            return false;
        }

        $secret = (string) config('services.shopify.api_secret');
        $calculated = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($calculated, $hmacHeader);
    }

    private function findOrdersCreateWebhook(Shop $shop, string $targetUrl): ?array
    {
        $response = $this->graphQl(
            $shop,
            <<<'GRAPHQL'
query OrdersCreateWebhooks {
  webhookSubscriptions(first: 20, topics: [ORDERS_CREATE]) {
    edges {
      node {
        id
        topic
        endpoint {
          __typename
          ... on WebhookHttpEndpoint {
            callbackUrl
          }
        }
      }
    }
  }
}
GRAPHQL
        );

        return collect(data_get($response, 'data.webhookSubscriptions.edges', []))
            ->pluck('node')
            ->first(function ($webhook) use ($targetUrl) {
                return data_get($webhook, 'endpoint.callbackUrl') === $targetUrl;
            });
    }

    private function graphQl(Shop $shop, string $query, array $variables = []): array
    {
        $payload = ['query' => $query];

        if (!empty($variables)) {
            $payload['variables'] = (object) $variables;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => $shop->access_token,
        ])->post(
            sprintf(
                'https://%s/admin/api/%s/graphql.json',
                $shop->shop,
                config('services.shopify.api_version', '2026-01')
            ),
            $payload
        );

        //  LOG RAW RESPONSE
        \Log::info('SHOPIFY GRAPHQL RAW RESPONSE', [
            'body' => $response->body()
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('HTTP ERROR: ' . $response->status());
        }

        $payload = $response->json();

        //  Top-level GraphQL errors
        if (!empty($payload['errors'])) {
            $message = collect($payload['errors'])
                ->map(fn($error) => is_array($error) ? ($error['message'] ?? json_encode($error)) : (string) $error)
                ->implode(' ');

            throw new \RuntimeException($message !== '' ? $message : 'GraphQL top-level error');
        }

        //  IMPORTANT: Shopify userErrors detect kar
        $userErrors = data_get($payload, 'data.webhookSubscriptionCreate.userErrors', []);

        if (!empty($userErrors)) {
            \Log::error('SHOPIFY USER ERRORS', $userErrors);

            $message = collect($userErrors)
                ->pluck('message')
                ->implode(' ');

            throw new \RuntimeException($message ?: 'Shopify user error');
        }

        return $payload;
    }

    private function publicAppUrl(): string
    {
        $appUrl = trim((string) config('services.shopify.app_url'));

        if ($appUrl !== '' && !str_contains($appUrl, 'localhost')) {
            return $appUrl;
        }

        $redirectUri = trim((string) config('services.shopify.redirect_uri'));

        if ($redirectUri !== '') {
            $scheme = parse_url($redirectUri, PHP_URL_SCHEME);
            $host = parse_url($redirectUri, PHP_URL_HOST);
            $port = parse_url($redirectUri, PHP_URL_PORT);

            if ($scheme && $host) {
                return $scheme . '://' . $host . ($port ? ':' . $port : '');
            }
        }

        return $appUrl !== '' ? $appUrl : rtrim((string) config('app.url'), '/');
    }
}
