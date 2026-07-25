<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GatewayTransaction extends Model
{
    use HasFactory;

    protected $table = 'gateway_transactions';

    protected $fillable = [
        'username',
        'type',
        'status',
        'port',
        'price',
        'ref_id',
        'tracking_code',
        'card_number',
        'ip',
        'description',
        'payment_date',
    ];

    /**
     * Get the user for this transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}
