<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Cors implements FilterInterface
{
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

    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return RequestInterface|ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        log_message('info', "CORS: Inciando CORS Filter");
        log_message('info', "CORS Filter: Processing request for " . $request->getURI());
        $response = service('response');

        // Obtener la lista completa de orígenes permitidos
        $allowedOrigins = $this->getAllowedOriginsList();

        // 1. Obtener el origen de la solicitud
        $origin = $request->getHeaderLine('Origin');

        // 2. Verificar si el origen (con o sin barra) está permitido
        if (!empty($origin) && in_array($origin, $allowedOrigins)) {

            // **Punto clave:** Respondemos con la URL EXACTA que envió el navegador.
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            log_message('info', "CORS: Permitiendo origen: $origin");

            $method = $request->getMethod();
            // 3. Manejo de la solicitud OPTIONS (Preflight)
            if ($method === 'options') {
                // Establece las cabeceras para el preflight
                $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
                $response->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, X-Requested-With, Content-Type, Accept, Authorization');
                $response->setHeader('Access-Control-Max-Age', '3600');

                log_message('info', "CORS: Respondiendo a preflight OPTIONS para origen: $origin");

                // Devuelve la respuesta para el preflight y detiene la ejecución
                return $response->setStatusCode(200);
            } else {
                // Para solicitudes no-OPTIONS, simplemente continúa
                log_message('info', "CORS: Continuando con solicitud $method para origen: $origin");
            }
        } else {
            log_message('info', "CORS: Denegando origen: $origin");
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return ResponseInterface|void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigins = $this->getAllowedOriginsList();

        if (!empty($origin) && in_array($origin, $allowedOrigins)) {
            // Asegura que el ACAO esté en la respuesta final (no-OPTIONS)
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        }

        return $response;
    }
}
