<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!function_exists('createJWT')) {
    function createJWT(array $payload): string
    {
        $key = \Config\Services::getSecretKey();

        $issuedAt = time();
        $expirationTime = $issuedAt + (3600 * 4); // 4 hours

        $payload['iat'] = $issuedAt;
        $payload['exp'] = $expirationTime;

        return JWT::encode($payload, $key, 'HS256');
    }
}

if (!function_exists('decodeJWT')) {
    function decodeJWT(string $jwt): array
    {
        $key = \Config\Services::getSecretKey();

        try {
            return (array) JWT::decode($jwt, new Key($key, 'HS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            return ['error' => 'Token has expired'];
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return ['error' => 'Invalid token signature'];
        } catch (\Firebase\JWT\BeforeValidException $e) {
            return ['error' => 'Token not active'];
        } catch (\Throwable $e) {
            return ['error' => 'Invalid token'];
        }
    }
}
