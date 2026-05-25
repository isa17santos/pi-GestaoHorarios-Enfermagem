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
                'start_time' => '23:00:00',
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
            [
                'name' => 'parental leave',
                'color' => '#fce7d8',
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

        // 2. Generate DRAFT for August 2026
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

        // Populate first 5 days of August in the draft
        for ($day = 1; $day <= 5; $day++) {
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
                $previousShifts[$nurseId] = $typeId;
            }

            foreach ($morningNurses as $nurseId) {
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

        // Shift Swap Request 1: May (Status: Pending)
        // Andre Sousa (nurses[1]) and Bruno Andrade (nurses[2]) on May 10 (Both work)
        $mayDate = '2026-05-10';
        $andreShiftIdMay = $shiftsByDateAndNurse[$mayDate][$nurses[1]->id] ?? null;
        $brunoShiftIdMay = $shiftsByDateAndNurse[$mayDate][$nurses[2]->id] ?? null;
        if ($andreShiftIdMay && $brunoShiftIdMay) {
            $swapIdMay = DB::table('shift_swap_requests')->insertGetId([
                'created_by' => $nurses[1]->id,
                'status' => 'pending',
                'notes' => 'Troca de turnos no dia 10 de Maio.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('shift_swap_participants')->insert([
                [
                    'swap_request_id' => $swapIdMay,
                    'user_id' => $nurses[1]->id,
                    'role' => 'requester',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'swap_request_id' => $swapIdMay,
                    'user_id' => $nurses[2]->id,
                    'role' => 'target',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);
            DB::table('shift_swap_request_shifts')->insert([
                [
                    'swap_request_id' => $swapIdMay,
                    'shift_id' => $andreShiftIdMay,
                    'owner_user_id' => $nurses[1]->id,
                    'type' => 'offered',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'swap_request_id' => $swapIdMay,
                    'shift_id' => $brunoShiftIdMay,
                    'owner_user_id' => $nurses[2]->id,
                    'type' => 'requested',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);
        }


        // Shift Swap Request 2: June (Status: Accepted)
        // Helena Coelho (nurses[4]) and Joana Silva (nurses[5]) on June 19 (Both work)
        $juneDate = '2026-06-19';
        $helenaShiftIdJune = $shiftsByDateAndNurse[$juneDate][$nurses[4]->id] ?? null;
        $joanaShiftIdJune = $shiftsByDateAndNurse[$juneDate][$nurses[5]->id] ?? null;
        if ($helenaShiftIdJune && $joanaShiftIdJune) {
            $swapIdJune = DB::table('shift_swap_requests')->insertGetId([
                'created_by' => $nurses[4]->id,
                'status' => 'accepted',
                'notes' => 'Troca de turno no dia 19 de Junho.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('shift_swap_participants')->insert([
                [
                    'swap_request_id' => $swapIdJune,
                    'user_id' => $nurses[4]->id,
                    'role' => 'requester',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'swap_request_id' => $swapIdJune,
                    'user_id' => $nurses[5]->id,
                    'role' => 'target',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);
            DB::table('shift_swap_request_shifts')->insert([
                [
                    'swap_request_id' => $swapIdJune,
                    'shift_id' => $helenaShiftIdJune,
                    'owner_user_id' => $nurses[4]->id,
                    'type' => 'offered',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'swap_request_id' => $swapIdJune,
                    'shift_id' => $joanaShiftIdJune,
                    'owner_user_id' => $nurses[5]->id,
                    'type' => 'requested',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);
        }


        // Shift Swap Request 3: July (Status: Rejected)
        // Ines Carvalho (nurses[7]) and Sofia Almeida (nurses[8]) on July 20 (Both work)
        $julyDate = '2026-07-20';
        $inesShiftIdJuly = $shiftsByDateAndNurse[$julyDate][$nurses[7]->id] ?? null;
        $sofiaShiftIdJuly = $shiftsByDateAndNurse[$julyDate][$nurses[8]->id] ?? null;
        if ($inesShiftIdJuly && $sofiaShiftIdJuly) {
            $swapIdJuly = DB::table('shift_swap_requests')->insertGetId([
                'created_by' => $nurses[7]->id,
                'status' => 'rejected',
                'notes' => 'Pedido de troca de turno em Julho.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('shift_swap_participants')->insert([
                [
                    'swap_request_id' => $swapIdJuly,
                    'user_id' => $nurses[7]->id,
                    'role' => 'requester',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'swap_request_id' => $swapIdJuly,
                    'user_id' => $nurses[8]->id,
                    'role' => 'target',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);
            DB::table('shift_swap_request_shifts')->insert([
                [
                    'swap_request_id' => $swapIdJuly,
                    'shift_id' => $inesShiftIdJuly,
                    'owner_user_id' => $nurses[7]->id,
                    'type' => 'offered',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'swap_request_id' => $swapIdJuly,
                    'shift_id' => $sofiaShiftIdJuly,
                    'owner_user_id' => $nurses[8]->id,
                    'type' => 'requested',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ]);
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
