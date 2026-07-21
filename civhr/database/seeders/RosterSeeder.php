<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The office roster. Idempotent — matches on email, so re-running updates
 * rather than duplicating.
 *
 * Signatories on CS Form No. 6 are driven by roles:
 *   hr_officer  → 7.A "Authorized Officer"  (Marie Cris A Uri)
 *   approver    → 7.C/7.D "Authorized Official" (LTC Adrian Lee G Mission)
 *   recommender → 7.B options (assign as needed)
 *
 * TEMP passwords are set here for testing only — have each person change
 * theirs, and do not treat these as secret.
 */
class RosterSeeder extends Seeder
{
    private const TEMP_PASSWORD = 'password';

    public function run(): void
    {
        // [last, first, middle, suffix, email, [roles], [extra employee fields]]
        $roster = [
            ['Bercades', 'Justin Gerrick Elmon', 'L', null, 'justinbercades241999@gmail.com', ['employee'], []],
            ['Bulanan', 'Cyric Richard', 'N', null, 'cyricbulanan@gmail.com', ['employee'], []],
            ['Calagos', 'Raynold Anthony', 'D', null, 'calagosraynold@gmail.com', ['employee'], []],
            ['De La Peña', 'Meldith', null, null, 'meldithdelapena@gmail.com', ['employee'], []],
            ['Estrella', 'Marferia', 'T', null, 'estrella.marferia@civhr.test', ['employee'], []],
            ['Figura', 'Eliaqium', 'C', null, 'eliaquimfigura@gmail.com', ['employee'], []],
            ['Junquera', 'Ray Anthony', 'S', null, 'anthonyjunqueraae@gmail.com', ['employee'], []],
            ['Murthi', 'Debenpillai', 'C', null, 'murthideben@gmail.com', ['employee'], []],
            ['Relato', 'Dianne', 'R', null, 'diannerelato1@gmail.com', ['employee'], []],
            ['Soriano', 'Dolgelio Paulo', 'M', null, 'gio.soriano2121@gmail.com', ['employee'], []],
            ['Urquiola', 'Arrius Jamiel', 'J', null, 'arriusurquiola@gmail.com', ['employee'], []],
            ['Montejo', 'Philip RJ', 'A', null, 'montejo.philip@civhr.test', ['employee'], []],
            ['Baguio', 'Stalin Joseph', 'G', null, 'stalindaj7@gmail.com', ['superadmin', 'admin', 'employee'], []],
            ['Rosalejos', 'Joseph Samuel', 'B', 'III', 'josephsamuel.rosalejos@gmail.com', ['employee'], []],
            ['Candido', 'Jhona Jean', 'C', null, 'candido.jhonajean@civhr.test', ['employee'], []],
            ['Garcia', 'Christine Rae', 'G', null, 'garcia.christine@civhr.test', ['employee'], []],

            // Admins (supervisor + assistant) who process leaves.
            ['Montemayor', 'Jean Marie', 'B', null, 'jheanmarie3194@gmail.com', ['admin', 'employee'], []],

            // Marie Cris A Uri — admin AND the fixed 7.A Authorized Officer.
            ['Uri', 'Marie Cris', 'A', null, 'mariecris.uri1024@gmail.com', ['admin', 'hr_officer', 'employee'], [
                'position' => 'Admin Officer IV (HRMO II)',
                'designation' => 'Wing Civilian Supervisor',
            ]],

            // LTC Adrian Lee G Mission — the fixed 7.C/7.D Authorized Official.
            // Not part of the plantilla roster above; present so the form has a
            // signing official. Give a login only if he needs one.
            ['Mission', 'Adrian Lee', 'G', null, 'adrian.mission@civhr.test', ['approver'], [
                'rank' => 'LTC',
                'designation' => 'Director for Personnel',
            ]],
        ];

        $roleIds = Role::pluck('id', 'name');

        foreach ($roster as [$last, $first, $middle, $suffix, $email, $roles, $extra]) {
            $employee = Employee::updateOrCreate(
                ['email' => $email],
                array_merge([
                    'last_name'   => $last,
                    'first_name'  => $first,
                    'middle_name' => $middle,
                    'suffix'      => $suffix,
                ], $extra)
            );

            // Only set the temp password when first creating the account, so
            // re-running the seeder never clobbers a password someone changed.
            $user = User::firstOrNew(['email' => $email]);
            $user->name        = trim("$first $last");
            $user->employee_id = $employee->id;
            $user->is_active   = true;
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
