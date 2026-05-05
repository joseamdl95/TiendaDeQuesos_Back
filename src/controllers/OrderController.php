<?php

require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Admin.php';


class OrderController {

    // LISTADO PEDIDOS
    public static function getOrders(PDO $pdo) {
        $id_usuario = Auth::check();

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id_pedido,
                    fecha_pedido,
                    total,
                    total_iva,
                    estado
                FROM pedido
                WHERE id_usuario = :id_usuario
                ORDER BY fecha_pedido DESC
            ");

            $stmt->execute([
                'id_usuario' => $id_usuario
            ]);

            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'orders' => $orders
            ]);

        } catch (Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    // DETALLE PEDIDO
    public static function getOrder(PDO $pdo, $id_pedido) {
        $id_usuario = Auth::check();

        try {

            // pedido + dirección
            $stmt = $pdo->prepare("
                SELECT
                    p.id_pedido,
                    p.fecha_pedido,
                    p.total,
                    p.total_iva,
                    p.id_direccion,

                    d.direccion,
                    d.cp,
                    d.ciudad,
                    d.provincia

                FROM pedido p

                LEFT JOIN direccion d
                    ON d.id_direccion = p.id_direccion

                WHERE p.id_pedido = :id_pedido
                AND p.id_usuario = :id_usuario
            ");

            $stmt->execute([
                'id_pedido' => $id_pedido,
                'id_usuario' => $id_usuario
            ]);

            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Pedido no encontrado");
            }

            // líneas
            $stmt = $pdo->prepare("
                SELECT
                    lp.cantidad,
                    p.nombre,
                    p.precio,
                    p.iva
                FROM linea_pedido lp
                JOIN producto p
                    ON p.id_producto = lp.id_producto
                WHERE lp.id_pedido = :id_pedido
            ");

            $stmt->execute([
                'id_pedido' => $id_pedido
            ]);

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);


            echo json_encode([
                'order' => $order,
                'items' => $items
            ]);

        } catch (Throwable $e) {

            http_response_code(404);

            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    //Listado de pedidos para ADMIN
    public static function getOrdersAdmin(PDO $pdo) {
        Admin::check($pdo);

        try{

            $stmt = $pdo->query("
                SELECT
                    p.id_pedido,
                    p.fecha_pedido,
                    p.total,
                    p.total_iva,
                    p.estado,
                    u.nombre,
                    u.apellidos,
                    u.email
                FROM pedido p
                JOIN usuario u
                    ON u.id_usuario = p.id_usuario
                ORDER BY p.fecha_pedido DESC
            ");

            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'orders' => $orders
            ]);

        } catch (Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'error' => 'Error obteniendo pedidos',
                'detalle' => $e->getMessage()
            ]);
        }
    }

     // DETALLE PEDIDOAdmin
    public static function getOrderAdmin(PDO $pdo, $id_pedido) {
        Admin::check($pdo);

        try {

            // pedido + dirección
            $stmt = $pdo->prepare("
                SELECT
                    p.id_pedido,
                    p.fecha_pedido,
                    p.total,
                    p.total_iva,
                    p.id_direccion,

                    d.direccion,
                    d.cp,
                    d.ciudad,
                    d.provincia

                FROM pedido p

                LEFT JOIN direccion d
                    ON d.id_direccion = p.id_direccion

                WHERE p.id_pedido = :id_pedido
            ");

            $stmt->execute([
                'id_pedido' => $id_pedido,
            ]);

            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Pedido no encontrado");
            }

            // líneas
            $stmt = $pdo->prepare("
                SELECT
                    lp.cantidad,
                    p.nombre,
                    p.precio,
                    p.iva
                FROM linea_pedido lp
                JOIN producto p
                    ON p.id_producto = lp.id_producto
                WHERE lp.id_pedido = :id_pedido
            ");

            $stmt->execute([
                'id_pedido' => $id_pedido
            ]);

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);


            echo json_encode([
                'order' => $order,
                'items' => $items
            ]);

        } catch (Throwable $e) {

            http_response_code(404);

            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    //Actualizar estado para ADMIN
    public static function updateStatus (PDO $pdo, $id) {
        Admin::check($pdo);

        try {

            $data = json_decode(file_get_contents("php://input"), true);

            $estado = $data['estado'] ?? '';

            $validos = [
                'PENDIENTE',
                'EN PREPARACION',
                'ENVIADO',
                'ENTREGADO',
                'CANCELADO'
            ];

            if (!in_array($estado, $validos)) {
                throw new Exception("Estado inválido");
            }

            $stmt = $pdo->prepare("
                UPDATE pedido
                SET estado = :estado
                WHERE id_pedido = :id
            ");

            $stmt->execute([
                'estado' => $estado,
                'id' => $id
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