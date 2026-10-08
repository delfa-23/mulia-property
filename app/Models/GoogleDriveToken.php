<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleDriveToken extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['token', 'account_email'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted:array',
        ];
    }
}
