<?php
// src/views/partials/dean/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default user name
$userName = 'Dean';
if (isset($_SESSION['user']['first_name']) && isset($_SESSION['user']['last_name'])) {
    $userName = htmlspecialchars($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']);
} elseif (isset($_SESSION['user']['username'])) {
    $userName = htmlspecialchars($_SESSION['user']['username']);
} else {
    error_log("header.php: Missing user data in session: " . json_encode($_SESSION['user'] ?? 'null'));
}
?>

<header class="bg-white shadow p-4 flex items-center justify-between">
    <div class="flex items-center">
        <h2 class="text-lg font-semibold text-gray-800">Dean Portal</h2>
    </div>
    <div class="flex items-center">
        <span class="text-sm text-gray-600 mr-4"><?= $userName ?></span>
        <a href="/logout" class="btn-primary px-3 py-1 rounded-md text-sm text-white" style="background: var(--prmsu-gold);">
            <i class="fas fa-sign-out-alt mr-1"></i> Logout
        </a>
    </div>
</header>

<style>
    :root {
        --prmsu-gold: rgb(239, 187, 15);
    }

    .btn-primary:hover {
        background: #d4a013;
    }
</style>