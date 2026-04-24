<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uber_order_id',
        'display_id',
        'status',
        'customer_name',
        'total',
        'raw_json'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
