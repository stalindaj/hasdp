<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'superadmin',  'label' => 'Super Administrator'],
            ['name' => 'admin',       'label' => 'Administrator'],
            ['name' => 'employee',    'label' => 'Employee'],
            ['name' => 'hr_officer',  'label' => 'HR Officer'],
            ['name' => 'approver',    'label' => 'Approving Official'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                ['label' => $role['label'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}