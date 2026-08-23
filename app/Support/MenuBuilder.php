<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Convierte el array declarativo de config/menu.php en la estructura que
 * consume el partial del sidebar: resuelve URLs, filtra por permisos y
 * calcula qué rama está activa según la URL actual.
 */
class MenuBuilder
{
    /** Contador para generar ids únicos de los <div class="collapse">. */
    private int $sequence = 0;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(?array $items = null): array
    {
        $items ??= config('menu', []);

        $result = [];

        foreach ($items as $item) {
            $normalized = $this->normalize($item);

            if ($normalized !== null) {
                $result[] = $normalized;
            }
        }

        return $this->removeEmptySections($result);
    }

    /**
     * Descarta los encabezados de sección que se quedaron sin ítems visibles.
     * Sin esto, un vendedor vería los títulos "Análisis" o "Sistema" sueltos,
     * porque los enlaces que había debajo los filtró el control de permisos.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function removeEmptySections(array $items): array
    {
        $result = [];

        foreach ($items as $index => $item) {
            if (($item['type'] ?? null) === 'title') {
                $next = $items[$index + 1] ?? null;

                // Título al final de la lista, o seguido de otro título:
                // en ambos casos no encabeza nada.
                if ($next === null || ($next['type'] ?? null) === 'title') {
                    continue;
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null  null si el usuario no debe verlo
     */
    private function normalize(array $item): ?array
    {
        if (! $this->isVisible($item)) {
            return null;
        }

        if (($item['type'] ?? 'link') === 'title') {
            return ['type' => 'title', 'label' => $item['label']];
        }

        $children = [];

        foreach ($item['children'] ?? [] as $child) {
            $normalizedChild = $this->normalize($child);

            if ($normalizedChild !== null) {
                $children[] = $normalizedChild;
            }
        }

        // Un padre que se quedó sin hijos visibles no aporta nada.
        if (! empty($item['children']) && $children === []) {
            return null;
        }

        $active = $this->isActive($item)
            || collect($children)->contains(fn (array $child) => $child['active'] ?? false);

        return [
            'type' => 'link',
            'id' => 'sidebar-'.Str::slug($item['label']).'-'.(++$this->sequence),
            'label' => $item['label'],
            'icon' => $item['icon'] ?? null,
            'badge' => $item['badge'] ?? null,
            'url' => $this->resolveUrl($item),
            'active' => $active,
            'children' => $children,
        ];
    }

    /**
     * Oculta el ítem si requiere un permiso que el usuario no tiene.
     *
     * @param  array<string, mixed>  $item
     */
    private function isVisible(array $item): bool
    {
        if (empty($item['permission'])) {
            return true;
        }

        $user = Auth::user();

        return $user !== null && $user->can($item['permission']);
    }

    /**
     * Resuelve la URL del ítem. Si la ruta todavía no existe (módulo aún no
     * implementado) devuelve '#' en lugar de reventar con RouteNotFound.
     *
     * @param  array<string, mixed>  $item
     */
    private function resolveUrl(array $item): string
    {
        if (! empty($item['url'])) {
            return $item['url'];
        }

        if (! empty($item['route'])) {
            return Route::has($item['route']) ? route($item['route']) : '#';
        }

        return '#';
    }

    /**
     * Marca el ítem como activo comparando la URL actual contra los patrones
     * declarados en 'active' (o contra la propia ruta si no se declaró ninguno).
     *
     * @param  array<string, mixed>  $item
     */
    private function isActive(array $item): bool
    {
        $patterns = (array) ($item['active'] ?? []);

        if ($patterns === [] && ! empty($item['route']) && Route::has($item['route'])) {
            $patterns = [ltrim(Str::after(route($item['route']), config('app.url')), '/') ?: '/'];
        }

        return $patterns !== [] && Request::is(...$patterns);
    }
}
