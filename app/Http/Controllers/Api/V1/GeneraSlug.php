<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Deriva el slug cuando la app no lo manda.
 *
 * En el panel el slug es un campo visible que se autocompleta mientras se
 * escribe el nombre. En el teléfono no tiene sentido pedirlo: es un dato
 * técnico que nadie teclea de pie en la tienda. Así que la API lo acepta si
 * viene y lo deriva del nombre si no.
 *
 * Derivarlo obliga a resolver los choques aquí: dos productos llamados igual
 * darían el mismo slug y el índice único rechazaría el segundo con un error
 * que no dice nada. Se le añade un sufijo numérico, que es lo que espera quien
 * registra dos variantes del mismo aparato.
 */
trait GeneraSlug
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $consulta
     */
    private function slugUnico(
        ?string $pedido,
        string $nombre,
        Builder $consulta,
        ?int $ignorarId,
    ): string {
        // Un slug escrito a mano se respeta tal cual: ya pasó por la regla
        // `unique` de la validación, así que si choca es un error del usuario y
        // debe verlo, no que se lo cambiemos por la espalda.
        if ($pedido !== null && $pedido !== '') {
            return $pedido;
        }

        $base = Str::slug($nombre);

        // Un nombre de solo símbolos deja `Str::slug` en cadena vacía, y un
        // slug vacío rompe las URL del panel.
        if ($base === '') {
            $base = 'sin-nombre';
        }

        $slug = $base;
        $sufijo = 2;

        while ($this->slugOcupado($consulta, $slug, $ignorarId)) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $consulta
     */
    private function slugOcupado(Builder $consulta, string $slug, ?int $ignorarId): bool
    {
        return (clone $consulta)
            ->where('slug', $slug)
            ->when($ignorarId !== null, fn ($q) => $q->whereKeyNot($ignorarId))
            ->exists();
    }
}
