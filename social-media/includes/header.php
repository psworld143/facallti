<!-- Navigation -->
<nav class="bg-white fixed top-0 left-0 right-0 z-50 shadow-lg border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-3">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-10 w-auto">
                    <div class="h-8 w-px bg-gray-300"></div>
                    <div>
                        <h1 class="text-xl font-bold text-seait-dark">SEAIT Social Media</h1>
                        <p class="text-sm text-gray-600">Welcome back, <?php echo $_SESSION['first_name']; ?></p>
                    </div>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <a href="../index.php" class="flex items-center px-4 py-2 text-seait-dark hover:text-seait-orange transition-colors duration-200 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-home mr-2"></i>
                    <span class="hidden sm:inline">View Site</span>
                </a>
                <div class="h-6 w-px bg-gray-300"></div>
                <a href="logout.php" class="flex items-center px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-200 shadow-sm">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </div>
</nav>