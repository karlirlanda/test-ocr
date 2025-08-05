<?php
// app/Helpers/OcrService.php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;


class OcrService
{
    public function extractFromIdCard(string $imagePath): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Token ' . env('MINDEE_API_KEY'),
            // 'Content-Type' => 'multipart/form-data',
        ])->attach(
                'document',
                file_get_contents($imagePath),
                basename($imagePath)
            )->post('https://api.mindee.net/v1/products/mindee/international_id/v2/predict_async');

        if (!$response->successful()) {
            logger()->error('Mindee API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => 'Failed to fetch OCR data.'];
        } else {
            return $response->json();
        }
    }
}
