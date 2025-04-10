<?php
// views/partials/faculty/header.php
?>

<header class="fixed top-0 right-0 left-64 bg-white shadow-sm z-40 transition-all duration-300 ease-in-out">
    <div class="flex items-center justify-between px-6 py-4">
        <!-- Breadcrumbs/Title -->
        <div class="flex items-center space-x-4">
            <h2 class="text-lg font-semibold text-gray-800 tracking-wide">Faculty Portal</h2>
            <span class="text-gray-400">/</span>
            <span class="text-sm text-gray-600 capitalize"><?= basename($currentUri) ?></span>
        </div>

        <!-- User Profile & Actions -->
        <div class="flex items-center space-x-6">
            <!-- Notification Bell -->
            <button class="relative text-gray-500 hover:text-gray-700 focus:outline-none transition-colors">
                <i class="fas fa-bell text-xl"></i>
                <span class="absolute top-0 right-0 h-2 w-2 rounded-full bg-red-500"></span>
            </button>

            <!-- User Dropdown -->
            <div class="relative group">
                <button class="flex items-center space-x-2 focus:outline-none">
                    <div class="h-9 w-9 rounded-full bg-gradient-to-r from-blue-600 to-blue-400 flex items-center justify-center text-white font-semibold shadow">
                        <?= strtoupper(substr($_SESSION['user']['username'], 0, 1)) ?>
                    </div>
                    <span class="text-gray-700 font-medium hidden md:inline-block"><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
                    <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform group-hover:rotate-180"></i>
                </button>

                <!-- Dropdown Menu -->
                <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-all duration-200">
                    <a href="/faculty/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-user mr-2"></i> Your Profile
                    </a>
                    <a href="/faculty/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-cog mr-2"></i> Settings
                    </a>
                    <div class="border-t border-gray-200"></div>
                    <a href="/faculty/logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
    header {
        backdrop-filter: blur(8px);
        background-color: rgba(255, 255, 255, 0.8);
    }

    .dropdown-enter {
        opacity: 0;
        transform: translateY(-10px);
    }

    .dropdown-enter-active {
        opacity: 1;
        transform: translateY(0);
        transition: opacity 200ms, transform 200ms;
    }

    .dropdown-exit {
        opacity: 1;
    }

    .dropdown-exit-active {
        opacity: 0;
        transform: translateY(-10px);
        transition: opacity 200ms, transform 200ms;
    }
</style>