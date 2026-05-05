<?php

require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Uuid.php';

class CheckoutController {

    public static function checkout(PDO $pdo) {
        $id_usuario = Auth::check();


        try {

            $pdo->beginTransaction();

            // ver si trae direccion

            $data = json_decode(file_get_contents("php://input"), true);

            $id_direccion = $data['id_direccion'] ?? null;

            if (!$id_direccion) {
                throw new Exception("Selecciona dirección");
            }

            //ver si la direccion pertenece al usuario

            $stmt = $pdo->prepare("
                SELECT Id_direccion
                FROM direccion
                WHERE Id_direccion = :id
                AND id_usuario = :usuario
            ");

            $stmt->execute([
                'id' => $id_direccion,
                'usuario' => $id_usuario
            ]);

            if (!$stmt->fetch()) {
                throw new Exception("Dirección no válida");
            }

            // Obtener carrito usuario
            $stmt = $pdo->prepare("
                SELECT id_carrito
                FROM carrito
                WHERE id_usuario = :id_usuario
            ");

            $stmt->execute([
                'id_usuario' => $id_usuario
            ]);

            $carrito = $stmt->fetch();

            if (!$carrito) {
                throw new Exception("Carrito vacío");
            }

            // 2️⃣ Obtener productos carrito
            $stmt = $pdo->prepare("
                SELECT
                    cp.id_producto,
                    cp.cantidad,
                    p.nombre,
                    p.precio,
                    p.iva,
                    p.stock
                FROM carrito_producto cp
                JOIN producto p
                    ON p.id_producto = cp.id_producto
                WHERE cp.id_carrito = :id_carrito
            ");

            $stmt->execute([
                'id_carrito' => $carrito['id_carrito']
            ]);

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$items) {
                throw new Exception("Carrito vacío");
            }

            $total = 0;
            $totalIva = 0;

            // 3️⃣ Validar stock + calcular total
            foreach ($items as $item) {

                if ($item['stock'] < $item['cantidad']) {
                    throw new Exception(
                        "Sin stock de " . $item['nombre']
                    );
                }

                $subtotal =
                    $item['precio'] * $item['cantidad'];

                $iva =
                    $subtotal *
                    ($item['iva'] / 100);

                $total += $subtotal;
                $totalIva += $iva;
            }

            // 4️⃣ Crear pedido
            $id_pedido = uuidv4();

            $stmt = $pdo->prepare("
                INSERT INTO pedido (
                    id_pedido,
                    id_usuario,
                    id_direccion,
                    total,
                    total_iva
                )
                VALUES (
                    :id_pedido,
                    :id_usuario,
                    :id_direccion,
                    :total,
                    :total_iva
                )
            ");

            $stmt->execute([
                'id_pedido' => $id_pedido,
                'id_usuario' => $id_usuario,
                'id_direccion' => $id_direccion,
                'total' => $total,
                'total_iva' => $totalIva
            ]);

            // 5️⃣ Líneas + restar stock
            foreach ($items as $item) {

                $stmt = $pdo->prepare("
                    INSERT INTO linea_pedido (
                        id_linea,
                        id_pedido,
                        id_producto,
                        cantidad
                    )
                    VALUES (
                        :id_linea,
                        :id_pedido,
                        :id_producto,
                        :cantidad
                    )
                ");

                $stmt->execute([
                    'id_linea' => uuidv4(),
                    'id_pedido' => $id_pedido,
                    'id_producto' => $item['id_producto'],
                    'cantidad' => $item['cantidad']
                ]);

                $stmt = $pdo->prepare("
                    UPDATE producto
                    SET stock = stock - :cantidad
                    WHERE id_producto = :id_producto
                ");

                $stmt->execute([
                    'cantidad' => $item['cantidad'],
                    'id_producto' => $item['id_producto']
                ]);
            }

            // 6️⃣ Vaciar carrito
            $stmt = $pdo->prepare("
                DELETE FROM carrito_producto
                WHERE id_carrito = :id_carrito
            ");

            $stmt->execute([
                'id_carrito' => $carrito['id_carrito']
            ]);

            $pdo->commit();

            echo json_encode([
                'ok' => true,
                'pedido' => $id_pedido,
                'total' => round($total + $totalIva, 2)
            ]);

        } catch (Throwable $e) {

            $pdo->rollBack();

            http_response_code(400);

            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }
}