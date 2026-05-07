<?php
/** @var TwoFactor $twoFactor */
require_once __DIR__ . '/../utils/Uuid.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/TwoFactor.php';
require_once __DIR__ . '/../utils/Logs.php';

use Firebase\JWT\JWT;

class AuthController {

    public static function register(PDO $pdo) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['email'], $data['password'], $data['nombre'],$data['apellidos'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        $id = uuidv4();
        $email = $data['email'];
        $nombre = $data['nombre'];
        $apellidos = $data['apellidos'];
        $telefono = trim($data['telefono'] ?? '') ?: null;

        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO usuario (id_usuario, email, nombre, apellidos, telefono, password_hash)
            VALUES (:id_usuario, :email, :nombre, :apellidos, :telefono, :password)
        ");

        try {

            $pdo->beginTransaction();

            // 1. Crear usuario
            $stmt->execute([
                'id_usuario' => $id,
                'email' => $email,
                'nombre' => $nombre,
                'apellidos'=> $apellidos,
                'telefono' => $telefono,
                'password' => $passwordHash
            ]);

            crearLog(
                $pdo,
                $id,
                'USUARIO_REGISTRADO',
                'Nuevo usuario registrado con email: ' . $email
            );

            $pdo->commit();

        } catch (PDOException $e) {

            $pdo->rollBack();

            if ($e->getCode() == 23000) {
                http_response_code(409);
                echo json_encode(['error' => 'El email ya está en uso']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error del servidor','detalle' => $e->getMessage()]);
                
            }
            return;
        }

        echo json_encode(['ok' => true]);
    }

    public static function login(PDO $pdo) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['email'], $data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        
        $stmt = $pdo->prepare("
            SELECT
                id_usuario,
                password_hash,
                2fa_activo,
                rol,
                nombre,
                apellidos,
                email
            FROM usuario
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciales incorrectas']);
            crearLog(
                $pdo,
                null,
                'LOGIN_FAIL',
                'Intento de login fallido para email: ' . $data['email']
            );
            return;
        }

        //Si el 2FA está activo, NO damos el token final
        if ($user['2fa_activo']) {
            http_response_code(200);
            echo json_encode([
                'requires_2fa' => true,
                'email' => $data['email'] // Lo pasamos para que el front sepa a quién validar
            ]);
            return;
        }

        // Si no hay 2FA, generamos el token normal
        $payload = [
            'sub' => $user['id_usuario'],
            'iat' => time(),
            'exp' => time() + 3600
        ];

        $jwtConfig = require __DIR__ . '/../../config/jwt.php';
        $secret = $jwtConfig['secret'];
        $token = JWT::encode($payload, $secret, 'HS256');

        crearLog(
            $pdo,
            $user['id_usuario'],
            'LOGIN_OK',
            'Inicio de sesión correcto'
        );

        echo json_encode([
            'token' => $token,
            'user' => [
                'id_usuario' => $user['id_usuario'],
                'nombre' => $user['nombre'],
                'apellidos' => $user['apellidos'],
                'email' => $user['email'],
                'rol' => $user['rol'],
                '2fa_activo' => (bool)$user['2fa_activo']
            ]
        ]);
    }

    public static function verify2FALogin(PDO $pdo) {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['email'], $data['code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        // Buscamos el secreto del usuario
        $stmt = $pdo->prepare("
            SELECT
                id_usuario,
                2fa_secreto,
                rol,
                nombre,
                apellidos,
                email
            FROM usuario
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $data['email']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }

        // Importamos la utilidad que creaste/crearás en src/utils/TwoFactor.php
        require_once __DIR__ . '/../utils/TwoFactor.php';

        // Validamos el código con el secreto guardado en la DB
        if (TwoFactor::verifyCode($user['2fa_secreto'], $data['code'])) {
            // CÓDIGO CORRECTO: Ahora sí generamos el token JWT final
            $payload = [
                'sub' => $user['id_usuario'],
                'iat' => time(),
                'exp' => time() + 3600
            ];

            $jwtConfig = require __DIR__ . '/../../config/jwt.php';
            $secret = $jwtConfig['secret'];
            $token = JWT::encode($payload, $secret, 'HS256');

            crearLog(
                $pdo,
                $user['id_usuario'],
                'LOGIN_2FA_OK',
                'Login completado con 2FA'
            );

            echo json_encode([
                'token' => $token,
                'user' => [
                    'id_usuario' => $user['id_usuario'],
                    '2fa_activo' => true,
                    'rol' => $user['rol'],
                    'nombre' => $user['nombre'],
                    'apellidos' => $user['apellidos'],
                    'email' => $user['email']
                ]
            ]);
        } else {
            http_response_code(401);
            crearLog(
                $pdo,
                $user['id_usuario'],
                'LOGIN_2FA_FAIL',
                'Código 2FA inválido'
            );
            echo json_encode(['error' => 'Código 2FA inválido o expirado']);
        }
    }

    public static function forgotPassword(PDO $pdo) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email requerido']);
            return;
        }

        $stmt = $pdo->prepare("SELECT id_usuario FROM usuario WHERE email = :email");
        $stmt->execute(['email' => $data['email']]);
        $user = $stmt->fetch();

        if (!$user) {
            // no revelar si existe o no
            echo json_encode(['ok' => true]);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $pdo->prepare("
            UPDATE usuario
            SET reset_token = :token,
                reset_expira = :expira
            WHERE id_usuario = :id_usuario
        ");

        $stmt->execute([
            'token' => $token,
            'expira' => $expires,
            'id_usuario' => $user['id_usuario']
        ]);

        // 🔥 MOCK → simulamos email
        $resetLink = "https://tienda-de-quesos-front.vercel.app/reset-password?token=$token";

        crearLog(
            $pdo,
            $user['id_usuario'],
            'PASSWORD_RESET_REQUEST',
            'Solicitud de recuperación de contraseña'
        );

        echo json_encode([
            'ok' => true,
            'reset_link' => $resetLink // SOLO DEV → luego quitar
        ]);
    }

    public static function resetPassword(PDO $pdo) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['token']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        $stmt = $pdo->prepare("
            SELECT id_usuario, reset_expira, password_hash
            FROM usuario
            WHERE reset_token = :token
            LIMIT 1
        ");

        $stmt->execute(['token' => $data['token']]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(400);
            echo json_encode(['error' => 'Token inválido']);
            return;
        }

        if (password_verify($data['password'], $user['password_hash'])) {
            http_response_code(400);
            echo json_encode(['error' => 'La nueva contraseña no puede ser igual a la anterior']);
            return;
        }

        if (strtotime($user['reset_expira']) < time()) {
            http_response_code(400);
            echo json_encode(['error' => 'Token expirado']);
            return;
        }

        $newHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE usuario
            SET password_hash = :password,
                reset_token = NULL,
                reset_expira = NULL
            WHERE id_usuario = :id_usuario
        ");

        $stmt->execute([
            'password' => $newHash,
            'id_usuario' => $user['id_usuario']
        ]);

        crearLog(
            $pdo,
            $user['id_usuario'],
            'PASSWORD_RESET_SUCCESS',
            'Contraseña restablecida correctamente'
        );

        echo json_encode(['ok' => true]);
    }
}
