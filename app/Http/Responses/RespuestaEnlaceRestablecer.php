<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Respuesta al pedir el enlace para restablecer la contraseña. Es la misma
 * exista o no la cuenta (o si se pidió hace muy poco), para no revelar qué
 * correos están registrados.
 */
class RespuestaEnlaceRestablecer implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const string MENSAJE = 'Si el correo está registrado, te enviamos un enlace para crear una contraseña nueva. Revisa tu bandeja de entrada y la carpeta de spam.';

    /**
     * Fortify entrega el estado del envío; se ignora a propósito.
     */
    public function __construct(public string $status = '') {}

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['message' => self::MENSAJE]);
    }
}
