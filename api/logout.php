<?php
/**
 * EPA 703 - Sistema de Logout Mejorado
 * Cierra la sesión correctamente y redirige al login
 */

// Configurar headers para evitar caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Configurar tipo de contenido
header('Content-Type: application/json; charset=utf-8');

// Permitir CORS si es necesario
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Guardar información para log antes de destruir
    $user_id = $_SESSION['user_id'] ?? 'unknown';
    $email = $_SESSION['email'] ?? 'unknown';
    $tipo_usuario = $_SESSION['tipo_usuario'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Log del logout
    error_log("LOGOUT - User ID: $user_id, Email: $email, Tipo: $tipo_usuario, IP: $ip, Time: " . date('Y-m-d H:i:s'));
    
    // Limpiar sesión de la base de datos si existe
    if (isset($_SESSION['user_id'])) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("DELETE FROM sesiones WHERE usuario_id = :user_id");
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            
            error_log("Sesión eliminada de la base de datos para user_id: {$_SESSION['user_id']}");
        } catch (Exception $e) {
            error_log("Error eliminando sesión de BD: " . $e->getMessage());
            // Continuar con el logout aunque falle la BD
        }
    }
    
    // Destruir todas las variables de sesión
    $_SESSION = array();
    
    // Destruir la cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destruir la sesión completamente
    session_destroy();
    
    // Responder con JSON de éxito
    echo json_encode([
        'success' => true,
        'message' => 'Sesión cerrada exitosamente',
        'redirect' => 'login.html?logout=success'
    ]);
    
    error_log("Logout completado exitosamente");
    
} catch (Exception $e) {
    error_log("Error en logout.php: " . $e->getMessage());
    
    // Intentar cerrar sesión de todas formas
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al cerrar sesión: ' . $e->getMessage(),
        'redirect' => 'login.html?logout=error'
    ]);
}

exit();
?>