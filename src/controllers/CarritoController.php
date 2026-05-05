<?php

require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Uuid.php';


class CarritoController {

    // 🛒 OBTENER CARRITO
    public static function getCart(PDO $pdo) {
        $id_usuario = Auth::check();

        try {

            // Buscar carrito
            $stmt = $pdo->prepare("SELECT id_carrito FROM carrito WHERE id_usuario = :id_usuario");
            $stmt->execute(['id_usuario' => $id_usuario]);
            $carrito = $stmt->fetch();

            if (!$carrito) {
                echo json_encode(['products' => []]);
                return;
            }

            // Obtener productos
            $stmt = $pdo->prepare("
                SELECT 
                    cp.id_producto,
                    cp.cantidad,
                    p.nombre,
                    p.precio,
                    p.iva,
                    p.url_imagen,
                    p.stock
                FROM carrito_producto cp
                JOIN producto p ON p.id_producto = cp.id_producto
                WHERE cp.id_carrito = :id_carrito
            ");

            $stmt->execute(['id_carrito' => $carrito['id_carrito']]);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['products' => $productos]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // ➕ AÑADIR PRODUCTO
    public static function add(PDO $pdo) {
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        try {

            $id_producto = $data['id_producto'] ?? null;
            $cantidad = $data['cantidad'] ?? 1;

            if (!$id_producto) {
                http_response_code(400);
                throw new Exception("Producto requerido");
            }

            //comprobar stock
            $stmt = $pdo->prepare("
                SELECT stock FROM producto
                WHERE id_producto = :id_producto
            ");
            $stmt->execute(['id_producto' => $id_producto]);

            $product = $stmt->fetch();

            if (!$product) {
                throw new Exception("Producto no encontrado");
            }

            if ($cantidad > $product['stock']) {
                http_response_code(400);
                throw new Exception("Stock insuficiente");
            }

            // 1. Obtener o crear carrito
            $stmt = $pdo->prepare("SELECT id_carrito FROM carrito WHERE id_usuario = :id_usuario");
            $stmt->execute(['id_usuario' => $id_usuario]);
            $carrito = $stmt->fetch();

            if (!$carrito) {
                $id_carrito = uuidv4();

                $stmt = $pdo->prepare("
                    INSERT INTO carrito (id_carrito, id_usuario)
                    VALUES (:id_carrito, :id_usuario)
                ");

                $stmt->execute([
                    'id_carrito' => $id_carrito,
                    'id_usuario' => $id_usuario
                ]);

            } else {
                $id_carrito = $carrito['id_carrito'];
            }

            // 2. Ver si ya existe producto
            $stmt = $pdo->prepare("
                SELECT cantidad 
                FROM carrito_producto 
                WHERE id_carrito = :id_carrito AND id_producto = :id_producto
            ");

            $stmt->execute([
                'id_carrito' => $id_carrito,
                'id_producto' => $id_producto
            ]);

            $existing = $stmt->fetch();

            if ($existing) {
                // sumar cantidad
                $stmt = $pdo->prepare("
                    UPDATE carrito_producto
                    SET cantidad = cantidad + :cantidad
                    WHERE id_carrito = :id_carrito AND id_producto = :id_producto
                ");

                $stmt->execute([
                    'cantidad' => $cantidad,
                    'id_carrito' => $id_carrito,
                    'id_producto' => $id_producto
                ]);

            } else {
                // insertar
                $stmt = $pdo->prepare("
                    INSERT INTO carrito_producto (id, id_carrito, id_producto, cantidad)
                    VALUES (:id, :id_carrito, :id_producto, :cantidad)
                ");

                $stmt->execute([
                    'id' => uuidv4(),
                    'id_carrito' => $id_carrito,
                    'id_producto' => $id_producto,
                    'cantidad' => $cantidad
                ]);
            }

            echo json_encode(['ok' => true]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    //MODIFICAR CANTIDAD
    public static function update(PDO $pdo) {
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            $id_producto = $data['id_producto'] ?? null;
            $cantidad = $data['cantidad'] ?? null;

            if (!$id_producto || $cantidad === null) {
                http_response_code(400);
                throw new Exception("Datos incompletos");
            }

            //comprobar stock
            $stmt = $pdo->prepare("
                SELECT stock FROM producto
                WHERE id_producto = :id_producto
            ");
            $stmt->execute(['id_producto' => $id_producto]);

            $product = $stmt->fetch();

            if (!$product) {
                throw new Exception("Producto no encontrado");
            }

            if ($cantidad > $product['stock']) {
                http_response_code(400);
                throw new Exception("Stock insuficiente");
            }

            // obtener carrito
            $stmt = $pdo->prepare("SELECT id_carrito FROM carrito WHERE id_usuario = :id_usuario");
            $stmt->execute(['id_usuario' => $id_usuario]);
            $carrito = $stmt->fetch();

            if (!$carrito) {
                throw new Exception("Carrito no encontrado");
            }

            // si cantidad = 0 → eliminar
            if ($cantidad <= 0) {
                $stmt = $pdo->prepare("
                    DELETE FROM carrito_producto 
                    WHERE id_carrito = :id_carrito AND id_producto = :id_producto
                ");

            } else {
                $stmt = $pdo->prepare("
                    UPDATE carrito_producto
                    SET cantidad = :cantidad
                    WHERE id_carrito = :id_carrito AND id_producto = :id_producto
                ");
            }

            $stmt->execute([
                'id_carrito' => $carrito['id_carrito'],
                'id_producto' => $id_producto,
                'cantidad' => $cantidad
            ]);

            echo json_encode(['ok' => true]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    //ELIMINAR PRODUCTO
    public static function remove(PDO $pdo) {
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            $id_producto = $data['id_producto'] ?? null;

            if (!$id_producto) {
                http_response_code(400);
                throw new Exception("Producto requerido");
            }

            $stmt = $pdo->prepare("SELECT id_carrito FROM carrito WHERE id_usuario = :id_usuario");
            $stmt->execute(['id_usuario' => $id_usuario]);
            $carrito = $stmt->fetch();

            if (!$carrito) {
                throw new Exception("Carrito no encontrado");
            }

            $stmt = $pdo->prepare("
                DELETE FROM carrito_producto
                WHERE id_carrito = :id_carrito AND id_producto = :id_producto
            ");

            $stmt->execute([
                'id_carrito' => $carrito['id_carrito'],
                'id_producto' => $id_producto
            ]);

            echo json_encode(['ok' => true]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    //merge del carrito logeado
    public static function merge(PDO $pdo) {
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);

        try {

            $items = $data['items'] ?? [];

            if (!is_array($items)) {
                throw new Exception("Formato inválido");
            }

            // obtener o crear carrito usuario
            $stmt = $pdo->prepare("SELECT id_carrito FROM carrito WHERE id_usuario=:id_usuario");
            $stmt->execute(['id_usuario' => $id_usuario]);
            $carrito = $stmt->fetch();

            if (!$carrito) {
                $id_carrito = uuidv4();

                $stmt = $pdo->prepare("
                    INSERT INTO carrito (id_carrito,id_usuario)
                    VALUES (:id_carrito,:id_usuario)
                ");

                $stmt->execute([
                    'id_carrito' => $id_carrito,
                    'id_usuario' => $id_usuario
                ]);

            } else {
                $id_carrito = $carrito['id_carrito'];
            }

            foreach ($items as $item) {

                $id_producto = $item['id_producto'];
                $cantidad = intval($item['cantidad']);

                $stmt = $pdo->prepare("
                    SELECT cantidad
                    FROM carrito_producto
                    WHERE id_carrito=:id_carrito
                    AND id_producto=:id_producto
                ");

                $stmt->execute([
                    'id_carrito' => $id_carrito,
                    'id_producto' => $id_producto
                ]);

                $existe = $stmt->fetch();

                if ($existe) {

                    $stmt = $pdo->prepare("
                        UPDATE carrito_producto
                        SET cantidad = cantidad + :cantidad
                        WHERE id_carrito=:id_carrito
                        AND id_producto=:id_producto
                    ");

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO carrito_producto
                        (id,id_carrito,id_producto,cantidad)
                        VALUES
                        (:id,:id_carrito,:id_producto,:cantidad)
                    ");
                }

                $params = [
                    'id_carrito' => $id_carrito,
                    'id_producto' => $id_producto,
                    'cantidad' => $cantidad
                ];

                if (!$existe) {
                    $params['id'] = uuidv4();
                }

                $stmt->execute($params);
            }

            echo json_encode(['ok' => true]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    //carrito preeview para visitantes
    public static function preview(PDO $pdo) {
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            $items = $data['items'] ?? [];

            if (!is_array($items)) {
                throw new Exception("Formato inválido");
            }

            $products = [];

            foreach ($items as $item) {

                $id_producto = $item['id_producto'] ?? null;
                $cantidad = intval($item['cantidad'] ?? 0);

                if (!$id_producto || $cantidad <= 0) {
                    continue;
                }

                $stmt = $pdo->prepare("
                    SELECT
                        id_producto,
                        nombre,
                        precio,
                        iva,
                        stock,
                        url_imagen
                    FROM producto
                    WHERE id_producto = :id_producto
                ");

                $stmt->execute([
                    'id_producto' => $id_producto
                ]);

                $producto = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$producto) {
                    continue;
                }

                // limitar por stock real
                $cantidadFinal = min($cantidad, intval($producto['stock']));

                if ($cantidadFinal <= 0) {
                    continue;
                }

                $producto['cantidad'] = $cantidadFinal;
                $producto['maximo_alcanzado'] = ($cantidad > $cantidadFinal);

                $products[] = $producto;
            }

            echo json_encode([
                'products' => $products
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

}