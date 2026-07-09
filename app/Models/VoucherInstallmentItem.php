<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherInstallmentItem extends Model
{
    use HasFactory;

    protected $table = 'voucher_installment_items';

    protected $fillable = [
        'term_coupon_id',
        'title',
        'amount',
        'due_date',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the coupon that this installment item belongs to.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(TermCoupon::class, 'term_coupon_id');
    }

    /**
     * Get the registration installments created from this template item.
     */
    public function registrationInstallments(): HasMany
    {
        return $this->hasMany(RegistrationInstallment::class, 'voucher_installment_item_id');
    }
}
