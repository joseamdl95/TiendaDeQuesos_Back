<?php

require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Uuid.php';

class AddressController {

    // LISTAR
    public static function getAll(PDO $pdo) {
        $id_usuario = Auth::check();

        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM direccion
                WHERE id_usuario = :id_usuario
                ORDER BY facturacion DESC, alias ASC
            ");

            $stmt->execute([
                'id_usuario' => $id_usuario
            ]);

            echo json_encode([
                'addresses' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    //Obtener direccion
    public static function getOne(PDO $pdo, $id){
        $id_usuario = Auth::check();

        try{
            $stmt = $pdo->prepare("
                SELECT 
                    alias,
                    direccion,
                    cp,
                    ciudad,
                    provincia
                FROM direccion
                WHERE id_direccion = :id
                AND id_usuario = :usuario
            ");

            $stmt->execute([
                'id' => $id,
                'usuario' => $id_usuario
            ]);

             echo json_encode([
                'address' => $stmt->fetch(PDO::FETCH_ASSOC)
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }

    }

    // CREAR
    public static function create(PDO $pdo) {
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        try {

            $id_direccion = uuidv4();

            if (($data['facturacion'] ?? 0) == 1) {
                $stmt = $pdo->prepare("
                    UPDATE direccion
                    SET facturacion = 0
                    WHERE id_usuario = :id_usuario
                ");
                $stmt->execute([
                    'id_usuario' => $id_usuario
                ]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO direccion (
                    Id_direccion,
                    id_usuario,
                    alias,
                    direccion,
                    cp,
                    ciudad,
                    provincia,
                    facturacion
                )
                VALUES (
                    :id,
                    :usuario,
                    :alias,
                    :direccion,
                    :cp,
                    :ciudad,
                    :provincia,
                    :facturacion
                )
            ");

            $stmt->execute([
                'id' => $id_direccion,
                'usuario' => $id_usuario,
                'alias' => $data['alias'],
                'direccion' => $data['direccion'],
                'cp' => $data['cp'],
                'ciudad' => $data['ciudad'],
                'provincia' => $data['provincia'],
                'facturacion' => $data['facturacion'] ?? 0
            ]);

            echo json_encode([
                'ok' => true
            ]);

        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    // EDITAR
    public static function update(PDO $pdo, $id) {
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        try {

            if (($data['facturacion'] ?? 0) == 1) {
                $stmt = $pdo->prepare("
                    UPDATE direccion
                    SET facturacion = 0
                    WHERE id_usuario = :id_usuario
                ");
                $stmt->execute([
                    'id_usuario' => $id_usuario
                ]);
            }

            $stmt = $pdo->prepare("
                UPDATE direccion
                SET
                    alias = :alias,
                    direccion = :direccion,
                    cp = :cp,
                    ciudad = :ciudad,
                    provincia = :provincia,
                    facturacion = :facturacion
                WHERE Id_direccion = :id
                AND id_usuario = :usuario
            ");

            $stmt->execute([
                'alias' => $data['alias'],
                'direccion' => $data['direccion'],
                'cp' => $data['cp'],
                'ciudad' => $data['ciudad'],
                'provincia' => $data['provincia'],
                'facturacion' => $data['facturacion'] ?? 0,
                'id' => $id,
                'usuario' => $id_usuario
            ]);

            echo json_encode([
                'ok' => true
            ]);

        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    // BORRAR
    public static function delete(PDO $pdo, $id) {
        $id_usuario = Auth::check();

        try {

            $stmt = $pdo->prepare("
                DELETE FROM direccion
                WHERE Id_direccion = :id
                AND id_usuario = :usuario
            ");

            $stmt->execute([
                'id' => $id,
                'usuario' => $id_usuario
            ]);

            echo json_encode([
                'ok' => true
            ]);

        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }
}