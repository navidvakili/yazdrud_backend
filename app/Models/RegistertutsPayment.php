<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistertutsPayment extends Model
{
    use HasFactory;

    protected $table = 'registertuts_payments';

    protected $fillable = [
        'transaction_id',
        'register_id',
    ];

    /**
     * Get the transaction for this payment.
     */
    public function transaction()
    {
        return $this->belongsTo(GatewayTransaction::class, 'transaction_id');
    }

    /**
     * Get the registration for this payment.
     */
    public function registration()
    {
        return $this->belongsTo(Registertut::class, 'register_id');
    }
}
