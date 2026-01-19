<?php

namespace Database\Seeders;

use App\Models\Persona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class RolesJsonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = base_path('roles.json');

        if (! File::exists($jsonPath)) {
            $this->command->error("roles.json not found at $jsonPath");

            return;
        }

        $json = json_decode(File::get($jsonPath), true);

        if (! isset($json['persona_library'])) {
            $this->command->error("No 'persona_library' key found in roles.json");

            return;
        }

        $personas = $json['persona_library'];
        $count = 0;

        foreach ($personas as $key => $data) {
            Persona::updateOrCreate(
                ['name' => $data['name'] ?? $key],
                [
                    'provider' => $data['provider'] ?? 'openai',
                    'model' => $data['model'] ?? null,
                    'system_prompt' => $data['system'] ?? '',
                    'guidelines' => $data['guidelines'] ?? [],
                    'temperature' => $data['temperature'] ?? 0.7, // Default if missing
                    'notes' => $data['notes'] ?? null,
                ]
            );
            $count++;
        }

        $this->command->info("Successfully seeded $count personas from roles.json");
    }
}
