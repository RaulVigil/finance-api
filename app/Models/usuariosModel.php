<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuariosModel extends Model
{
    protected $table = 'usuarios';
    protected $primaryKey = 'usuario_id';

    protected $allowedFields = [
        'nombre',
        'email',
        'contrasena',
        'saldo_actual',
        'tipo_usuario',
        'estado',
        'esta_verificado',
        'token_verificacion',
        'fecha_verificacion',
        'fecha_creacion',
        'fecha_actualizacion',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'fecha_creacion';
    protected $updatedField  = 'fecha_actualizacion';
}
