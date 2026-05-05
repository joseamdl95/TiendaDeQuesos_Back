<?php

require_once __DIR__ . '/Auth.php';

class Admin {

    public static function check(PDO $pdo) {

        $id_usuario = Auth::check();

        $stmt = $pdo->prepare("
            SELECT rol
            FROM usuario
            WHERE id_usuario = :id
        ");

        $stmt->execute([
            'id' => $id_usuario
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || $user['rol'] !== 'ADMIN') {
            http_response_code(403);

            echo json_encode([
                'error' => 'Acceso denegado'
            ]);

            exit;
        }

        return $id_usuario;
    }
}