<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 401,
                    'message' => 'Usuario no autenticado',
                ]);
        }

        $parts = explode(' ', $authHeader);

        if (count($parts) !== 2 || $parts[0] !== 'Bearer' || empty($parts[1])) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 401,
                    'message' => 'Token inválido (formato esperado: Bearer <token>)',
                ]);
        }

        helper('jwt_helper');
        $decoded = decodeJWT($parts[1]);

        if (isset($decoded['error'])) {
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 401,
                    'message' => $decoded['error'],
                ]);
        }

        /**
         * ✅ Forma segura y compatible en CI4:
         * Guardamos el usuario decodificado en SERVER para leerlo luego con getServer().
         */
        if ($request instanceof \CodeIgniter\HTTP\IncomingRequest) {
            $request->setGlobal('server', [
                'auth_user' => $decoded, // aquí queda disponible
            ]);
        }

        // Si retornas null, CI4 deja pasar la request.
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }
}
