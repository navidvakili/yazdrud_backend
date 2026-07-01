<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;

class CourseResource extends JsonResource
{
    public function toArray($request)
    {
        $remaining = $this->capacity > 0
            ? $this->capacity - $this->registered_count
            : -1;

        if ($this->active && ($this->capacity == 0 || $remaining > 0)) {
            $status = 'active';
        } elseif ($this->active && $remaining <= 0) {
            $status = 'full';
        } else {
            $status = 'soon';
        }

        // Determine registration period status
        $today = Carbon::today();
        $regStatus = 'none';
        if ($this->registration_start_date && $this->registration_end_date) {
            if ($this->registration_start_date > $today) {
                $regStatus = 'before';
            } elseif ($this->registration_end_date < $today) {
                $regStatus = 'after';
            } else {
                $regStatus = 'open';
            }
        } elseif ($this->registration_start_date && !$this->registration_end_date) {
            $regStatus = $this->registration_start_date <= $today ? 'open' : 'before';
        } elseif (!$this->registration_start_date && $this->registration_end_date) {
            $regStatus = $this->registration_end_date >= $today ? 'open' : 'after';
        }

        return [
            'id'              => (string) $this->id,
            'title'           => $this->title,
            'category'        => $this->determineCategory(),
            'mentor'          => $this->instructor ?? 'مربی',
            'duration'        => $this->duration ? $this->duration . ' ساعت' : 'نامشخص',
            'price'           => (int) $this->amount,
            'capacity'        => (int) $this->capacity,
            'registeredCount' => (int) $this->registered_count,
            'startDate'       => $this->start_date ? $this->formatShamsiDate($this->start_date) : 'نامشخص',
            'end_date'        => $this->end_date ? $this->formatShamsiDate($this->end_date) : null,
            'daysOfWeek'      => $this->days_of_week ?? [],
            'courseTime'      => $this->course_time ?? '',
            'location'        => $this->location ?? '',
            'description'     => $this->description ?? '',
            'syllabus'        => $this->parseSyllabus(),
            'prerequisites'   => $this->parsePrerequisites(),
            'status'          => $status,
            'banner'          => $this->getBannerGradient(),
            'image_url'       => $this->image_url,
            'sections'             => $this->sections ?? ['normal'],
            'registration_status'  => $regStatus,
            'group_id'             => $this->group_id,
            'group_title'     => $this->group?->title,
            'instructor_name' => $this->courseInstructor?->name ?? $this->instructor,
            'instructor_id'   => $this->instructor_id,
            'registration_start_date' => $this->registration_start_date
                ? $this->formatShamsiDate($this->registration_start_date)
                : null,
            'registration_end_date'   => $this->registration_end_date
                ? $this->formatShamsiDate($this->registration_end_date)
                : null,
            'created_at'      => $this->created_at ? $this->created_at->toISOString() : null,
        ];
    }

    private function determineCategory(): string
    {
        $title = $this->title ?? '';
        if (preg_match('/آی‌تی|فناوری|کامپیوتر|برنامه‌نویسی|دیجیتال|react|برنامه نویسی/i', $title)) {
            return 'it';
        }
        if (preg_match('/استارت‌آپ|کارآفرینی|کسب‌وکار|استارتاپ/i', $title)) {
            return 'startup';
        }
        if (preg_match('/هنر|طراحی|بسته‌بندی|معماری/i', $title)) {
            return 'design';
        }
        if (preg_match('/مهارت|نرم|فردی|مذاکره/i', $title)) {
            return 'softskills';
        }
        return 'it';
    }

    private function parseSyllabus(): array
    {
        if (!empty($this->syllabus)) {
            $lines = explode("\n", $this->syllabus);
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines);
            return array_values($lines);
        }
        return [];
    }

    private function parsePrerequisites(): array
    {
        if (!empty($this->prerequisites)) {
            $lines = explode("\n", $this->prerequisites);
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines);
            return array_values($lines);
        }
        return ['آشنایی مقدماتی با مفاهیم مرتبط'];
    }

    private function getBannerGradient(): string
    {
        $gradients = [
            'bg-gradient-to-br from-blue-600 to-indigo-900',
            'bg-gradient-to-br from-amber-500 to-orange-800',
            'bg-gradient-to-br from-emerald-600 to-teal-900',
            'bg-gradient-to-br from-rose-600 to-red-950',
            'bg-gradient-to-br from-purple-600 to-indigo-950',
            'bg-gradient-to-br from-cyan-600 to-slate-800',
        ];
        return $gradients[array_rand($gradients)];
    }

    private function formatShamsiDate($date): string
    {
        if (!$date) return 'نامشخص';
        try {
            if ($date instanceof Carbon) {
                // Date is a Carbon instance (Gregorian from MySQL DATE column) → convert to Jalali
                $v = new Verta($date->toDateString());
            } else {
                // Fallback: parse as string
                $parts = explode('-', $date);
                if (count($parts) !== 3) return $date;
                $v = new Verta();
                $v->setDateJalali((int) $parts[0], (int) $parts[1], (int) $parts[2]);
            }
            return $v->format('Y/m/d');
        } catch (\Exception $e) {
            return (string) $date;
        }
    }
}
