<?php

/**
 * Crear un log encadenado (inalterable) por usuario
 */
function crearLog($pdo, $id_usuario, $evento, $descripcion_evento = null) {

    // Obtener último log del usuario
    $stmt = $pdo->prepare("
        SELECT hash_actual, cadena
        FROM evento
        WHERE id_usuario = ?
        ORDER BY cadena DESC
        LIMIT 1
    ");

    $stmt->execute([$id_usuario]);

    $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);

    $hash_anterior = $ultimo ? $ultimo['hash_actual'] : str_repeat('0', 64);
    $cadena = $ultimo ? (int)$ultimo['cadena'] + 1 : 1;

    $fecha = date('Y-m-d H:i:s');

    $data = json_encode([
        'id_usuario' => $id_usuario,
        'evento' => $evento,
        'descripcion_evento' => $descripcion_evento,
        'fecha' => $fecha,
        'cadena' => $cadena
    ], JSON_UNESCAPED_UNICODE);

    $hash_actual = hash('sha256', $data . $hash_anterior);

    $stmt = $pdo->prepare("
        INSERT INTO evento
        (id_evento, id_usuario, evento, descripcion_evento, cadena, hash_actual, hash_anterior, fecha_creacion)
        VALUES
        (UUID(), ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $id_usuario,
        $evento,
        $descripcion_evento,
        $cadena,
        $hash_actual,
        $hash_anterior,
        $fecha
    ]);
}

function verificarIntegridadLogs(PDO $pdo, $id_usuario) {

    $stmt = $pdo->prepare("
        SELECT cadena, hash_actual, hash_anterior, id_usuario, evento, descripcion_evento, fecha_creacion
        FROM evento
        WHERE id_usuario = :id_usuario
        ORDER BY cadena ASC
    ");

    $stmt->execute([
        'id_usuario' => $id_usuario
    ]);

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$logs) {
        return [
            'ok' => true,
            'total' => 0
        ];
    }

    $hashAnteriorEsperado = str_repeat('0', 64);

    foreach ($logs as $log) {

        $data = json_encode([
            'id_usuario' => $log['id_usuario'],
            'evento' => $log['evento'],
            'descripcion_evento' => $log['descripcion_evento'],
            'fecha' => $log['fecha_creacion'],
            'cadena' => (int)$log['cadena']
        ], JSON_UNESCAPED_UNICODE);

        $hashCalculado = hash('sha256', $data . $hashAnteriorEsperado);

        if ($hashCalculado !== $log['hash_actual']) {
            return [
                'ok' => false,
                'cadena' => $log['cadena'],
                'error' => 'Hash inválido'
            ];
        }

        if ($log['hash_anterior'] !== $hashAnteriorEsperado) {
            return [
                'ok' => false,
                'cadena' => $log['cadena'],
                'error' => 'Cadena rota'
            ];
        }

        $hashAnteriorEsperado = $log['hash_actual'];
    }

    return [
        'ok' => true,
        'total' => count($logs)
    ];
}