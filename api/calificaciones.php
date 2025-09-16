<?php
// calificaciones.php
session_start();

// Verificamos si el usuario inició sesión
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit;
}

// Conexión a MySQL
$conexion = new mysqli("localhost","root","","escuela");
if($conexion->connect_error){
    die("Error de conexión: ".$conexion->connect_error);
}

// Consulta de calificaciones del usuario
$usuario_id = $_SESSION['usuario_id'];
$sql = "SELECT m.nombre AS materia, c.nota, c.fecha, c.comentario
        FROM calificaciones c
        INNER JOIN materias m ON c.materia_id = m.id
        WHERE c.usuario_id = $usuario_id
        ORDER BY m.nombre, c.fecha DESC";

$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Calificaciones</title>
    <link rel="stylesheet" href="css/calificaciones.css">
</head>
<body>
<section class="grades-section">
    <div class="grades-header">
        <h2>Mis Calificaciones</h2>
        <div class="grades-sub">Notas y comentarios de tus materias</div>
    </div>

    <div class="grades-table-wrap">
        <table class="grades-table">
            <thead>
                <tr>
                    <th>Materia</th>
                    <th>Nota</th>
                    <th>Fecha</th>
                    <th>Comentario</th>
                </tr>
            </thead>
            <tbody>
            <?php if($resultado->num_rows > 0): ?>
                <?php while($fila = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['materia']) ?></td>
                        <td class="nota"><?= $fila['nota'] ?></td>
                        <td><?= $fila['fecha'] ?></td>
                        <td><?= htmlspecialchars($fila['comentario']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No hay calificaciones cargadas.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</body>
</html>
<?php $conexion->close(); ?>
