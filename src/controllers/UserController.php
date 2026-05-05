<?php
/** @var TwoFactor $twoFactor */
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/TwoFactor.php';
require_once __DIR__ . '/../utils/Logs.php';
require_once __DIR__ . '/../utils/NifValidator.php';

class UserController {
    public static function enable2FA(PDO $pdo) {
        $id_usuario = Auth::check();
        
        try{
            $secret = TwoFactor::generateSecret();

            // Guardar en DB
            $stmt = $pdo->prepare("UPDATE usuario SET 2fa_secreto = :secret WHERE id_usuario = :id_usuario");
            $stmt->execute(['secret' => $secret, 'id_usuario' => $id_usuario]);

            crearLog(
                $pdo,
                $id_usuario,
                '2FA_INICIADO',
                'Usuario inició configuración 2FA'
            );

            // Parámetros limpios para el QR
            $issuer = "InAltera";
            $userLabel = "Usuario"; 
            
            // Construimos el string otpauth
            $otpauth = "otpauth://totp/" . $issuer . ":" . $userLabel . "?secret=" . $secret . "&issuer=" . $issuer;
            
            // Generamos la URL de Google Charts usando urlencode solo en el contenido del QR
            $qrChart = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);

            echo json_encode([
                'qr' => $qrChart,
                'secret' => $secret
            ]);
        } catch (Throwable $e) {

            crearLog(
                $pdo,
                $id_usuario ?? null,
                'ERROR_ENABLE_2FA',
                $e->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function verify2FA(PDO $pdo) {
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents("php://input"), true);
        try{
            $code = $data['code'] ?? '';

            $stmt = $pdo->prepare("SELECT 2fa_secreto FROM usuario WHERE id_usuario = :id_usuario");
            $stmt->execute(['id_usuario' => $id_usuario]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("Usuario no encontrado");
            }

            if (TwoFactor::verifyCode($user['2fa_secreto'], $code)) {
                // 🏁 SI ES CORRECTO: Activamos definitivamente
                $stmt = $pdo->prepare("UPDATE usuario SET 2fa_activo = 1 WHERE id_usuario = :id_usuario");
                $stmt->execute(['id_usuario' => $id_usuario]);
                crearLog(
                    $pdo,
                    $id_usuario,
                    '2FA_ACTIVADO',
                    'Usuario activó autenticación en dos factores'
                );
                echo json_encode(['ok' => true]);
            } else {
                http_response_code(400);
                throw new Exception('Código inválido');
            }
        } catch (Throwable $e) {

            crearLog(
                $pdo,
                $id_usuario ?? null,
                'ERROR_VERIFY_2FA',
                $e->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function disable2FA(PDO $pdo) {
        $id_usuario = Auth::check();

        try {

            $stmt = $pdo->prepare("
                UPDATE usuario
                SET 2fa_activo = 0,
                2fa_secreto = NULL
                WHERE id_usuario = :id_usuario
            ");

            $stmt->execute(['id_usuario' => $id_usuario]);

            // 🔹 Log evento de seguridad
            crearLog(
                $pdo,
                $id_usuario,
                '2FA_DESACTIVADO',
                'Usuario desactivó autenticación en dos factores'
            );

            echo json_encode(['ok' => true]);

        } catch (Throwable $e) {

            crearLog(
                $pdo,
                $id_usuario ?? null,
                'ERROR_DISABLE_2FA',
                $e->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'error' => 'Error desactivando 2FA'
            ]);
        }
    }

    public static function updateDatos(PDO $pdo){
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents('php://input'), true);

        try{
            if (empty($data['nombre'])) {
                http_response_code(400);
                throw new Exception('nombre requerido');
            }

            if (empty($data['apellidos'])) {
                http_response_code(400);
                throw new Exception('apellidos requeridos');
            }

            $stmt = $pdo->prepare("
                UPDATE usuario
                SET nombre = :nombre, apellidos = :apellidos, telefono = :telefono
                WHERE id_usuario = :id_usuario
            ");

            $stmt->execute([
                'nombre' => $data['nombre'],
                'apellidos' => $data['apellidos'],
                'telefono' => number_format($data['telefono'],0,"",""),
                'id_usuario' => $id_usuario
            ]);

            crearLog(
                $pdo,
                $id_usuario,
                'USER_DATOS_MODIFICADOS',
                'Usuario actualizó datos personales'
            );

            echo json_encode(['ok' => true]);
        }catch (PDOException $e) {
            crearLog(
            $pdo,
            $id_usuario ?? null,
            'ERROR_USER_UPDATE',
            $e->getMessage()
        );

            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function updateEmail(PDO $pdo){
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            if (empty($data['email'])) {
            http_response_code(400);
            throw new Exception('email requerido');

        }

            $stmt = $pdo->prepare("
                UPDATE usuario
                SET email = :email
                WHERE id_usuario = :id_usuario
            ");

            $stmt->execute([
                'email' => $data['email'],
                'id_usuario' => $id_usuario
            ]);

            crearLog(
                $pdo,
                $id_usuario,
                'EMAIL_MODIFICADO',
                'Usuario cambió su email'
            );

            echo json_encode(['ok' => true]);

        } catch (PDOException $e) {

            crearLog(
                $pdo,
                $id_usuario ?? null,
                'ERROR_EMAIL_UPDATE',
                $e->getMessage()
            );
            // Código SQLSTATE para duplicados (UNIQUE)
            if ($e->getCode() == 23000) {
                http_response_code(400);
                echo json_encode(['error' => 'El email ya está en uso']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error del servidor']);
            }
        }
    }

    public static function updatePassword(PDO $pdo){
        $id_usuario = Auth::check();
        $data = json_decode(file_get_contents('php://input'), true);

        try {

            if (empty($data['current_password']) || empty($data['new_password'])) {
                http_response_code(400);
                throw new Exception('Datos incompletos');
            }

            // Obtener contraseña actual
            $stmt = $pdo->prepare("SELECT password_hash FROM usuario WHERE id_usuario = :id_usuario");
            $stmt->execute(['id_usuario' => $id_usuario]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($data['current_password'], $user['password_hash'])) {
                http_response_code(400);
                throw new Exception('Contraseña actual incorrecta');
            }

            if (password_verify($data['new_password'], $user['password_hash'])) {
                http_response_code(400);
                throw new Exception('La nueva contraseña no puede ser igual a la actual');
            }

            $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE usuario
                SET password_hash = :password
                WHERE id_usuario = :id_usuario
            ");

            $stmt->execute([
                'password' => $newHash,
                'id_usuario' => $id_usuario
            ]);
            
            crearLog(
                $pdo,
                $id_usuario,
                'PASSWORD_CAMBIADO',
                'Usuario cambió su contraseña'
            );

            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {

        // 🔹 Log de error
        crearLog(
            $pdo,
            $id_usuario ?? null,
            'ERROR_PASSWORD_UPDATE',
            $e->getMessage()
        );

        if (http_response_code() === 200) {
            http_response_code(500);
        }

        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
    }
}
