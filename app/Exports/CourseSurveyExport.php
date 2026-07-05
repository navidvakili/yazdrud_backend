<?php

namespace App\Exports;

use App\Models\CourseSurvey;
use Hekmatinasser\Verta\Verta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CourseSurveyExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
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
        return $this->query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Headings row.
     */
    public function headings(): array
    {
        return [
            'ردیف',
            'شناسه',
            'نام',
            'نام خانوادگی',
            'نام کاربری',
            'شماره همراه',
            'دوره / کارگاه',
            'امتیاز',
            'دوره‌های پیشنهادی',
            'توضیحات',
            'آدرس IP',
            'تاریخ ثبت',
        ];
    }

    /**
     * Map each row.
     */
    public function map($survey): array
    {
        $this->index++;

        $createdAt = $this->toJalali($survey->created_at, 'Y/m/d H:i') ?? '';
        $rating = $survey->rating ? str_repeat('⭐', (int) $survey->rating) : '-';

        return [
            $this->index,
            $survey->id,
            $survey->first_name ?? '',
            $survey->last_name ?? '',
            $survey->full_name ?? '',
            $survey->phone_number ?? '',
            $survey->course?->title ?? '-',
            $rating,
            $survey->suggestions ?? '-',
            $survey->comment ?? '-',
            $survey->ip_address ?? '-',
            $createdAt,
        ];
    }

    /**
     * Style the sheet.
     */
    public function styles(Worksheet $sheet)
    {
        // Set right-to-left direction for Persian support
        $sheet->setRightToLeft(true);

        $lastColumn = 'L'; // up to column L (12 columns)
        $lastRow = $sheet->getHighestRow();

        // Style the header row
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2D3748'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Auto filter
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        // Border style for all cells
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(6);   // ردیف
        $sheet->getColumnDimension('B')->setWidth(8);   // شناسه
        $sheet->getColumnDimension('C')->setWidth(15);  // نام
        $sheet->getColumnDimension('D')->setWidth(20);  // نام خانوادگی
        $sheet->getColumnDimension('E')->setWidth(20);  // نام کاربری
        $sheet->getColumnDimension('F')->setWidth(15);  // شماره همراه
        $sheet->getColumnDimension('G')->setWidth(25);  // دوره
        $sheet->getColumnDimension('H')->setWidth(15);  // امتیاز
        $sheet->getColumnDimension('I')->setWidth(35);  // پیشنهادات
        $sheet->getColumnDimension('J')->setWidth(35);  // توضیحات
        $sheet->getColumnDimension('K')->setWidth(18);  // IP
        $sheet->getColumnDimension('L')->setWidth(20);  // تاریخ

        return [];
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
