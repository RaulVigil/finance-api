<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoriasModel extends Model
{
    protected $table      = 'categorias';
    protected $primaryKey = 'categoria_id';

    protected $allowedFields = [
        'nombre',
    ];

    protected $returnType = 'array';
}
