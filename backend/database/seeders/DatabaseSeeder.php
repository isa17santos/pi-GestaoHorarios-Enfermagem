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

        /*
        |--------------------------------------------------------------------------
        | DADOS BASE DO PROJETO
        |--------------------------------------------------------------------------
        | Este bloco deve manter-se. Serve para arrancar a implementação com
        | utilizadores reais de referência e com os tipos de turno oficiais.
        */
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

        DB::table('shift_types')->insert([
            [
                'name' => 'morning',
                'color' => '#3B82F6',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'min_nurses' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'afternoon',
                'color' => '#F59E0B',
                'start_time' => '16:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'night',
                'color' => '#1E3A8A',
                'start_time' => '23:00:00',
                'end_time' => '08:00:00',
                'min_nurses' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'dayOff',
                'color' => '#9CA3AF',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'holidays',
                'color' => '#22C55E',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'sick leave',
                'color' => '#EF4444',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'parental leave',
                'color' => '#8B5CF6',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | DADOS TEMPORARIOS PARA TESTES DA API PARCIAL
        |--------------------------------------------------------------------------
        | Este bloco existe apenas para facilitar o desenvolvimento e teste dos
        | endpoints GET nesta fase. Pode ser removido quando existirem fluxos
        | reais de criacao/edicao de dados na aplicacao.
        */
        $headNurseId = DB::table('users')
            ->where('email', 'ana.antunes@example.pt')
            ->value('id');

        $nurses = DB::table('users')
            ->where('role', UserRole::Nurse->value)
            ->orderBy('id')
            ->get(['id', 'name', 'email']);

        $shiftTypeIds = DB::table('shift_types')
            ->pluck('id', 'name');

        DB::table('schedules')->insert([
            [
                'created_by' => $headNurseId,
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'created_by' => $headNurseId,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-30',
                'status' => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $schedules = DB::table('schedules')->orderBy('id')->get(['id']);
        $draftScheduleId = $schedules[0]->id;
        $publishedScheduleId = $schedules[1]->id;

        DB::table('user_schedules')->insert([
            [
                'user_id' => $nurses[0]->id,
                'schedule_id' => $draftScheduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[1]->id,
                'schedule_id' => $draftScheduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[2]->id,
                'schedule_id' => $draftScheduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[3]->id,
                'schedule_id' => $publishedScheduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[4]->id,
                'schedule_id' => $publishedScheduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[5]->id,
                'schedule_id' => $publishedScheduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('nurse_preferences')->insert([
            [
                'user_id' => $nurses[0]->id,
                'schedule_id' => $draftScheduleId,
                'prefers_morning' => true,
                'prefers_afternoon' => false,
                'prefers_night' => false,
                'avoid_weekends' => false,
                'prefers_weekends' => false,
                'notes' => 'Prefere turno da manha durante a semana.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[1]->id,
                'schedule_id' => $draftScheduleId,
                'prefers_morning' => false,
                'prefers_afternoon' => true,
                'prefers_night' => false,
                'avoid_weekends' => true,
                'prefers_weekends' => false,
                'notes' => 'Disponivel para tardes.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[2]->id,
                'schedule_id' => $draftScheduleId,
                'prefers_morning' => false,
                'prefers_afternoon' => false,
                'prefers_night' => true,
                'avoid_weekends' => false,
                'prefers_weekends' => true,
                'notes' => 'Aceita noites em semanas alternadas.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[3]->id,
                'schedule_id' => $publishedScheduleId,
                'prefers_morning' => true,
                'prefers_afternoon' => true,
                'prefers_night' => false,
                'avoid_weekends' => true,
                'prefers_weekends' => false,
                'notes' => 'Flexivel exceto noites.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[4]->id,
                'schedule_id' => $publishedScheduleId,
                'prefers_morning' => false,
                'prefers_afternoon' => true,
                'prefers_night' => true,
                'avoid_weekends' => false,
                'prefers_weekends' => true,
                'notes' => 'Prefere tardes, mas pode fazer noites.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[5]->id,
                'schedule_id' => $publishedScheduleId,
                'prefers_morning' => true,
                'prefers_afternoon' => false,
                'prefers_night' => true,
                'avoid_weekends' => false,
                'prefers_weekends' => false,
                'notes' => 'Prefere manhas e aceita algumas noites.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('shifts')->insert([
            [
                'schedule_id' => $draftScheduleId,
                'shift_type_id' => $shiftTypeIds['morning'],
                'shift_date' => '2026-03-10',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'schedule_id' => $draftScheduleId,
                'shift_type_id' => $shiftTypeIds['afternoon'],
                'shift_date' => '2026-03-10',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'schedule_id' => $draftScheduleId,
                'shift_type_id' => $shiftTypeIds['night'],
                'shift_date' => '2026-03-10',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'schedule_id' => $publishedScheduleId,
                'shift_type_id' => $shiftTypeIds['morning'],
                'shift_date' => '2026-04-05',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'schedule_id' => $publishedScheduleId,
                'shift_type_id' => $shiftTypeIds['afternoon'],
                'shift_date' => '2026-04-06',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'schedule_id' => $publishedScheduleId,
                'shift_type_id' => $shiftTypeIds['night'],
                'shift_date' => '2026-04-07',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $shifts = DB::table('shifts')->orderBy('id')->get(['id']);

        DB::table('user_shifts')->insert([
            [
                'user_id' => $nurses[0]->id,
                'shift_id' => $shifts[0]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[1]->id,
                'shift_id' => $shifts[1]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[2]->id,
                'shift_id' => $shifts[2]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[0]->id,
                'shift_id' => $shifts[3]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[1]->id,
                'shift_id' => $shifts[3]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[2]->id,
                'shift_id' => $shifts[3]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[3]->id,
                'shift_id' => $shifts[4]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[4]->id,
                'shift_id' => $shifts[4]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[5]->id,
                'shift_id' => $shifts[4]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[0]->id,
                'shift_id' => $shifts[5]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[1]->id,
                'shift_id' => $shifts[5]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $nurses[2]->id,
                'shift_id' => $shifts[5]->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
