<?php

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function authenticate($emailOrUsername, $password, $userType) {
        try {
            // Verificar intentos de login (crear tabla si no existe)
            if (!$this->checkLoginAttempts($emailOrUsername)) {
                throw new Exception("Demasiados intentos de login. Intenta nuevamente en " . (LOCKOUT_TIME / 60) . " minutos.");
            }
            
            // Buscar usuario según el tipo
            $sql = "SELECT u.*, 
                           CASE 
                               WHEN u.tipo_usuario = 'estudiante' THEN e.legajo
                               WHEN u.tipo_usuario = 'profesor' THEN p.legajo
                               ELSE NULL
                           END as legajo,
                           CASE
                               WHEN u.tipo_usuario = 'estudiante' THEN c.nombre
                               ELSE NULL
                           END as curso_nombre
                    FROM usuarios u
                    LEFT JOIN estudiantes e ON u.id = e.usuario_id
                    LEFT JOIN profesores p ON u.id = p.usuario_id
                    LEFT JOIN cursos c ON e.curso_id = c.id
                    WHERE (u.email = :identifier) 
                    AND u.tipo_usuario = :user_type 
                    AND u.activo = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'identifier' => $emailOrUsername,
                'user_type' => $userType
            ]);
            
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->recordFailedAttempt($emailOrUsername);
                throw new Exception("Credenciales inválidas");
            }
            
            // Verificar contraseña
            if (!Security::verifyPassword($password, $user['password_hash'])) {
                $this->recordFailedAttempt($emailOrUsername);
                throw new Exception("Credenciales inválidas");
            }
            
            // Login exitoso
            $this->clearFailedAttempts($emailOrUsername);
            $this->updateLastLogin($user['id']);
            
            return $this->getUserData($user);
            
        } catch (Exception $e) {
            error_log("Error en autenticación: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function checkLoginAttempts($identifier) {
        // Crear tabla de intentos si no existe
        $this->createLoginAttemptsTable();
        
        $sql = "SELECT intentos, ultima_actividad FROM login_attempts WHERE identifier = :identifier";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['identifier' => $identifier]);
        $attempts = $stmt->fetch();
        
        if (!$attempts) {
            return true;
        }
        
        $timeDiff = time() - strtotime($attempts['ultima_actividad']);
        
        if ($attempts['intentos'] >= MAX_LOGIN_ATTEMPTS && $timeDiff < LOCKOUT_TIME) {
            return false;
        }
        
        if ($timeDiff >= LOCKOUT_TIME) {
            $this->clearFailedAttempts($identifier);
        }
        
        return true;
    }
    
    private function recordFailedAttempt($identifier) {
        $this->createLoginAttemptsTable();
        
        $sql = "INSERT INTO login_attempts (identifier, intentos, ultima_actividad) 
                VALUES (:identifier, 1, NOW()) 
                ON DUPLICATE KEY UPDATE 
                intentos = intentos + 1, ultima_actividad = NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['identifier' => $identifier]);
    }
    
    private function clearFailedAttempts($identifier) {
        $sql = "DELETE FROM login_attempts WHERE identifier = :identifier";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['identifier' => $identifier]);
    }
    
    private function createLoginAttemptsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL,
            intentos INT DEFAULT 1,
            ultima_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_identifier (identifier)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
    }
    
    private function updateLastLogin($userId) {
        // Actualizar ultima_conexion si existe la columna
        $sql = "UPDATE usuarios SET updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $userId]);
        
        // Crear entrada en sesiones
        $sessionId = session_id() ?: Security::generateToken(16);
        $sql = "INSERT INTO sesiones (id, usuario_id, ip_address, user_agent, ultima_actividad) 
                VALUES (:session_id, :user_id, :ip, :user_agent, NOW())
                ON DUPLICATE KEY UPDATE ultima_actividad = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    private function getUserData($user) {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'telefono' => $user['telefono'],
            'tipo_usuario' => $user['tipo_usuario'],
            'legajo' => $user['legajo'],
            'curso_nombre' => $user['curso_nombre'],
            'activo' => $user['activo']
        ];
    }
    
    public function createUser($userData) {
        try {
            $this->db->beginTransaction();
            
            // Validar datos
            $this->validateUserData($userData);
            
            // Verificar si el usuario ya existe
            if ($this->userExists($userData['email'])) {
                throw new Exception("El email ya existe");
            }
            
            // Hash de la contraseña
            $hashedPassword = Security::hashPassword($userData['password']);
            
            $sql = "INSERT INTO usuarios (nombre, apellido, email, telefono, dni, fecha_nacimiento, 
                           direccion, tipo_usuario, password_hash, activo, fecha_registro) 
                    VALUES (:nombre, :apellido, :email, :telefono, :dni, :fecha_nacimiento, 
                           :direccion, :tipo_usuario, :password_hash, 1, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                'nombre' => $userData['nombre'],
                'apellido' => $userData['apellido'],
                'email' => $userData['email'],
                'telefono' => $userData['telefono'] ?? null,
                'dni' => $userData['dni'] ?? null,
                'fecha_nacimiento' => $userData['fecha_nacimiento'] ?? null,
                'direccion' => $userData['direccion'] ?? null,
                'tipo_usuario' => $userData['tipo_usuario'],
                'password_hash' => $hashedPassword
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Crear registro específico según tipo de usuario
            if ($userData['tipo_usuario'] === 'estudiante' && isset($userData['legajo'])) {
                $sql = "INSERT INTO estudiantes (usuario_id, legajo, fecha_ingreso, estado) 
                        VALUES (:usuario_id, :legajo, NOW(), 'activo')";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'usuario_id' => $userId,
                    'legajo' => $userData['legajo']
                ]);
            } elseif ($userData['tipo_usuario'] === 'profesor' && isset($userData['legajo'])) {
                $sql = "INSERT INTO profesores (usuario_id, legajo, fecha_ingreso, estado) 
                        VALUES (:usuario_id, :legajo, NOW(), 'activo')";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'usuario_id' => $userId,
                    'legajo' => $userData['legajo']
                ]);
            }
            
            $this->db->commit();
            return $userId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function validateUserData($userData) {
        $required = ['nombre', 'apellido', 'email', 'password', 'tipo_usuario'];
        
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new Exception("El campo {$field} es obligatorio");
            }
        }
        
        if (!Security::validateEmail($userData['email'])) {
            throw new Exception("Email inválido");
        }
        
        if (strlen($userData['password']) < PASSWORD_MIN_LENGTH) {
            throw new Exception("La contraseña debe tener al menos " . PASSWORD_MIN_LENGTH . " caracteres");
        }
        
        $validTypes = ['estudiante', 'profesor', 'admin', 'secretario'];
        if (!in_array($userData['tipo_usuario'], $validTypes)) {
            throw new Exception("Tipo de usuario inválido");
        }
    }
    
    private function userExists($email) {
        $sql = "SELECT id FROM usuarios WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() !== false;
    }
    
    public function getUserDetails($userId) {
        $sql = "SELECT u.*, 
                       CASE 
                           WHEN u.tipo_usuario = 'estudiante' THEN e.legajo
                           WHEN u.tipo_usuario = 'profesor' THEN p.legajo
                           ELSE NULL
                       END as legajo,
                       CASE
                           WHEN u.tipo_usuario = 'estudiante' THEN c.nombre
                           ELSE NULL
                       END as curso_nombre,
                       CASE
                           WHEN u.tipo_usuario = 'estudiante' THEN o.nombre
                           ELSE NULL
                       END as orientacion_nombre
                FROM usuarios u
                LEFT JOIN estudiantes e ON u.id = e.usuario_id
                LEFT JOIN profesores p ON u.id = p.usuario_id
                LEFT JOIN cursos c ON e.curso_id = c.id
                LEFT JOIN orientaciones o ON c.orientacion_id = o.id
                WHERE u.id = :id AND u.activo = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch();
    }
}
?>