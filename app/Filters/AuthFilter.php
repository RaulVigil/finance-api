<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use Config\Services; // Import the Services class

class AuthFilter implements FilterInterface
{
    use ResponseTrait;

    public function before(RequestInterface $request, $arguments = null)
    {
        // Obtain the response instance from Services
        $response = Services::response();

        // Validar el JWT que viene en Bearer en el header
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            return $response->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)->setJSON([
                'status' => ResponseInterface::HTTP_UNAUTHORIZED,
                'message' => 'You must log in to access Tripiazone.',
            ]);
        } else {
            
            // Validar el JWT
            $parts = explode(' ', $authHeader);
            if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
                return ['error' => 'Invalid Authorization header format'];
            }
    
            // Obtener el JWT
            $jwt = $parts[1];
            
            // Decodificar el JWT
            $decoded = decodeJWT($jwt);
           

            // Verificar si hubo errores en la decodificaciÃ³n
            if (isset($decoded['error'])) {
                return $response->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)->setJSON([
                    'status' => ResponseInterface::HTTP_UNAUTHORIZED,
                    'message' => 'You must log in to access Tripiazone.',
                    'error' => $decoded['error']
                ]);
            }
        }
        // If you want to continue the filter logic add it here
        // e.g. validate the JWT and set a user in session, etc.

        // ...
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}