<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();

        //----------------------------- USERS --------------------------
        DB::table('users')->insert([
            [
                'name' => 'Miguel Ferreira',
                'email' => 'miguel.ferreira@example.pt',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin->value,
                'active' => true,
                'must_change_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Ana Antunes',
                'email' => 'ana.antunes@example.pt',
                'password' => Hash::make('password'),
                'role' => UserRole::HeadNurse->value,
                'active' => true,
                'must_change_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Filipe Moreira',
                'email' => 'filipe.moreira@example.pt',
                'password' => Hash::make('password'),
                'role' => UserRole::HeadNurse->value,
                'active' => false,
                'must_change_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Andre Sousa',
                'email' => 'andre.sousa@example.pt',
                'password' => Hash::make('password'),
                'role' => UserRole::Nurse->value,
                'active' => true,
                'must_change_password' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Bruno Andrade',
                'email' => 'bruno.andrade@example.pt',
                'password' => Hash::make('password'),
                'role' => UserRole::Nurse->value,
                'active' => true,
                'must_change_password' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Julio Magalhaes',
                'email' => 'julio.magalhaes@example.pt',
                'password' => Hash::make('password'),
                'role' => UserRole::Nurse->value,
                'active' => true,
                'must_change_password' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        collect([
            'Helena Coelho',
            'Joana Silva',
            'Mariana Rocha',
            'Ines Carvalho',
            'Sofia Almeida',
            'Beatriz Sousa',
            'Carlos Santos',
            'Diana Oliveira',
            'Eduardo Ribeiro',
            'Fernanda Pinto',
            'Gabriela Costa',
        ])->each(function (string $name) use ($now): void {
            $email = str($name)
                ->lower()
                ->ascii()
                ->replace(' ', '.')
                ->append('@example.pt')
                ->toString();

            DB::table('users')->insert([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => UserRole::Nurse->value,
                'active' => true,
                'must_change_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        //----------------------------- SHIFT TYPES --------------------------
        DB::table('shift_types')->insert([
            [
                'name' => 'morning',
                'color' => '#d9f3ff',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'min_nurses' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'afternoon',
                'color' => '#dff7e8',
                'start_time' => '16:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'night',
                'color' => '#fff3d6',
                'start_time' => '00:00:00',
                'end_time' => '08:00:00',
                'min_nurses' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'dayOff',
                'color' => '#f4e6ff',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'holidays',
                'color' => '#ffdfe4',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'sick leave',
                'color' => '#e3efff',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);


        $headNurseId = DB::table('users')
            ->where('email', 'ana.antunes@example.pt')
            ->value('id');

        $nurses = DB::table('users')
            ->whereIn('role', [UserRole::Nurse->value, UserRole::HeadNurse->value])
            ->where('active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        $shiftTypeIds = DB::table('shift_types')
            ->pluck('id', 'name');


        //----------------------------- SCHEDULES --------------------------
        // Map to save the shift assignments to generate exchanges later
        $shiftsByDateAndNurse = [];

        // Weekly off days matrix (Mon=0, Tue=1, Wed=2, Thu=3, Fri=4, Sat=5, Sun=6)
        // Each nurse (0 to 13) has exactly 2 days off per week.
        // The number of off-duty nurses per day is exactly 4.
        $weeklyOffDays = [
            0  => [0, 1], // Mon, Tue - Ana Antunes
            1  => [0, 1], // Mon, Tue - Andre Sousa
            2  => [0, 2], // Mon, Wed - Bruno Andrade
            3  => [0, 3], // Mon, Thu - Julio Magalhaes
            4  => [1, 2], // Tue, Wed - Helena Coelho
            5  => [1, 3], // Tue, Thu - Joana Silva
            6  => [2, 3], // Wed, Thu - Mariana Rocha
            7  => [2, 4], // Wed, Fri - Ines Carvalho
            8  => [3, 4], // Thu, Fri - Sofia Almeida
            9  => [4, 5], // Fri, Sat - Beatriz Sousa
            10 => [4, 5], // Fri, Sat - Carlos Santos
            11 => [5, 6], // Sat, Sun - Diana Oliveira
            12 => [5, 6], // Sat, Sun - Eduardo Ribeiro
            13 => [5, 6], // Sat, Sun - Fernanda Pinto
            14 => [6, 2], // Sun, Wed - Gabriela Costa
        ];

        // Generate published schedules (MAY, JUNE, JULY 2026)
        $publishedMonths = [
            ['year' => 2026, 'month' => 5, 'days' => 31, 'start' => '2026-05-01', 'end' => '2026-05-31'],
            ['year' => 2026, 'month' => 6, 'days' => 30, 'start' => '2026-06-01', 'end' => '2026-06-30'],
            ['year' => 2026, 'month' => 7, 'days' => 31, 'start' => '2026-07-01', 'end' => '2026-07-31'],
        ];

        // Tracking previous shifts to avoid 11h rest conflict
        $previousShifts = [];

        foreach ($publishedMonths as $pm) {
            $scheduleId = DB::table('schedules')->insertGetId([
                'created_by' => $headNurseId,
                'start_date' => $pm['start'],
                'end_date' => $pm['end'],
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Associate all 15 nurses with this schedule
            foreach ($nurses as $nurse) {
                DB::table('user_schedules')->insert([
                    'user_id' => $nurse->id,
                    'schedule_id' => $scheduleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Creates shifts for all days of the month
            for ($day = 1; $day <= $pm['days']; $day++) {
                $dateStr = sprintf('%04d-%02d-%02d', $pm['year'], $pm['month'], $day);
                $carbonDate = \Carbon\Carbon::parse($dateStr);
                $dayOfWeekIndex = ($carbonDate->dayOfWeekIso - 1);
                $offNurses = [];
                $workingNurses = [];

                // Apply the day off according to the matrix for all days of the month
                foreach ($nurses as $idx => $nurse) {
                    $offDays = $weeklyOffDays[$idx] ?? [];
                    if (in_array($dayOfWeekIndex, $offDays)) {
                        $offNurses[] = $nurse->id;
                    } else {
                        $workingNurses[] = $nurse->id;
                    }
                }

                // Create day off shifts
                foreach ($offNurses as $nurseId) {
                    $shiftId = DB::table('shifts')->insertGetId([
                        'schedule_id' => $scheduleId,
                        'shift_type_id' => $shiftTypeIds['dayOff'],
                        'shift_date' => $dateStr,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('user_shifts')->insert([
                        'user_id' => $nurseId,
                        'shift_id' => $shiftId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $shiftsByDateAndNurse[$dateStr][$nurseId] = $shiftId;
                    $previousShifts[$nurseId] = $shiftTypeIds['dayOff'];
                }

                // Rotate professionals on duty for a dynamic pattern
                $workCount = count($workingNurses);
                $morningCount = 4;
                $afternoonCount = ($workCount === 11) ? 4 : 3;
                $nightCount = 3;

                // Execute the intelligent assignment of daily shifts respecting the 11h rule
                $assignedShifts = $this->assignShifts(
                    $workingNurses,
                    $previousShifts,
                    $shiftTypeIds->toArray(),
                    $morningCount,
                    $afternoonCount,
                    $nightCount
                );

                $morningNurses = [];
                $afternoonNurses = [];
                $nightNurses = [];

                foreach ($assignedShifts as $nurseId => $typeId) {
                    if ($typeId === $shiftTypeIds['morning']) {
                        $morningNurses[] = $nurseId;
                    } elseif ($typeId === $shiftTypeIds['afternoon']) {
                        $afternoonNurses[] = $nurseId;
                    } elseif ($typeId === $shiftTypeIds['night']) {
                        $nightNurses[] = $nurseId;
                    }
                    $previousShifts[$nurseId] = $typeId;
                }


                // Create morning shifts
                foreach ($morningNurses as $nurseId) {
                    $shiftId = DB::table('shifts')->insertGetId([
                        'schedule_id' => $scheduleId,
                        'shift_type_id' => $shiftTypeIds['morning'],
                        'shift_date' => $dateStr,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('user_shifts')->insert([
                        'user_id' => $nurseId,
                        'shift_id' => $shiftId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $shiftsByDateAndNurse[$dateStr][$nurseId] = $shiftId;
                }

                // Create afternoon shifts
                foreach ($afternoonNurses as $nurseId) {
                    $shiftId = DB::table('shifts')->insertGetId([
                        'schedule_id' => $scheduleId,
                        'shift_type_id' => $shiftTypeIds['afternoon'],
                        'shift_date' => $dateStr,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('user_shifts')->insert([
                        'user_id' => $nurseId,
                        'shift_id' => $shiftId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $shiftsByDateAndNurse[$dateStr][$nurseId] = $shiftId;
                }

                // Create night shifts
                foreach ($nightNurses as $nurseId) {
                    $shiftId = DB::table('shifts')->insertGetId([
                        'schedule_id' => $scheduleId,
                        'shift_type_id' => $shiftTypeIds['night'],
                        'shift_date' => $dateStr,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('user_shifts')->insert([
                        'user_id' => $nurseId,
                        'shift_id' => $shiftId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $shiftsByDateAndNurse[$dateStr][$nurseId] = $shiftId;
                }
            }
        }

                // Generate DRAFT for August 2026
        $draftScheduleId = DB::table('schedules')->insertGetId([
            'created_by' => $headNurseId,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        foreach ($nurses as $nurse) {
            DB::table('user_schedules')->insert([
                'user_id' => $nurse->id,
                'schedule_id' => $draftScheduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Get Andre Sousa's ID to exclude him during the first week
        $andreId = DB::table('users')
            ->where('email', 'andre.sousa@example.pt')
            ->value('id');

        // Populate all 31 days of August in the draft
        for ($day = 1; $day <= 31; $day++) {
            $dateStr = sprintf('2026-08-%02d', $day);
            $carbonDate = \Carbon\Carbon::parse($dateStr);
            $dayOfWeekIndex = ($carbonDate->dayOfWeekIso - 1);
            $offNurses = [];
            $workingNurses = [];

            // Apply the day off according to the matrix for all days of the draft
            foreach ($nurses as $idx => $nurse) {
                $offDays = $weeklyOffDays[$idx] ?? [];
                if (in_array($dayOfWeekIndex, $offDays)) {
                    $offNurses[] = $nurse->id;
                } else {
                    $workingNurses[] = $nurse->id;
                }
            }

            foreach ($offNurses as $nurseId) {
                // Skip inserting shifts for Ana Antunes and Andre Sousa in the first week (days 1 to 7)
                if ($day <= 7 && ($nurseId == $headNurseId || $nurseId == $andreId)) {
                    $previousShifts[$nurseId] = null;
                    continue;
                }
                $shiftId = DB::table('shifts')->insertGetId([
                    'schedule_id' => $draftScheduleId,
                    'shift_type_id' => $shiftTypeIds['dayOff'],
                    'shift_date' => $dateStr,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('user_shifts')->insert([
                    'user_id' => $nurseId,
                    'shift_id' => $shiftId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $shiftsByDateAndNurse[$dateStr][$nurseId] = $shiftId;
                $previousShifts[$nurseId] = $shiftTypeIds['dayOff'];
            }

            $workCount = count($workingNurses);
            $morningCount = 4;
            $afternoonCount = ($workCount === 11) ? 4 : 3;
            $nightCount = 3;

            // Execute the intelligent assignment of daily shifts respecting the 11h rule
            $assignedShifts = $this->assignShifts(
                $workingNurses,
                $previousShifts,
                $shiftTypeIds->toArray(),
                $morningCount,
                $afternoonCount,
                $nightCount
            );

            $morningNurses = [];
            $afternoonNurses = [];
            $nightNurses = [];

            foreach ($assignedShifts as $nurseId => $typeId) {
                if ($typeId === $shiftTypeIds['morning']) {
                    $morningNurses[] = $nurseId;
                } elseif ($typeId === $shiftTypeIds['afternoon']) {
                    $afternoonNurses[] = $nurseId;
                } elseif ($typeId === $shiftTypeIds['night']) {
                    $nightNurses[] = $nurseId;
                }

                // Clear previous shift reference for the first week to avoid rest conflicts when they resume
                if ($day <= 7 && ($nurseId == $headNurseId || $nurseId == $andreId)) {
                    $previousShifts[$nurseId] = null;
                } else {
                    $previousShifts[$nurseId] = $typeId;
                }
            }

            foreach ($morningNurses as $nurseId) {
                if ($day <= 7 && ($nurseId == $headNurseId || $nurseId == $andreId)) {
                    continue;
                }
                $shiftId = DB::table('shifts')->insertGetId([
                    'schedule_id' => $draftScheduleId,
                    'shift_type_id' => $shiftTypeIds['morning'],
                    'shift_date' => $dateStr,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('user_shifts')->insert([
                    'user_id' => $nurseId,
                    'shift_id' => $shiftId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $shiftsByDateAndNurse[$dateStr][$nurseId] = $shiftId;
            }

            foreach ($afternoonNurses as $nurseId) {
                if ($day <= 7 && ($nurseId == $headNurseId || $nurseId == $andreId)) {
                    continue;
                }
                $shiftId = DB::table('shifts')->insertGetId([
                    'schedule_id' => $draftScheduleId,
                    'shift_type_id' => $shiftTypeIds['afternoon'],
                    'shift_date' => $dateStr,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('user_shifts')->insert([
                    'user_id' => $nurseId,
                    'shift_id' => $shiftId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $shiftsByDateAndNurse[$dateStr][$nurseId] = $shiftId;
            }

            foreach ($nightNurses as $nurseId) {
                if ($day <= 7 && ($nurseId == $headNurseId || $nurseId == $andreId)) {
                    continue;
                }
                $shiftId = DB::table('shifts')->insertGetId([
                    'schedule_id' => $draftScheduleId,
                    'shift_type_id' => $shiftTypeIds['night'],
                    'shift_date' => $dateStr,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('user_shifts')->insert([
                    'user_id' => $nurseId,
                    'shift_id' => $shiftId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $shiftsByDateAndNurse[$dateStr][$nurseId] = $shiftId;
            }
        }

        //------------------------ Nurse Preferences ----------------------------
        $preferencesData = [];
        $prefsPool = [
            [
                'prefers_morning' => true,
                'prefers_afternoon' => false,
                'prefers_night' => false,
                'avoid_weekends' => true,
                'prefers_weekends' => false,
                'notes' => 'Prefiro turnos da manhã e quero evitar fins de semana por motivos familiares.',
            ],
            [
                'prefers_morning' => false,
                'prefers_afternoon' => true,
                'prefers_night' => false,
                'avoid_weekends' => false,
                'prefers_weekends' => true,
                'notes' => 'Disponível para tardes e fins de semana.',
            ],
            [
                'prefers_morning' => false,
                'prefers_afternoon' => false,
                'prefers_night' => true,
                'avoid_weekends' => false,
                'prefers_weekends' => false,
                'notes' => 'Prefiro fazer noites sempre que possível.',
            ],
            [
                'prefers_morning' => true,
                'prefers_afternoon' => true,
                'prefers_night' => false,
                'avoid_weekends' => false,
                'prefers_weekends' => false,
                'notes' => 'Preferência por turnos diurnos (manhã ou tarde).',
            ],
            [
                'prefers_morning' => false,
                'prefers_afternoon' => true,
                'prefers_night' => true,
                'avoid_weekends' => true,
                'prefers_weekends' => false,
                'notes' => 'Evitar fins de semana, prefiro tardes ou noites.',
            ],
        ];

        // Mapping of 1 regular nurse without preferences in each of the months.
        // (Note: The Head Nurse is always omitted from all preferences in all months)
        $months = [
            5 => 'Gabriela Costa', // No preferences in May
            6 => 'Andre Sousa',    // No preferences in June
            7 => 'Bruno Andrade',  // No preferences in July
        ];

        foreach ($months as $month => $excludedNurseName) {
            $poolIndex = 0;
            foreach ($nurses as $nurse) {
                // The Head Nurse never has preferences
                if ($nurse->id === $headNurseId) {
                    continue;
                }

                // Nurse assigned to this month has no preferences
                if ($nurse->name === $excludedNurseName) {
                    continue;
                }

                $p = $prefsPool[$poolIndex % count($prefsPool)];
                $poolIndex++;
                $preferencesData[] = [
                    'user_id' => $nurse->id,
                    'month' => $month,
                    'year' => 2026,
                    'prefers_morning' => $p['prefers_morning'],
                    'prefers_afternoon' => $p['prefers_afternoon'],
                    'prefers_night' => $p['prefers_night'],
                    'avoid_weekends' => $p['avoid_weekends'],
                    'prefers_weekends' => $p['prefers_weekends'],
                    'notes' => $p['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('nurse_preferences')->insert($preferencesData);


        //------------------- Shift Swap ---------------------------
        // Only active nurses are considered for shift swap requests
        $nurseUsers = DB::table('users')
            ->where('role', UserRole::Nurse->value)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        $nurseCount = $nurseUsers->count();

        if ($nurseCount >= 6) {
            foreach ($nurseUsers as $index => $requester) {
                // Define the target users (targets) using different circular indices.
                // By making each nurse send 2 pending requests in a circular manner (i+4 and i+5),
                // we ensure that each one also receives exactly 2 pending requests.
                $targetAccepted = $nurseUsers[($index + 1) % $nurseCount];
                $targetCancelled = $nurseUsers[($index + 2) % $nurseCount];
                $targetRejected = $nurseUsers[($index + 3) % $nurseCount];
                $targetPending1 = $nurseUsers[($index + 4) % $nurseCount];
                $targetPending2 = $nurseUsers[($index + 5) % $nurseCount];

                // Find active work shifts (excluding days off, holidays and sick leaves) for the requester
                $requesterShifts = DB::table('user_shifts')
                    ->join('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
                    ->where('user_shifts.user_id', $requester->id)
                    ->whereNotIn('shifts.shift_type_id', [
                        $shiftTypeIds['dayOff'],
                        $shiftTypeIds['holidays'],
                        $shiftTypeIds['sick leave']
                    ])
                    ->orderBy('shifts.shift_date')
                    ->pluck('shifts.id')
                    ->toArray();

                // Find active work shifts (excluding days off, holidays and sick leaves) for the target users
                $targetAcceptedShifts = DB::table('user_shifts')
                    ->join('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
                    ->where('user_shifts.user_id', $targetAccepted->id)
                    ->whereNotIn('shifts.shift_type_id', [
                        $shiftTypeIds['dayOff'],
                        $shiftTypeIds['holidays'],
                        $shiftTypeIds['sick leave']
                    ])
                    ->orderBy('shifts.shift_date')
                    ->pluck('shifts.id')
                    ->toArray();

                $targetCancelledShifts = DB::table('user_shifts')
                    ->join('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
                    ->where('user_shifts.user_id', $targetCancelled->id)
                    ->whereNotIn('shifts.shift_type_id', [
                        $shiftTypeIds['dayOff'],
                        $shiftTypeIds['holidays'],
                        $shiftTypeIds['sick leave']
                    ])
                    ->orderBy('shifts.shift_date')
                    ->pluck('shifts.id')
                    ->toArray();

                $targetRejectedShifts = DB::table('user_shifts')
                    ->join('shifts', 'user_shifts.shift_id', '=', 'shifts.id')
                    ->where('user_shifts.user_id', $targetRejected->id)
                    ->whereNotIn('shifts.shift_type_id', [
                        $shiftTypeIds['dayOff'],
                        $shiftTypeIds['holidays'],
                        $shiftTypeIds['sick leave']
                    ])
                    ->orderBy('shifts.shift_date')
                    ->pluck('shifts.id')
                    ->toArray();

                // Create Accepted Shift Swap (Status: accepted)
                if (count($requesterShifts) > 0 && count($targetAcceptedShifts) > 0) {
                    $reqShift = $requesterShifts[0];
                    $tarShift = $targetAcceptedShifts[min(count($targetAcceptedShifts) - 1, 1)];

                    $swapId = DB::table('shift_swap_requests')->insertGetId([
                        'created_by' => $requester->id,
                        'status' => 'accepted',
                        'notes' => 'Troca aceite de ' . $requester->name . '.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('shift_swap_participants')->insert([
                        ['swap_request_id' => $swapId, 'user_id' => $requester->id, 'role' => 'requester', 'created_at' => $now, 'updated_at' => $now],
                        ['swap_request_id' => $swapId, 'user_id' => $targetAccepted->id, 'role' => 'target', 'created_at' => $now, 'updated_at' => $now]
                    ]);

                    DB::table('shift_swap_request_shifts')->insert([
                        ['swap_request_id' => $swapId, 'shift_id' => $reqShift, 'owner_user_id' => $requester->id, 'type' => 'offered', 'created_at' => $now, 'updated_at' => $now],
                        ['swap_request_id' => $swapId, 'shift_id' => $tarShift, 'owner_user_id' => $targetAccepted->id, 'type' => 'requested', 'created_at' => $now, 'updated_at' => $now]
                    ]);
                }

                // Create Cancelled Shift Swap (Status: cancelled)
                if (count($requesterShifts) > 1 && count($targetCancelledShifts) > 1) {
                    $reqShift = $requesterShifts[1];
                    $tarShift = $targetCancelledShifts[min(count($targetCancelledShifts) - 1, 2)];

                    $swapId = DB::table('shift_swap_requests')->insertGetId([
                        'created_by' => $requester->id,
                        'status' => 'cancelled',
                        'notes' => 'Troca cancelada de ' . $requester->name . '.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('shift_swap_participants')->insert([
                        ['swap_request_id' => $swapId, 'user_id' => $requester->id, 'role' => 'requester', 'created_at' => $now, 'updated_at' => $now],
                        ['swap_request_id' => $swapId, 'user_id' => $targetCancelled->id, 'role' => 'target', 'created_at' => $now, 'updated_at' => $now]
                    ]);

                    DB::table('shift_swap_request_shifts')->insert([
                        ['swap_request_id' => $swapId, 'shift_id' => $reqShift, 'owner_user_id' => $requester->id, 'type' => 'offered', 'created_at' => $now, 'updated_at' => $now],
                        ['swap_request_id' => $swapId, 'shift_id' => $tarShift, 'owner_user_id' => $targetCancelled->id, 'type' => 'requested', 'created_at' => $now, 'updated_at' => $now]
                    ]);
                }

                // Create Rejected Shift Swap (Status: rejected)
                if (count($requesterShifts) > 2 && count($targetRejectedShifts) > 2) {
                    $reqShift = $requesterShifts[2];
                    $tarShift = $targetRejectedShifts[min(count($targetRejectedShifts) - 1, 3)];

                    $swapId = DB::table('shift_swap_requests')->insertGetId([
                        'created_by' => $requester->id,
                        'status' => 'rejected',
                        'notes' => 'Troca rejeitada de ' . $requester->name . '.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('shift_swap_participants')->insert([
                        ['swap_request_id' => $swapId, 'user_id' => $requester->id, 'role' => 'requester', 'created_at' => $now, 'updated_at' => $now],
                        ['swap_request_id' => $swapId, 'user_id' => $targetRejected->id, 'role' => 'target', 'created_at' => $now, 'updated_at' => $now]
                    ]);

                    DB::table('shift_swap_request_shifts')->insert([
                        ['swap_request_id' => $swapId, 'shift_id' => $reqShift, 'owner_user_id' => $requester->id, 'type' => 'offered', 'created_at' => $now, 'updated_at' => $now],
                        ['swap_request_id' => $swapId, 'shift_id' => $tarShift, 'owner_user_id' => $targetRejected->id, 'type' => 'requested', 'created_at' => $now, 'updated_at' => $now]
                    ]);
                }

                // Date Configuration and Creation of Pending Requests for July 30th
                $datePending = '2026-07-30';
                $createdAtPending = \Carbon\Carbon::parse($datePending)->setTime(10, 0, 0);

                // Get the specific shifts generated for July 30th for each user
                $reqShiftPending = $shiftsByDateAndNurse[$datePending][$requester->id] ?? null;
                $tarShiftPending1 = $shiftsByDateAndNurse[$datePending][$targetPending1->id] ?? null;
                $tarShiftPending2 = $shiftsByDateAndNurse[$datePending][$targetPending2->id] ?? null;

                // Create 1st Pending Request Sent (Status: pending)
                if ($reqShiftPending && $tarShiftPending1) {
                    $swapId = DB::table('shift_swap_requests')->insertGetId([
                        'created_by' => $requester->id,
                        'status' => 'pending',
                        'notes' => 'Troca pendente de 30 de Julho por ' . $requester->name . '.',
                        'created_at' => $createdAtPending,
                        'updated_at' => $createdAtPending,
                    ]);

                    DB::table('shift_swap_participants')->insert([
                        ['swap_request_id' => $swapId, 'user_id' => $requester->id, 'role' => 'requester', 'created_at' => $createdAtPending, 'updated_at' => $createdAtPending],
                        ['swap_request_id' => $swapId, 'user_id' => $targetPending1->id, 'role' => 'target', 'created_at' => $createdAtPending, 'updated_at' => $createdAtPending]
                    ]);

                    DB::table('shift_swap_request_shifts')->insert([
                        ['swap_request_id' => $swapId, 'shift_id' => $reqShiftPending, 'owner_user_id' => $requester->id, 'type' => 'offered', 'created_at' => $createdAtPending, 'updated_at' => $createdAtPending],
                        ['swap_request_id' => $swapId, 'shift_id' => $tarShiftPending1, 'owner_user_id' => $targetPending1->id, 'type' => 'requested', 'created_at' => $createdAtPending, 'updated_at' => $createdAtPending]
                    ]);
                }

                // Create 2nd Pending Request Sent (Status: pending)
                if ($reqShiftPending && $tarShiftPending2) {
                    $swapId = DB::table('shift_swap_requests')->insertGetId([
                        'created_by' => $requester->id,
                        'status' => 'pending',
                        'notes' => 'Troca pendente de 30 de Julho por ' . $requester->name . '.',
                        'created_at' => $createdAtPending,
                        'updated_at' => $createdAtPending,
                    ]);

                    DB::table('shift_swap_participants')->insert([
                        ['swap_request_id' => $swapId, 'user_id' => $requester->id, 'role' => 'requester', 'created_at' => $createdAtPending, 'updated_at' => $createdAtPending],
                        ['swap_request_id' => $swapId, 'user_id' => $targetPending2->id, 'role' => 'target', 'created_at' => $createdAtPending, 'updated_at' => $createdAtPending]
                    ]);

                    DB::table('shift_swap_request_shifts')->insert([
                        ['swap_request_id' => $swapId, 'shift_id' => $reqShiftPending, 'owner_user_id' => $requester->id, 'type' => 'offered', 'created_at' => $createdAtPending, 'updated_at' => $createdAtPending],
                        ['swap_request_id' => $swapId, 'shift_id' => $tarShiftPending2, 'owner_user_id' => $targetPending2->id, 'type' => 'requested', 'created_at' => $createdAtPending, 'updated_at' => $createdAtPending]
                    ]);
                }
            }
        }

        //------------------- MEDICAL LEAVES & REPLACED SHIFTS ---------------------------
        $medicalLeavesData = [
            [
                'email' => 'helena.coelho@example.pt',
                'start_date' => '2026-06-08',
                'end_date' => '2026-06-12',
                'reason' => 'Recuperação pós-operatória',
            ],
            [
                'email' => 'julio.magalhaes@example.pt',
                'start_date' => '2026-07-13',
                'end_date' => '2026-07-17',
                'reason' => 'Gripe influenza grave',
            ],
            [
                'email' => 'diana.oliveira@example.pt',
                'start_date' => '2026-05-04',
                'end_date' => '2026-05-08',
                'reason' => 'Lesão no joelho',
            ],
            [
                'email' => 'diana.oliveira@example.pt',
                'start_date' => '2026-10-04',
                'end_date' => '2026-10-08',
                'reason' => 'Lesão no joelho',
            ],
        ];
        foreach ($medicalLeavesData as $leave) {
            $userId = DB::table('users')->where('email', $leave['email'])->value('id');
            if (!$userId) {
                continue;
            }
            $leaveId = DB::table('medical_leaves')->insertGetId([
                'user_id' => $userId,
                'start_date' => $leave['start_date'],
                'end_date' => $leave['end_date'],
                'reason' => $leave['reason'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            // Replace shifts on the schedule for this nurse during the medical leave period
            $start = \Carbon\Carbon::parse($leave['start_date']);
            $end = \Carbon\Carbon::parse($leave['end_date']);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateStr = $date->toDateString();
                // Find the original shift assigned to this nurse on this date
                $originalShift = DB::table('shifts')
                    ->join('user_shifts', 'shifts.id', '=', 'user_shifts.shift_id')
                    ->where('user_shifts.user_id', $userId)
                    ->where('shifts.shift_date', $dateStr)
                    ->select('shifts.id', 'shifts.schedule_id', 'shifts.shift_type_id')
                    ->first();
                if ($originalShift) {
                    // Update original shift type to 'sick leave'
                    DB::table('shifts')
                        ->where('id', $originalShift->id)
                        ->update([
                            'shift_type_id' => $shiftTypeIds['sick leave'],
                            'updated_at' => $now,
                        ]);
                    // Log the replacement
                    DB::table('medical_leave_replaced_shifts')->insert([
                        'medical_leave_id' => $leaveId,
                        'shift_date' => $dateStr,
                        'schedule_id' => $originalShift->schedule_id,
                        'original_shift_id' => $originalShift->id,
                        'original_shift_type_id' => $originalShift->shift_type_id,
                        'temp_shift_id' => null,
                        'was_shared' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
        //------------------- VACATIONS & REPLACED SHIFTS ---------------------------
        $vacationsData = [
            [
                'email' => 'andre.sousa@example.pt',
                'start_date' => '2026-07-20',
                'end_date' => '2026-07-26',
            ],
            [
                'email' => 'joana.silva@example.pt',
                'start_date' => '2026-06-22',
                'end_date' => '2026-06-28',
            ],
            [
                'email' => 'carlos.santos@example.pt',
                'start_date' => '2026-05-18',
                'end_date' => '2026-05-24',
            ],
            [
                'email' => 'carlos.santos@example.pt',
                'start_date' => '2026-10-18',
                'end_date' => '2026-10-24',
            ],
        ];
        foreach ($vacationsData as $vacation) {
            $userId = DB::table('users')->where('email', $vacation['email'])->value('id');
            if (!$userId) {
                continue;
            }
            $vacationId = DB::table('vacations')->insertGetId([
                'user_id' => $userId,
                'start_date' => $vacation['start_date'],
                'end_date' => $vacation['end_date'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            // Replace shifts on the schedule for this nurse during the vacation period
            $start = \Carbon\Carbon::parse($vacation['start_date']);
            $end = \Carbon\Carbon::parse($vacation['end_date']);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateStr = $date->toDateString();
                // Find the original shift assigned to this nurse on this date
                $originalShift = DB::table('shifts')
                    ->join('user_shifts', 'shifts.id', '=', 'user_shifts.shift_id')
                    ->where('user_shifts.user_id', $userId)
                    ->where('shifts.shift_date', $dateStr)
                    ->select('shifts.id', 'shifts.schedule_id', 'shifts.shift_type_id')
                    ->first();
                if ($originalShift) {
                    // Update original shift type to 'holidays'
                    DB::table('shifts')
                        ->where('id', $originalShift->id)
                        ->update([
                            'shift_type_id' => $shiftTypeIds['holidays'],
                            'updated_at' => $now,
                        ]);
                    // Log the replacement
                    DB::table('vacation_replaced_shifts')->insert([
                        'vacation_id' => $vacationId,
                        'shift_date' => $dateStr,
                        'schedule_id' => $originalShift->schedule_id,
                        'original_shift_id' => $originalShift->id,
                        'original_shift_type_id' => $originalShift->shift_type_id,
                        'temp_shift_id' => null,
                        'was_shared' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
        //------------------- SYSTEM NOTIFICATIONS & USER NOTIFICATIONS ---------------------------
        $notifications = [
            [
                'subject' => 'Horário Publicado',
                'body' => 'O horário para o mês de Junho de 2026 foi publicado pela Enfermeira Chefe Ana Antunes.',
                'read' => false,
            ],
            [
                'subject' => 'Pedido de Troca de Turno',
                'body' => 'Recebeste um novo pedido de troca de turno de Andre Sousa para o dia 10 de Maio.',
                'read' => false,
            ],
            [
                'subject' => 'Férias Aprovadas',
                'body' => 'O teu período de férias de 20/07/2026 a 26/07/2026 foi registado e aprovado.',
                'read' => true,
            ],
            [
                'subject' => 'Baixa Médica Registada',
                'body' => 'Foi registada uma baixa médica para Helena Coelho de 08/06/2026 a 12/06/2026.',
                'read' => false,
            ],
        ];
        foreach ($notifications as $notifData) {
            $notifId = DB::table('notifications')->insertGetId([
                'subject' => $notifData['subject'],
                'body' => $notifData['body'],
                'read' => $notifData['read'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($notifData['subject'] === 'Pedido de Troca de Turno') {
                $targetUserId = DB::table('users')->where('email', 'bruno.andrade@example.pt')->value('id');
                if ($targetUserId) {
                    DB::table('user_notifications')->insert([
                        'user_id' => $targetUserId,
                        'notification_id' => $notifId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } elseif ($notifData['subject'] === 'Férias Aprovadas') {
                $targetUserId = DB::table('users')->where('email', 'andre.sousa@example.pt')->value('id');
                if ($targetUserId) {
                    DB::table('user_notifications')->insert([
                        'user_id' => $targetUserId,
                        'notification_id' => $notifId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } elseif ($notifData['subject'] === 'Horário Publicado') {
                foreach ($nurses as $nurse) {
                    DB::table('user_notifications')->insert([
                        'user_id' => $nurse->id,
                        'notification_id' => $notifId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } else {
                $adminIds = DB::table('users')
                    ->whereIn('email', ['ana.antunes@example.pt'])
                    ->pluck('id');
                foreach ($adminIds as $adminId) {
                    DB::table('user_notifications')->insert([
                        'user_id' => $adminId,
                        'notification_id' => $notifId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }



    private function assignShifts(
        array $workingNurses,
        array $previousShifts,
        array $shiftTypeIds,
        int $morningCount,
        int $afternoonCount,
        int $nightCount
    ): ?array {
        $result = [];
        if ($this->backtrack(
            $workingNurses,
            0,
            $previousShifts,
            $shiftTypeIds,
            $morningCount,
            $afternoonCount,
            $nightCount,
            $result
        )) {
            return $result;
        }
        return null;
    }
    private function backtrack(
        array $workingNurses,
        int $index,
        array $previousShifts,
        array $shiftTypeIds,
        int $morningLeft,
        int $afternoonLeft,
        int $nightLeft,
        array &$result
    ): bool {
        if ($index === count($workingNurses)) {
            return $morningLeft === 0 && $afternoonLeft === 0 && $nightLeft === 0;
        }

        $nurseId = $workingNurses[$index];
        $prevShift = $previousShifts[$nurseId] ?? null;

        // Try Morning
        if ($morningLeft > 0) {
            $canWorkMorning = true;
            if ($prevShift === $shiftTypeIds['afternoon'] || $prevShift === $shiftTypeIds['night']) {
                $canWorkMorning = false;
            }
            if ($canWorkMorning) {
                $result[$nurseId] = $shiftTypeIds['morning'];
                if ($this->backtrack($workingNurses, $index + 1, $previousShifts, $shiftTypeIds, $morningLeft - 1, $afternoonLeft, $nightLeft, $result)) {
                    return true;
                }
                unset($result[$nurseId]);
            }
        }

        // Try Afternoon
        if ($afternoonLeft > 0) {
            $canWorkAfternoon = true;
            if ($prevShift === $shiftTypeIds['night']) {
                $canWorkAfternoon = false;
            }
            if ($canWorkAfternoon) {
                $result[$nurseId] = $shiftTypeIds['afternoon'];
                if ($this->backtrack($workingNurses, $index + 1, $previousShifts, $shiftTypeIds, $morningLeft, $afternoonLeft - 1, $nightLeft, $result)) {
                    return true;
                }
                unset($result[$nurseId]);
            }
        }

        // Try Night
        if ($nightLeft > 0) {
            $result[$nurseId] = $shiftTypeIds['night'];
            if ($this->backtrack($workingNurses, $index + 1, $previousShifts, $shiftTypeIds, $morningLeft, $afternoonLeft, $nightLeft - 1, $result)) {
                return true;
            }
            unset($result[$nurseId]);
        }

        return false;
    }
}
