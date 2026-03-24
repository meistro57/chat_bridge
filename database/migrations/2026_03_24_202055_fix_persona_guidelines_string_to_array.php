<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert any persona guidelines stored as a JSON string into a JSON array.
     *
     * Root cause: some UI paths stored guidelines as a plain string value
     * (e.g. "Be concise.") rather than a JSON array (["Be concise."]).
     * The JSON cast returns a PHP string in that case, breaking foreach iteration.
     */
    public function up(): void
    {
        DB::table('personas')
            ->whereNotNull('guidelines')
            ->get(['id', 'guidelines'])
            ->each(function (object $persona): void {
                $decoded = json_decode($persona->guidelines, true);

                // Only act when the stored JSON decodes to a scalar string.
                if (! is_string($decoded)) {
                    return;
                }

                DB::table('personas')
                    ->where('id', $persona->id)
                    ->update(['guidelines' => json_encode([$decoded])]);
            });
    }

    public function down(): void
    {
        // Not reversible without a snapshot — data migration only.
    }
};
