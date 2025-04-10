<?php
// views/partials/dean/header.php
?>

<header class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-900">
            Welcome, <?= htmlspecialchars($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']) ?>
        </h2>
        <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-500"><?= date('F j, Y') ?></span>
            <a href="/dean/logout" class="text-gray-600 hover:text-gray-900">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</header>