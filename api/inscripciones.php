<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $pdo = Database::connect();
    
    // Obtener parámetros de filtro
    $estado = $_GET['estado'] ?? '';
    $ciclo = $_GET['ciclo'] ?? '';
    $anio = $_GET['anio'] ?? '';
    
    $query = "SELECT * FROM inscripciones WHERE 1=1";
    $params = [];
    
    if ($estado) {
        $query .= " AND estado = ?";
        $params[] = $estado;
    }
    
    if ($ciclo) {
        $query .= " AND ciclo = ?";
        $params[] = $ciclo;
    }
    
    if ($anio) {
        $query .= " AND YEAR(fecha_creacion) = ?";
        $params[] = $anio;
    }
    
    $query .= " ORDER BY fecha_creacion DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'inscripciones' => $inscripciones
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>