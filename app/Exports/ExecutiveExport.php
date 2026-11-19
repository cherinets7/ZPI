<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExecutiveExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $rows) {}

    public function headings(): array
    {
        return ['Department', 'Average performance', 'Paid payroll (ETB)'];
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn ($row) => [$row['department'], $row['score'], $row['payroll']]);
    }
}
