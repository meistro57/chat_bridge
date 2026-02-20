<?php

namespace Database\Seeders;

use App\Models\ConversationTemplate;
use App\Models\Persona;
use Illuminate\Database\Seeder;

class ConversationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $personas = Persona::query()->limit(2)->get();

        if ($personas->count() < 2) {
            return;
        }

        [$personaA, $personaB] = [$personas[0], $personas[1]];

        ConversationTemplate::updateOrCreate(
            ['name' => 'Classic Debate'],
            [
                'description' => 'Two personas debate a controversial topic with evidence and counterpoints.',
                'category' => 'Debate',
                'starter_message' => 'Debate the impact of AI on creative work. Persona A: optimistic. Persona B: skeptical.',
                'max_rounds' => 10,
                'persona_a_id' => $personaA->id,
                'persona_b_id' => $personaB->id,
                'is_public' => true,
                'user_id' => null,
            ]
        );

        ConversationTemplate::updateOrCreate(
            ['name' => 'Brainstorm Sprint'],
            [
                'description' => 'Rapid idea generation with follow-up questions and prioritization.',
                'category' => 'Brainstorm',
                'starter_message' => 'Generate bold product ideas for a privacy-first social app. Ask clarifying questions before listing ideas.',
                'max_rounds' => 8,
                'persona_a_id' => $personaA->id,
                'persona_b_id' => $personaB->id,
                'is_public' => true,
                'user_id' => null,
            ]
        );

        ConversationTemplate::updateOrCreate(
            ['name' => 'Interview Mode'],
            [
                'description' => 'Persona B interviews Persona A with probing questions, summaries, and takeaways.',
                'category' => 'Interview',
                'starter_message' => 'Interview about scaling a startup from 10 to 200 employees. Focus on culture, hiring, and product.',
                'max_rounds' => 12,
                'persona_a_id' => $personaA->id,
                'persona_b_id' => $personaB->id,
                'is_public' => true,
                'user_id' => null,
            ]
        );

        ConversationTemplate::updateOrCreate(
            ['name' => 'Story Studio'],
            [
                'description' => 'Collaborative storytelling with alternating turns and scene-setting.',
                'category' => 'Story',
                'starter_message' => 'Write a sci-fi short story set on a drifting space habitat. Alternate narrative beats each round.',
                'max_rounds' => 14,
                'persona_a_id' => $personaA->id,
                'persona_b_id' => $personaB->id,
                'is_public' => true,
                'user_id' => null,
            ]
        );
    }
}
