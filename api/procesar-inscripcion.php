<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Validar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos del formulario
    $datos = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'apellido' => trim($_POST['apellido'] ?? ''),
        'dni' => trim($_POST['dni'] ?? ''),
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
        'email' => trim($_POST['email'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'ciclo' => $_POST['ciclo'] ?? '',
        'turno' => $_POST['turno'] ?? '',
        'nivel_educativo' => $_POST['nivel_educativo'] ?? '',
        'motivacion' => trim($_POST['motivacion'] ?? '')
    ];

    // Validar campos obligatorios
    $camposObligatorios = ['nombre', 'apellido', 'dni', 'fecha_nacimiento', 'email', 'telefono', 'direccion', 'ciclo', 'turno'];
    foreach ($camposObligatorios as $campo) {
        if (empty($datos[$campo])) {
            throw new Exception("El campo $campo es obligatorio");
        }
    }

    // Validar formato de email
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("El formato del email no es válido");
    }

    // Validar DNI (solo números, 7-8 dígitos)
    if (!preg_match('/^\d{7,8}$/', $datos['dni'])) {
        throw new Exception("El DNI debe contener solo números (7-8 dígitos)");
    }

    // Conectar a la base de datos
    $pdo = Database::connect();

    // Verificar si ya existe una inscripción con este DNI o email
    $stmt = $pdo->prepare("SELECT id FROM inscripciones WHERE dni = ? OR email = ?");
    $stmt->execute([$datos['dni'], $datos['email']]);
    
    if ($stmt->fetch()) {
        throw new Exception("Ya existe una inscripción con este DNI o email");
    }

    // Procesar archivos subidos
    $archivos = [];
    $uploadDir = '../uploads/inscripciones/';
    
    // Crear directorio si no existe
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Procesar DNI
    if (isset($_FILES['dni']) && $_FILES['dni']['error'] === UPLOAD_ERR_OK) {
        $archivoDni = guardarArchivo($_FILES['dni'], $uploadDir, 'dni_' . $datos['dni']);
        $archivos['dni'] = $archivoDni;
    } else {
        throw new Exception("Es obligatorio subir una foto del DNI");
    }

    // Procesar certificado (opcional)
    if (isset($_FILES['certificado']) && $_FILES['certificado']['error'] === UPLOAD_ERR_OK) {
        $archivoCertificado = guardarArchivo($_FILES['certificado'], $uploadDir, 'certificado_' . $datos['dni']);
        $archivos['certificado'] = $archivoCertificado;
    }

    // Generar número de seguimiento único
    $numeroSeguimiento = 'INS-' . date('Ymd') . '-' . substr(uniqid(), -6);

    // Insertar en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO inscripciones (
            numero_seguimiento, nombre, apellido, dni, fecha_nacimiento, email, 
            telefono, direccion, ciclo, turno, nivel_educativo, motivacion,
            archivo_dni, archivo_certificado, estado, fecha_creacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())
    ");

    $stmt->execute([
        $numeroSeguimiento,
        $datos['nombre'],
        $datos['apellido'],
        $datos['dni'],
        $datos['fecha_nacimiento'],
        $datos['email'],
        $datos['telefono'],
        $datos['direccion'],
        $datos['ciclo'],
        $datos['turno'],
        $datos['nivel_educativo'],
        $datos['motivacion'],
        $archivos['dni'],
        $archivos['certificado'] ?? null
    ]);

    $inscripcionId = $pdo->lastInsertId();

    // Enviar email de confirmación (opcional)
    enviarEmailConfirmacion($datos, $numeroSeguimiento);

    echo json_encode([
        'success' => true,
        'message' => 'Inscripción procesada exitosamente',
        'numero_seguimiento' => $numeroSeguimiento,
        'inscripcion_id' => $inscripcionId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function guardarArchivo($archivo, $directorio, $prefijo) {
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = $prefijo . '_' . time() . '.' . $extension;
    $rutaCompleta = $directorio . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        throw new Exception("Error al guardar el archivo: " . $archivo['name']);
    }

    return $nombreArchivo;
}

function enviarEmailConfirmacion($datos, $numeroSeguimiento) {
    // Implementar envío de email de confirmación
    // Usar PHPMailer o función mail() de PHP
}
?>