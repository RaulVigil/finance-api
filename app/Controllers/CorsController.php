<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;


class CorsController extends BaseController
{
    use ResponseTrait;

    // Define la lista de orígenes permitidos
    private $allowedOrigins = [
        'http://localhost:5173', // Desarrollo local
        'https://financeapp.raulo.dev', // Producción
    ];

    /**
     * Genera la lista completa de orígenes permitidos (con y sin barra final).
     * @return array
     */
    private function getAllowedOriginsList(): array
    {
        $allowed = [];
        foreach ($this->allowedOrigins as $origin) {
            // 1. Añade la versión sin barra final (como está en la lista base)
            $allowed[] = $origin;

            // 2. Añade la versión CON barra final
            $allowed[] = $origin . '/';
        }
        return $allowed;
    }

    public function cors(): ResponseInterface
    {
        // Obtener la lista completa de orígenes permitidos
        $allowedOrigins = $this->getAllowedOriginsList();

        // 1. Obtener el origen de la solicitud
        $origin = service('request')->getHeaderLine('Origin');

        // 2. Verificar si el origen está en la lista de permitidos
        if (!empty($origin) && in_array($origin, $allowedOrigins)) {
            $this->response->setHeader('Access-Control-Allow-Origin', $origin);
        }

        // 3. Configurar los demás encabezados CORS
        $this->response->setHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $this->response->setStatusCode(200);
    }
}
