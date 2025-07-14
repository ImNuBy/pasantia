<?php

require_once 'config/config.php';
require_once 'classes/Session.php';

// Verificar si el usuario está logueado
if (!Session::isLoggedIn()) {
    header('Location: login.html');
    exit;
}

// Obtener datos del usuario actual
$currentUser = Session::getUserData();

// Función para verificar permisos
function requireRole($requiredRoles) {
    global $currentUser;
    
    if (!is_array($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }
    
    $userRoleLevel = ROLES[$currentUser['tipo_usuario']] ?? 0;
    $hasPermission = false;
    
    foreach ($requiredRoles as $role) {
        if ($userRoleLevel >= ROLES[$role]) {
            $hasPermission = true;
            break;
        }
    }
    
    if (!$hasPermission) {
        header('HTTP/1.0 403 Forbidden');
        echo "No tienes permisos para acceder a esta página.";
        exit;
    }
}
?>