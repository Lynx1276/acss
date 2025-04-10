<!-- Mobile header -->
<header class="bg-white shadow md:hidden">
    <div class="px-4 py-3 flex items-center justify-between">
        <button id="sidebar-toggle" class="text-gray-500 focus:outline-none">
            <i class="fas fa-bars"></i>
        </button>
        <div class="flex items-center">
            <i class="fas fa-calendar-alt text-indigo-600 text-xl mr-2"></i>
            <h1 class="text-lg font-bold text-gray-900">ACSS</h1>
        </div>
        <div class="w-8"></div> <!-- Spacer for alignment -->
    </div>
</header>

<!-- Desktop header -->
<header class="bg-white shadow hidden md:block">
    <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-gray-900"><?= $pageTitle ?? 'Department Chair Dashboard' ?></h1>
            <p class="text-sm text-gray-500"><?= $pageSubtitle ?? 'Welcome back! Here\'s what\'s happening with your department.' ?></p>
        </div>
        <div class="flex space-x-3">
            <?php if (isset($showScheduleButton) && $showScheduleButton): ?>
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-plus mr-2"></i> New Schedule
                </button>
            <?php endif; ?>
            <?php if (isset($showExportButton) && $showExportButton): ?>
                <button class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-download mr-2"></i> Export
                </button>
            <?php endif; ?>
        </div>
    </div>
</header>