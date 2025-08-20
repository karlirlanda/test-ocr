<?php

namespace App\Services;

use Aws\Exception\AwsException;
use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Log;

class TextractService
{
    protected $client;

    public function __construct()
    {
        $this->client = new TextractClient([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
    }

    public function analyzeId(string $filePath): ?array
    {
        try {
            $result = $this->client->analyzeID([
                'DocumentPages' => [
                    [
                        'Bytes' => file_get_contents($filePath),
                    ],
                ],
            ]);


            // return $result->toArray();

            return $this->formatResponse($result->toArray());
        } catch (\Exception $e) {
            Log::error('Textract AnalyzeID error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract common fields from AnalyzeID response
     */
    protected function formatResponse(array $response): array
    {
        $fields = collect($response['IdentityDocuments'][0]['IdentityDocumentFields'] ?? [])
            ->mapWithKeys(function ($item) {
                $type = $item['Type']['Text'] ?? null;
                $value = $item['ValueDetection']['Text'] ?? null;
                return [$type => $value];
            });

        Log::info($fields);

        return [
            'first_name' => $fields['FIRST_NAME'] ?? null,
            'last_name' => $fields['LAST_NAME'] ?? null,
            'id_number' => $fields['DOCUMENT_NUMBER'] ?? null,
            'dob' => $fields['DATE_OF_BIRTH'] ?? null,
            'expiration' => $fields['EXPIRATION_DATE'] ?? null,
            'gender' => $fields['SEX'] ?? null,
            'address' => $fields['ADDRESS'] ?? null,
            'issuing_date' => $fields['ISSUE_DATE'] ?? null,
        ];
    }

    public function detectDocumentText(string $filePath): ?array
    {
        try {
            $result = $this->client->detectDocumentText([
                'Document' => [
                    'Bytes' => file_get_contents($filePath),
                ],
            ]);

            return $result['Blocks'] ?? [];
        } catch (AwsException $e) {
            logger()->error('Textract failed: ' . $e->getMessage());
            return null;
        }
    }
}
