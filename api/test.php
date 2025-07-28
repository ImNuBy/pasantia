
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // Información del sistema
    $system_info = [
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'session_status' => session_status(),
        'extensions' => [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'json' => extension_loaded('json')
        ]
    ];
    
    // Probar conexión a base de datos
    $db_status = 'error';
    $db_message = '';
    
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=epa703;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Probar consulta simple
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
        $result = $stmt->fetch();
        
        $db_status = 'connected';
        $db_message = "Conectado. Usuarios en BD: " . $result['total'];
        
    } catch (PDOException $e) {
        $db_status = 'error';
        $db_message = "Error de conexión: " . $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'API funcionando correctamente',
        'system_info' => $system_info,
        'database' => [
            'status' => $db_status,
            'message' => $db_message
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
