<?php
/**
 * EPA 703 - Crear Usuario Administrador
 * Script para crear nuevos usuarios admin desde PHP
 */

require_once '../config/database.php';

// Configuración del nuevo admin
$nuevo_admin = [
    'nombre' => 'Laura',
    'apellido' => 'Secretaria',
    'email' => 'secretaria@epa703.edu.ar',
    'telefono' => '11-4567-8901',
    'dni' => '28456789',
    'fecha_nacimiento' => '1985-03-10',
    'direccion' => 'Av. Principal 890, Buenos Aires',
    'tipo_usuario' => 'admin',
    'password' => '123456' // Será hasheada automáticamente
];

try {
    // Conectar a la base de datos
    $pdo = getDBConnection();
    
    echo "🔗 Conectado a la base de datos EPA 703\n";
    
    // Verificar si el email ya existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $stmt->execute(['email' => $nuevo_admin['email']]);
    
    if ($stmt->fetch()) {
        echo "⚠️  El usuario con email {$nuevo_admin['email']} ya existe\n";
        exit;
    }
    
    // Hashear la contraseña
    $password_hash = password_hash($nuevo_admin['password'], PASSWORD_DEFAULT);
    
    // Insertar nuevo usuario admin
    $sql = "INSERT INTO usuarios (
                nombre, apellido, email, telefono, dni, fecha_nacimiento, 
                direccion, tipo_usuario, password_hash, activo, fecha_registro
            ) VALUES (
                :nombre, :apellido, :email, :telefono, :dni, :fecha_nacimiento,
                :direccion, :tipo_usuario, :password_hash, 1, NOW()
            )";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'nombre' => $nuevo_admin['nombre'],
        'apellido' => $nuevo_admin['apellido'],
        'email' => $nuevo_admin['email'],
        'telefono' => $nuevo_admin['telefono'],
        'dni' => $nuevo_admin['dni'],
        'fecha_nacimiento' => $nuevo_admin['fecha_nacimiento'],
        'direccion' => $nuevo_admin['direccion'],
        'tipo_usuario' => $nuevo_admin['tipo_usuario'],
        'password_hash' => $password_hash
    ]);
    
    if ($result) {
        $user_id = $pdo->lastInsertId();
        echo "✅ Usuario admin creado exitosamente!\n";
        echo "📋 Detalles del nuevo admin:\n";
        echo "   - ID: {$user_id}\n";
        echo "   - Nombre: {$nuevo_admin['nombre']} {$nuevo_admin['apellido']}\n";
        echo "   - Email: {$nuevo_admin['email']}\n";
        echo "   - Contraseña: {$nuevo_admin['password']}\n";
        echo "   - DNI: {$nuevo_admin['dni']}\n";
        echo "   - Teléfono: {$nuevo_admin['telefono']}\n";
        echo "\n🔐 Credenciales de acceso:\n";
        echo "   - Usuario: {$nuevo_admin['email']}\n";
        echo "   - Contraseña: {$nuevo_admin['password']}\n";
        
        // Mostrar todos los admins actuales
        echo "\n👥 Lista de administradores actuales:\n";
        $stmt = $pdo->query("
            SELECT id, nombre, apellido, email, fecha_registro, activo
            FROM usuarios 
            WHERE tipo_usuario = 'admin' 
            ORDER BY fecha_registro DESC
        ");
        
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($admins as $admin) {
            $status = $admin['activo'] ? '✅' : '❌';
            echo "   {$status} {$admin['nombre']} {$admin['apellido']} - {$admin['email']}\n";
        }
        
    } else {
        echo "❌ Error al crear el usuario admin\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "\n";
    echo "\n🔧 Verifica que:\n";
    echo "   1. La base de datos 'epa703' exista\n";
    echo "   2. El archivo config/database.php esté configurado correctamente\n";
    echo "   3. MySQL esté corriendo\n";
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
}

/**
 * Función para crear múltiples usuarios admin de una vez
 */
function crearMultiplesAdmins() {
    $admins = [
        [
            'nombre' => 'Ana',
            'apellido' => 'Vicedirectora',
            'email' => 'vicedirectora@epa703.edu.ar',
            'telefono' => '11-5678-9012',
            'dni' => '32123456',
            'password' => '123456'
        ],
        [
            'nombre' => 'Roberto',
            'apellido' => 'Supervisor',
            'email' => 'supervisor@epa703.edu.ar',
            'telefono' => '11-6789-0123',
            'dni' => '29876543',
            'password' => '123456'
        ]
    ];
    
    try {
        $pdo = getDBConnection();
        
        foreach ($admins as $admin) {
            // Verificar si existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmt->execute(['email' => $admin['email']]);
            
            if (!$stmt->fetch()) {
                // Crear usuario
                $password_hash = password_hash($admin['password'], PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO usuarios (
                            nombre, apellido, email, telefono, dni, 
                            tipo_usuario, password_hash, activo, fecha_registro
                        ) VALUES (
                            :nombre, :apellido, :email, :telefono, :dni,
                            'admin', :password_hash, 1, NOW()
                        )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nombre' => $admin['nombre'],
                    'apellido' => $admin['apellido'],
                    'email' => $admin['email'],
                    'telefono' => $admin['telefono'],
                    'dni' => $admin['dni'],
                    'password_hash' => $password_hash
                ]);
                
                echo "✅ Admin creado: {$admin['nombre']} {$admin['apellido']}\n";
            } else {
                echo "⚠️  Ya existe: {$admin['email']}\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error creando admins múltiples: " . $e->getMessage() . "\n";
    }
}

// Descomenta esta línea para crear múltiples admins
// crearMultiplesAdmins();

echo "\n🎉 Proceso completado!\n";
echo "💡 Puedes ejecutar este script desde la terminal con: php create_admin.php\n";
?>