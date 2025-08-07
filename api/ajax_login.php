<?php
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $conn = new mysqli('localhost', 'root', '', 'hotel_rivo');

    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos");
    }

    // Get form data from POST
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($email) || empty($password)) {
        throw new Exception("Email y contraseña son obligatorios");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email no válido");
    }

    // Prepare query to prevent SQL injection
    $query = $conn->prepare("SELECT id_usuario, contraseña, rol FROM usuarios WHERE email = ?");
    $query->bind_param("s", $email);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password with password_verify
        if (password_verify($password, $user['contraseña'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_role'] = $user['rol'];

            // Determine redirect URL based on role
            $redirect_url = ($user['rol'] === 'admin') ? 'admin_dashboard.php' : 'index.php';

            // Return success response
            $response = [
                'success' => true,
                'message' => 'Login exitoso',
                'redirect' => $redirect_url,
                'user_role' => $user['rol']
            ];
            
            echo json_encode($response);
        } else {
            throw new Exception("Credenciales incorrectas");
        }
    } else {
        throw new Exception("Credenciales incorrectas");
    }

} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    echo json_encode($response);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>