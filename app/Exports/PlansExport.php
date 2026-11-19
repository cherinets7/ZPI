<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlansExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $rows) {}

    public function headings(): array
    {
        return ['Department', 'Employee', 'KPI', 'Type', 'Period', 'Budget year', 'Period no.', 'Target', 'Achieved', 'Score', 'Status'];
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            return [
                $row->department?->name,
                $row->user?->name,
                $row->kpiIndicator?->title,
                $row->type,
                $row->period_type,
                $row->eth_year,
                $row->eth_period,
                $row->target_value,
                $row->achieved_value,
                $row->performance_score,
                $row->status,
            ];
        });
    }
}
