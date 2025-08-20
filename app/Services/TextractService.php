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

    public function parseDocument(array $lines, string $expectedType): array
    {
        $detectedType = $this->detectDocumentType($lines);

        if ($detectedType !== $expectedType) {
            return [
                'status' => 'mismatch',
                'expected' => $expectedType,
                'detected' => $detectedType,
                'message' => "Uploaded document does not match the expected type.",
                'data' => null,
            ];
        }

        $data = null;

        switch ($detectedType) {
            case 'passport':
                $data = $this->parsePassport($lines);
                break;
            case 'license':
                $data = $this->parseDriversLicense($lines);
                break;
            // TODO: add SSS, PhilHealth, etc
            default:
                return [
                    'status' => 'unsupported',
                    'expected' => $expectedType,
                    'detected' => $detectedType,
                    'message' => "Unsupported document type.",
                    'data' => null,
                ];
        }

        return [
            'status' => 'success',
            'expected' => $expectedType,
            'detected' => $detectedType,
            'message' => "Text extracted successfully",
            'data' => $data,
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

            $blocks = $result['Blocks'] ?? [];

            $lines = collect($blocks)
                ->where('BlockType', 'LINE')
                ->pluck('Text')
                ->values()
                ->toArray();

            // Log::info($lines);

            return $lines;

        } catch (AwsException $e) {
            logger()->error('Textract failed: ' . $e->getMessage());
            return null;
        }
    }

    private function detectDocumentType(array $lines): string
    {
        $joined = strtolower(implode(' ', $lines));

        if (preg_match('/passport|pasaporte|mrz|place of birth/i', $joined)) {
            return 'passport';
        }

        if (preg_match('/driver|land transportation|transpo/i', $joined)) {
            return 'license';
        }

        if (preg_match('/sss|social security/i', $joined)) {
            return 'sss';
        }

        if (preg_match('/philhealth/i', $joined)) {
            return 'philhealth';
        }

        // fallback
        return 'invalid';
    }


    public function parseId(array $lines, string $type): array
    {
        switch (strtolower($type)) {
            case 'license':
            case 'driver_license':
                return $this->parseDriversLicense($lines);

            case 'passport':
                return $this->parsePassport($lines);

            case 'sss':
                return $this->parseSss($lines);

            // TODO: add more parsers (philhealth, tin, umid, etc.)
            default:
                return [];
        }
    }

    public function parseDriversLicense(array $lines): array
    {
        // Normalize lines (trim + single spaces)
        $norm = array_map(fn($s) => trim(preg_replace('/\s+/', ' ', (string) $s)), $lines);

        $data = [
            'first_name' => null,
            'last_name' => null,
            'middle_name' => null,
            'date_of_birth' => null,
            'id_number' => null,
            'address' => null,
        ];

        // -------- Name --------
        for ($i = 0; $i < count($norm); $i++) {
            if (stripos($norm[$i], 'Last Name') !== false && isset($norm[$i + 1])) {
                [$last, $rest] = explode(',', $norm[$i + 1], 2);
                [$first, $middle] = $this->splitName($rest);
                $data['last_name'] = ucwords(strtolower(trim($last)));
                $data['first_name'] = $first;
                $data['middle_name'] = $middle;
                break;
            }
        }
        // fallback kung walang label pero may "LAST, FIRST ..."
        if (!$data['first_name'] && !$data['last_name']) {
            foreach ($norm as $line) {
                if (strpos($line, ',') !== false) {
                    [$last, $rest] = explode(',', $line, 2);
                    [$first, $middle] = $this->splitName($rest);
                    $data['last_name'] = ucwords(strtolower(trim($last)));
                    $data['first_name'] = $first;
                    $data['middle_name'] = $middle;
                    break;
                }
            }
        }

        // -------- Date of Birth --------
        for ($i = 0; $i < count($norm) - 6; $i++) {
            if (
                stripos($norm[$i], 'Nationality') !== false &&
                stripos($norm[$i + 1] ?? '', 'Sex') !== false &&
                stripos($norm[$i + 2] ?? '', 'Date of Birth') !== false
            ) {
                $dobCandidate = $norm[$i + 6] ?? null;
                if ($dobCandidate && preg_match('/\d{4}[\/\-]\d{2}[\/\-]\d{2}/', $dobCandidate)) {
                    $data['date_of_birth'] = $dobCandidate;
                    break;
                }
            }
        }
        if (!$data['date_of_birth']) {
            foreach ($norm as $i => $line) {
                if (stripos($line, 'Date of Birth') !== false || stripos($line, 'Birthdate') !== false) {
                    for ($j = $i + 1; $j <= $i + 8 && isset($norm[$j]); $j++) {
                        if (preg_match('/\d{4}[\/\-]\d{2}[\/\-]\d{2}/', $norm[$j])) {
                            $data['date_of_birth'] = $norm[$j];
                            break 2;
                        }
                    }
                }
            }
        }

        // -------- ID Number --------
        for ($i = 0; $i < count($norm); $i++) {
            if (stripos($norm[$i], 'License No') !== false) {
                for ($j = $i + 1; $j <= $i + 6 && isset($norm[$j]); $j++) {
                    $line = $norm[$j];
                    if (stripos($line, 'Expiration') !== false || stripos($line, 'Agency Code') !== false)
                        continue;

                    if (preg_match('/([A-Z0-9\-]{5,})\s+(\d{4}[\/\-]\d{2}[\/\-]\d{2})/i', $line, $m)) {
                        $data['id_number'] = $m[1];
                        break 2;
                    }
                    if (preg_match('/^[A-Z0-9\-]{5,}$/i', $line)) {
                        $data['id_number'] = $line;
                        break 2;
                    }
                }
            }
        }

        // -------- Address --------
        for ($i = 0; $i < count($norm); $i++) {
            if (stripos($norm[$i], 'Address') !== false) {
                $parts = [];
                $stoppers = ['License No', 'DL Codes', 'Expiration', 'Agency Code', 'Conditions'];
                for ($j = $i + 1; $j < count($norm); $j++) {
                    $stop = false;
                    foreach ($stoppers as $s) {
                        if (stripos($norm[$j], $s) !== false) {
                            $stop = true;
                            break;
                        }
                    }
                    if ($stop)
                        break;
                    if (strlen($norm[$j]) >= 3)
                        $parts[] = $norm[$j];
                }
                if ($parts)
                    $data['address'] = implode(', ', $parts);
                break;
            }
        }

        Log::debug('DL parsed:', $data);
        return $data;
    }

    private function parsePassport(array $lines): array
    {
        $norm = array_map(fn($s) => trim(preg_replace('/\s+/', ' ', (string) $s)), $lines);

        $data = [
            'first_name' => null,
            'last_name' => null,
            'middle_name' => null,
            'date_of_birth' => null,
            'id_number' => null,
        ];

        foreach ($norm as $i => $line) {
            // Last name (Apelyido / Surname)
            if (!$data['last_name'] && preg_match('/apelyido|surname/i', $line) && isset($norm[$i + 1])) {
                $data['last_name'] = ucwords(strtolower($norm[$i + 1]));
            }

            // Given names
            if (!$data['first_name'] && preg_match('/pangalan|given/i', $line) && isset($norm[$i + 1])) {
                $data['first_name'] = ucwords(strtolower($norm[$i + 1]));
            }

            // Middle name
            if (!$data['middle_name'] && preg_match('/middle/i', $line) && isset($norm[$i + 1])) {
                $data['middle_name'] = ucwords(strtolower($norm[$i + 1]));
            }

            // Date of Birth
            if (preg_match('/birth|kapanganakan/i', $line)) {
                for ($j = $i + 1; $j <= $i + 3 && isset($norm[$j]); $j++) {
                    if (
                        preg_match('/\d{1,2}\s+[A-Z]{3}\s+\d{4}/i', $norm[$j]) ||
                        preg_match('/\d{4}[\/\-]\d{2}[\/\-]\d{2}/', $norm[$j])
                    ) {
                        $data['date_of_birth'] = $norm[$j];
                        break;
                    }
                }
            }

            // Passport No
            if (preg_match('/pasaporte|passport/i', $line)) {
                for ($j = $i; $j <= $i + 5 && isset($norm[$j]); $j++) {
                    if (preg_match('/^[A-Z0-9]{7,9}$/i', str_replace(' ', '', $norm[$j]))) {
                        $data['id_number'] = str_replace(' ', '', $norm[$j]);
                        break;
                    }
                }
            }
        }

        // Fallback for Passport No → scan all lines
        if (!$data['id_number']) {
            foreach ($norm as $line) {
                if (preg_match('/^[A-Z0-9]{7,9}$/i', str_replace(' ', '', $line))) {
                    $data['id_number'] = str_replace(' ', '', $line);
                    break;
                }
            }
        }

        return $data;
    }






    // -------------------------
    // SSS PARSER (sample, can refine later)
    // -------------------------
    private function parseSss(array $lines): array
    {
        $norm = array_map(fn($s) => trim(preg_replace('/\s+/', ' ', (string) $s)), $lines);

        return [
            'first_name' => null, // depende sa layout ng SSS card
            'last_name' => null,
            'middle_name' => null,
            'date_of_birth' => null,
            'id_number' => $this->findLineContaining($norm, 'SSS No'),
            'address' => null,
        ];
    }

    /**
     * Split name string into [first_name, middle_name]
     * Handles multi-word first names and middle names (Dela Cruz, De Los Santos, etc.)
     */
    private function splitName(string $rest): array
    {
        $parts = preg_split('/\s+/', trim($rest));
        $firstNameParts = [];
        $middleNameParts = [];

        // Common Filipino multi-word middle name starters
        $middlePrefixes = ['DE', 'DEL', 'DELA', 'DE LOS', 'DE LA'];

        if (count($parts) > 1) {
            $middleNameParts[] = array_pop($parts); // last word
            $prev = strtoupper(end($parts));

            // check for known prefixes
            if (in_array($prev, $middlePrefixes)) {
                $middleNameParts[] = array_pop($parts);
            }

            $firstNameParts = $parts;
        } else {
            $firstNameParts = $parts;
        }

        $first = ucwords(strtolower(implode(' ', $firstNameParts)));
        $middle = $middleNameParts ? ucwords(strtolower(implode(' ', array_reverse($middleNameParts)))) : null;

        return [$first, $middle];
    }

    private function findLineContaining(array $lines, string $keyword): ?string
    {
        foreach ($lines as $line) {
            if (stripos($line, $keyword) !== false) {
                return $line;
            }
        }
        return null;
    }
}
