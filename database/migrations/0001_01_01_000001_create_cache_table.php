<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Cache tables omitted: this API uses CACHE_STORE=redis exclusively.
     * If database cache is ever needed, restore the Schema::create calls.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
