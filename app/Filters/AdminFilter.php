<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use CodeIgniter\API\ResponseTrait;

class AdminFilter implements FilterInterface
{
    use ResponseTrait;

    public function before(RequestInterface $request, $arguments = null)
    {
        $response = Services::response();

        // Obtener el header Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            return $response->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)->setJSON([
                'status' => ResponseInterface::HTTP_UNAUTHORIZED,
                'message' => 'Debes iniciar sesión para acceder.',
            ]);
        }

        // Validar formato Bearer
        $parts = explode(' ', $authHeader);
        if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
            return $response->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)->setJSON([
                'status' => ResponseInterface::HTTP_UNAUTHORIZED,
                'message' => 'Formato de cabecera Authorization inválido.',
            ]);
        }

        // Decodificar el JWT
        $jwt = $parts[1];
        $decoded = decodeJWT($jwt);

        if (isset($decoded['error'])) {
            return $response->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)->setJSON([
                'status' => ResponseInterface::HTTP_UNAUTHORIZED,
                'message' => 'Token inválido o expirado.',
                'error' => $decoded['error']
            ]);
        }

        // Revisar que el usuario sea admin
        if (!isset($decoded['user_type']) || $decoded['user_type'] !== 'admin') {
            return $response->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)->setJSON([
                'status' => ResponseInterface::HTTP_FORBIDDEN,
                'message' => 'Acceso denegado: No eres Admin.',
            ]);
        }

        // Opcional: puedes guardar info de usuario en el request para el controller
        // $request->user = $decoded;

        // Si todo está bien, sigue con la request
        // No return significa "deja pasar"
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada
    }
}
