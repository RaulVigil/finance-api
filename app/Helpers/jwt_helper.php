<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

if (!function_exists('createJWT')) {
    function createJWT($payload)
    {
        $key = (string) \Config\Services::getSecretKey();
        $issuedAt = time();
        $expirationTime = $issuedAt + (3600 * 4); // jwt válida por 4 hora
        //$expirationTime = $issuedAt + 5;

        $payload['iat'] = $issuedAt;
        $payload['exp'] = $expirationTime;

        return JWT::encode($payload, $key, 'HS256');
    }
}

if (!function_exists('decodeJWT')) {
    function decodeJWT($jwt)
    {
        $key = (string) \Config\Services::getSecretKey();
        try {
            $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
            return (array) $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            return ['error' => 'Token has expired'];
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return ['error' => 'Invalid token signature'];
        } catch (\Firebase\JWT\BeforeValidException $e) {
            return ['error' => 'Token not active'];
        } catch (\Exception $e) {
            return ['error' => 'Invalid token'];
        }
    }
}

if (!function_exists('autorization')) {
    function autorization()
    {
        // Verificar que la cabecera de autorización exista
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return ['error' => 'Authorization header missing'];
        }
    
        // Obtener la cabecera
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
        $jwt = explode(' ', $headers)[1] ?? null;
    
        if (!$jwt) {
            return ['error' => 'Token not provided'];
        }
    
        // Decodificar el JWT
        $decoded = decodeJWT($jwt);
    
        // Verificar si hubo errores en la decodificación
        if (isset($decoded['error'])) {
            return $decoded; // Retornar el error de la función decodeJWT
        }
    
        // Extraer `id_user` correctamente
        $userId = $decoded['id_user'] ?? null;
    
        if (!$userId) {
            return ['error' => 'Invalid token data: id_user missing'];
        }
    
        // Buscar el usuario en la base de datos
        $db = \Config\Database::connect();
        $user = $db->table('users')->where('id_user', $userId)->get()->getRow();
    
        if (!$user) {
            return ['error' => 'User not found'];
        }
    
        return ['user' => $user]; // Retorna el usuario autenticado
    }
    
}


