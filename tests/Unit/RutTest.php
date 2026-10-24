<?php

use App\Rules\Rut;

test('ruts are normalized without dots and with an uppercase check digit', function (string $entrada, string $esperado) {
    expect(Rut::normalizar($entrada))->toBe($esperado);
})->with([
    ['12.345.678-5', '12345678-5'],
    [' 10.000.013-k ', '10000013-K'],
    ['123456785', '12345678-5'],
]);

test('the check digit follows modulus 11', function (string $cuerpo, string $digito) {
    expect(Rut::digitoVerificador($cuerpo))->toBe($digito);
})->with([['12345678', '5'], ['10000013', 'K'], ['11111111', '1']]);

test('only well formed ruts with a matching check digit pass', function (mixed $rut, bool $valido) {
    $fallas = [];
    (new Rut)->validate('rut', $rut, function (string $mensaje) use (&$fallas) {
        $fallas[] = $mensaje;
    });
    expect($fallas === [])->toBe($valido);
})->with([
    ['12345678-5', true], ['10000013-K', true], ['12345678-9', false],
    ['12.345.678-5', false], ['123-4', false], [12345678, false],
]);
