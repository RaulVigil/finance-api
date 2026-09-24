<?php

namespace App\Models;

use CodeIgniter\Model;

class GastosFijosPlantillasModel extends Model
{
    protected $table      = 'gastos_fijos_plantillas';
    protected $primaryKey = 'plantilla_id';

    protected $allowedFields = [
        'usuario_id',
        'nombre',
        'descripcion',
        'categoria_id',
        'deuda_id',
        'monto_estimado',
        'activa',
    ];
}
