<?php

namespace App\Exports;

use App\Enums\EmployeeTypeEnum;
use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesExport implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize, WithEvents
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'الموظفين';
    }

    public function headings(): array
    {
        return [
            'كود الموظف',
            'الاسم',
            'الوظيفة',
            'القسم',
            'نوع الموظف',
            'المدير المباشر',
            'الهاتف',
            'البريد الإلكتروني',
            'الرقم القومي',
            'تاريخ التعيين',
            'الراتب الأساسي (ج.م)',
            'نسبة عمولة التحصيل %',
            'الحالة',
            'رقم السيارة',
            'رخصة القيادة',
            'ملاحظات',
        ];
    }

    public function array(): array
    {
        return Employee::query()
            ->with('manager')
            ->when($this->filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($this->filters['employee_type'] ?? null, fn ($q, $v) => $q->where('employee_type', $v))
            ->when($this->filters['sub_role'] ?? null, fn ($q, $v) => $q->where('sub_role', $v))
            ->when($this->filters['department'] ?? null, fn ($q, $v) => $q->where('department', $v))
            ->when($this->filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (Employee $e) => [
                $e->employee_code,
                $e->name,
                $e->position,
                $e->department,
                $this->typeLabel($e),
                $e->manager?->name,
                $e->phone,
                $e->email,
                $e->national_id,
                $e->joining_date?->format('Y-m-d'),
                $e->base_salary !== null ? (float) $e->base_salary : null,
                $e->collection_commission_rate !== null ? (float) $e->collection_commission_rate : null,
                self::STATUS_LABELS[$e->status] ?? $e->status,
                $e->car_number,
                $e->car_license,
                $e->notes,
            ])
            ->all();
    }

    public const STATUS_LABELS = [
        'active'   => 'نشط',
        'inactive' => 'غير نشط',
        'on_leave' => 'إجازة',
        'suspended'=> 'موقوف',
        'resigned' => 'استقال',
    ];

    protected function typeLabel(Employee $e): string
    {
        $type = $e->employee_type instanceof EmployeeTypeEnum
            ? $e->employee_type
            : EmployeeTypeEnum::tryFrom((string) $e->employee_type);

        return $type?->label() ?? (string) $e->employee_type;
    }

    public function styles(Worksheet $sheet): void
    {
        $lastCol = $sheet->getHighestColumn();

        $sheet->getParent()?->getDefaultStyle()->getFont()->setName('Arial');
        $sheet->getParent()?->getDefaultStyle()->getFont()->setSize(11);
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A237E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $highest = $sheet->getHighestRow();
        $sheet->getStyle("A1:{$lastCol}{$highest}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->setRightToLeft(true);
            },
        ];
    }
}
