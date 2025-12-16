<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProdHeader extends Model
{
    use HasFactory;

    protected $table = 'prod_header';

    protected $fillable = [
        'prod_index',
        'prod_no',
        'planning_date',
        'item',
        'old_partno',
        'description',
        'mat_desc',
        'customer',
        'model',
        'unique_no',
        'sanoh_code',
        'snp',
        'sts',
        'status',
        'qty_order',
        'qty_delivery',
        'qty_os',
        'warehouse',
        'divisi',
    ];

    /**
     * Get the prod labels for the prod header.
     */
    public function prodLabels()
    {
        return $this->hasMany(ProdLabel::class, 'prod_no', 'prod_no');
    }
}
