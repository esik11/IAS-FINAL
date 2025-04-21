<?php
session_start();

class AuthMiddleware {
    public static function checkAuth() {
        if (!isset($_SESSION['user_authenticated']) || !$_SESSION['user_authenticated']) {
            header('Location: ../auth/login.php');
            exit();
        }
    }
} 