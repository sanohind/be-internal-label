<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdLabel extends Model
{
    protected $table = 'prod_label';

    protected $fillable = [
        'prod_index',
        'prod_no',
        'prod_status',
        'divisi',
        'partno',
        'lot_no',
        'lot_date',
        'receipt_date',
        'lot_qty',
        'status',
        'print_status',
    ];
}
