<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PRMSU - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        prmsu: {
                            maroon: '#800000',
                            gold: '#FFD700',
                            navy: '#0A142F',
                            gray: '#F3F4F6',
                        }
                    },
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .login-container {
            background-image: linear-gradient(rgba(10, 20, 47, 0.7), rgba(10, 20, 47, 0.7)), url('https://i.ibb.co/3mJSKdz/prmsu-bg.jpg');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>

<body class="bg-gray-100 font-poppins">
    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Left side - PRMSU branding -->
        <div class="login-container hidden md:flex md:w-1/2 flex-col justify-center items-center p-12 text-white">
            <div class="text-center">
                <img src="https://i.ibb.co/0j8Mcrx/prmsu-logo.png" alt="PRMSU Logo" class="w-32 h-32 mx-auto mb-6">
                <h1 class="text-3xl font-bold mb-2">President Ramon Magsaysay State University</h1>
                <h2 class="text-xl mb-6">Scheduling System</h2>
                <p class="text-sm max-w-md mx-auto">Streamlining class scheduling for better academic planning and resource management.</p>
            </div>
        </div>

        <!-- Right side - Login form -->
        <div class="flex flex-col justify-center items-center p-8 md:w-1/2">
            <div class="w-full max-w-md">
                <!-- Mobile logo (visible on small screens) -->
                <div class="md:hidden text-center mb-8">
                    <img src="https://i.ibb.co/0j8Mcrx/prmsu-logo.png" alt="PRMSU Logo" class="w-24 h-24 mx-auto">
                    <h1 class="text-xl font-bold text-prmsu-maroon">PRMSU Scheduling System</h1>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-8">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-prmsu-navy">Sign In</h2>
                        <p class="text-gray-600">Access your account</p>
                    </div>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                            <p><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                            <?php unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form class="space-y-6" action="/login" method="POST">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input id="username" name="username" type="text" required class="pl-10 appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-prmsu-maroon focus:border-prmsu-maroon">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                <a href="#" class="text-xs text-prmsu-maroon hover:text-red-800">Forgot password?</a>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input id="password" name="password" type="password" required class="pl-10 appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-prmsu-maroon focus:border-prmsu-maroon">
                            </div>
                        </div>

                        <div class="flex items-center">
                            <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-prmsu-maroon focus:ring-prmsu-maroon border-gray-300 rounded">
                            <label for="remember_me" class="ml-2 block text-sm text-gray-700">Remember me</label>
                        </div>

                        <div>
                            <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-prmsu-maroon hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-prmsu-maroon transition duration-150">
                                Sign in
                            </button>
                        </div>
                    </form>

                    <div class="mt-6 text-center">
                        <p class="text-sm text-gray-600">
                            Don't have an account? <a href="/register" class="font-medium text-prmsu-maroon hover:text-red-800">Register here</a>
                        </p>
                    </div>
                </div>

                <div class="mt-8 text-center text-gray-500 text-xs">
                    <p>&copy; <?php echo date('Y'); ?> President Ramon Magsaysay State University. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>