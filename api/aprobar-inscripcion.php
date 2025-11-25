<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) {
        throw new Exception('ID de inscripción no proporcionado');
    }
    
    $pdo = Database::connect();
    
    // Obtener datos de la inscripción
    $stmt = $pdo->prepare("SELECT * FROM inscripciones WHERE id = ?");
    $stmt->execute([$id]);
    $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inscripcion) {
        throw new Exception('Inscripción no encontrada');
    }
    
    // Crear usuario de estudiante
    $legajo = 'EST-' . date('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    $password = password_hash($inscripcion['dni'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre, apellido, email, dni, password, tipo_usuario, activo, fecha_registro)
        VALUES (?, ?, ?, ?, ?, 'estudiante', 1, NOW())
    ");
    $stmt->execute([
        $inscripcion['nombre'],
        $inscripcion['apellido'],
        $inscripcion['email'],
        $inscripcion['dni'],
        $password
    ]);
    
    $usuarioId = $pdo->lastInsertId();
    
    // Crear registro de estudiante
    $stmt = $pdo->prepare("
        INSERT INTO estudiantes (usuario_id, legajo, ciclo, fecha_ingreso, estado)
        VALUES (?, ?, ?, NOW(), 'activo')
    ");
    $stmt->execute([$usuarioId, $legajo, $inscripcion['ciclo']]);
    
    // Actualizar estado de la inscripción
    $stmt = $pdo->prepare("
        UPDATE inscripciones 
        SET estado = 'aprobada', fecha_revision = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    
    // Enviar email de confirmación (implementar)
    // enviarEmailAprobacion($inscripcion, $legajo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Inscripción aprobada y estudiante creado exitosamente',
        'legajo' => $legajo
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>