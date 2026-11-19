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
    public function __construct(private Department $department, private User $actor) {}

    public function collection(Collection $rows): void
    {
        $audit = app(AuditService::class);
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($email === '' || $name === '' || User::query()->where('email', $email)->exists()) {
                continue;
            }

            $employee = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => (string) ($row['password'] ?: 'Password@123'),
                'base_salary' => (float) ($row['base_salary'] ?? 0),
                'role' => 'employee',
                'department_id' => $this->department->id,
                'status' => 'active',
                'locale' => 'en',
            ]);
            $audit->log('employee.imported', $employee, [], ['email' => $email], $this->department->id, $this->actor);
        }
    }
}
