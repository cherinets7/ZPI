<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeesImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, string> */
    public array $errors = [];

    public int $imported = 0;

    public function __construct(private Department $department, private User $actor) {}

    public function collection(Collection $rows): void
    {
        $audit = app(AuditService::class);
        foreach ($rows->values() as $index => $row) {
            $line = $index + 2;
            if ($this->blank($row)) {
                continue;
            }

            $email = trim((string) ($row['email'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || $email === '') {
                $this->errors[] = __('ui.import_row_required', ['row' => $line]);

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = __('ui.import_row_email', ['row' => $line, 'email' => $email]);

                continue;
            }

            if (User::query()->where('email', $email)->exists()) {
                $this->errors[] = __('ui.import_row_duplicate', ['row' => $line, 'email' => $email]);

                continue;
            }

            $salary = $row['base_salary'] ?? '';
            if ($salary !== '' && $salary !== null && ! is_numeric($salary)) {
                $this->errors[] = __('ui.import_row_salary', ['row' => $line]);

                continue;
            }

            $employee = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => trim((string) ($row['password'] ?? '')) !== '' ? (string) $row['password'] : 'Password@123',
                'base_salary' => ($salary === '' || $salary === null) ? 0 : (float) $salary,
                'role' => 'employee',
                'department_id' => $this->department->id,
                'status' => 'active',
                'locale' => 'en',
            ]);
            $audit->log('employee.imported', $employee, [], ['email' => $email], $this->department->id, $this->actor);
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
