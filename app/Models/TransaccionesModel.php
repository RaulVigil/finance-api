<?php

namespace App\Models;

use CodeIgniter\Model;

class TransaccionesModel extends Model
{
    protected $table            = 'transacciones';
    protected $primaryKey       = 'transaccion_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    
    protected $allowedFields    = [
        'usuario_id',
        'fecha',
        'tipo',         // 'Ingreso' o 'Egreso'
        'monto',
        'estado',       // 'pendiente' o 'pagado'
        'saldo_despues', 
        'descripcion',
        'categoria_id',
        'deuda_id',     // Puede ser null
        'presupuesto_estimado'
    ];
    
}