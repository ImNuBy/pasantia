
<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar si hay sesión activa
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        
        // Verificar timeout de sesión (1 hora)
        $session_timeout = 3600; // 1 hora
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $session_timeout) {
            // Sesión expirada
            session_unset();
            session_destroy();
            
            echo json_encode([
                'success' => true,
                'logged_in' => false,
                'message' => 'Sesión expirada'
            ]);
        } else {
            // Sesión válida
            $user_data = [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['email'],
                'nombre' => $_SESSION['nombre'],
                'apellido' => $_SESSION['apellido'],
                'tipo_usuario' => $_SESSION['tipo_usuario']
            ];
            
            if (isset($_SESSION['legajo'])) {
                $user_data['legajo'] = $_SESSION['legajo'];
            }
            
            echo json_encode([
                'success' => true,
                'logged_in' => true,
                'user' => $user_data
            ]);
        }
    } else {
        // No hay sesión activa
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error verificando sesión: ' . $e->getMessage()
    ]);
}
?>
