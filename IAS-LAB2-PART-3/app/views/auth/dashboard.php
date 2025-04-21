<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Correct path to autoload.php from dashboard.php location
require_once __DIR__ . '/../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function isAuthenticated() {
    // Check session first
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        error_log("Session authentication failed");
        return false;
    }

    // Check JWT token
    $jwt = $_COOKIE['jwt_token'] ?? $_SESSION['jwt_token'] ?? null;
    if (!$jwt) {
        error_log("No JWT token found");
        return false;
    }

    try {
        $decoded = JWT::decode($jwt, new Key('your_secret_key', 'HS256'));
        return true;
    } catch (Exception $e) {
        error_log("JWT validation failed: " . $e->getMessage());
        return false;
    }
}

// Debug file paths
error_log("Current file: " . __FILE__);
error_log("Autoload path: " . __DIR__ . '/../../../vendor/autoload.php');
error_log("Autoload exists: " . (file_exists(__DIR__ . '/../../../vendor/autoload.php') ? 'Yes' : 'No'));

// Debug session data
error_log("Session data: " . print_r($_SESSION, true));

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    error_log("Authentication check failed - redirecting to login");
    header('Location: login.php');
    exit();
}

$userEmail = $_SESSION['user_email'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/IAS-LAB2-PART-3/assets/css/style.css">
    <script type="module" src="../../../assets/js/auth-check.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <h1>Welcome to Dashboard</h1>
        <p>Logged in as: <?php echo htmlspecialchars($userEmail); ?></p>
        
        <!-- Debug information -->
        <div class="debug-info" style="background: #f5f5f5; padding: 10px; margin: 10px 0; font-family: monospace;">
            <h3>Debug Information</h3>
            <pre><?php
                echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "\n";
                echo "Session ID: " . session_id() . "\n";
                echo "Authenticated: " . (isset($_SESSION['authenticated']) ? "Yes" : "No") . "\n";
                echo "User Email: " . (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : "Not set") . "\n";
                echo "Last Activity: " . date('Y-m-d H:i:s') . "\n";
            ?></pre>
        </div>

        <form action="logout.php" method="post">
            <button type="submit" class="btn-submit">Logout</button>
        </form>
    </div>

    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-app.js";
        import { getAuth } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";
        import { firebaseConfig } from '/IAS-LAB2-PART-3/assets/js/firebase-config.js';

        // Initialize Firebase
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);

        // Check authentication and email verification
        auth.onAuthStateChanged((user) => {
            if (user) {
                if (user.emailVerified) {
                    document.getElementById('userInfo').innerHTML = `
                        <p>Welcome, ${user.displayName || user.email}</p>
                        <p>Email: ${user.email}</p>
                    `;
                } else {
                    window.location.href = 'login.php';
                }
            } else {
                window.location.href = 'login.php';
            }
        });

        // Define the logout function
        document.getElementById('logoutButton').addEventListener('click', () => {
            auth.signOut().then(() => {
                window.location.href = 'login.php';
            }).catch((error) => {
                console.error('Logout error:', error);
            });
        });
    </script>

    <script>
    // Implement auto-logout for inactivity
    let inactivityTimeout;
    const INACTIVE_TIMEOUT = 15 * 60 * 1000; // 15 minutes

    function resetInactivityTimer() {
        clearTimeout(inactivityTimeout);
        inactivityTimeout = setTimeout(logout, INACTIVE_TIMEOUT);
    }

    function logout() {
        window.location.href = 'logout.php';
    }

    // Reset timer on user activity
    ['click', 'mousemove', 'keypress'].forEach(event => {
        document.addEventListener(event, resetInactivityTimer);
    });

    // Start the initial timer
    resetInactivityTimer();
    </script>

    <style>
        .dashboard-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-submit {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-submit:hover {
            background: #c82333;
        }
    </style>
</body>
</html>