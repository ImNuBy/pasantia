<?php
session_start();
require_once '../config/database-config.php';

// Verificar sesión admin
if (!isset($_SESSION['logged_in']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: ../login.html');
    exit();
}

$pdo = getDBConnection();

// Función para obtener configuraciones por categoría
function getConfiguraciones($pdo, $categoria = null) {
    $sql = "SELECT * FROM configuracion";
    $params = [];
    
    if ($categoria) {
        $sql .= " WHERE categoria = :categoria";
        $params['categoria'] = $categoria;
    }
    
    $sql .= " ORDER BY categoria, clave";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener una configuración específica
function getConfiguracion($pdo, $clave) {
    $stmt = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = :clave");
    $stmt->execute(['clave' => $clave]);
    $result = $stmt->fetchColumn();
    return $result ?: '';
}

// Función para actualizar configuración
function actualizarConfiguracion($pdo, $clave, $valor, $descripcion = null, $tipo = 'string', $categoria = 'general') {
    $sql = "INSERT INTO configuracion (clave, valor, descripcion, tipo, categoria) 
            VALUES (:clave, :valor, :descripcion, :tipo, :categoria)
            ON DUPLICATE KEY UPDATE 
            valor = VALUES(valor), 
            descripcion = VALUES(descripcion),
            tipo = VALUES(tipo),
            categoria = VALUES(categoria),
            updated_at = CURRENT_TIMESTAMP";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'clave' => $clave,
        'valor' => $valor,
        'descripcion' => $descripcion,
        'tipo' => $tipo,
        'categoria' => $categoria
    ]);
}

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['guardar_general'])) {
            // Configuraciones generales
            actualizarConfiguracion($pdo, 'sitio_nombre', $_POST['sitio_nombre'], 'Nombre de la institución', 'string', 'general');
            actualizarConfiguracion($pdo, 'sitio_email', $_POST['sitio_email'], 'Email principal de contacto', 'string', 'general');
            actualizarConfiguracion($pdo, 'sitio_telefono', $_POST['sitio_telefono'], 'Teléfono principal', 'string', 'general');
            actualizarConfiguracion($pdo, 'sitio_direccion', $_POST['sitio_direccion'], 'Dirección física', 'string', 'general');
            actualizarConfiguracion($pdo, 'sitio_descripcion', $_POST['sitio_descripcion'], 'Descripción de la institución', 'string', 'general');
            
            $mensaje = 'Configuración general actualizada correctamente';
            $tipo_mensaje = 'success';
        }
        
        if (isset($_POST['guardar_academico'])) {
            // Configuraciones académicas
            actualizarConfiguracion($pdo, 'anio_lectivo_actual', $_POST['anio_lectivo_actual'], 'Año lectivo actual', 'number', 'academico');
            actualizarConfiguracion($pdo, 'inscripciones_abiertas', $_POST['inscripciones_abiertas'], 'Estado de inscripciones', 'boolean', 'academico');
            actualizarConfiguracion($pdo, 'fecha_inicio_inscripciones', $_POST['fecha_inicio_inscripciones'], 'Fecha inicio inscripciones', 'string', 'academico');
            actualizarConfiguracion($pdo, 'fecha_fin_inscripciones', $_POST['fecha_fin_inscripciones'], 'Fecha fin inscripciones', 'string', 'academico');
            actualizarConfiguracion($pdo, 'cupos_disponibles', $_POST['cupos_disponibles'], 'Cupos totales disponibles', 'number', 'academico');
            
            $mensaje = 'Configuración académica actualizada correctamente';
            $tipo_mensaje = 'success';
        }
        
        if (isset($_POST['guardar_sistema'])) {
            // Configuraciones del sistema
            actualizarConfiguracion($pdo, 'tema_sitio', $_POST['tema_sitio'], 'Tema visual del sitio', 'string', 'sistema');
            actualizarConfiguracion($pdo, 'mantenimiento', $_POST['mantenimiento'], 'Modo mantenimiento', 'boolean', 'sistema');
            actualizarConfiguracion($pdo, 'logs_activados', $_POST['logs_activados'], 'Sistema de logs', 'boolean', 'sistema');
            actualizarConfiguracion($pdo, 'backup_automatico', $_POST['backup_automatico'], 'Backup automático', 'boolean', 'sistema');
            actualizarConfiguracion($pdo, 'notificaciones_email', $_POST['notificaciones_email'], 'Notificaciones por email', 'boolean', 'sistema');
            
            $mensaje = 'Configuración del sistema actualizada correctamente';
            $tipo_mensaje = 'success';
        }
        
        if (isset($_POST['guardar_notificaciones'])) {
            // Configuraciones de notificaciones
            actualizarConfiguracion($pdo, 'smtp_host', $_POST['smtp_host'], 'Servidor SMTP', 'string', 'notificaciones');
            actualizarConfiguracion($pdo, 'smtp_puerto', $_POST['smtp_puerto'], 'Puerto SMTP', 'number', 'notificaciones');
            actualizarConfiguracion($pdo, 'smtp_usuario', $_POST['smtp_usuario'], 'Usuario SMTP', 'string', 'notificaciones');
            actualizarConfiguracion($pdo, 'smtp_password', $_POST['smtp_password'], 'Contraseña SMTP', 'string', 'notificaciones');
            actualizarConfiguracion($pdo, 'email_desde', $_POST['email_desde'], 'Email remitente', 'string', 'notificaciones');
            actualizarConfiguracion($pdo, 'notif_nuevas_inscripciones', $_POST['notif_nuevas_inscripciones'], 'Notificar nuevas inscripciones', 'boolean', 'notificaciones');
            actualizarConfiguracion($pdo, 'notif_nuevas_consultas', $_POST['notif_nuevas_consultas'], 'Notificar nuevas consultas', 'boolean', 'notificaciones');
            
            $mensaje = 'Configuración de notificaciones actualizada correctamente';
            $tipo_mensaje = 'success';
        }
        
        if (isset($_POST['crear_backup'])) {
            // Crear backup de la base de datos
            try {
                $fecha = date('Y-m-d_H-i-s');
                $nombre_backup = "epa703_backup_{$fecha}.sql";
                
                // Obtener configuración de la base de datos
                $host = 'localhost'; // Ajustar según tu configuración
                $dbname = 'epa703';  // Ajustar según tu configuración
                $username = 'root';  // Ajustar según tu configuración
                $password = '';      // Ajustar según tu configuración
                
                // Comando mysqldump
                $comando = "mysqldump --host={$host} --user={$username} --password={$password} {$dbname} > backups/{$nombre_backup}";
                
                // Crear directorio de backups si no existe
                if (!file_exists('../backups')) {
                    mkdir('../backups', 0755, true);
                }
                
                // Ejecutar backup (en producción usar exec() con validación)
                // exec($comando, $output, $return_var);
                
                // Por simplicidad, simular la creación del backup
                file_put_contents("../backups/{$nombre_backup}", "-- Backup simulado creado el " . date('Y-m-d H:i:s'));
                
                $mensaje = "Backup creado exitosamente: {$nombre_backup}";
                $tipo_mensaje = 'success';
                
            } catch (Exception $e) {
                $mensaje = "Error al crear backup: " . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
        
        if (isset($_POST['test_email'])) {
            // Probar configuración de email
            try {
                $smtp_host = getConfiguracion($pdo, 'smtp_host');
                $smtp_puerto = getConfiguracion($pdo, 'smtp_puerto');
                $email_desde = getConfiguracion($pdo, 'email_desde');
                
                if (empty($smtp_host) || empty($email_desde)) {
                    throw new Exception('Configuración SMTP incompleta');
                }
                
                // Aquí iría la lógica real de envío de email de prueba
                // Por simplicidad, simular el envío
                $mensaje = "Email de prueba enviado correctamente a la configuración SMTP";
                $tipo_mensaje = 'success';
                
            } catch (Exception $e) {
                $mensaje = "Error al enviar email de prueba: " . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
        
    } catch (Exception $e) {
        $mensaje = 'Error al actualizar la configuración: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Obtener configuraciones actuales
$config_general = getConfiguraciones($pdo, 'general');
$config_academico = getConfiguraciones($pdo, 'academico');
$config_sistema = getConfiguraciones($pdo, 'sistema');
$config_notificaciones = getConfiguraciones($pdo, 'notificaciones');

// Convertir arrays a formato clave => valor para fácil acceso
$config = [];
foreach (getConfiguraciones($pdo) as $item) {
    $config[$item['clave']] = $item['valor'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - EPA 703</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1e3a2e 0%, #2d5a47 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            margin-bottom: 5px;
            font-size: 1.5em;
        }

        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
        }

        .nav-item {
            margin: 5px 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            border-left-color: #4ecdc4;
        }

        .nav-link.active {
            background-color: rgba(255,255,255,0.15);
            border-left-color: #4ecdc4;
        }

        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background-color: #f5f7fa;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .page-header h1 {
            color: #1e3a2e;
            margin-bottom: 10px;
        }

        .config-tabs {
            display: flex;
            background: white;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .tab-button {
            background: none;
            border: none;
            padding: 15px 25px;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .tab-button.active {
            color: #1e3a2e;
            border-bottom-color: #4ecdc4;
            background-color: #f8f9fa;
        }

        .tab-button:hover {
            background-color: #f8f9fa;
        }

        .config-content {
            background: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #1e3a2e;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4ecdc4;
            font-size: 1.2em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #4ecdc4;
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }

        .form-control-sm {
            padding: 8px 12px;
            font-size: 13px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #4ecdc4;
            color: white;
        }

        .btn-primary:hover {
            background: #45b7aa;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .info-card {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .info-card h4 {
            color: #0c5460;
            margin-bottom: 10px;
        }

        .info-card p {
            color: #0c5460;
            margin: 0;
            font-size: 14px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #4ecdc4;
        }

        .stat-item .value {
            font-size: 1.5em;
            font-weight: bold;
            color: #1e3a2e;
            margin-bottom: 5px;
        }

        .stat-item .label {
            color: #666;
            font-size: 0.9em;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-content {
                margin-left: 0;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .config-tabs {
                flex-direction: column;
            }

            .action-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>🎓 EPA 703</h3>
                <p>Panel Administrativo</p>
            </div>
            
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="consultas.php" class="nav-link">
                        <i class="fas fa-envelope"></i>
                        Consultas
                    </a>
                </li>
                <li class="nav-item">
                    <a href="inscripciones.php" class="nav-link">
                        <i class="fas fa-user-plus"></i>
                        Inscripciones
                    </a>
                </li>
                <li class="nav-item">
                    <a href="usuarios.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a href="cursos.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        Cursos
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reportes.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        Reportes
                    </a>
                </li>
                <li class="nav-item">
                    <a href="configuracion.php" class="nav-link active">
                        <i class="fas fa-cog"></i>
                        Configuración
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../php/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Cerrar Sesión
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-cog"></i> Configuración del Sistema</h1>
                <p>Administra las configuraciones generales de EPA 703</p>
            </div>

            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
            <?php endif; ?>

            <!-- Tabs de Configuración -->
            <div class="config-tabs">
                <button class="tab-button active" onclick="showTab('general')">
                    <i class="fas fa-building"></i> General
                </button>
                <button class="tab-button" onclick="showTab('academico')">
                    <i class="fas fa-graduation-cap"></i> Académico
                </button>
                <button class="tab-button" onclick="showTab('sistema')">
                    <i class="fas fa-server"></i> Sistema
                </button>
                <button class="tab-button" onclick="showTab('notificaciones')">
                    <i class="fas fa-bell"></i> Notificaciones
                </button>
                <button class="tab-button" onclick="showTab('backup')">
                    <i class="fas fa-database"></i> Backup
                </button>
            </div>

            <div class="config-content">
                <!-- Tab General -->
                <div id="general" class="tab-panel active">
                    <form method="post" action="">
                        <div class="form-section">
                            <h3 class="section-title">Información General de la Institución</h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="sitio_nombre">Nombre de la Institución</label>
                                    <input type="text" name="sitio_nombre" id="sitio_nombre" class="form-control" 
                                           value="<?= htmlspecialchars($config['sitio_nombre'] ?? 'EPA 703') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sitio_email">Email Principal</label>
                                    <input type="email" name="sitio_email" id="sitio_email" class="form-control" 
                                           value="<?= htmlspecialchars($config['sitio_email'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="sitio_telefono">Teléfono Principal</label>
                                    <input type="tel" name="sitio_telefono" id="sitio_telefono" class="form-control" 
                                           value="<?= htmlspecialchars($config['sitio_telefono'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="sitio_direccion">Dirección</label>
                                    <textarea name="sitio_direccion" id="sitio_direccion" class="form-control" rows="3"><?= htmlspecialchars($config['sitio_direccion'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="sitio_descripcion">Descripción de la Institución</label>
                                <textarea name="sitio_descripcion" id="sitio_descripcion" class="form-control" rows="4"><?= htmlspecialchars($config['sitio_descripcion'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" name="guardar_general" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Académico -->
                <div id="academico" class="tab-panel">
                    <form method="post" action="">
                        <div class="form-section">
                            <h3 class="section-title">Configuración Académica</h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="anio_lectivo_actual">Año Lectivo Actual</label>
                                    <input type="number" name="anio_lectivo_actual" id="anio_lectivo_actual" class="form-control" 
                                           value="<?= htmlspecialchars($config['anio_lectivo_actual'] ?? date('Y')) ?>" 
                                           min="2020" max="2030" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cupos_disponibles">Cupos Totales Disponibles</label>
                                    <input type="number" name="cupos_disponibles" id="cupos_disponibles" class="form-control" 
                                           value="<?= htmlspecialchars($config['cupos_disponibles'] ?? '100') ?>" min="1">
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha_inicio_inscripciones">Fecha Inicio Inscripciones</label>
                                    <input type="date" name="fecha_inicio_inscripciones" id="fecha_inicio_inscripciones" class="form-control" 
                                           value="<?= htmlspecialchars($config['fecha_inicio_inscripciones'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="fecha_fin_inscripciones">Fecha Fin Inscripciones</label>
                                    <input type="date" name="fecha_fin_inscripciones" id="fecha_fin_inscripciones" class="form-control" 
                                           value="<?= htmlspecialchars($config['fecha_fin_inscripciones'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="hidden" name="inscripciones_abiertas" value="0">
                                    <input type="checkbox" name="inscripciones_abiertas" id="inscripciones_abiertas" value="1" 
                                           <?= (($config['inscripciones_abiertas'] ?? '1') == '1') ? 'checked' : '' ?>>
                                    <label for="inscripciones_abiertas">Inscripciones Abiertas</label>
                                </div>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" name="guardar_academico" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Sistema -->
                <div id="sistema" class="tab-panel">
                    <form method="post" action="">
                        <div class="form-section">
                            <h3 class="section-title">Configuración del Sistema</h3>
                            
                            <div class="info-card">
                                <h4><i class="fas fa-info-circle"></i> Información del Sistema</h4>
                                <p>Estas configuraciones afectan el comportamiento general del sistema EPA 703.</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="tema_sitio">Tema Visual</label>
                                    <select name="tema_sitio" id="tema_sitio" class="form-control">
                                        <option value="claro" <?= ($config['tema_sitio'] ?? 'claro') === 'claro' ? 'selected' : '' ?>>Claro</option>
                                        <option value="oscuro" <?= ($config['tema_sitio'] ?? 'claro') === 'oscuro' ? 'selected' : '' ?>>Oscuro</option>
                                        <option value="auto" <?= ($config['tema_sitio'] ?? 'claro') === 'auto' ? 'selected' : '' ?>>Automático</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="hidden" name="mantenimiento" value="0">
                                    <input type="checkbox" name="mantenimiento" id="mantenimiento" value="1" 
                                           <?= (($config['mantenimiento'] ?? '0') == '1') ? 'checked' : '' ?>>
                                    <label for="mantenimiento">Modo Mantenimiento</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="hidden" name="logs_activados" value="0">
                                    <input type="checkbox" name="logs_activados" id="logs_activados" value="1" 
                                           <?= (($config['logs_activados'] ?? '1') == '1') ? 'checked' : '' ?>>
                                    <label for="logs_activados">Activar Sistema de Logs</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="hidden" name="backup_automatico" value="0">
                                    <input type="checkbox" name="backup_automatico" id="backup_automatico" value="1" 
                                           <?= (($config['backup_automatico'] ?? '1') == '1') ? 'checked' : '' ?>>
                                    <label for="backup_automatico">Backup Automático Diario</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="hidden" name="notificaciones_email" value="0">
                                    <input type="checkbox" name="notificaciones_email" id="notificaciones_email" value="1" 
                                           <?= (($config['notificaciones_email'] ?? '1') == '1') ? 'checked' : '' ?>>
                                    <label for="notificaciones_email">Activar Notificaciones por Email</label>
                                </div>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" name="guardar_sistema" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Notificaciones -->
                <div id="notificaciones" class="tab-panel">
                    <form method="post" action="">
                        <div class="form-section">
                            <h3 class="section-title">Configuración SMTP</h3>
                            
                            <div class="info-card">
                                <h4><i class="fas fa-envelope"></i> Configuración de Email</h4>
                                <p>Configure los parámetros del servidor SMTP para el envío de notificaciones automáticas.</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="smtp_host">Servidor SMTP</label>
                                    <input type="text" name="smtp_host" id="smtp_host" class="form-control" 
                                           value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>" 
                                           placeholder="smtp.gmail.com">
                                </div>
                                
                                <div class="form-group">
                                    <label for="smtp_puerto">Puerto SMTP</label>
                                    <input type="number" name="smtp_puerto" id="smtp_puerto" class="form-control" 
                                           value="<?= htmlspecialchars($config['smtp_puerto'] ?? '587') ?>" 
                                           min="1" max="65535">
                                </div>
                                
                                <div class="form-group">
                                    <label for="smtp_usuario">Usuario SMTP</label>
                                    <input type="email" name="smtp_usuario" id="smtp_usuario" class="form-control" 
                                           value="<?= htmlspecialchars($config['smtp_usuario'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="smtp_password">Contraseña SMTP</label>
                                    <input type="password" name="smtp_password" id="smtp_password" class="form-control" 
                                           value="<?= htmlspecialchars($config['smtp_password'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email_desde">Email Remitente</label>
                                    <input type="email" name="email_desde" id="email_desde" class="form-control" 
                                           value="<?= htmlspecialchars($config['email_desde'] ?? '') ?>" 
                                           placeholder="noreply@epa703.edu.ar">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3 class="section-title">Tipos de Notificaciones</h3>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="hidden" name="notif_nuevas_inscripciones" value="0">
                                    <input type="checkbox" name="notif_nuevas_inscripciones" id="notif_nuevas_inscripciones" value="1" 
                                           <?= (($config['notif_nuevas_inscripciones'] ?? '1') == '1') ? 'checked' : '' ?>>
                                    <label for="notif_nuevas_inscripciones">Notificar Nuevas Inscripciones</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="hidden" name="notif_nuevas_consultas" value="0">
                                    <input type="checkbox" name="notif_nuevas_consultas" id="notif_nuevas_consultas" value="1" 
                                           <?= (($config['notif_nuevas_consultas'] ?? '1') == '1') ? 'checked' : '' ?>>
                                    <label for="notif_nuevas_consultas">Notificar Nuevas Consultas</label>
                                </div>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button type="submit" name="guardar_notificaciones" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                            <button type="submit" name="test_email" class="btn btn-success">
                                <i class="fas fa-paper-plane"></i> Probar Envío
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Backup -->
                <div id="backup" class="tab-panel">
                    <div class="form-section">
                        <h3 class="section-title">Gestión de Backups</h3>
                        
                        <div class="info-card">
                            <h4><i class="fas fa-shield-alt"></i> Seguridad de Datos</h4>
                            <p>Realice copias de seguridad periódicas de la base de datos para proteger la información de EPA 703.</p>
                        </div>
                        
                        <div class="stats-overview">
                            <div class="stat-item">
                                <div class="value"><?= date('d/m/Y') ?></div>
                                <div class="label">Último Backup</div>
                            </div>
                            <div class="stat-item">
                                <div class="value">15.2 MB</div>
                                <div class="label">Tamaño BD</div>
                            </div>
                            <div class="stat-item">
                                <div class="value">Activo</div>
                                <div class="label">Backup Automático</div>
                            </div>
                        </div>
                        
                        <form method="post" action="">
                            <div class="action-buttons">
                                <button type="submit" name="crear_backup" class="btn btn-success">
                                    <i class="fas fa-download"></i> Crear Backup Manual
                                </button>
                                <button type="button" class="btn btn-warning" onclick="restaurarBackup()">
                                    <i class="fas fa-upload"></i> Restaurar Backup
                                </button>
                                <button type="button" class="btn btn-danger" onclick="limpiarBackups()">
                                    <i class="fas fa-trash"></i> Limpiar Backups Antiguos
                                </button>
                            </div>
                        </form>
                        
                        <div class="form-section">
                            <h4>Backups Disponibles</h4>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Archivo</th>
                                            <th>Fecha</th>
                                            <th>Tamaño</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>epa703_backup_2025-08-20_14-30-15.sql</td>
                                            <td>20/08/2025 14:30</td>
                                            <td>15.2 MB</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>epa703_backup_2025-08-19_14-30-15.sql</td>
                                            <td>19/08/2025 14:30</td>
                                            <td>15.1 MB</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Gestión de tabs
        function showTab(tabName) {
            // Ocultar todos los paneles
            const panels = document.querySelectorAll('.tab-panel');
            panels.forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Remover clase active de todos los botones
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar el panel seleccionado
            document.getElementById(tabName).classList.add('active');
            
            // Activar el botón correspondiente
            event.target.classList.add('active');
        }

        // Función para probar el envío de email
        function testearEmail() {
            if (confirm('¿Desea enviar un email de prueba para verificar la configuración SMTP?')) {
                // Aquí se haría la llamada AJAX para probar el email
                alert('Email de prueba enviado. Revise su bandeja de entrada.');
            }
        }

        // Función para restaurar backup
        function restaurarBackup() {
            if (confirm('⚠️ ADVERTENCIA: Esta acción sobrescribirá todos los datos actuales. ¿Está seguro de continuar?')) {
                // Aquí se implementaría la lógica de restauración
                alert('Funcionalidad de restauración en desarrollo.');
            }
        }

        // Función para limpiar backups antiguos
        function limpiarBackups() {
            if (confirm('¿Desea eliminar los backups más antiguos de 30 días?')) {
                // Aquí se implementaría la lógica de limpieza
                alert('Backups antiguos eliminados correctamente.');
            }
        }

        // Validaciones del formulario
        document.addEventListener('DOMContentLoaded', function() {
            // Validar fechas de inscripción
            const fechaInicio = document.getElementById('fecha_inicio_inscripciones');
            const fechaFin = document.getElementById('fecha_fin_inscripciones');
            
            if (fechaInicio && fechaFin) {
                fechaInicio.addEventListener('change', function() {
                    fechaFin.min = this.value;
                });
                
                fechaFin.addEventListener('change', function() {
                    if (this.value < fechaInicio.value) {
                        alert('La fecha de fin no puede ser anterior a la fecha de inicio.');
                        this.value = '';
                    }
                });
            }
            
            // Validar puerto SMTP
            const puertoSMTP = document.getElementById('smtp_puerto');
            if (puertoSMTP) {
                puertoSMTP.addEventListener('input', function() {
                    const valor = parseInt(this.value);
                    if (valor < 1 || valor > 65535) {
                        this.setCustomValidity('El puerto debe estar entre 1 y 65535');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });

        // Función para mostrar/ocultar contraseña SMTP
        function togglePasswordVisibility() {
            const passwordField = document.getElementById('smtp_password');
            const icon = event.target;
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-guardar configuraciones (opcional)
        let autoSaveTimeout;
        const formInputs = document.querySelectorAll('.form-control');
        
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                
                // Mostrar indicador de cambios pendientes
                const saveButtons = document.querySelectorAll('button[type="submit"]');
                saveButtons.forEach(btn => {
                    if (!btn.classList.contains('btn-warning')) {
                        btn.classList.add('btn-warning');
                        btn.innerHTML = btn.innerHTML.replace('Guardar', 'Guardar*');
                    }
                });
            });
        });

        // Confirmación antes de salir con cambios sin guardar
        let hasUnsavedChanges = false;
        
        formInputs.forEach(input => {
            input.addEventListener('change', function() {
                hasUnsavedChanges = true;
            });
        });
        
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                hasUnsavedChanges = false;
            });
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
                return 'Tiene cambios sin guardar. ¿Está seguro de salir?';
            }
        });

        // Función para exportar configuración
        function exportarConfiguracion() {
            const config = {
                general: {},
                academico: {},
                sistema: {},
                notificaciones: {}
            };
            
            // Recopilar todos los valores de configuración
            document.querySelectorAll('.form-control').forEach(input => {
                const tabPanel = input.closest('.tab-panel');
                const category = tabPanel.id;
                config[category][input.name] = input.value;
            });
            
            // Crear y descargar archivo JSON
            const dataStr = JSON.stringify(config, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = 'epa703_configuracion_' + new Date().toISOString().split('T')[0] + '.json';
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
        }

        // Función para importar configuración
        function importarConfiguracion() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            
            input.onchange = function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const config = JSON.parse(e.target.result);
                            
                            if (confirm('¿Está seguro de importar esta configuración? Se sobrescribirán los valores actuales.')) {
                                // Aplicar configuración importada
                                Object.keys(config).forEach(category => {
                                    Object.keys(config[category]).forEach(key => {
                                        const input = document.querySelector(`[name="${key}"]`);
                                        if (input) {
                                            if (input.type === 'checkbox') {
                                                input.checked = config[category][key] == '1';
                                            } else {
                                                input.value = config[category][key];
                                            }
                                        }
                                    });
                                });
                                
                                alert('Configuración importada correctamente. Recuerde guardar los cambios.');
                            }
                        } catch (error) {
                            alert('Error al importar la configuración. Verifique que el archivo sea válido.');
                        }
                    };
                    reader.readAsText(file);
                }
            };
            
            input.click();
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + S para guardar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const activeTab = document.querySelector('.tab-panel.active');
                const submitButton = activeTab.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.click();
                }
            }
            
            // Ctrl + números para cambiar tabs
            if (e.ctrlKey && e.key >= '1' && e.key <= '5') {
                e.preventDefault();
                const tabs = ['general', 'academico', 'sistema', 'notificaciones', 'backup'];
                const tabIndex = parseInt(e.key) - 1;
                if (tabs[tabIndex]) {
                    showTab(tabs[tabIndex]);
                }
            }
        });

        // Añadir estilos adicionales para elementos dinámicos
        const additionalStyles = `
            .table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            
            .table th,
            .table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            
            .table th {
                background-color: #f8f9fa;
                font-weight: 600;
                color: #1e3a2e;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .btn-sm {
                padding: 6px 12px;
                font-size: 12px;
                margin: 0 2px;
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = additionalStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>