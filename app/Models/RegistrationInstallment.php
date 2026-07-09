<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationInstallment extends Model
{
    use HasFactory;

    protected $table = 'registration_installments';

    protected $fillable = [
        'register_id',
        'voucher_installment_item_id',
        'title',
        'amount',
        'due_date',
        'payment_method',
        'status',
        'paid_at',
        'paid_amount',
        'tracking_number',
        'verified_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the registration that this installment belongs to.
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registertut::class, 'register_id');
    }

    /**
     * Get the voucher installment item template (if any).
     */
    public function voucherInstallmentItem(): BelongsTo
    {
        return $this->belongsTo(VoucherInstallmentItem::class, 'voucher_installment_item_id');
    }

    /**
     * Get the admin user who verified this installment.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope a query to only include pending installments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include paid installments.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope a query to only include overdue installments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Get the status text in Persian.
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'در انتظار پرداخت',
            'paid'    => 'پرداخت شده',
            'overdue' => 'معوق',
            default   => 'نامشخص',
        };
    }

    /**
     * Get the payment method text in Persian.
     */
    public function getPaymentMethodTextAttribute(): string
    {
        return $this->payment_method === 'online' ? 'آنلاین' : 'آفلاین';
    }

    /**
     * Get the formatted amount.
     */
    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount) . ' ریال';
    }
}
