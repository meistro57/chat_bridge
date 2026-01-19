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
        $rolesPath = base_path('../roles.json');

        if (! File::exists($rolesPath)) {
            return;
        }

        $rolesData = json_decode(File::get($rolesPath), true);

        if (! isset($rolesData['persona_library'])) {
            return;
        }

        foreach ($rolesData['persona_library'] as $key => $data) {
            Persona::updateOrCreate(
                ['name' => $data['name'] ?? $key],
                [
                    'provider' => $data['provider'],
                    'model' => $data['model'] ?? null,
                    'system_prompt' => $data['system'],
                    'guidelines' => $data['guidelines'] ?? [],
                    'temperature' => $data['temperature'] ?? ($rolesData['temp_a'] ?? 0.6),
                    'notes' => $data['notes'] ?? null,
                ]
            );
        }
    }
}
