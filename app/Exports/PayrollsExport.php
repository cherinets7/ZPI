<?php

namespace App\Exports;

use App\Support\EthiopianDateHelper;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PayrollsExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $rows) {}

    public function headings(): array
    {
        return ['Employee', 'Department', 'Ethiopian year', 'Ethiopian month', 'Base salary', 'Performance', 'Attendance ratio', 'Adjustment', 'Net pay', 'Status'];
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            return [
                $row->user?->name,
                $row->department?->name,
                $row->eth_year,
                EthiopianDateHelper::monthName((int) $row->eth_month, 'en'),
                $row->base_salary,
                $row->performance_score,
                $row->attendance_ratio,
                $row->bonus_or_adjustment,
                $row->net_pay,
                $row->status,
            ];
        });
    }
}
