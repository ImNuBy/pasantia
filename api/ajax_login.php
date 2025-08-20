<?php
/**
 * EPA 703 - Sistema de Login
 * Archivo: ajax_login.php
 * Maneja la autenticación de usuarios vía AJAX
 */

// Iniciar sesión
session_start();

// Headers de respuesta JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit();
}

try {
    // Obtener datos del formulario
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validar datos obligatorios
    if (empty($usuario) || empty($password) || empty($role)) {
        throw new Exception('Todos los campos son obligatorios');
    }

    // Validar formato de email
    if (!filter_var($usuario, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Formato de email no válido');
    }

    // Validar que el rol sea válido
    $roles_validos = ['estudiante', 'profesor', 'admin', 'secretario'];
    if (!in_array($role, $roles_validos)) {
        throw new Exception('Rol no válido');
    }

    // ================================================
    // CONFIGURACIÓN DE BASE DE DATOS
    // Usando la configuración actual de EPA 703
    // ================================================
    require_once '../config/database.php';
    
    try {
        $pdo = getDBConnection();
    } catch (Exception $e) {
        // Si falla la conexión, usar usuarios de prueba
        validarUsuarioPrueba($usuario, $password, $role);
        exit();
    }

    // ================================================
    // CONSULTA A LA BASE DE DATOS ACTUAL
    // ================================================
    $sql = "SELECT id, nombre, apellido, email, password_hash, tipo_usuario, dni, telefono, activo
            FROM usuarios 
            WHERE email = :email AND tipo_usuario = :role AND activo = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'email' => $usuario,
        'role' => $role
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Credenciales incorrectas');
    }

    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {
        throw new Exception('Credenciales incorrectas');
    }

    // ================================================
    // LOGIN EXITOSO
    // ================================================
    
    // Crear sesión
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nombre'] = $user['nombre'];
    $_SESSION['apellido'] = $user['apellido'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
    $_SESSION['dni'] = $user['dni'];
    $_SESSION['telefono'] = $user['telefono'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);

    // Obtener información adicional según el rol
    $additional_info = obtenerInfoAdicional($pdo, $user['id'], $role);

    // Registrar sesión en la base de datos
    registrarSesion($pdo, $user['id']);

    // Determinar URL de redirección según el rol
    $redirect_urls = [
        'estudiante' => 'panel-estudiante.html',
        'profesor' => 'panel-profesor.html',
        'admin' => 'panel-admin.html',
        'secretario' => 'panel-secretario.html'
    ];

    $redirect_url = $redirect_urls[$role] ?? 'index.html';

    // Log del login exitoso
    error_log("EPA 703 - Login exitoso: {$user['email']} ({$role}) - IP: " . $_SERVER['REMOTE_ADDR']);

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => '¡Bienvenido/a!',
        'redirect' => $redirect_url,
        'user' => array_merge([
            'id' => $user['id'],
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'email' => $user['email'],
            'tipo_usuario' => $user['tipo_usuario'],
            'dni' => $user['dni'],
            'telefono' => $user['telefono']
        ], $additional_info)
    ]);

} catch (Exception $e) {
    // Log del error
    error_log("EPA 703 - Error en login: " . $e->getMessage() . " - IP: " . $_SERVER['REMOTE_ADDR']);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Obtener información adicional según el tipo de usuario
 */
function obtenerInfoAdicional($pdo, $user_id, $tipo_usuario) {
    $info = [];
    
    try {
        switch ($tipo_usuario) {
            case 'estudiante':
                $stmt = $pdo->prepare("
                    SELECT e.legajo, e.fecha_ingreso, e.estado, c.nombre as curso_nombre
                    FROM estudiantes e
                    LEFT JOIN cursos c ON e.curso_id = c.id
                    WHERE e.usuario_id = :user_id
                ");
                $stmt->execute(['user_id' => $user_id]);
                $estudiante = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($estudiante) {
                    $info = $estudiante;
                }
                break;

            case 'profesor':
                $stmt = $pdo->prepare("
                    SELECT p.legajo, p.especialidad, p.titulo, p.fecha_ingreso, p.estado
                    FROM profesores p
                    WHERE p.usuario_id = :user_id
                ");
                $stmt->execute(['user_id' => $user_id]);
                $profesor = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($profesor) {
                    $info = $profesor;
                }
                break;

            case 'admin':
            case 'secretario':
                // Para admin y secretario, agregar permisos o información adicional
                $info['permisos'] = obtenerPermisos($tipo_usuario);
                break;
        }
    } catch (Exception $e) {
        error_log("Error obteniendo información adicional: " . $e->getMessage());
    }
    
    return $info;
}

/**
 * Registrar sesión en la base de datos
 */
function registrarSesion($pdo, $user_id) {
    try {
        // Generar ID único para la sesión
        $session_id = session_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO sesiones (id, usuario_id, ip_address, user_agent, payload, ultima_actividad)
            VALUES (:id, :usuario_id, :ip_address, :user_agent, :payload, NOW())
            ON DUPLICATE KEY UPDATE
                ultima_actividad = NOW(),
                payload = :payload
        ");
        
        $payload = json_encode([
            'login_time' => time(),
            'user_agent' => $user_agent,
            'ip' => $ip_address
        ]);
        
        $stmt->execute([
            'id' => $session_id,
            'usuario_id' => $user_id,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'payload' => $payload
        ]);
        
    } catch (Exception $e) {
        error_log("Error registrando sesión: " . $e->getMessage());
    }
}

/**
 * Obtener permisos según el tipo de usuario
 */
function obtenerPermisos($tipo_usuario) {
    $permisos = [
        'admin' => [
            'gestionar_usuarios', 'ver_reportes', 'configurar_sistema',
            'gestionar_inscripciones', 'gestionar_cursos', 'ver_estadisticas'
        ],
        'secretario' => [
            'gestionar_inscripciones', 'ver_estudiantes', 'generar_certificados'
        ],
        'profesor' => [
            'ver_estudiantes', 'cargar_notas', 'ver_asistencias'
        ],
        'estudiante' => [
            'ver_perfil', 'ver_notas', 'descargar_certificados'
        ]
    ];
    
    return $permisos[$tipo_usuario] ?? [];
}

/**
 * Función para validar usuarios de prueba cuando hay problemas de conexión
 */
function validarUsuarioPrueba($usuario, $password, $role) {
    // Usuarios de prueba para desarrollo (solo si falla la BD)
    $usuarios_prueba = [
        'estudiante' => [
            'email' => 'estudiante@epa703.edu.ar',
            'password' => '123456',
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'legajo' => '2024001'
        ],
        'profesor' => [
            'email' => 'profesor@epa703.edu.ar',
            'password' => '123456',
            'nombre' => 'María',
            'apellido' => 'González',
            'legajo' => 'PROF001'
        ],
        'admin' => [
            'email' => 'admin@epa703.edu.ar',
            'password' => '123456',
            'nombre' => 'Carlos',
            'apellido' => 'Rodríguez',
            'legajo' => 'ADM001'
        ]
    ];

    // Verificar si el usuario de prueba existe
    if (!isset($usuarios_prueba[$role]) || 
        $usuarios_prueba[$role]['email'] !== $usuario || 
        $usuarios_prueba[$role]['password'] !== $password) {
        
        echo json_encode([
            'success' => false,
            'message' => 'Credenciales incorrectas'
        ]);
        return;
    }

    $user_data = $usuarios_prueba[$role];

    // Crear sesión de prueba
    $_SESSION['user_id'] = rand(1000, 9999);
    $_SESSION['nombre'] = $user_data['nombre'];
    $_SESSION['apellido'] = $user_data['apellido'];
    $_SESSION['email'] = $user_data['email'];
    $_SESSION['tipo_usuario'] = $role;
    $_SESSION['legajo'] = $user_data['legajo'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    $redirect_urls = [
        'estudiante' => 'panel-estudiante.html',
        'profesor' => 'panel-profesor.html',
        'admin' => 'panel-admin.html'
    ];

    $redirect_url = $redirect_urls[$role] ?? 'index.html';

    echo json_encode([
        'success' => true,
        'message' => '¡Bienvenido/a! (Modo de prueba)',
        'redirect' => $redirect_url,
        'user' => [
            'nombre' => $user_data['nombre'],
            'apellido' => $user_data['apellido'],
            'email' => $user_data['email'],
            'tipo_usuario' => $role,
            'legajo' => $user_data['legajo']
        ]
    ]);
}
?>