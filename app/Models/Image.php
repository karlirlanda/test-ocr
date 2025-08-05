<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $connection = 'mysql';

    protected $table = 'images';

    protected $fillables = [
        'name',
        'value'
    ];
}
