
<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=epa703;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Agregando datos de ejemplo para estudiantes...\n";
    
    // Buscar el usuario estudiante
    $sql = "SELECT id FROM usuarios WHERE email = 'alumno@eest2.edu.ar' AND tipo_usuario = 'estudiante'";
    $stmt = $pdo->query($sql);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "❌ Usuario estudiante no encontrado. Ejecuta primero create_users.php\n";
        exit;
    }
    
    $userId = $user['id'];
    
    // Verificar si ya existe registro de estudiante
    $sql = "SELECT id FROM estudiantes WHERE usuario_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $existingStudent = $stmt->fetch();
    
    if (!$existingStudent) {
        // Crear registro de estudiante
        $sql = "INSERT INTO estudiantes (usuario_id, legajo, fecha_ingreso, estado) 
                VALUES (:user_id, 'EST001', '2022-03-01', 'activo')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $estudianteId = $pdo->lastInsertId();
        echo "✅ Registro de estudiante creado (ID: $estudianteId)\n";
    } else {
        $estudianteId = $existingStudent['id'];
        echo "ℹ️ Usando estudiante existente (ID: $estudianteId)\n";
    }
    
    // Crear algunas calificaciones de ejemplo
    $sql = "SELECT id FROM materias LIMIT 3";
    $stmt = $pdo->query($sql);
    $materias = $stmt->fetchAll();
    
    if (!empty($materias)) {
        $sql = "INSERT IGNORE INTO calificaciones (estudiante_id, materia_id, profesor_id, tipo_evaluacion, nota, fecha_evaluacion, periodo, anio_lectivo) 
                VALUES (:estudiante_id, :materia_id, 1, :tipo, :nota, :fecha, '1er_cuatrimestre', :anio)";
        $stmt = $pdo->prepare($sql);
        
        $calificaciones = [
            ['materia_id' => $materias[0]['id'], 'tipo' => 'parcial', 'nota' => 8.5, 'fecha' => date('Y-m-d', strtotime('-5 days'))],
            ['materia_id' => $materias[1]['id'], 'tipo' => 'trabajo_practico', 'nota' => 9.0, 'fecha' => date('Y-m-d', strtotime('-10 days'))],
            ['materia_id' => $materias[2]['id'], 'tipo' => 'parcial', 'nota' => 7.5, 'fecha' => date('Y-m-d', strtotime('-15 days'))]
        ];
        
        foreach ($calificaciones as $cal) {
            $stmt->execute([
                'estudiante_id' => $estudianteId,
                'materia_id' => $cal['materia_id'],
                'tipo' => $cal['tipo'],
                'nota' => $cal['nota'],
                'fecha' => $cal['fecha'],
                'anio' => date('Y')
            ]);
        }
        
        echo "✅ Calificaciones de ejemplo agregadas\n";
    }
    
    // Crear algunas asistencias de ejemplo
    if (!empty($materias)) {
        $sql = "INSERT IGNORE INTO asistencias (estudiante_id, materia_id, fecha, estado) 
                VALUES (:estudiante_id, :materia_id, :fecha, :estado)";
        $stmt = $pdo->prepare($sql);
        
        for ($i = 1; $i <= 20; $i++) {
            $fecha = date('Y-m-d', strtotime("-$i days"));
            
            // Solo días hábiles
            if (date('N', strtotime($fecha)) < 6) {
                foreach ($materias as $materia) {
                    $estado = (rand(1, 100) <= 90) ? 'presente' : 'ausente'; // 90% presente
                    
                    $stmt->execute([
                        'estudiante_id' => $estudianteId,
                        'materia_id' => $materia['id'],
                        'fecha' => $fecha,
                        'estado' => $estado
                    ]);
                }
            }
        }
        
        echo "✅ Asistencias de ejemplo agregadas\n";
    }
    
    echo "\n🎉 Datos de ejemplo agregados correctamente!\n";
    echo "Ahora el panel del estudiante debería mostrar información.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
