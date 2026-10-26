<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ComprobanteResource;
use App\Models\Venta;
use Illuminate\Http\Request;

/**
 * Comprobante de una venta por su enlace público. No exige sesión: la ruta solo responde con
 * una firma válida y vigente, emitida por quien podía ver la venta.
 */
class ComprobanteController extends Controller
{
    public function __invoke(Request $request, string $venta): ComprobanteResource
    {
        $encontrada = Venta::withoutGlobalScopes()->where('public_id', $venta)->firstOrFail();

        return new ComprobanteResource($encontrada->load(ComprobanteResource::relaciones()));
    }
}
