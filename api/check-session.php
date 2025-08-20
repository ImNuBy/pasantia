<?php
/**
 * EPA 703 - Check Session API
 * Verificar estado de autenticación y obtener información del usuario
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar si hay sesión activa
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode([
            'authenticated' => false,
            'message' => 'No hay sesión activa'
        ]);
        exit;
    }
    
    // Verificar timeout de sesión (opcional)
    $session_timeout = 8 * 60 * 60; // 8 horas
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $session_timeout) {
        // Limpiar sesión por timeout
        session_destroy();
        echo json_encode([
            'authenticated' => false,
            'message' => 'Sesión expirada'
        ]);
        exit;
    }
    
    // Actualizar última actividad
    $_SESSION['last_activity'] = time();
    
    // Preparar información del usuario
    $userInfo = [
        'id' => $_SESSION['user_id'] ?? null,
        'nombre' => $_SESSION['nombre'] ?? '',
        'apellido' => $_SESSION['apellido'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'tipo_usuario' => $_SESSION['tipo_usuario'] ?? '',
        'dni' => $_SESSION['dni'] ?? '',
        'telefono' => $_SESSION['telefono'] ?? ''
    ];
    
    // Obtener información adicional de la base de datos si es necesario
    if (isset($_SESSION['user_id'])) {
        try {
            require_once '../config/database.php';
            $pdo = getDBConnection();
            
            $stmt = $pdo->prepare("
                SELECT 
                    u.nombre,
                    u.apellido,
                    u.email,
                    u.tipo_usuario,
                    u.telefono,
                    u.dni,
                    u.activo,
                    u.fecha_registro,
                    CASE 
                        WHEN u.tipo_usuario = 'estudiante' THEN e.legajo
                        WHEN u.tipo_usuario = 'profesor' THEN p.legajo
                        ELSE NULL
                    END as legajo,
                    CASE 
                        WHEN u.tipo_usuario = 'estudiante' THEN e.estado
                        WHEN u.tipo_usuario = 'profesor' THEN p.estado
                        ELSE 'activo'
                    END as estado_especifico
                FROM usuarios u
                LEFT JOIN estudiantes e ON u.id = e.usuario_id AND u.tipo_usuario = 'estudiante'
                LEFT JOIN profesores p ON u.id = p.usuario_id AND u.tipo_usuario = 'profesor'
                WHERE u.id = ? AND u.activo = 1
            ");
            
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData) {
                // Actualizar información del usuario en la sesión
                $_SESSION['nombre'] = $userData['nombre'];
                $_SESSION['apellido'] = $userData['apellido'];
                $_SESSION['email'] = $userData['email'];
                $_SESSION['tipo_usuario'] = $userData['tipo_usuario'];
                $_SESSION['telefono'] = $userData['telefono'];
                $_SESSION['dni'] = $userData['dni'];
                
                $userInfo = array_merge($userInfo, $userData);
            } else {
                // Usuario no encontrado o inactivo - invalidar sesión
                session_destroy();
                echo json_encode([
                    'authenticated' => false,
                    'message' => 'Usuario no encontrado o inactivo'
                ]);
                exit;
            }
            
        } catch (Exception $e) {
            error_log("Error verificando usuario en BD: " . $e->getMessage());
            // Continuar con la información de sesión si hay error de BD
        }
    }
    
    // Verificar permisos específicos según el tipo de usuario
    $permisos = obtenerPermisos($userInfo['tipo_usuario']);
    
    // Respuesta exitosa
    echo json_encode([
        'authenticated' => true,
        'user' => $userInfo,
        'permisos' => $permisos,
        'session_info' => [
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'session_id' => session_id()
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en check-session.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'message' => 'Error interno del servidor'
    ]);
}

/**
 * Obtener permisos según el tipo de usuario
 */
function obtenerPermisos($tipoUsuario) {
    $permisos = [
        'admin' => [
            'gestionar_usuarios',
            'ver_todas_consultas',
            'responder_consultas',
            'gestionar_inscripciones',
            'ver_reportes',
            'configurar_sistema',
            'gestionar_profesores',
            'gestionar_estudiantes',
            'gestionar_cursos',
            'ver_estadisticas',
            'exportar_datos',
            'enviar_notificaciones'
        ],
        'secretario' => [
            'ver_consultas',
            'responder_consultas',
            'gestionar_inscripciones',
            'ver_estudiantes',
            'ver_profesores',
            'generar_certificados',
            'ver_reportes_basicos'
        ],
        'profesor' => [
            'ver_estudiantes_asignados',
            'cargar_notas',
            'ver_asistencias',
            'generar_reportes_academicos',
            'comunicarse_estudiantes'
        ],
        'estudiante' => [
            'ver_perfil',
            'actualizar_datos_personales',
            'ver_notas',
            'ver_asistencias',
            'descargar_certificados',
            'enviar_consultas'
        ]
    ];
    
    return $permisos[$tipoUsuario] ?? [];
}

/**
 * Verificar si el usuario tiene un permiso específico
 */
function tienePermiso($tipoUsuario, $permiso) {
    $permisos = obtenerPermisos($tipoUsuario);
    return in_array($permiso, $permisos);
}
?>