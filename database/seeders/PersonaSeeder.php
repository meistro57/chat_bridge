<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PersonaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesPath = base_path('roles.json');

        if (! File::exists($rolesPath)) {
            return;
        }

        $rolesData = json_decode(File::get($rolesPath), true);

        if (! isset($rolesData['persona_library'])) {
            return;
        }

        $admin = \App\Models\User::where('email', 'admin')->orWhere('role', 'admin')->first();

        foreach ($rolesData['persona_library'] as $key => $data) {
            Persona::updateOrCreate(
                ['name' => $data['name'] ?? $key],
                [
                    'user_id' => $admin?->id ?? 1,
                    'system_prompt' => $data['system'],
                    'guidelines' => $data['guidelines'] ?? [],
                    'temperature' => $data['temperature'] ?? ($rolesData['temp_a'] ?? 0.6),
                    'notes' => $data['notes'] ?? null,
                ]
            );
        }
    }
}
