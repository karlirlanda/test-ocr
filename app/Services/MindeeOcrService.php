<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MindeeOcrService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.mindee.net/v1/products/mindee';

    public function __construct()
    {
        $this->apiKey = config('services.mindee.key');
    }

    public function extractFromDriverLicense(string $filePath): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Token ' . env('MINDEE_API_KEY'),
        ])
            ->attach('document', file_get_contents($filePath), basename($filePath))
            ->post("{$this->baseUrl}/driver_license/v1/predict_async");

        if ($response->failed()) {
            return $response;
        }

        return $response['job']['id'] ?? null;
    }

    public function extractFromDriverLicenseResult(string $jobId): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Token ' . env('MINDEE_API_KEY'),
            'Content-Type' => 'multipart/form-data'
        ])->get("{$this->baseUrl}/driver_license/v1/documents/queue/{$jobId}");

        if ($response->failed()) {
            return null;
        }

        return $response['document']['inference']['prediction'] ?? null;
    }

    public function extractFromPassport(string $filePath): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Token ' . env('MINDEE_API_KEY'),
        ])->attach('document', fopen($filePath, 'r'), basename($filePath))
            ->post("{$this->baseUrl}/passport/v1/predict");

        if ($response->failed()) {
            return null;
        }

        return $response['document']['inference']['prediction'] ?? null;
    }
}
