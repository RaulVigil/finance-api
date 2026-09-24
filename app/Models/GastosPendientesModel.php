<?php

namespace App\Models;

use CodeIgniter\Model;

class GastosPendientesModel extends Model
{
    protected $table      = 'gastos_pendientes';
    protected $primaryKey = 'gasto_id';

    protected $allowedFields = [
        'usuario_id',
        'plantilla_id',
        'nombre',
        'descripcion',
        'categoria_id',
        'deuda_id',
        'monto',
        'mes',
        'anio',
        'estado',
        'fecha_pago',
        'transaccion_id',
    ];
}
