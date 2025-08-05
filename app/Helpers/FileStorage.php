<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorage
{
    public static function upload($file, $type)
    {
        $fileExtension = $file->extension();

        $fileName = Str::random(8) . Carbon::now()->format('YmdHisu') . '.' . $fileExtension;

        Storage::put('image/' . $type . '/' . $fileName, file_get_contents($file));

        return 'image/' . $type . '/' . $fileName;
    }

    public static function delete($path)
    {
        isset($path) ? Storage::delete($path) : null;
    }
}
