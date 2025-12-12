<?php

namespace App\Controllers;

class Debug extends BaseController
{
    public function headers()
    {
        $headers = getallheaders();
    
        // Si no está en los headers normales, intenta obtenerlo desde $_SERVER
        if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }
    
        if (!isset($headers['Authorization']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
    
        return $this->response->setJSON([
            'headers' => $headers,
            'server' => $_SERVER
        ]);
    }
    
}
