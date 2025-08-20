<?php

namespace App\Http\Controllers;

use App\Helpers\DataTransformer;
use App\Helpers\FileStorage;
use App\Services\CloudVisionService;
use Aws\Textract\TextractClient;
use Illuminate\Http\Request;
use App\Services\TextractService;
use App\Services\MindeeOcrService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ImageController extends Controller
{
    protected $textractService;

    public function __construct(TextractService $textractService)
    {
        $this->textractService = $textractService;
    }

    public function awsOcr(Request $request, TextractService $textract)
    {
        $request->validate([
            'document' => 'required|file|mimes:jpeg,png,pdf|max:5120',
        ]);

        $path = $request->file('document')->store('textract', 'local');
        $fullPath = storage_path('app/private/' . $path);

        // $data = $textract->analyzeId($fullPath);

        $data = $textract->detectDocumentText($fullPath);

        if (!$data) {
            return response()->json(['message' => 'Extraction failed'], 500);
        }

        $data = DataTransformer::extractPersonData($data);

        return response()->json([
            'message' => 'Text extracted successfully',
            'data' => $data
        ]);
    }
}
