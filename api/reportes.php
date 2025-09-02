<?php
session_start();
require_once '../config/database-config.php';

// Verificar sesión admin
if (!isset($_SESSION['logged_in']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header('Location: ../login.html');
    exit();
}

$pdo = getDBConnection();

// Función para obtener estadísticas generales
function getEstadisticasGenerales($pdo) {
    $stats = [];
    
    // Total estudiantes
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'estudiante'");
    $stats['total_estudiantes'] = $stmt->fetchColumn();
    
    // Estudiantes activos (con actividad en últimos 30 días)
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'estudiante' AND last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['estudiantes_activos'] = $stmt->fetchColumn();
    
    // Total inscripciones
    $stmt = $pdo->query("SELECT COUNT(*) FROM inscripciones");
    $stats['total_inscripciones'] = $stmt->fetchColumn();
    
    // Inscripciones pendientes
    $stmt = $pdo->query("SELECT COUNT(*) FROM inscripciones WHERE estado_inscripcion = 'pendiente'");
    $stats['inscripciones_pendientes'] = $stmt->fetchColumn();
    
    // Consultas sin responder
    $stmt = $pdo->query("SELECT COUNT(*) FROM contactos WHERE estado = 'pendiente'");
    $stats['consultas_pendientes'] = $stmt->fetchColumn();
    
    // Total cursos
    $stmt = $pdo->query("SELECT COUNT(*) FROM materias WHERE activa = 1");
    $stats['total_cursos'] = $stmt->fetchColumn();
    
    return $stats;
}

// Función para obtener inscripciones por mes
function getInscripcionesPorMes($pdo, $anio = null) {
    if (!$anio) $anio = date('Y');
    
    $sql = "SELECT 
                MONTH(fecha_inscripcion) as mes,
                MONTHNAME(fecha_inscripcion) as nombre_mes,
                COUNT(*) as cantidad,
                SUM(CASE WHEN estado_inscripcion = 'aprobada' THEN 1 ELSE 0 END) as aprobadas,
                SUM(CASE WHEN estado_inscripcion = 'rechazada' THEN 1 ELSE 0 END) as rechazadas,
                SUM(CASE WHEN estado_inscripcion = 'pendiente' THEN 1 ELSE 0 END) as pendientes
            FROM inscripciones 
            WHERE YEAR(fecha_inscripcion) = :anio
            GROUP BY MONTH(fecha_inscripcion)
            ORDER BY mes";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['anio' => $anio]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener top consultas por categoría
function getConsultasPorCategoria($pdo) {
    $sql = "SELECT 
                asunto,
                COUNT(*) as cantidad,
                AVG(CASE WHEN estado = 'resuelta' THEN 1 ELSE 0 END) * 100 as porcentaje_resueltas
            FROM contactos 
            GROUP BY asunto
            ORDER BY cantidad DESC
            LIMIT 10";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener estudiantes por orientación
function getEstudiantesPorOrientacion($pdo) {
    $sql = "SELECT 
                o.nombre as orientacion,
                COUNT(i.id) as cantidad
            FROM orientaciones o
            LEFT JOIN inscripciones i ON o.id = i.orientacion_id 
                AND i.estado_inscripcion = 'aprobada'
            WHERE o.activa = 1
            GROUP BY o.id, o.nombre
            ORDER BY cantidad DESC";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar solicitud de reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_reporte'])) {
    $tipo_reporte = $_POST['tipo_reporte'];
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin = $_POST['fecha_fin'] ?? null;
    $formato = $_POST['formato'] ?? 'html';
    
    try {
        $reporte_generado = null;
        
        switch ($tipo_reporte) {
            case 'inscripciones':
                $sql = "SELECT 
                            i.id,
                            i.nombre,
                            i.apellido,
                            i.email,
                            i.telefono,
                            i.fecha_inscripcion,
                            i.estado_inscripcion,
                            o.nombre as orientacion
                        FROM inscripciones i
                        LEFT JOIN orientaciones o ON i.orientacion_id = o.id
                        WHERE 1=1";
                
                $params = [];
                if ($fecha_inicio) {
                    $sql .= " AND i.fecha_inscripcion >= :fecha_inicio";
                    $params['fecha_inicio'] = $fecha_inicio;
                }
                if ($fecha_fin) {
                    $sql .= " AND i.fecha_inscripcion <= :fecha_fin";
                    $params['fecha_fin'] = $fecha_fin;
                }
                $sql .= " ORDER BY i.fecha_inscripcion DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reporte_generado = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'estudiantes':
                $sql = "SELECT 
                            u.id,
                            u.nombre,
                            u.apellido,
                            u.email,
                            u.telefono,
                            u.created_at as fecha_registro,
                            u.last_activity,
                            CASE 
                                WHEN u.last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'Activo'
                                ELSE 'Inactivo'
                            END as estado
                        FROM usuarios u
                        WHERE u.tipo_usuario = 'estudiante'";
                
                $params = [];
                if ($fecha_inicio) {
                    $sql .= " AND u.created_at >= :fecha_inicio";
                    $params['fecha_inicio'] = $fecha_inicio;
                }
                if ($fecha_fin) {
                    $sql .= " AND u.created_at <= :fecha_fin";
                    $params['fecha_fin'] = $fecha_fin;
                }
                $sql .= " ORDER BY u.created_at DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reporte_generado = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'consultas':
                $sql = "SELECT 
                            c.id,
                            c.nombre,
                            c.email,
                            c.asunto,
                            c.fecha_contacto,
                            c.estado
                        FROM contactos c
                        WHERE 1=1";
                
                $params = [];
                if ($fecha_inicio) {
                    $sql .= " AND c.fecha_contacto >= :fecha_inicio";
                    $params['fecha_inicio'] = $fecha_inicio;
                }
                if ($fecha_fin) {
                    $sql .= " AND c.fecha_contacto <= :fecha_fin";
                    $params['fecha_fin'] = $fecha_fin;
                }
                $sql .= " ORDER BY c.fecha_contacto DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reporte_generado = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
        
        if ($formato === 'excel' && $reporte_generado) {
            // Generar archivo Excel
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="reporte_' . $tipo_reporte . '_' . date('Y-m-d') . '.xls"');
            
            echo '<table border="1">';
            if (!empty($reporte_generado)) {
                // Encabezados
                echo '<tr>';
                foreach (array_keys($reporte_generado[0]) as $header) {
                    echo '<th>' . htmlspecialchars($header) . '</th>';
                }
                echo '</tr>';
                
                // Datos
                foreach ($reporte_generado as $fila) {
                    echo '<tr>';
                    foreach ($fila as $valor) {
                        echo '<td>' . htmlspecialchars($valor) . '</td>';
                    }
                    echo '</tr>';
                }
            }
            echo '</table>';
            exit();
        }
        
        $mensaje_exito = "Reporte de " . ucfirst($tipo_reporte) . " generado exitosamente (" . count($reporte_generado ?? []) . " registros)";
        
    } catch (Exception $e) {
        $mensaje_error = "Error al generar el reporte: " . $e->getMessage();
    }
}

$stats = getEstadisticasGenerales($pdo);
$inscripciones_mes = getInscripcionesPorMes($pdo);
$consultas_categoria = getConsultasPorCategoria($pdo);
$estudiantes_orientacion = getEstudiantesPorOrientacion($pdo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - EPA 703</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css" rel="stylesheet">
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
            color: #4ecdc4;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #1e3a2e;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .report-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section-title {
            color: #1e3a2e;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4ecdc4;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #4ecdc4;
            color: white;
        }

        .btn-primary:hover {
            background: #45b7aa;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }

        .table-responsive {
            overflow-x: auto;
        }

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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 5px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
                    <a href="reportes.php" class="nav-link active">
                        <i class="fas fa-chart-bar"></i>
                        Reportes
                    </a>
                </li>
                <li class="nav-item">
                    <a href="configuracion.php" class="nav-link">
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
                <h1><i class="fas fa-chart-bar"></i> Reportes y Estadísticas</h1>
                <p>Análisis detallado de la información del sistema EPA 703</p>
            </div>

            <?php if (isset($mensaje_exito)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $mensaje_exito ?>
            </div>
            <?php endif; ?>

            <?php if (isset($mensaje_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= $mensaje_error ?>
            </div>
            <?php endif; ?>

            <?php if (isset($reporte_generado) && !empty($reporte_generado)): ?>
            <!-- Mostrar Reporte Generado -->
            <div class="report-section">
                <h2 class="section-title">Resultado del Reporte - <?= ucfirst($tipo_reporte) ?></h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <?php foreach (array_keys($reporte_generado[0]) as $header): ?>
                                <th><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $header))) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reporte_generado as $fila): ?>
                            <tr>
                                <?php foreach ($fila as $valor): ?>
                                <td><?= htmlspecialchars($valor ?? '') ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="action-buttons">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="tipo_reporte" value="<?= $tipo_reporte ?>">
                        <input type="hidden" name="fecha_inicio" value="<?= $fecha_inicio ?>">
                        <input type="hidden" name="fecha_fin" value="<?= $fecha_fin ?>">
                        <input type="hidden" name="formato" value="excel">
                        <button type="submit" name="generar_reporte" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Descargar Excel
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Estadísticas Generales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_estudiantes']) ?></div>
                    <div class="stat-label">Total Estudiantes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['estudiantes_activos']) ?></div>
                    <div class="stat-label">Estudiantes Activos</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['total_inscripciones']) ?></div>
                    <div class="stat-label">Total Inscripciones</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['inscripciones_pendientes']) ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
            </div>

            <!-- Generador de Reportes -->
            <div class="report-section">
                <h2 class="section-title">Generar Reporte Personalizado</h2>
                
                <form method="post" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tipo_reporte">Tipo de Reporte</label>
                            <select name="tipo_reporte" id="tipo_reporte" class="form-control" required>
                                <option value="">Seleccionar tipo...</option>
                                <option value="inscripciones">Inscripciones por Período</option>
                                <option value="estudiantes">Listado de Estudiantes</option>
                                <option value="consultas">Consultas Recibidas</option>
                                <option value="estadisticas">Estadísticas Generales</option>
                                <option value="orientaciones">Estudiantes por Orientación</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="formato">Formato de Salida</label>
                            <select name="formato" id="formato" class="form-control">
                                <option value="html">Ver en Pantalla</option>
                                <option value="pdf">Descargar PDF</option>
                                <option value="excel">Descargar Excel</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha Inicio</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_fin">Fecha Fin</label>
                            <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
                        </div>
                    </div>
                    
                    <button type="submit" name="generar_reporte" class="btn btn-primary">
                        <i class="fas fa-download"></i> Generar Reporte
                    </button>
                </form>
            </div>

            <!-- Gráfico de Inscripciones por Mes -->
            <div class="report-section">
                <h2 class="section-title">Inscripciones por Mes - <?= date('Y') ?></h2>
                <div class="chart-container">
                    <canvas id="inscripcionesChart"></canvas>
                </div>
            </div>

            <!-- Tabla de Consultas por Categoría -->
            <div class="report-section">
                <h2 class="section-title">Top Consultas por Categoría</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Asunto</th>
                                <th>Cantidad</th>
                                <th>% Resueltas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultas_categoria as $consulta): ?>
                            <tr>
                                <td><?= htmlspecialchars($consulta['asunto']) ?></td>
                                <td><?= number_format($consulta['cantidad']) ?></td>
                                <td><?= number_format($consulta['porcentaje_resueltas'], 1) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gráfico de Estudiantes por Orientación -->
            <div class="report-section">
                <h2 class="section-title">Estudiantes por Orientación</h2>
                <div class="chart-container">
                    <canvas id="orientacionesChart"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Datos para el gráfico de inscripciones
        const inscripcionesData = {
            labels: [<?php 
                $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                         'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                echo "'" . implode("','", $meses) . "'"; 
            ?>],
            datasets: [{
                label: 'Inscripciones',
                data: [<?php 
                    $data = array_fill(0, 12, 0);
                    foreach ($inscripciones_mes as $mes) {
                        $data[$mes['mes'] - 1] = $mes['cantidad'];
                    }
                    echo implode(',', $data);
                ?>],
                backgroundColor: 'rgba(78, 205, 196, 0.2)',
                borderColor: 'rgba(78, 205, 196, 1)',
                borderWidth: 2,
                fill: true
            }]
        };

        // Configurar gráfico de inscripciones
        const ctxInscripciones = document.getElementById('inscripcionesChart').getContext('2d');
        new Chart(ctxInscripciones, {
            type: 'line',
            data: inscripcionesData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Datos para el gráfico de orientaciones
        const orientacionesData = {
            labels: [<?php 
                $nombres = array_map(function($o) { return "'" . $o['orientacion'] . "'"; }, $estudiantes_orientacion);
                echo implode(',', $nombres);
            ?>],
            datasets: [{
                label: 'Estudiantes',
                data: [<?php 
                    $cantidades = array_map(function($o) { return $o['cantidad']; }, $estudiantes_orientacion);
                    echo implode(',', $cantidades);
                ?>],
                backgroundColor: [
                    'rgba(78, 205, 196, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 99, 132, 0.8)'
                ]
            }]
        };

        // Configurar gráfico de orientaciones
        const ctxOrientaciones = document.getElementById('orientacionesChart').getContext('2d');
        new Chart(ctxOrientaciones, {
            type: 'doughnut',
            data: orientacionesData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Establecer fecha de hoy como máximo
        document.getElementById('fecha_inicio').max = new Date().toISOString().split('T')[0];
        document.getElementById('fecha_fin').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>