<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->restrictOnDelete();
            $table->string('sku', 40)->unique();
            $table->string('nombre', 150);
            $table->string('slug', 160)->unique();
            $table->string('modelo', 120)->nullable();
            $table->text('descripcion')->nullable();
            $table->json('especificaciones')->nullable();
            $table->string('imagen', 255)->nullable();
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->unsignedInteger('meses_garantia')->default(12);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['categoria_id', 'marca_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
