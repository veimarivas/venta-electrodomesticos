<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            if (Schema::hasColumn('unidades', 'garantia_hasta')) {
                $table->dropColumn('garantia_hasta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            if (! Schema::hasColumn('unidades', 'garantia_hasta')) {
                $table->date('garantia_hasta')->nullable()->after('ubicacion');
            }
        });
    }
};
