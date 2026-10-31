<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * The panel's gate only grants handles present in the manifest, which
     * reads spatie permission rows — the row must exist or the handle
     * denies for everyone.
     */
    public function up(): void
    {
        Permission::firstOrCreate([
            'name' => 'media:manage',
            'guard_name' => config('lunar.staff.guard', 'staff'),
        ]);
    }

    public function down(): void
    {
        Permission::where('name', 'media:manage')->delete();
    }
};
