<?php

namespace App\Http\Controllers;

use App\Helpers\FileStorage;
use Illuminate\Http\Request;
use App\Services\TextractService;
use Illuminate\Support\Facades\Log;
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

        try {
            $request->validate([
                'document' => ['required', 'file', 'mimes:jpeg,png,pdf', 'max:5120'],
                'type' => ['string', 'in:passport,license']
            ]);

            $path = FileStorage::upload($request->file('document'), 'textract');

            $fullPath = storage_path('app/private/' . $path);

            $text = $textract->detectDocumentText($fullPath);

            if (!$text) {
                return response()->json(['message' => 'Extraction failed'], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $idValidation = $textract->parseDocument($text, $request->type);



            if ($idValidation['status'] === 'mismatch') {
                return response()->json(['message' => 'ID and Type mismatch'], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $data = $textract->parseId($text, $request->type);


            return response()->json([
                'message' => 'Text extracted successfully',
                'data' => $data
            ], HttpResponse::HTTP_OK);

        } catch (\Exception $e) {
            Log::info($e);

            return response()->json([
                'message' => 'API failed ',
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

}
