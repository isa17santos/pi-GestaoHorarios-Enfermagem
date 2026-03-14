<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
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

        User::query()->create([
            'name' => 'Miguel Ferreira',
            'email' => 'miguel.ferreira@example.pt',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'active' => true,
            'new_account' => false,
        ]);

        User::query()->create([
            'name' => 'Ana Antunes',
            'email' => 'ana.antunes@example.pt',
            'password' => Hash::make('password'),
            'role' => UserRole::HeadNurse,
            'active' => true,
            'new_account' => false,
        ]);

        collect([
            'Helena Coelho',
            'Joana Silva',
            'Mariana Rocha',
            'Ines Carvalho',
            'Sofia Almeida',
            'Beatriz Sousa',
        ])->each(function (string $name): void {
            $email = str($name)
                ->lower()
                ->ascii()
                ->replace(' ', '.')
                ->append('@example.pt')
                ->toString();

            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => UserRole::Nurse,
                'active' => true,
                'new_account' => false,
            ]);
        });

        DB::table('shift_types')->insert([
            [
                'name' => 'morning',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'afternoon',
                'start_time' => '16:00:00',
                'end_time' => '00:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'night',
                'start_time' => '00:00:00',
                'end_time' => '08:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
