<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'config.php';
require_once 'logger.php';

function redirectIfNotLoggedIn() {
    if (!isset($_SESSION['user'])) {
        header("Location: " . BASE_URL . "/public/login.php");
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

function isManager() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'manager';
}

function isRecordOfficer() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'record_officer';
}

function isSurveyor() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'surveyor';
}

function restrictAccess(array $allowed_roles, $resource = 'restricted resource') {
    if (!isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], $allowed_roles)) {
        logAction('unauthorized_access', "Attempted access to $resource", 'warning');
        die("Access denied!");
    }
}
?>