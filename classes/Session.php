<?php

class Session {
    
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerar ID de sesión periódicamente por seguridad
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
        } else if (time() - $_SESSION['created_at'] > 1800) { // 30 minutos
            session_regenerate_id(true);
            $_SESSION['created_at'] = time();
        }
        
        // Verificar timeout de sesión
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            self::destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function login($userData, $rememberMe = false) {
        self::start();
        
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['nombre'] = $userData['nombre'];
        $_SESSION['apellido'] = $userData['apellido'];
        $_SESSION['tipo_usuario'] = $userData['tipo_usuario'];
        $_SESSION['legajo'] = $userData['legajo'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Token JWT para APIs
        $jwtPayload = [
            'user_id' => $userData['id'],
            'email' => $userData['email'],
            'tipo_usuario' => $userData['tipo_usuario'],
            'iat' => time(),
            'exp' => time() + SESSION_LIFETIME
        ];
        
        $_SESSION['jwt_token'] = Security::createJWT($jwtPayload);
        
        return true;
    }
    
    public static function logout() {
        self::start();
        
        // Limpiar sesión de la base de datos
        if (isset($_SESSION['user_id'])) {
            $db = Database::getInstance()->getConnection();
            $sql = "DELETE FROM sesiones WHERE usuario_id = :user_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
        }
        
        self::destroy();
    }
    
    public static function isLoggedIn() {
        self::start();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Sesión no válida']);
            exit;
        }
    }
    
    public static function requireRole($requiredRoles) {
        self::requireLogin();
        
        $userType = $_SESSION['tipo_usuario'];
        $userRoleLevel = ROLES[$userType] ?? 0;
        
        $hasPermission = false;
        foreach ($requiredRoles as $role) {
            if ($userRoleLevel >= ROLES[$role]) {
                $hasPermission = true;
                break;
            }
        }
        
        if (!$hasPermission) {
            http_response_code(403);
            echo json_encode(['error' => 'Permisos insuficientes']);
            exit;
        }
    }
    
    public static function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }
    
    public static function getUserData() {
        self::requireLogin();
        
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'nombre' => $_SESSION['nombre'],
            'apellido' => $_SESSION['apellido'],
            'tipo_usuario' => $_SESSION['tipo_usuario'],
            'legajo' => $_SESSION['legajo'],
            'jwt_token' => $_SESSION['jwt_token']
        ];
    }
}
?>