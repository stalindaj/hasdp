<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data the app cannot run without — idempotent, safe to
        // re-run on every deploy.
        $this->call(RoleSeeder::class);
        $this->call(LeaveTypeSeeder::class);
        $this->call(HolidaySeeder::class);

        // The office roster (employees + login accounts). Also idempotent, and
        // never resets an existing account's password.
        $this->call(RosterSeeder::class);
    }
}