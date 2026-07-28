<?php

namespace App\Services\Amazon;

class AmazonRuleSuggestionService
{
    public function getSuggestion(
        string $message
    ): ?string {

        if (
            str_contains(
                $message,
                'Waist Size Value'
            )
        ) {

            return
                'Waist Size is only allowed when Size Class is waist or waist_inseam.';
        }

        if (
            str_contains(
                $message,
                'Inseam Size Value'
            )
        ) {

            return
                'Inseam Size is only allowed when Size Class is waist_inseam.';
        }

        if (
            str_contains(
                $message,
                'Bottoms Height Type'
            )
        ) {

            return
                'Height Type is not available for the current Size Class.';
        }

        if (
            str_contains(
                $message,
                'External Product ID'
            )
        ) {

            return
                'Provide a GTIN/UPC/EAN or enable Product Identifier Exemption.';
        }

        return null;
    }
}