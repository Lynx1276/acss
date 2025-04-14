<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PRMSU - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
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
                            lightMaroon: '#9E2A2A'
                        }
                    },
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 4px 20px rgba(0, 0, 0, 0.08)',
                        'input-focus': '0 0 0 3px rgba(128, 0, 0, 0.2)'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
        }

        .login-container {
            background-image: linear-gradient(135deg, rgba(10, 20, 47, 0.85), rgba(128, 0, 0, 0.85)),
                url('https://i.ibb.co/3mJSKdz/prmsu-bg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .input-transition {
            transition: all 0.3s ease;
        }

        .btn-hover {
            transition: all 0.3s ease;
            transform: translateY(0);
        }

        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(128, 0, 0, 0.15);
        }

        .floating-label {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
            pointer-events: none;
            color: #9CA3AF;
        }

        .input-field:focus+.floating-label,
        .input-field:not(:placeholder-shown)+.floating-label {
            top: 0;
            left: 10px;
            font-size: 0.75rem;
            background: white;
            padding: 0 4px;
            color: #800000;
        }
    </style>
</head>

<body class="font-poppins">
    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Left side - PRMSU branding -->
        <div class="login-container hidden md:flex md:w-1/2 flex-col justify-center items-center p-12 text-white animate__animated animate__fadeIn">
            <div class="text-center max-w-md">
                <img src="https://i.ibb.co/0j8Mcrx/prmsu-logo.png" alt="PRMSU Logo" class="w-32 h-32 mx-auto mb-6 animate__animated animate__fadeInUp">
                <h1 class="text-3xl font-bold mb-2 animate__animated animate__fadeInUp animate__delay-1s">President Ramon Magsaysay State University</h1>
                <div class="h-1 w-20 bg-prmsu-gold mx-auto my-4 animate__animated animate__fadeIn animate__delay-2s"></div>
                <h2 class="text-xl mb-6 animate__animated animate__fadeInUp animate__delay-2s">Scheduling System</h2>
                <p class="text-sm opacity-90 animate__animated animate__fadeIn animate__delay-3s">Streamlining class scheduling for better academic planning and resource management.</p>
            </div>

            <!-- University motto -->
            <div class="absolute bottom-8 text-center text-sm opacity-80 animate__animated animate__fadeIn animate__delay-3s">
                <p>"Quality Education for Service"</p>
            </div>
        </div>

        <!-- Right side - Login form -->
        <div class="flex flex-col justify-center items-center p-6 md:w-1/2 animate__animated animate__fadeIn">
            <div class="w-full max-w-md">
                <!-- Mobile logo -->
                <div class="md:hidden text-center mb-8">
                    <img src="https://i.ibb.co/0j8Mcrx/prmsu-logo.png" alt="PRMSU Logo" class="w-20 h-20 mx-auto animate__animated animate__fadeIn">
                    <h1 class="text-xl font-bold text-prmsu-maroon mt-2">PRMSU Scheduling System</h1>
                </div>

                <div class="bg-white rounded-xl shadow-soft p-8 animate__animated animate__fadeInUp">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-prmsu-navy">Welcome Back</h2>
                        <p class="text-gray-600">Sign in to access your account</p>
                    </div>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md flex items-start" role="alert">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="font-medium"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form class="space-y-5" action="/login" method="POST">
                        <!-- Username Field -->
                        <div class="relative">
                            <input id="username" name="username" type="text" required placeholder=" "
                                class="input-field w-full px-4 py-3 border border-gray-200 rounded-lg input-transition focus:border-prmsu-maroon focus:ring-2 focus:ring-prmsu-maroon focus:shadow-input-focus">
                            <label for="username" class="floating-label">Username</label>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="relative">
                            <input id="password" name="password" type="password" required placeholder=" "
                                class="input-field w-full px-4 py-3 border border-gray-200 rounded-lg input-transition focus:border-prmsu-maroon focus:ring-2 focus:ring-prmsu-maroon focus:shadow-input-focus">
                            <label for="password" class="floating-label">Password</label>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-prmsu-maroon focus:ring-prmsu-maroon border-gray-300 rounded">
                                <label for="remember_me" class="ml-2 block text-sm text-gray-700">Remember me</label>
                            </div>
                            <a href="#" class="text-sm font-medium text-prmsu-maroon hover:text-prmsu-lightMaroon">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-hover w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-prmsu-maroon to-prmsu-lightMaroon focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-prmsu-maroon">
                            Sign In
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </form>

                    <div class="mt-6">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-200"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-white text-gray-500">Don't have an account?</span>
                            </div>
                        </div>

                        <div class="mt-6">
                            <a href="/register" class="w-full flex justify-center py-2.5 px-4 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-prmsu-navy hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-prmsu-maroon">
                                Create new account
                            </a>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-center text-xs text-gray-500">
                    <p>&copy; <?php echo date('Y'); ?> President Ramon Magsaysay State University. All rights reserved.</p>
                    <p class="mt-1">v2.1.0</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add animation class when elements come into view
        document.addEventListener('DOMContentLoaded', function() {
            const animateElements = document.querySelectorAll('.animate-on-scroll');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate__fadeInUp');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });

            animateElements.forEach(el => observer.observe(el));
        });
    </script>
</body>

</html>