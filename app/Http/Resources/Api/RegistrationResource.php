<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray($request)
    {
        $statusMap = [
            'paid'     => 'accepted',
            'approved' => 'accepted',
            'rejected' => 'rejected',
            'pending'  => 'pending',
        ];

        $frontStatus = $statusMap[$this->actual_status] ?? 'pending';

        return [
            'id'                => 'SAU-' . str_pad($this->id, 5, '0', STR_PAD_LEFT),
            'courseId'          => (string) $this->course_id,
            'courseTitle'       => $this->course?->title ?? 'دوره آموزشی',
            'fullName'          => $this->fullname,
            'nationalId'        => $this->kodmeli,
            'phoneNumber'       => $this->mobile,
            'email'             => $this->email ?? '',
            'educationLevel'    => $this->getEducationLevel(),
            'universityRelation'=> $this->type == 1 ? 'student' : ($this->type == 2 ? 'alumni' : 'external'),
            'fieldOfStudy'      => '',
            'skills'            => $this->skills ?? '',
            'motivation'        => $this->motivation ?? '',
            'status'            => $frontStatus,
            'adminNotes'        => $this->getAdminNotes(),
            'payment_method'    => $this->payment_method,
            'actual_status'     => $this->actual_status,
            'createdAt'         => $this->created_at ? $this->created_at->toISOString() : '',
            'created_at'        => $this->created_at ? $this->created_at->toISOString() : '',
        ];
    }

    private function getEducationLevel(): string
    {
        return 'کارشناسی';
    }

    private function getAdminNotes(): string
    {
        if ($this->verified_receipt) {
            return 'مدارک تایید شده است. ثبت نام نهایی گردید.';
        }
        if ($this->rejected_receipt) {
            return 'مدرک بانکی رد شد. دلیل: ' . ($this->rejection_reason ?? 'نامشخص');
        }
        if ($this->payment_method === 'online') {
            return 'پرداخت آنلاین انجام شده است. در انتظار تایید نهایی.';
        }
        return 'پیش‌ثبت‌نام شما با موفقیت ذخیره شد. در انتظار تایید فیش بانکی.';
    }
}
