<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\KpiIndicator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KpiIndicatorsImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['department_code'] ?? '')));
            $title = trim((string) ($row['title'] ?? ''));
            $department = Department::query()->where('code', $code)->first();
            if (! $department || $title === '') {
                continue;
            }

            KpiIndicator::query()->create([
                'department_id' => $department->id,
                'title' => $title,
                'unit' => (string) ($row['unit'] ?: 'number'),
                'weight' => (float) ($row['weight'] ?: 1),
                'is_active' => true,
            ]);
        }
    }
}
