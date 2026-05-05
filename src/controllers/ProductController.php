<?php
require_once __DIR__ . '/../utils/Admin.php';
require_once __DIR__ . '/../utils/Uuid.php';


class ProductController {
   
    private static function ivaValido($iva){
        $Valido = [0,4,10,21];
        return (in_array($iva, $Valido));
    }

    public static function getAll(PDO $pdo) {
        
        try {
            $stmt = $pdo->query("
                SELECT id_producto, nombre, descripcion, precio, iva, stock, url_imagen
                FROM producto
                WHERE activo = 1;
            ");

            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'products' => $productos
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Error obteniendo productos',
                'detalle' => $e->getMessage()
            ]);
        }
    }

    public static function getAllAdmin(PDO $pdo) {
        Admin::check($pdo);

        try {
            $stmt = $pdo->query("
                SELECT id_producto, nombre, descripcion, precio, iva, stock, url_imagen, activo
                FROM producto
                ORDER BY activo DESC, nombre ASC
                ;
            ");

            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'products' => $productos
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Error obteniendo productos',
                'detalle' => $e->getMessage()
            ]);
        }
    }

    public static function getOne(PDO $pdo, $id) {
        try {
            $stmt = $pdo->prepare("
                SELECT id_producto, nombre, descripcion, precio, iva, stock, url_imagen 
                FROM producto 
                WHERE id_producto = :id
            ");

            $stmt->execute(['id' => $id]);

            $producto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                http_response_code(404);
                echo json_encode(['error' => 'Producto no encontrado']);
                return;
            }

            echo json_encode([
                'product' => $producto
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Error obteniendo producto',
                'detalle' => $e->getMessage()
            ]);
        }
    }

    // CREAR
    public static function create(PDO $pdo) {
        Admin::check($pdo);

        try {

            $data = json_decode(file_get_contents("php://input"), true);

            $nombre = trim($data['nombre'] ?? '');
            $descripcion = trim($data['descripcion'] ?? '');
            $precio = $data['precio'] ?? null;
            $iva = $data['iva'] ?? null;
            $stock = $data['stock'] ?? null;
            $url_imagen = trim($data['url_imagen'] ?? '');

            if ($nombre === '') {
                throw new Exception("Nombre obligatorio");
            }

            if ($descripcion === '') {
                throw new Exception("Descripción obligatoria");
            }

            if (!is_numeric($precio) || $precio < 0) {
                throw new Exception("Precio inválido");
            }

            if (!is_numeric($iva) || !self::ivaValido($iva)) {
                throw new Exception("IVA inválido");
            }

            if (!is_numeric($stock) || $stock < 0) {
                throw new Exception("Stock inválido");
            }

            $stmt = $pdo->prepare("
                INSERT INTO producto (
                    id_producto,
                    nombre,
                    descripcion,
                    precio,
                    iva,
                    stock,
                    url_imagen, 
                    activo
                )
                VALUES (
                    :id,
                    :nombre,
                    :descripcion,
                    :precio,
                    :iva,
                    :stock,
                    :url_imagen,
                    1
                )
            ");

            $stmt->execute([
                'id' => uuidv4(),
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'iva' => $iva,
                'stock' => $stock,
                'url_imagen' => $url_imagen
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
        Admin::check($pdo);

        try {

            $data = json_decode(file_get_contents("php://input"), true);

            $nombre = trim($data['nombre'] ?? '');
            $descripcion = trim($data['descripcion'] ?? '');
            $precio = $data['precio'] ?? null;
            $iva = $data['iva'] ?? null;
            $stock = $data['stock'] ?? null;
            $url_imagen = trim($data['url_imagen'] ?? '');
            $activo = $data['activo'] ?? 1;

            if ($nombre === '') {
                throw new Exception("Nombre obligatorio");
            }

            if ($descripcion === '') {
                throw new Exception("Descripción obligatoria");
            }

            if (!is_numeric($precio) || $precio < 0) {
                throw new Exception("Precio inválido");
            }

            if (!is_numeric($iva) || !self::ivaValido($iva)) {
                throw new Exception("IVA inválido");
            }

            if (!is_numeric($stock) || $stock < 0) {
                throw new Exception("Stock inválido");
            }

            if (!in_array((int)$activo, [0,1], true)) {
                throw new Exception("Activo inválido");
            }

            $stmt = $pdo->prepare("
                UPDATE producto
                SET
                    nombre = :nombre,
                    descripcion = :descripcion,
                    precio = :precio,
                    iva = :iva,
                    stock = :stock,
                    url_imagen = :url_imagen,
                    activo = :activo
                WHERE id_producto = :id
            ");

            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio' => $precio,
                'iva' => $iva,
                'stock' => $stock,
                'url_imagen' => $url_imagen,
                'activo'=> $activo,
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