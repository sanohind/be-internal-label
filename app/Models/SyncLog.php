<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_type',
        'prod_index',
        'records_synced',
        'status',
        'message',
        'error_details',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}
