<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'register_id',
        'certificate_number',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    /**
     * Get the registration associated with this certificate.
     */
    public function registration()
    {
        return $this->belongsTo(Registertut::class, 'register_id');
    }

    /**
     * Generate a certificate number combining course ID and registration ID.
     * Format: {courseId}-{registerId}  (e.g., "12-34")
     */
    public static function generateCertificateNumber(int $courseId, int $registerId): string
    {
        return $courseId . '-' . $registerId;
    }
}
