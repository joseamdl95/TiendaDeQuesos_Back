<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {

    public static function check() {

        $jwtConfig = require __DIR__ . '/../../config/jwt.php';
        $secret = $jwtConfig['secret'];

        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido']);
            exit;
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            return $decoded->sub;

        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido']);
            exit;
        }
    }
}