<?php
/**
 * Regenera los recortes de la marca a partir de logo_hogar.png.
 *
 *   php scripts/generar_marca.php
 *
 * `logo_hogar.png` es el original con el fondo recortado y **no se sirve**: es
 * la fuente de todo lo demás. Cuando cambia el logo, hay que rehacer:
 *
 *   public/assets/images/marca-login.png    478 px de ancho · login del panel
 *   public/assets/images/marca-sidebar.png  260 px de ancho · menú y topbar
 *   public/assets/images/favicon.ico        cuadrado azul noche · pestaña
 *   public/favicon.ico                      copia del anterior (raíz)
 *
 * El recorte NO está a fuego: se mide por el canal alfa del propio archivo, así
 * que un logo nuevo con otro ancho o con la tira de categorías en otro sitio se
 * recorta solo. El logotipo —sin la tira— es el que va al favicon y al icono de
 * la app: a 48 dp los rótulos de esa tira miden menos de un píxel.
 */

$raiz   = dirname(__DIR__);
$origen = $raiz.'/public/assets/images/logo_hogar.png';
$destino = $raiz.'/public/assets/images';
$NOCHE  = [0x0a, 0x18, 0x2b];

if (! is_file($origen)) {
    fwrite(STDERR, "No se encontró $origen\n");
    exit(1);
}

$original = imagecreatefrompng($origen);
$ancho    = imagesx($original);
$alto     = imagesy($original);

/** Recuadro del contenido: píxeles con alfa por debajo del umbral. */
function cajaDeFilas(\GdImage $img, int $w, int $desde, int $hasta): ?array
{
    $minX = $w; $maxX = -1; $minY = $hasta; $maxY = $desde;

    for ($y = $desde; $y <= $hasta; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) < 120) {
                if ($x < $minX) $minX = $x;
                if ($x > $maxX) $maxX = $x;
                if ($y < $minY) $minY = $y;
                if ($y > $maxY) $maxY = $y;
            }
        }
    }

    return $maxX < 0 ? null : ['x' => $minX, 'y' => $minY, 'w' => $maxX - $minX + 1, 'h' => $maxY - $minY + 1];
}

$caja = cajaDeFilas($original, $ancho, 0, $alto - 1);

if ($caja === null) {
    fwrite(STDERR, "El logo no tiene píxeles visibles.\n");
    exit(1);
}

// Huecos internos de filas vacías: el más alto separa el logotipo de la tira.
$contenido = [];
$huecos = [];
$inicio = null;

for ($y = $caja['y']; $y <= $caja['y'] + $caja['h'] - 1; $y++) {
    $hay = false;

    for ($x = 0; $x < $ancho; $x++) {
        if (((imagecolorat($original, $x, $y) >> 24) & 0x7F) < 120) {
            $hay = true;
            break;
        }
    }

    $contenido[$y] = $hay;

    if (! $hay) {
        $inicio ??= $y;
    } elseif ($inicio !== null) {
        $huecos[] = [$inicio, $y - 1];
        $inicio = null;
    }
}

usort($huecos, fn ($a, $b) => ($b[1] - $b[0]) <=> ($a[1] - $a[0]));

$logotipo = $huecos === []
    ? $caja
    : cajaDeFilas($original, $ancho, $caja['y'], $huecos[0][0] - 1);

/** Copia un recuadro del original sobre un lienzo, escalándolo sin deformarlo. */
function componer(\GdImage $original, array $caja, int $lienzoW, int $lienzoH, ?array $fondo = null): \GdImage
{
    $img = imagecreatetruecolor($lienzoW, $lienzoH);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, $fondo === null
        ? imagecolorallocatealpha($img, 0, 0, 0, 127)
        : imagecolorallocate($img, ...$fondo));
    imagealphablending($img, true);

    // Contener, no estirar: el logo cambia de proporción entre versiones y
    // deformarlo es justo lo que no se debe hacer con una marca.
    $escala = min($lienzoW / $caja['w'], $lienzoH / $caja['h']);
    $w = (int) round($caja['w'] * $escala);
    $h = (int) round($caja['h'] * $escala);

    imagecopyresampled(
        $img, $original,
        (int) round(($lienzoW - $w) / 2), (int) round(($lienzoH - $h) / 2),
        $caja['x'], $caja['y'],
        $w, $h, $caja['w'], $caja['h']
    );

    return $img;
}

/** Escala el logo completo a un ancho dado, conservando la proporción. */
function anchoFijo(\GdImage $original, array $caja, int $ancho): \GdImage
{
    return componer($original, $caja, $ancho, (int) round($ancho * $caja['h'] / $caja['w']));
}

/** Bloque PNG en memoria, para empaquetar el .ico. */
function pngDe(\GdImage $img): string
{
    ob_start();
    imagepng($img, null, 9);

    return (string) ob_get_clean();
}

/** Empaqueta varios PNG en un .ico (Vista+ los admite comprimidos). */
function ico(array $imagenes): string
{
    $n = count($imagenes);
    $cabecera = pack('vvv', 0, 1, $n);
    $directorio = '';
    $datos = '';
    $offset = 6 + 16 * $n;

    foreach ($imagenes as [$lado, $png]) {
        $directorio .= pack('CCCCvvVV', $lado === 256 ? 0 : $lado, $lado === 256 ? 0 : $lado, 0, 0, 1, 32, strlen($png), $offset);
        $offset += strlen($png);
        $datos .= $png;
    }

    return $cabecera.$directorio.$datos;
}

// ---- Recortes que sirven el panel -----------------------------------------
$login = anchoFijo($original, $caja, 478);
imagepng($login, "$destino/marca-login.png", 9);
printf("marca-login.png   %d×%d\n", imagesx($login), imagesy($login));

$sidebar = anchoFijo($original, $caja, 260);
imagepng($sidebar, "$destino/marca-sidebar.png", 9);
printf("marca-sidebar.png %d×%d\n", imagesx($sidebar), imagesy($sidebar));

// ---- Favicon: el logotipo sobre el azul noche ------------------------------
$entradas = [];

foreach ([16, 32, 48] as $lado) {
    // En el favicon el logotipo llena más que en el icono adaptativo: no hay
    // máscara del sistema que recorte las esquinas.
    $img = imagecreatetruecolor($lado, $lado);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocate($img, ...$NOCHE));
    imagealphablending($img, true);

    $w = (int) round($lado * 0.9);
    $h = (int) round($w * $logotipo['h'] / $logotipo['w']);

    imagecopyresampled(
        $img, $original,
        (int) round(($lado - $w) / 2), (int) round(($lado - $h) / 2),
        $logotipo['x'], $logotipo['y'],
        $w, $h, $logotipo['w'], $logotipo['h']
    );

    $entradas[] = [$lado, pngDe($img)];
    imagedestroy($img);
}

$ico = ico($entradas);
file_put_contents("$destino/favicon.ico", $ico);
copy("$destino/favicon.ico", $raiz.'/public/favicon.ico');
printf("favicon.ico       %d bytes (%s)\n", strlen($ico), '16/32/48');

printf("Recorte del logotipo: x=%d y=%d w=%d h=%d\n", $logotipo['x'], $logotipo['y'], $logotipo['w'], $logotipo['h']);
echo "Marca regenerada.\n";
