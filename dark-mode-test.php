<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dark Mode Test - SEAIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    <script src="assets/js/dark-mode.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 dark-mode min-h-screen" data-theme="light">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Test Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-seait-dark mb-8">Dark Mode Test Page</h1>

        <!-- Test Sections -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Cards Section -->
            <div class="space-y-6">
                <h2 class="text-2xl font-semibold text-seait-dark">Cards</h2>

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-seait-dark mb-4">Light Card</h3>
                    <p class="text-gray-600 mb-4">This card should adapt to the current theme.</p>
                    <button class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                        Test Button
                    </button>
                </div>

                <div class="bg-gray-100 rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-seait-dark mb-4">Gray Card</h3>
                    <p class="text-gray-600 mb-4">This card uses gray background.</p>
                    <button class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                        Test Button
                    </button>
                </div>
            </div>

            <!-- Form Section -->
            <div class="space-y-6">
                <h2 class="text-2xl font-semibold text-seait-dark">Forms</h2>

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-seait-dark mb-4">Test Form</h3>
                    <form class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" placeholder="Enter your name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" placeholder="Enter your email">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange" rows="3" placeholder="Enter your message"></textarea>
                        </div>
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded hover:bg-orange-600 transition">
                            Submit
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Theme Info -->
        <div class="mt-12 bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-semibold text-seait-dark mb-4">Theme Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <h3 class="font-semibold text-seait-dark mb-2">Current Theme</h3>
                    <p id="current-theme" class="text-gray-600">Loading...</p>
                </div>
                <div>
                    <h3 class="font-semibold text-seait-dark mb-2">System Preference</h3>
                    <p id="system-preference" class="text-gray-600">Loading...</p>
                </div>
                <div>
                    <h3 class="font-semibold text-seait-dark mb-2">Storage</h3>
                    <p id="storage-info" class="text-gray-600">Loading...</p>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="font-semibold text-seait-dark mb-2">Test Controls</h3>
                <div class="flex space-x-4">
                    <button onclick="testTheme('light')" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                        Force Light
                    </button>
                    <button onclick="testTheme('dark')" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-900 transition">
                        Force Dark
                    </button>
                    <button onclick="clearStorage()" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                        Clear Storage
                    </button>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg">
            <h2 class="text-xl font-semibold text-yellow-800 mb-4">Testing Instructions</h2>
            <div class="space-y-2 text-yellow-700">
                <p><strong>1.</strong> Click the sun/moon icon in the navbar to toggle dark mode</p>
                <p><strong>2.</strong> Press Ctrl+J (or Cmd+J on Mac) to toggle with keyboard</p>
                <p><strong>3.</strong> Check that all elements adapt to the theme change</p>
                <p><strong>4.</strong> Refresh the page to verify persistence</p>
                <p><strong>5.</strong> Change your system dark mode setting to test automatic detection</p>
            </div>
        </div>
    </div>

    <script>
        // Update theme information
        function updateThemeInfo() {
            const currentTheme = window.darkMode ? window.darkMode.getCurrentTheme() : 'Unknown';
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const storedTheme = localStorage.getItem('theme');

            document.getElementById('current-theme').textContent = currentTheme;
            document.getElementById('system-preference').textContent = systemPrefersDark ? 'Dark' : 'Light';
            document.getElementById('storage-info').textContent = storedTheme || 'None (using system)';
        }

        // Test functions
        function testTheme(theme) {
            if (window.darkMode) {
                window.darkMode.setTheme(theme);
                updateThemeInfo();
            }
        }

        function clearStorage() {
            localStorage.removeItem('theme');
            if (window.darkMode) {
                window.darkMode.init();
                updateThemeInfo();
            }
        }

        // Update info when theme changes
        document.addEventListener('themeChanged', updateThemeInfo);

        // Initial update
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(updateThemeInfo, 100);
        });
    </script>
</body>
</html>