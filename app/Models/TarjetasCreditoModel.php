<?php

namespace App\Models;

use CodeIgniter\Model;

class TarjetasCreditoModel extends Model
{
    protected $table      = 'tarjetas_credito';
    protected $primaryKey = 'tarjeta_id';

    protected $allowedFields = [
        'usuario_id',
        'nombre',
        'banco',
        'limite_credito',
        'saldo_disponible',
        'saldo_utilizado',
        'dia_corte',
        'dia_pago',
        'estado',
    ];
}
