<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();

            // Jerarquía padre/hijo. El restrict es una red de seguridad: la
            // app bloquea borrar una categoría con subcategorías, así que el
            // constraint solo saltaría ante un forceDelete accidental.
            $table->foreignId('padre_id')->nullable()
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->string('nombre', 100);
            $table->string('slug', 120)->unique();
            $table->text('descripcion')->nullable();
            $table->string('imagen', 255)->nullable();
            $table->unsignedInteger('posicion')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // El árbol se recorre hermano por hermano según posicion.
            $table->index(['padre_id', 'posicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
