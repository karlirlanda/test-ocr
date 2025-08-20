<?php

// app/Services/CloudVisionService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;

class CloudVisionService
{
    protected function getAccessToken(): string
    {
        $client = new GoogleClient();
        $client->setAuthConfig(env('GOOGLE_APPLICATION_CREDENTIALS'));
        $client->addScope('https://www.googleapis.com/auth/cloud-vision');

        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }

    public function annotateImage(string $filePath): ?array
    {
        $imageContent = base64_encode(file_get_contents($filePath));

        $payload = [
            'requests' => [
                [
                    'image' => ['content' => $imageContent],
                    'features' => [
                        ['type' => 'DOCUMENT_TEXT_DETECTION']
                    ]
                ]
            ]
        ];

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post('https://vision.googleapis.com/v1/images:annotate', $payload);

        Log::info($response);

        if ($response->failed()) {
            return null;
        }

        return $response->json()['responses'][0] ?? null;
    }
}
