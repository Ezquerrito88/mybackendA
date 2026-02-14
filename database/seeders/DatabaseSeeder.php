<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categories;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('123456'),
        ]);

        Categories::create(['name' => 'Derechos Humanos']);
        Categories::create(['name' => 'Medio Ambiente']);
        Categories::create(['name' => 'Salud']);
        Categories::create(['name' => 'Educación']);
        Categories::create(['name' => 'Justicia Económica']);
    }
}