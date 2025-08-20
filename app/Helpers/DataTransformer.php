<?php

namespace App\Helpers;

class DataTransformer
{
    public static function extractPersonData(array $blocks): array
    {
        $fields = [
            'first_name' => null,
            'last_name' => null,
            'birthday' => null,
            'address' => null,
            'id_number' => null,
            'gender' => null,
        ];

        $lines = array_values(array_column(
            array_filter($blocks, fn($b) => $b['BlockType'] === 'LINE'),
            'Text'
        ));

        foreach ($lines as $index => $line) {
            $upper = strtoupper(trim($line));

            if (preg_match('/^([A-ZÑ\s]+),\s*([A-ZÑ\s]+)/u', $upper, $match)) {
                $fields['last_name'] = ucwords(strtolower(trim($match[1])));
                $fields['first_name'] = ucwords(strtolower(trim($match[2])));
            }

            if (stripos($upper, 'DATE OF BIRTH') !== false) {
                for ($i = $index + 1; $i < count($lines); $i++) {
                    if (preg_match('/\b(19|20)\d{2}[\/\-\.]\d{2}[\/\-\.]\d{2}\b/', $lines[$i], $m)) {
                        $fields['birthday'] = $m[0];
                        break;
                    }
                }
            }

            if (stripos($upper, 'ADDRESS') !== false) {
                for ($i = $index + 1; $i < count($lines); $i++) {
                    $next = trim($lines[$i]);
                    if ($next && !preg_match('/(DATE OF BIRTH|SEX|GENDER|WEIGHT|HEIGHT)/i', $next)) {
                        $fields['address'] = ucwords(strtolower($next));
                        break;
                    }
                }
            }

            if (preg_match('/\b[A-Z0-9]{2,3}[-\s]?\d{2}[-\s]?\d{6}\b/', $upper, $m)) {
                $fields['id_number'] = $m[0];
            }

            if (stripos($upper, 'SEX') !== false || stripos($upper, 'GENDER') !== false) {
                for ($i = $index + 1; $i < count($lines); $i++) {
                    if (preg_match('/\b(MALE|FEMALE|M|F)\b/i', $lines[$i], $m)) {
                        $fields['gender'] = strtoupper($m[0]);
                        break;
                    }
                }
            }
        }

        return $fields;
    }

}
