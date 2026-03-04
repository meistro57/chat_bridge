<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SampleAvatarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $avatars = [
            'avatars/sample-aurora.png',
            'avatars/sample-ember.png',
            'avatars/sample-tide.png',
        ];

        $users = User::query()->orderBy('id')->get();

        foreach ($users as $index => $user) {
            if ($user->avatar) {
                continue;
            }

            $user->update([
                'avatar' => $avatars[$index % count($avatars)],
            ]);
        }
    }
}
