<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\TipoDeCambio;
use Illuminate\Http\JsonResponse;

/** El dólar del día: oficial del BCB y paralelo, con la historia corta. */
class TipoDeCambioController extends Controller
{
    public function show(TipoDeCambio $tipoDeCambio): JsonResponse
    {
        return response()->json(['data' => $tipoDeCambio->actual()]);
    }
}
