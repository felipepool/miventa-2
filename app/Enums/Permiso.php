<?php

namespace App\Enums;

enum Permiso: string
{
    case GestionarUsuarios = 'gestionar_usuarios';
    case GestionarCatalogo = 'gestionar_catalogo';
    case GestionarSucursales = 'gestionar_sucursales';
    case GestionarNegocio = 'gestionar_negocio';
    case OperarCaja = 'operar_caja';
    case GestionarCajas = 'gestionar_cajas';
    case Vender = 'vender';
    case AnularVentas = 'anular_ventas';

    /** Descuentos sobre el tope que el negocio fija para los cajeros. */
    case DescontarSinLimite = 'descontar_sin_limite';
    case VerCocina = 'ver_cocina';
    case VerReportes = 'ver_reportes';
    case GestionarInventario = 'gestionar_inventario';
    case GestionarMesas = 'gestionar_mesas';
    case AtenderMesas = 'atender_mesas';
}
