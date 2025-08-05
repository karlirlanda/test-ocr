<?php

namespace App\Http\Controllers;


use App\Models\Image;
use App\Helpers\OcrService;
use App\Helpers\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ImageController extends Controller
{
    public function read(Request $request, OcrService $ocrService)
    {
        $file = $request->image;
        $path = $file->store('ids');
        $fullPath = storage_path("app/private/{$path}");

        $data = $ocrService->extractFromIdCard($fullPath);

        return response()->json($data);
    }

    public function get(Request $request)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Token ' . env('MINDEE_API_KEY'),
        ])->get("https://api.mindee.net/v1/products/mindee/international_id/v2/documents/queue/5fc63e59-0268-4cf4-a3fc-b6dbd60a3d1a");

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
