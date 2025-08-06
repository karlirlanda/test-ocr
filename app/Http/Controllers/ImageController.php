<?php

namespace App\Http\Controllers;

use App\Helpers\FileStorage;
use Illuminate\Http\Request;
use App\Services\MindeeOcrService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ImageController extends Controller
{
    public function store(Request $request, MindeeOcrService $mindee)
    {
        $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'type' => 'required|in:license,passport',
        ]);

        $file = $request->file('document');

        $type = $request->input('type');

        $path = FileStorage::upload($file, 'mindee');

        $fullPath = storage_path('app/private/' . $path);

        if ($type === 'license') {
            $jobId = $mindee->extractFromDriverLicense($fullPath);

            return response()->json([
                'type' => 'license',
                'job_id' => $jobId,
                'message' => 'Async request accepted. Use job_id to check result.',
            ], HttpResponse::HTTP_OK);
        }

        if ($request->type === 'passport') {
            $data = $mindee->extractFromPassport($fullPath);

            return response()->json([
                'type' => 'passport',
                'data' => [
                    'birth_date' => $data['birth_date']['value'] ?? null,
                    'birth_place' => $data['birth_place']['value'] ?? null,
                    'country' => $data['country']['value'] ?? null,
                    'expiry_date' => $data['expiry_date']['value'] ?? null,
                    'gender' => $data['gender']['value'] ?? null,
                    'first_name' => implode(' ', array_filter([
                        $data['given_names'][0]['value'] ?? null,
                        $data['given_names'][1]['value'] ?? null,
                    ])),
                    'last_name' => $data['surname']['value'] ?? null,
                    'id' => $data['id_number']['value'] ?? null,
                    'issuance_date' => $data['issuance_date']['value'] ?? null,
                ]
            ], HttpResponse::HTTP_OK);
        }

        return response()->json(['error' => 'Invalid document type'], HttpResponse::HTTP_BAD_REQUEST);
    }

    public function show(Request $request, MindeeOcrService $mindee)
    {
        $request->validate([
            'type' => 'required|in:license',
            'job_id' => 'required_if:type,license',
        ]);

        $type = $request->input('type');
        $jobId = $request->input('job_id');

        if ($type === 'license') {
            $data = $mindee->extractFromDriverLicenseResult($jobId);

            return response()->json([
                'type' => 'license',
                'data' => [
                    'restrictions' => $data['category']['value'] ?? null,
                    'date_of_birth' => $data['date_of_birth']['value'] ?? null,
                    'expiry_date' => $data['expiry_date']['value'] ?? null,
                    'first_name' => $data['first_name']['value'] ?? null,
                    'last_name' => $data['last_name']['value'] ?? null,
                    'id' => $data['id']['value'] ?? null,
                    'issued_date' => $data['issued_date']['value'] ?? null,
                ],
            ], HttpResponse::HTTP_OK);
        }

        return response()->json(['error' => 'Invalid request.'], 400);
    }
}
