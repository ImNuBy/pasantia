<?php
/**
 * EPA 703 - API Mis Cursos
 * Devuelve los cursos asignados al usuario logueado
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

try {
    require_once '../config/database.php';
    $pdo = getDBConnection();

    $usuarioId = $_SESSION['usuario_id'];

    // Consultar cursos según tipo de usuario
    if ($_SESSION['tipo_usuario'] === 'estudiante') {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.nombre,
                c.descripcion,
                c.fecha_inicio,
                c.fecha_fin,
                p.nombre AS profesor
            FROM cursos c
            INNER JOIN inscripciones i ON c.id = i.curso_id
            INNER JOIN profesores p ON c.profesor_id = p.id
            WHERE i.estudiante_id = :usuarioId
            ORDER BY c.fecha_inicio DESC
        ");
    } elseif ($_SESSION['tipo_usuario'] === 'profesor') {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.nombre,
                c.descripcion,
                c.fecha_inicio,
                c.fecha_fin
            FROM cursos c
            WHERE c.profesor_id = (
                SELECT id FROM profesores WHERE usuario_id = :usuarioId
            )
            ORDER BY c.fecha_inicio DESC
        ");
    } else {
        // Admin y otros ven todos los cursos
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.nombre,
                c.descripcion,
                c.fecha_inicio,
                c.fecha_fin,
                p.nombre AS profesor
            FROM cursos c
            LEFT JOIN profesores p ON c.profesor_id = p.id
            ORDER BY c.fecha_inicio DESC
        ");
    }

    $stmt->execute(['usuarioId' => $usuarioId]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'cursos' => $cursos
    ]);

} catch (Exception $e) {
    error_log("Error en mis-cursos-data.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}
