<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The 15SW civilian roster, from the HR monitoring sheet + PSIPOP item list.
 * Idempotent — matches on the employee number, so re-running updates rather
 * than duplicating, and never resets an existing account's password.
 *
 * Logins: username = employee number (e.g. 5807), temp password below —
 * everyone should change theirs on first login.
 *
 * Signatories on CS Form No. 6 are role-driven:
 *   hr_officer → 7.A (Marie Cris Uri) · approver → 7.C/7.D (Maricel C Tabaco)
 */
class RosterSeeder extends Seeder
{
    private const TEMP_PASSWORD = 'password123';

    public function run(): void
    {
        // [emp_no, item_no, last, first, middle, suffix, email, dob, contact,
        //  last_ape, ape_started, ape_completed, [roles], [extra employee fields]]
        $roster = [
            ['5807', 'AM2-6-1998',    'Bercades',   'Justin Gerrick Elmon', 'L', null, 'justinbercades241999@gmail.com', '1999-02-04', null, null, null, '2026-02-13', ['employee'], []],
            ['5797', 'AM1-34-1998',   'Bulanan',    'Cyric Richard', 'N', null, 'cyricbulanan@gmail.com', '2001-04-29', '09498021856', null, null, null, ['employee'], []],
            ['5803', 'AM1-22-1998',   'Calagos',    'Raynold Anthony', 'D', null, 'calagosraynold@gmail.com', '1998-08-17', '09064912048', null, '2026-07-01', null, ['employee'], []],
            ['5764', 'ADA6-7-2005',   'De La Peña', 'Meldith', null, null, 'meldithdelapena@gmail.com', '1996-02-17', null, null, null, null, ['employee'], []],
            ['5606', 'AM7-37-1998',   'Figura',     'Eliaqium', 'Corporal', null, 'eliaquimfigura@gmail.com', null, null, null, null, null, ['employee'], []],
            ['4487', 'ADA3-6-1998',   'Junquera',   'Ray Anthony', 'Saballa', null, 'anthonyjunqueraae@gmail.com', '1978-10-12', '09166483812', null, null, null, ['employee'], []],
            ['5038', 'ADA6-15-2013',  'Montemayor', 'Jean Marie', 'Tubat', null, 'jheanmarie3194@gmail.com', '1994-01-31', '09498021856', null, null, null, ['admin', 'employee'], []],
            ['5808', 'AM1-32-1998',   'Murthi',     'Debenpillai', 'C', null, 'murthideben@gmail.com', '1999-03-08', null, null, null, '2026-02-13', ['employee'], []],
            ['4774', 'ADOF5-18-2005', 'Relato',     'Dianne', 'Rodriguez', null, 'diannerelato1@gmail.com', '1984-10-18', '09392557438', null, null, null, ['employee'], []],
            ['5349', 'AM3-2-1998',    'Soriano',    'Dolgelio Paulo', 'Miranda', null, 'gio.soriano2121@gmail.com', null, null, null, null, null, ['employee'], []],
            ['5112', 'ADOF4-23-2005', 'Uri',        'Marie Cris', 'Agbayani', null, 'mariecris.uri1024@gmail.com', '1990-11-15', '09279217322', '2025-11-15', null, null, ['admin', 'hr_officer', 'employee'], [
                'position' => 'Admin Officer IV (HRMO II)',
                'designation' => 'Wing Civilian Supervisor',
            ]],
            ['5666', 'AM1-15-1998',   'Urquiola',   'Arrius Jamiel', 'Jalandoon', null, 'arriusurquiola@gmail.com', '1998-12-17', '09763225558', '2025-10-08', null, null, ['employee'], []],
            ['5868', 'ADA4-123-2005', 'Montejo',    'Philip RJ', 'A', null, 'montejo.philip@civhr.test', '1994-12-02', null, null, null, null, ['employee'], []],
            ['5867', 'ADAS1-30-2013', 'Baguio',     'Stalin Joseph', 'G', null, 'stalindaj7@gmail.com', '2002-02-01', null, null, null, null, ['superadmin', 'admin', 'employee'], []],
            ['5877', 'ADA3-143-2005', 'Rosalejos',  'Joseph Samuel', 'B', 'III', 'josephsamuel.rosalejos@gmail.com', '1998-09-06', null, null, null, null, ['employee'], []],
            ['5893', null,            'Candido',    'Jhona Jean', 'C', null, 'candido.jhonajean@civhr.test', '1998-10-28', null, null, null, null, ['employee'], []],
            ['5894', null,            'Garcia',     'Christine Rae', 'G', null, 'garcia.christine@civhr.test', '2000-08-31', null, null, null, null, ['employee'], []],

            // Maricel C Tabaco — the Director for Personnel; fixed 7.C/7.D
            // Authorized Official. Not on the plantilla list (the 'mission'
            // emp_no is a sentinel excluded from the roster); login optional.
            // The placeholder email is kept stable so re-seeding renames the
            // existing signatory in place rather than orphaning the old one.
            // Recorded as a civilian (name only, no rank/PAF) — set 'rank' +
            // 'is_civilian' => false here if she is in fact military.
            ['mission', null, 'Tabaco', 'Maricel', 'C', null, 'adrian.mission@civhr.test', null, null, null, null, null, ['approver'], [
                'rank' => null,
                'is_civilian' => true,
                'designation' => 'Director for Personnel',
            ]],
        ];

        $roleIds = Role::pluck('id', 'name');

        foreach ($roster as [$empNo, $itemNo, $last, $first, $middle, $suffix, $email, $dob, $contact, $ape, $apeStart, $apeDone, $roles, $extra]) {
            // Match on employee number OR email so re-seeding updates records
            // that predate employee numbers instead of colliding with them.
            $employee = Employee::where('emp_no', $empNo)
                ->orWhere('email', $email)
                ->first() ?? new Employee();

            $employee->fill(array_merge([
                'emp_no'             => $empNo,
                'item_no'            => $itemNo,
                'last_name'          => $last,
                'first_name'         => $first,
                'middle_name'        => $middle,
                'suffix'             => $suffix,
                'email'              => $email,
                'date_of_birth'      => $dob,
                'contact_no'         => $contact,
                'last_ape_date'      => $ape,
                'ape_date_started'   => $apeStart,
                'ape_date_completed' => $apeDone,
            ], $extra))->save();

            // Only set the temp password when first creating the account.
            $user = User::firstOrNew(['email' => $email]);
            $user->name              = trim("$first $last");
            $user->username          = $empNo;
            $user->employee_id       = $employee->id;
            $user->is_active         = true;
            $user->email_verified_at = $user->email_verified_at ?? now();
            if (! $user->exists) {
                $user->password = Hash::make(self::TEMP_PASSWORD);
            }
            $user->save();

            $user->roles()->sync(
                collect($roles)->map(fn ($r) => $roleIds[$r])->all()
            );
        }
    }
}
