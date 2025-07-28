<?php
/**
 * Navegación común para el panel administrativo EPA 703
 */

// Verificar sesión admin
if (!isset($_SESSION['logged_in']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: ../login.html');
    exit();
}

// Obtener datos de notificaciones para badges
try {
    require_once '../config/database-config.php';
    $pdo = getDBConnection();
    
    // Contar consultas pendientes
    $stmt = $pdo->query("SELECT COUNT(*) FROM contactos WHERE estado = 'pendiente'");
    $consultas_pendientes = $stmt->fetchColumn();
    
    // Contar inscripciones pendientes
    $stmt = $pdo->query("SELECT COUNT(*) FROM inscripciones WHERE estado_inscripcion = 'pendiente'");
    $inscripciones_pendientes = $stmt->fetchColumn();
    
    // Contar usuarios nuevos (últimas 24h)
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $usuarios_nuevos = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $consultas_pendientes = 0;
    $inscripciones_pendientes = 0;
    $usuarios_nuevos = 0;
}

// Determinar página activa
$current_page = basename($_SERVER['PHP_SELF']);
$menu_items = [
    'dashboard.php' => ['icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard', 'badge' => null],
    'consultas.php' => ['icon' => 'fas fa-envelope', 'title' => 'Consultas', 'badge' => $consultas_pendientes > 0 ? $consultas_pendientes : null],
    'usuarios.php' => ['icon' => 'fas fa-users', 'title' => 'Usuarios', 'badge' => $usuarios_nuevos > 0 ? $usuarios_nuevos : null],
    'inscripciones.php' => ['icon' => 'fas fa-user-plus', 'title' => 'Inscripciones', 'badge' => $inscripciones_pendientes > 0 ? $inscripciones_pendientes : null],
    'cursos.php' => ['icon' => 'fas fa-graduation-cap', 'title' => 'Cursos', 'badge' => null],
    'reportes.php' => ['icon' => 'fas fa-chart-bar', 'title' => 'Reportes', 'badge' => null],
    'configuracion.php' => ['icon' => 'fas fa-cog', 'title' => 'Configuración', 'badge' => null],
];
?>

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
        <?php foreach ($menu_items as $page => $item): ?>
        <li class="nav-item">
            <a href="<?= $page ?>" class="nav-link <?= $current_page === $page ? 'active' : '' ?>">
                <i class="<?= $item['icon'] ?>"></i>
                <span><?= $item['title'] ?></span>
                <?php if ($item['badge']): ?>
                <span class="badge bg-warning"><?= $item['badge'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
        
        <li class="nav-divider"></li>
        
        <li class="nav-item">
            <a href="../index.html" class="nav-link" target="_blank">
                <i class="fas fa-external-link-alt"></i>
                <span>Ver Sitio Web</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="system-status">
            <div class="status-item">
                <span class="status-indicator online"></span>
                <small>Sistema Online</small>
            </div>
            <div class="status-item">
                <i class="fas fa-clock"></i>
                <small><?= date('H:i') ?></small>
            </div>
        </div>
        
        <a href="../api/logout.php" class="btn btn-outline-light btn-sm">
            <i class="fas fa-sign-out-alt"></i>
            Cerrar Sesión
        </a>
    </div>
</nav>

<!-- CSS adicional para la navegación -->
<style>
.nav-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    margin: 1rem 0;
}

.system-status {
    padding: 1rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
}

.status-indicator.online {
    background: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
}

.sidebar-footer .btn {
    width: 100%;
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
}
</style>