
<?php
// Configuración de base de datos
$config = [
    'host' => 'localhost',
    'dbname' => 'epa703',
    'username' => 'root',
    'password' => ''
];

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", 
                   $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Conectado a la base de datos epa703\n";
    
    // Usuarios de prueba
    $users = [
        [
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'email' => 'alumno@eest2.edu.ar',
            'tipo_usuario' => 'estudiante',
            'password_hash' => password_hash('123456', PASSWORD_DEFAULT)
        ],
        [
            'nombre' => 'María',
            'apellido' => 'González',
            'email' => 'profesor@eest2.edu.ar',
            'tipo_usuario' => 'profesor',
            'password_hash' => password_hash('123456', PASSWORD_DEFAULT)
        ],
        [
            'nombre' => 'Carlos',
            'apellido' => 'Rodríguez',
            'email' => 'directivo@eest2.edu.ar',
            'tipo_usuario' => 'secretario',
            'password_hash' => password_hash('123456', PASSWORD_DEFAULT)
        ],
        [
            'nombre' => 'Ana',
            'apellido' => 'Martínez',
            'email' => 'admin@eest2.edu.ar',
            'tipo_usuario' => 'admin',
            'password_hash' => password_hash('123456', PASSWORD_DEFAULT)
        ]
    ];
    
    $sql = "INSERT INTO usuarios (nombre, apellido, email, tipo_usuario, password_hash, activo) 
            VALUES (:nombre, :apellido, :email, :tipo_usuario, :password_hash, 1)
            ON DUPLICATE KEY UPDATE 
            password_hash = VALUES(password_hash), 
            activo = 1";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($users as $user) {
        try {
            $stmt->execute($user);
            echo "✅ Usuario creado/actualizado: {$user['email']} ({$user['tipo_usuario']})\n";
        } catch (PDOException $e) {
            echo "❌ Error creando usuario {$user['email']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Proceso completado!\n";
    echo "Credenciales de prueba:\n";
    echo "- Contraseña para todos: 123456\n";
    
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
    echo "Verifica que:\n";
    echo "1. MySQL esté corriendo\n";
    echo "2. La base de datos 'epa703' exista\n";
    echo "3. Las credenciales sean correctas\n";
}
?>