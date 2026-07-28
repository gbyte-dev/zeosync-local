<?php
namespace App\Services\Amazon;
use App\Services\Amazon\AmazonRuleSuggestionService;
class AmazonIssueNormalizer
{
    protected AmazonRuleSuggestionService $suggestions;
    public function __construct(
        AmazonRuleSuggestionService $suggestions
    ) {
        $this->suggestions = $suggestions;
    }
    public function normalize(array $issues): array
    {
        return collect($issues)
            ->map(function ($issue) {
                $field =
                    $issue['attributeNames'][0]
                    ?? 'unknown';
                return [
                    'field' => $field,
                    'code' =>
                    $issue['code']
                        ?? null,
                    'severity' =>
                    $issue['severity']
                        ?? 'ERROR',
                    'message' =>
                    $this->makeReadable(
                        $issue['message'] ?? ''
                    ),
                    'suggestion' =>
                    $this->suggestions
                        ->getSuggestion(
                            $issue['message'] ?? ''
                        )
                ];
            })
            ->toArray();
    }
    private function makeReadable(
        string $message
    ): string {
        if (
            str_contains(
                $message,
                'not allowed'
            )
        ) {
            preg_match(
                "/attribute '([^']+)'/",
                $message,
                $matches
            );
            $attribute =
                $matches[1]
                ?? 'Field';
            return "{$attribute} is not valid for the current selections.";
        }
        if (
            str_contains(
                $message,
                'required but missing'
            )
        ) {
            preg_match(
                "/'([^']+)'/",
                $message,
                $matches
            );
            $field =
                $matches[1]
                ?? 'Field';
            return "{$field} is required.";
        }
        return $message;
    }
}
