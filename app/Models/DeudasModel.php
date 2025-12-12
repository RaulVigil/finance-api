<?php

namespace App\Models;

use CodeIgniter\Model;

class DeudasModel extends Model
{
    protected $table      = 'deudas';
    protected $primaryKey = 'deuda_id';
    protected $allowedFields = [
        'usuario_id',
        'nombre_deuda',
        'monto_total_inicial',
        'cuota_mensual',
        'saldo_pendiente', // Este es el que actualizaremos
        'fecha_inicio',
        'fecha_vencimiento',
        'estado'
    ];
}