<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $registros = [
            ['id' => 1, 'rol_id' => 1, 'nombreCompleto' => 'Carlos Eduardo Mendoza', 'nombreUsuario' => 'cmendoza', 'password' => Hash::make('12345678'), 'telefono' => '+591 70011122', 'estado' => 1],
            ['id' => 2, 'rol_id' => 2, 'nombreCompleto' => 'Ana María Gutiérrez', 'nombreUsuario' => 'agutierrez', 'password' => Hash::make('12345678'), 'telefono' => '+591 70033344', 'estado' => 1],
            ['id' => 3, 'rol_id' => 3, 'nombreCompleto' => 'Roberto Gómez Bolaños', 'nombreUsuario' => 'rgomez', 'password' => Hash::make('12345678'), 'telefono' => '+591 70055566', 'estado' => 1],
            ['id' => 4, 'rol_id' => 3, 'nombreCompleto' => 'Lucía Fernanda Rojas', 'nombreUsuario' => 'lrojas', 'password' => Hash::make('12345678'), 'telefono' => '+591 70077788', 'estado' => 1],
            ['id' => 5, 'rol_id' => 4, 'nombreCompleto' => 'Jorge Luis Torres', 'nombreUsuario' => 'jtorres', 'password' => Hash::make('12345678'), 'telefono' => '+591 70099900', 'estado' => 1],
        ];

        foreach ($registros as $item) {
            User::create($item);
        }
    }
}