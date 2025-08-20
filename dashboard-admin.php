<?php
/**
 * Dashboard Administrativo EPA 703
 * Archivo principal del panel de administración
 */

session_start();

// Verificar autenticación y permisos
if (!isset($_SESSION['logged_in']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: ../login.html');
    exit();
}

// Incluir configuración y funciones
require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    
    // Obtener estadísticas generales
    $stats = obtenerEstadisticasGenerales($pdo);
    $actividad_reciente = obtenerActividadReciente($pdo);
    $consultas_pendientes = obtenerConsultasPendientes($pdo);
    $usuarios_recientes = obtenerUsuariosRecientes($pdo);
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "Error al cargar datos del dashboard";
    
    // Datos por defecto en caso de error
    $stats = [
        'usuarios_por_tipo' => [],
        'consultas' => ['pendientes' => 0, 'hoy' => 0],
        'inscripciones' => ['pendientes' => 0, 'aprobadas' => 0],
        'academico' => ['cursos_activos' => 0, 'total_orientaciones' => 0],
        'estudiantes_por_estado' => ['activo' => 0, 'graduado' => 0]
    ];
    $actividad_reciente = [];
    $consultas_pendientes = [];
    $usuarios_recientes = [];
}

// Funciones auxiliares
function obtenerEstadisticasGenerales($pdo) {
    $stats = [];
    
    // Total de usuarios por tipo
    $stmt = $pdo->query("
        SELECT 
            tipo_usuario,
            COUNT(*) as total,
            COUNT(CASE WHEN activo = 1 THEN 1 END) as activos
        FROM usuarios 
        GROUP BY tipo_usuario
    ");
    $stats['usuarios_por_tipo'] = $stmt->fetchAll();
    
    // Estadísticas de consultas
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_consultas,
            COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as hoy
        FROM contactos
    ");
    $stats['consultas'] = $stmt->fetch();
    
    return $stats;
}

function obtenerActividadReciente($pdo) {
    $stmt = $pdo->prepare("
        SELECT 'usuario_registrado' as tipo, nombre, apellido, created_at 
        FROM usuarios 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'consulta_recibida' as tipo, nombre, email, created_at 
        FROM contactos 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function obtenerConsultasPendientes($pdo) {
    $stmt = $pdo->prepare("
        SELECT id, nombre, email, asunto, tipo_consulta, created_at,
               TIMESTAMPDIFF(HOUR, created_at, NOW()) as horas_pendiente
        FROM contactos 
        WHERE estado = 'pendiente'
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function obtenerUsuariosRecientes($pdo) {
    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, email, tipo_usuario, created_at
        FROM usuarios 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUserIcon($tipo) {
    switch($tipo) {
        case 'admin': return 'user-shield';
        case 'profesor': return 'chalkboard-teacher';
        case 'estudiante': return 'user-graduate';
        case 'secretario': return 'user-tie';
        default: return 'user';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - EPA 703</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/dashboard-styles.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar Navigation -->
    <nav id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h3>🎓 EPA 703</h3>
            <p>Panel Administrativo</p>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="user-info">
                <strong><?= htmlspecialchars($_SESSION['nombre'] ?? 'Admin') ?></strong>
                <small>Administrador</small>
            </div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="nav-item active">
                <a href="dashboard-admin.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="consultas.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Consultas</span>
                    <?php if (($stats['consultas']['pendientes'] ?? 0) > 0): ?>
                    <span class="badge bg-warning"><?= $stats['consultas']['pendientes'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="usuarios.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Usuarios</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="cursos.php" class="nav-link">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Cursos</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reportes.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="configuracion.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Configuración</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt"></i>
                Cerrar Sesión
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <button id="sidebar-toggle" class="btn btn-outline-secondary">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Dashboard Administrativo</h1>
            </div>
            <div class="header-right">
                <button onclick="refreshActivity()" class="btn btn-outline-primary">
                    <i class="fas fa-sync-alt"></i>
                    Actualizar
                </button>
                <div class="notification-badge">
                    <i class="fas fa-bell"></i>
                    <?php if (($stats['consultas']['pendientes'] ?? 0) > 0): ?>
                    <span class="badge"><?= $stats['consultas']['pendientes'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= $error ?>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= array_sum(array_column($stats['usuarios_por_tipo'], 'total')) ?></h3>
                        <p>Total Usuarios</p>
                        <small class="text-success">
                            <i class="fas fa-arrow-up"></i>
                            <?= array_sum(array_column($stats['usuarios_por_tipo'], 'activos')) ?> activos
                        </small>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <?php
                        $estudiantes = array_filter($stats['usuarios_por_tipo'], function($item) {
                            return $item['tipo_usuario'] === 'estudiante';
                        });
                        $total_estudiantes = !empty($estudiantes) ? reset($estudiantes)['total'] : 0;
                        ?>
                        <h3><?= $total_estudiantes ?></h3>
                        <p>Estudiantes</p>
                        <small class="text-info">Registrados</small>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-content">
                        <?php
                        $profesores = array_filter($stats['usuarios_por_tipo'], function($item) {
                            return $item['tipo_usuario'] === 'profesor';
                        });
                        $total_profesores = !empty($profesores) ? reset($profesores)['total'] : 0;
                        ?>
                        <h3><?= $total_profesores ?></h3>
                        <p>Profesores</p>
                        <small class="text-info">Activos</small>
                    </div>
                </div>

                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['consultas']['pendientes'] ?? 0 ?></h3>
                        <p>Consultas Pendientes</p>
                        <small class="text-warning">
                            <i class="fas fa-clock"></i>
                            Requieren atención
                        </small>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="dashboard-content">
                <div class="row">
                    <!-- Actividad Reciente -->
                    <div class="col-lg-8">
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h4><i class="fas fa-activity text-primary"></i> Actividad Reciente</h4>
                                <button onclick="refreshActivity()" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <div class="section-content">
                                <?php if (empty($actividad_reciente)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <p>No hay actividad reciente</p>
                                    </div>
                                <?php else: ?>
                                    <div class="activity-timeline">
                                        <?php foreach (array_slice($actividad_reciente, 0, 6) as $actividad): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker">
                                                <i class="fas fa-<?= $actividad['tipo'] === 'usuario_registrado' ? 'user-plus' : 'envelope' ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <p>
                                                    <?php if ($actividad['tipo'] === 'usuario_registrado'): ?>
                                                        <strong><?= htmlspecialchars($actividad['nombre'] . ' ' . $actividad['apellido']) ?></strong>
                                                        se registró como nuevo usuario
                                                    <?php else: ?>
                                                        <strong><?= htmlspecialchars($actividad['nombre']) ?></strong>
                                                        envió una nueva consulta
                                                    <?php endif; ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i>
                                                    <?= date('d/m/Y H:i', strtotime($actividad['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Lateral -->
                    <div class="col-lg-4">
                        <!-- Consultas Pendientes -->
                        <div class="dashboard-section mb-4">
                            <div class="section-header">
                                <h4><i class="fas fa-exclamation-circle text-warning"></i> Consultas Urgentes</h4>
                                <a href="consultas.php" class="btn btn-sm btn-outline-primary">Ver Todas</a>
                            </div>
                            <div class="section-content">
                                <?php if (empty($consultas_pendientes)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-check-circle"></i>
                                        <p>No hay consultas pendientes</p>
                                    </div>
                                <?php else: ?>
                                    <div class="consultas-list">
                                        <?php foreach (array_slice($consultas_pendientes, 0, 4) as $consulta): ?>
                                        <div class="consulta-item <?= $consulta['horas_pendiente'] > 24 ? 'urgent' : '' ?>">
                                            <div class="consulta-header">
                                                <strong><?= htmlspecialchars($consulta['nombre']) ?></strong>
                                                <span class="badge bg-<?= $consulta['tipo_consulta'] === 'inscripcion' ? 'primary' : 'secondary' ?>">
                                                    <?= ucfirst($consulta['tipo_consulta']) ?>
                                                </span>
                                            </div>
                                            <div class="consulta-content">
                                                <p><?= htmlspecialchars(substr($consulta['asunto'], 0, 60)) ?>...</p>
                                            </div>
                                            <div class="consulta-meta">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i>
                                                    Hace <?= $consulta['horas_pendiente'] ?> horas
                                                </small>
                                                <?php if ($consulta['horas_pendiente'] > 24): ?>
                                                    <span class="badge bg-danger ms-2">Urgente</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Usuarios Recientes -->
                        <div class="dashboard-section">
                            <div class="section-header">
                                <h4><i class="fas fa-user-plus text-success"></i> Usuarios Recientes</h4>
                                <a href="usuarios.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                            </div>
                            <div class="section-content">
                                <?php if (empty($usuarios_recientes)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>No hay usuarios recientes</p>
                                    </div>
                                <?php else: ?>
                                    <div class="usuarios-list">
                                        <?php foreach (array_slice($usuarios_recientes, 0, 4) as $usuario): ?>
                                        <div class="usuario-item">
                                            <div class="usuario-avatar">
                                                <i class="fas fa-<?= getUserIcon($usuario['tipo_usuario']) ?>"></i>
                                            </div>
                                            <div class="usuario-info">
                                                <strong><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></strong>
                                                <small><?= ucfirst($usuario['tipo_usuario']) ?></small>
                                                <div class="text-muted">
                                                    <small><?= date('d/m/Y', strtotime($usuario['created_at'])) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/dashboard-scripts.js"></script>
    
    <script>
        // Inicializar dashboard cuando cargue la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎓 Dashboard EPA 703 cargado correctamente');
            
            // Auto-actualizar cada 5 minutos
            setInterval(function() {
                refreshActivity();
            }, 300000);
        });
    </script>
</body>
</html>