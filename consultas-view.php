<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Consultas - EPA 703</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/consultas-styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="#">🎓 EPA 703 - Panel Admin</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    👤 <?= htmlspecialchars($_SESSION['nombre'] ?? 'Admin') ?>
                </span>
                <a href="../api/logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <strong>¡Éxito!</strong> Operación completada correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title">📋 Total Consultas</h5>
                        <h3 class="text-primary"><?= count($consultas) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title">⏳ Pendientes</h5>
                        <h3 class="text-warning"><?= $stats['por_estado']['pendiente'] ?? 0 ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title">📝 Inscripciones</h5>
                        <h3 class="text-info"><?= $stats['inscripciones_pendientes'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h5 class="card-title">📅 Hoy</h5>
                        <h3 class="text-success"><?= $stats['consultas_hoy'] ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="leido" <?= $filtro_estado === 'leido' ? 'selected' : '' ?>>Leído</option>
                            <option value="respondido" <?= $filtro_estado === 'respondido' ? 'selected' : '' ?>>Respondido</option>
                            <option value="cerrado" <?= $filtro_estado === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="inscripcion" <?= $filtro_tipo === 'inscripcion' ? 'selected' : '' ?>>Inscripción</option>
                            <option value="ciclos" <?= $filtro_tipo === 'ciclos' ? 'selected' : '' ?>>Ciclos</option>
                            <option value="horarios" <?= $filtro_tipo === 'horarios' ? 'selected' : '' ?>>Horarios</option>
                            <option value="requisitos" <?= $filtro_tipo === 'requisitos' ? 'selected' : '' ?>>Requisitos</option>
                            <option value="general" <?= $filtro_tipo === 'general' ? 'selected' : '' ?>>General</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($filtro_fecha) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="consultas.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de consultas -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Consultas (<?= count($consultas) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($consultas)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No hay consultas que mostrar</h5>
                        <p class="text-muted">Intenta cambiar los filtros para ver más resultados.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Inscripción</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultas as $consulta): ?>
                            <tr class="consulta-row">
                                <td><?= $consulta['id'] ?></td>
                                <td>
                                    <small><?= date('d/m/Y H:i', strtotime($consulta['created_at'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($consulta['nombre']) ?></strong>
                                    <?php if ($consulta['telefono']): ?>
                                        <br><small class="text-muted">📞 <?= htmlspecialchars($consulta['telefono']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($consulta['edad'] > 0): ?>
                                        <br><small class="text-muted">🎂 <?= $consulta['edad'] ?> años</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($consulta['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($consulta['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $tipos_consulta = [
                                        'inscripcion' => ['🎓 Inscripción', 'primary'],
                                        'ciclos' => ['📚 Ciclos', 'info'],
                                        'horarios' => ['🕒 Horarios', 'warning'],
                                        'requisitos' => ['📋 Requisitos', 'secondary'],
                                        'certificados' => ['📜 Certificados', 'success'],
                                        'becas' => ['💰 Becas', 'dark'],
                                        'general' => ['💬 General', 'light']
                                    ];
                                    $tipo_info = $tipos_consulta[$consulta['tipo_consulta']] ?? ['❓ Otro', 'secondary'];
                                    ?>
                                    <span class="badge bg-<?= $tipo_info[1] ?> estado-badge">
                                        <?= $tipo_info[0] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $estados = [
                                        'pendiente' => ['⏳ Pendiente', 'warning'],
                                        'leido' => ['👁️ Leído', 'info'],
                                        'respondido' => ['✅ Respondido', 'success'],
                                        'cerrado' => ['🔒 Cerrado', 'secondary']
                                    ];
                                    $estado_info = $estados[$consulta['estado']] ?? ['❓ Desconocido', 'secondary'];
                                    ?>
                                    <span class="badge bg-<?= $estado_info[1] ?> estado-badge">
                                        <?= $estado_info[0] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($consulta['inscripcion_id']): ?>
                                        <?php
                                        $estados_inscripcion = [
                                            'pendiente' => ['⏳ Pendiente', 'warning'],
                                            'aprobada' => ['✅ Aprobada', 'success'],
                                            'rechazada' => ['❌ Rechazada', 'danger']
                                        ];
                                        $inscripcion_info = $estados_inscripcion[$consulta['estado_inscripcion']] ?? ['❓ Sin estado', 'secondary'];
                                        ?>
                                        <span class="badge bg-<?= $inscripcion_info[1] ?> estado-badge">
                                            <?= $inscripcion_info[0] ?>
                                        </span>
                                        <?php if ($consulta['estado_inscripcion'] === 'aprobada' && $consulta['usuario_legajo']): ?>
                                            <br><small class="text-success">📝 Legajo: <?= $consulta['usuario_legajo'] ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm" role="group">
                                        <!-- Ver detalles -->
                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalDetalle<?= $consulta['id'] ?>">
                                            <i class="fas fa-eye"></i> Ver
                                        </button>
                                        
                                        <?php if ($consulta['tipo_consulta'] === 'inscripcion' && !$consulta['inscripcion_id']): ?>
                                        <!-- Procesar inscripción -->
                                        <button type="button" class="btn btn-outline-success btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalProcesar<?= $consulta['id'] ?>">
                                            <i class="fas fa-user-plus"></i> Procesar
                                        </button>
                                        <?php elseif ($consulta['tipo_consulta'] === 'inscripcion' && $consulta['estado_inscripcion'] === 'pendiente'): ?>
                                        <!-- Revisar inscripción pendiente -->
                                        <button type="button" class="btn btn-outline-warning btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalProcesar<?= $consulta['id'] ?>">
                                            <i class="fas fa-edit"></i> Revisar
                                        </button>
                                        <?php endif; ?>
                                        
                                        <!-- Cambiar estado -->
                                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEstado<?= $consulta['id'] ?>">
                                            <i class="fas fa-edit"></i> Estado
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modales para cada consulta -->
    <?php foreach ($consultas as $consulta): ?>
    
    <!-- Modal de detalles -->
    <div class="modal fade" id="modalDetalle<?= $consulta['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle"></i> Detalles de la Consulta #<?= $consulta['id'] ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user"></i> Información Personal</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Nombre:</strong></td><td><?= htmlspecialchars($consulta['nombre']) ?></td></tr>
                                <tr><td><strong>Email:</strong></td><td><?= htmlspecialchars($consulta['email']) ?></td></tr>
                                <?php if ($consulta['telefono']): ?>
                                <tr><td><strong>Teléfono:</strong></td><td><?= htmlspecialchars($consulta['telefono']) ?></td></tr>
                                <?php endif; ?>
                                <?php if ($consulta['edad'] > 0): ?>
                                <tr><td><strong>Edad:</strong></td><td><?= $consulta['edad'] ?> años</td></tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-clipboard"></i> Información de la Consulta</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Tipo:</strong></td><td><?= ucfirst($consulta['tipo_consulta']) ?></td></tr>
                                <tr><td><strong>Estado:</strong></td><td><?= ucfirst($consulta['estado']) ?></td></tr>
                                <tr><td><strong>Fecha:</strong></td><td><?= date('d/m/Y H:i:s', strtotime($consulta['created_at'])) ?></td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <h6><i class="fas fa-message"></i> Mensaje</h6>
                    <div class="alert alert-light">
                        <?= nl2br(htmlspecialchars($consulta['mensaje'])) ?>
                    </div>
                    
                    <?php if ($consulta['inscripcion_id']): ?>
                    <h6><i class="fas fa-graduation-cap"></i> Estado de Inscripción</h6>
                    <div class="alert alert-info">
                        <strong>Estado:</strong> <?= ucfirst($consulta['estado_inscripcion']) ?><br>
                        <?php if ($consulta['fecha_procesamiento']): ?>
                        <strong>Procesado:</strong> <?= date('d/m/Y H:i:s', strtotime($consulta['fecha_procesamiento'])) ?><br>
                        <?php endif; ?>
                        <?php if ($consulta['curso_asignado_nombre']): ?>
                        <strong>Curso:</strong> <?= htmlspecialchars($consulta['curso_asignado_nombre']) ?> (<?= ucfirst($consulta['curso_turno']) ?>)<br>
                        <?php endif; ?>
                        <?php if ($consulta['usuario_legajo']): ?>
                        <strong>Legajo asignado:</strong> <?= $consulta['usuario_legajo'] ?><br>
                        <?php endif; ?>
                        <?php if ($consulta['admin_observaciones']): ?>
                        <strong>Observaciones:</strong> <?= htmlspecialchars($consulta['admin_observaciones']) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de procesamiento de inscripción -->
    <?php if ($consulta['tipo_consulta'] === 'inscripcion'): ?>
    <div class="modal fade" id="modalProcesar<?= $consulta['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> Procesar Inscripción #<?= $consulta['id'] ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Estudiante:</strong> <?= htmlspecialchars($consulta['nombre']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($consulta['email']) ?></p>
                    
                    <div class="row">
                        <div class="col-12">
                            <!-- Formulario para aprobar -->
                            <form method="POST" id="formAprobar<?= $consulta['id'] ?>">
                                <input type="hidden" name="action" value="aprobar_inscripcion">
                                <input type="hidden" name="contacto_id" value="<?= $consulta['id'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Asignar a Curso:</label>
                                    <select name="curso_id" class="form-select" required>
                                        <option value="">Seleccionar curso...</option>
                                        <?php foreach ($cursos_disponibles as $curso): ?>
                                        <option value="<?= $curso['id'] ?>" 
                                                <?= ($consulta['curso_asignado_id'] == $curso['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($curso['nombre']) ?> - 
                                            <?= $curso['anio'] ?>° <?= $curso['division'] ?> - 
                                            <?= ucfirst($curso['turno']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                            
                            <!-- Formulario para rechazar -->
                            <form method="POST" id="formRechazar<?= $consulta['id'] ?>" style="display: none;">
                                <input type="hidden" name="action" value="rechazar_inscripcion">
                                <input type="hidden" name="contacto_id" value="<?= $consulta['id'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Motivo del rechazo:</label>
                                    <textarea name="motivo" class="form-control" rows="3" 
                                              placeholder="Explica el motivo del rechazo..." required></textarea>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="mostrarFormRechazo(<?= $consulta['id'] ?>)">
                        <i class="fas fa-times"></i> Rechazar
                    </button>
                    <button type="submit" form="formAprobar<?= $consulta['id'] ?>" class="btn btn-success">
                        <i class="fas fa-check"></i> Aprobar e Inscribir
                    </button>
                    <button type="submit" form="formRechazar<?= $consulta['id'] ?>" 
                            class="btn btn-danger" style="display: none;" id="btnConfirmarRechazo<?= $consulta['id'] ?>">
                        <i class="fas fa-times"></i> Confirmar Rechazo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal cambio de estado -->
    <div class="modal fade" id="modalEstado<?= $consulta['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Cambiar Estado #<?= $consulta['id'] ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cambiar_estado">
                        <input type="hidden" name="contacto_id" value="<?= $consulta['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Nuevo Estado:</label>
                            <select name="nuevo_estado" class="form-select" required>
                                <option value="pendiente" <?= $consulta['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="leido" <?= $consulta['estado'] === 'leido' ? 'selected' : '' ?>>Leído</option>
                                <option value="respondido" <?= $consulta['estado'] === 'respondido' ? 'selected' : '' ?>>Respondido</option>
                                <option value="cerrado" <?= $consulta['estado'] === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notas (opcional):</label>
                            <textarea name="notas" class="form-control" rows="2" 
                                      placeholder="Agrega notas sobre este cambio de estado..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Actualizar Estado
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php endforeach; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/consultas-scrpts.js"></script>
</body>
</html>