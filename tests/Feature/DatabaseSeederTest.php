<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_admin_and_personas(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertNotNull(User::where('email', 'admin@chatbridge.local')->first());
        $this->assertTrue(Persona::query()->exists());
    }
}
