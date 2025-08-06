<?php
/**
 * Sistema de Logout EPA 703
 * Cierra la sesión y redirige al login
 */

session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, también hay que borrar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Log del logout (opcional)
error_log("Logout realizado - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - Time: " . date('Y-m-d H:i:s'));

// Limpiar cualquier caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirigir al login con mensaje
header('Location: login.html?logout=success');
exit();
?>