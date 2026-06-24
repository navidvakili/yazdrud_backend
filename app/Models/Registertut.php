<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registertut extends Model
{
    use HasFactory;

    protected $table = 'registertuts';

    protected $fillable = [
        'kodmeli',
        'course_id',
        'type',
        'fullname',
        'id_edu',
        'mobile',
        'email',
        'payment_method',
        'bank_receipt',
        'verified_receipt',
        'verified_at',
        'rejected_receipt',
        'rejected_at',
        'rejection_reason',
        'note',
        'certificate_approved',
        'certificate_approved_at',
        'certificate_approved_by',
        'skills',
        'motivation',
        'status',
    ];

    protected $casts = [
        'verified_receipt' => 'boolean',
        'rejected_receipt' => 'boolean',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'certificate_approved' => 'boolean',
        'certificate_approved_at' => 'datetime',
    ];

    /**
     * Get the course that this registration belongs to.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Get the payment record for this registration.
     */
    public function payment()
    {
        return $this->hasOne(RegistertutsPayment::class, 'register_id');
    }

    /**
     * Get the certificate for this registration.
     */
    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'register_id');
    }

    /**
     * Get the actual status of the registration.
     */
    public function getActualStatusAttribute()
    {
        // Online payment successful
        if ($this->payment_method === 'online') {
            $payment = $this->payment;
            if ($payment && $payment->transaction && $payment->transaction->status === 'SUCCEED') {
                return 'paid';
            }
            return 'pending';
        }

        // Bank receipt
        if ($this->payment_method === 'bank') {
            if ($this->verified_receipt) {
                return 'approved';
            }
            if ($this->rejected_receipt) {
                return 'rejected';
            }
            return 'pending';
        }

        return $this->status ?? 'pending';
    }

    /**
     * Get the Persian status text.
     */
    public function getActualStatusTextAttribute()
    {
        $statuses = [
            'pending' => 'در انتظار تایید',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
            'paid' => 'پرداخت شده',
        ];
        return $statuses[$this->actual_status] ?? 'نامشخص';
    }

    /**
     * Get the type text.
     */
    public function getTypeTextAttribute()
    {
        return $this->type == 1 ? 'دانشجوی علم و هنر' : 'دانش‌آموخته';
    }
}
