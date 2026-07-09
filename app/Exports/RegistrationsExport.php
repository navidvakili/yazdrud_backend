<?php

namespace App\Exports;

use App\Models\Registertut;
use Hekmatinasser\Verta\Verta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RegistrationsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $query;
    protected $index = 0;

    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->query->orderBy('created_at', 'asc')->get();
    }

    /**
     * Headings row.
     */
    public function headings(): array
    {
        return [
            'ردیف',
            'کد فراگیر',
            'کد ملی',
            'نام',
            'شماره دانشجویی',
            'موبایل',
            'ایمیل',
            'نوع کاربر',
            'دوره آموزشی',
            'مبلغ (ریال)',
            'نوع پرداخت',
            'شماره پیگیری',
            'کد بن تخفیف',
            'عنوان بن تخفیف',
            'مبلغ تخفیف (ریال)',
            'شرایط تقسیط',
            'وضعیت اقساط',
            'تاریخ ثبت نام',
            'وضعیت',
        ];
    }

    /**
     * Map each row.
     */
    public function map($reg): array
    {
        $this->index++;

        $amount = $reg->payment_method === 'online'
            ? intval($reg->payment?->transaction?->price ?? 0)
            : intval($reg->course?->amount ?? 0);

        $createdAt = $this->toJalali($reg->created_at, 'Y/m/d H:i') ?? '';
        $trackingCode = $reg->payment?->transaction?->tracking_code ?? '';

        return [
            $this->index,
            $reg->enrollment_code ?? '',
            $reg->kodmeli,
            $reg->fullname,
            $reg->id_edu ?? '',
            $reg->mobile,
            $reg->email ?? '',
            $reg->type_text ?? '',
            $reg->course?->title ?? '',
            number_format($amount),
            $reg->payment_method === 'online' ? 'پرداخت آنلاین' : 'فیش بانکی',
            $trackingCode,
            $reg->coupon?->code ?? '',
            $reg->coupon?->title ?? '',
            $reg->discount_amount ? number_format($reg->discount_amount) : '',
            $this->formatInstallmentCondition($reg),
            $this->formatInstallmentStatus($reg),
            $createdAt,
            $reg->actual_status_text,
        ];
    }

    /**
     * Style the sheet.
     */
    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:S1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2D3748'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Set right-to-left direction for the entire sheet (Persian support)
        $sheet->setRightToLeft(true);

        // Auto filter
        $sheet->setAutoFilter('A1:S1');

        // Border style for all cells
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:S' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        return [];
    }

    private function formatInstallmentCondition($reg): string
    {
        if (!$reg->relationLoaded('installments') || $reg->installments->isEmpty()) {
            return '';
        }
        $total = $reg->installments->count();
        $amounts = $reg->installments->pluck('amount')->map(fn($v) => number_format($v))->implode(' + ');
        return "{$total} قسط ({$amounts} ریال)";
    }

    private function formatInstallmentStatus($reg): string
    {
        if (!$reg->relationLoaded('installments') || $reg->installments->isEmpty()) {
            return '';
        }
        $paid = $reg->installments->where('status', 'paid')->count();
        $total = $reg->installments->count();
        return "{$paid} از {$total} قسط پرداخت شده";
    }

    private function toJalali($date, $format): ?string
    {
        if (!$date) return null;
        try {
            return Verta::instance($date)->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }
}
