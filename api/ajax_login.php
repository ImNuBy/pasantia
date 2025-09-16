<?php
/**
 * API de Login EPA 703 - Versión Corregida
 * Archivo: api/ajax_login.php
 */

// Configuración de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Headers de respuesta
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

// Iniciar sesión
session_start();

try {
    // Log para debug
    error_log("Login API: Iniciando proceso de autenticación");
    
    // Obtener datos POST
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Log de datos recibidos (sin contraseña por seguridad)
    error_log("Login API: Usuario: $usuario, Rol: $role");

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

    // PRIMERO: Intentar con usuarios de prueba
    $usuarios_prueba = [
        'estudiante@epa703.edu.ar' => [
            'password' => '123456',
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'tipo_usuario' => 'estudiante',
            'legajo' => '2024001'
        ],
        'profesor@epa703.edu.ar' => [
            'password' => '123456',
            'nombre' => 'María',
            'apellido' => 'González',
            'tipo_usuario' => 'profesor',
            'legajo' => 'PROF001'
        ],
        'admin@epa703.edu.ar' => [
            'password' => '123456',
            'nombre' => 'Carlos',
            'apellido' => 'Rodríguez',
            'tipo_usuario' => 'admin',
            'legajo' => 'ADM001'
        ],
        'secretario@epa703.edu.ar' => [
            'password' => '123456',
            'nombre' => 'Ana',
            'apellido' => 'López',
            'tipo_usuario' => 'secretario',
            'legajo' => 'SEC001'
        ]
    ];

    // Verificar si es usuario de prueba
    if (isset($usuarios_prueba[$usuario]) && 
        $usuarios_prueba[$usuario]['tipo_usuario'] === $role &&
        $usuarios_prueba[$usuario]['password'] === $password) {
        
        $user_data = $usuarios_prueba[$usuario];
        
        error_log("Login API: Usuario de prueba válido - $usuario");
        
        // Crear sesión de prueba
        $_SESSION['user_id'] = rand(1000, 9999);
        $_SESSION['nombre'] = $user_data['nombre'];
        $_SESSION['apellido'] = $user_data['apellido'];
        $_SESSION['email'] = $usuario;
        $_SESSION['tipo_usuario'] = $role;
        $_SESSION['legajo'] = $user_data['legajo'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        session_regenerate_id(true);

        // Determinar URL de redirección
        $redirect_urls = [
            'estudiante' => 'panel-estudiante.html',
            'profesor' => 'panel-profesor.html',
            'admin' => 'panel-admin.html',
            'secretario' => 'panel-secretario.html'
        ];

        $redirect_url = $redirect_urls[$role] ?? 'index.html';

        echo json_encode([
            'success' => true,
            'message' => '¡Bienvenido/a! (Modo de prueba)',
            'redirect' => $redirect_url,
            'user' => [
                'id' => $_SESSION['user_id'],
                'nombre' => $user_data['nombre'],
                'apellido' => $user_data['apellido'],
                'email' => $usuario,
                'tipo_usuario' => $role,
                'legajo' => $user_data['legajo']
            ]
        ]);
        exit();
    }

    // SEGUNDO: Intentar con base de datos
    try {
        // Configuración de base de datos
        $db_config = [
            'host' => 'localhost',
            'dbname' => 'epa703',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4'
        ];

        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        error_log("Login API: Conexión a BD exitosa");

        // Buscar usuario en la base de datos
        $sql = "SELECT id, nombre, apellido, email, telefono, tipo_usuario, password_hash, activo 
                FROM usuarios 
                WHERE email = :email AND tipo_usuario = :tipo_usuario AND activo = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'email' => $usuario,
            'tipo_usuario' => $role
        ]);

        $user = $stmt->fetch();

        if ($user) {
            error_log("Login API: Usuario encontrado en BD: " . $user['nombre']);

            // Verificar contraseña
            $password_valid = false;

            // Verificar contraseña simple '123456' para pruebas
            if ($password === '123456') {
                $password_valid = true;
                error_log("Login API: Contraseña de prueba válida");
            }
            // También verificar hash si existe
            elseif (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                $password_valid = true;
                error_log("Login API: Hash de contraseña válido");
            }

            if ($password_valid) {
                // Crear sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['nombre'] = $user['nombre'];
                $_SESSION['apellido'] = $user['apellido'];
                $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
                $_SESSION['telefono'] = $user['telefono'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();

                session_regenerate_id(true);

                // Obtener información adicional según el tipo
                $additional_info = [];
                
                if ($role === 'estudiante') {
                    $sql = "SELECT e.legajo, c.nombre as curso_nombre
                            FROM estudiantes e
                            LEFT JOIN cursos c ON e.curso_id = c.id
                            WHERE e.usuario_id = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['user_id' => $user['id']]);
                    $student_info = $stmt->fetch();
                    
                    if ($student_info) {
                        $additional_info = $student_info;
                        $_SESSION['legajo'] = $student_info['legajo'];
                    }
                } elseif ($role === 'profesor') {
                    $sql = "SELECT legajo, especialidad FROM profesores WHERE usuario_id = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['user_id' => $user['id']]);
                    $profesor_info = $stmt->fetch();
                    
                    if ($profesor_info) {
                        $additional_info = $profesor_info;
                        $_SESSION['legajo'] = $profesor_info['legajo'];
                    }
                }

                // Determinar URL de redirección
                $redirect_urls = [
                    'estudiante' => 'panel-estudiante.html',
                    'profesor' => 'panel-profesor.html',
                    'admin' => 'panel-admin.html',
                    'secretario' => 'panel-secretario.html'
                ];

                $redirect_url = $redirect_urls[$role] ?? 'index.html';

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
                        'telefono' => $user['telefono']
                    ], $additional_info)
                ]);
                exit();
            }
        }

    } catch (PDOException $e) {
        error_log("Login API: Error de BD: " . $e->getMessage());
        // Continuar con validación de usuarios de prueba si la BD falla
    }

    // Si llegamos aquí, las credenciales son incorrectas
    error_log("Login API: Credenciales incorrectas para usuario: $usuario");
    throw new Exception('Credenciales incorrectas');

} catch (Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>