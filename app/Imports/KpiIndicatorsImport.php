<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\KpiIndicator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KpiIndicatorsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, string> */
    public array $errors = [];

    public int $imported = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows->values() as $index => $row) {
            $line = $index + 2;
            if ($this->blank($row)) {
                continue;
            }

            $code = strtoupper(trim((string) ($row['department_code'] ?? '')));
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                $this->errors[] = __('ui.import_row_title', ['row' => $line]);

                continue;
            }

            $department = $code === '' ? null : Department::query()->where('code', $code)->first();
            if (! $department) {
                $this->errors[] = __('ui.import_row_department', ['row' => $line, 'code' => $code !== '' ? $code : '—']);

                continue;
            }

            $weight = $row['weight'] ?? '';
            if ($weight !== '' && $weight !== null && ! is_numeric($weight)) {
                $this->errors[] = __('ui.import_row_weight', ['row' => $line]);

                continue;
            }

            KpiIndicator::query()->create([
                'department_id' => $department->id,
                'title' => $title,
                'unit' => trim((string) ($row['unit'] ?? '')) !== '' ? trim((string) $row['unit']) : 'number',
                'weight' => ($weight === '' || $weight === null) ? 1 : (float) $weight,
                'is_active' => true,
            ]);
            $this->imported++;
        }

        if ($this->imported === 0 && $this->errors === []) {
            $this->errors[] = __('ui.import_empty');
        }
    }

    private function blank(mixed $row): bool
    {
        return collect($row)->flatten()->every(fn ($value) => trim((string) $value) === '');
    }
}
