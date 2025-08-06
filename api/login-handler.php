<?php
/**
 * Manejador de Login EPA 703
 * Procesa el login y redirige al dashboard correspondiente
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    // Obtener datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos de entrada inválidos');
    }
    
    $email = trim($input['usuario'] ?? '');
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'estudiante';
    
    // Validar campos requeridos
    if (empty($email) || empty($password)) {
        throw new Exception('Email y contraseña son requeridos');
    }
    
    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Formato de email inválido');
    }
    
    // Conectar a base de datos
    require_once 'config/database-config.php';
    $pdo = getDBConnection();
    
    // Buscar usuario
    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, email, password_hash, tipo_usuario, activo, 
               fecha_registro, created_at
        FROM usuarios 
        WHERE email = ? AND tipo_usuario = ?
    ");
    $stmt->execute([$email, $role]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        // Log intento fallido
        error_log("Login fallido - Usuario no encontrado: $email (rol: $role)");
        throw new Exception('Credenciales incorrectas');
    }
    
    // Verificar que el usuario esté activo
    if (!$usuario['activo']) {
        error_log("Login fallido - Usuario inactivo: $email");
        throw new Exception('Usuario inactivo. Contacte al administrador.');
    }
    
    // Verificar contraseña
    if (!password_verify($password, $usuario['password_hash'])) {
        // Log intento fallido
        error_log("Login fallido - Contraseña incorrecta: $email");
        throw new Exception('Credenciales incorrectas');
    }
    
    // Login exitoso - Crear sesión
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['apellido'] = $usuario['apellido'];
    $_SESSION['email'] = $usuario['email'];
    $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
    $_SESSION['login_time'] = time();
    
    // Actualizar último login
    $stmt = $pdo->prepare("
        UPDATE usuarios 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$usuario['id']]);
    
    // Log login exitoso
    error_log("Login exitoso: {$usuario['email']} (rol: {$usuario['tipo_usuario']})");
    
    // Determinar URL de redirección según el tipo de usuario
    $redirect_urls = [
        'admin' => 'admin/dashboard-admin.php',
        'profesor' => 'panel-profesor.html',
        'estudiante' => 'panel-estudiante.html',
        'secretario' => 'panel-secretario.html'
    ];
    
    $redirect_url = $redirect_urls[$usuario['tipo_usuario']] ?? 'index.html';
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Login exitoso',
        'redirect' => $redirect_url,
        'user' => [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'email' => $usuario['email'],
            'tipo_usuario' => $usuario['tipo_usuario']
        ]
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Error en login: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>