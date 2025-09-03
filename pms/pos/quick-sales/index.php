<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../../includes/database.php';
require_once '../includes/pos-functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../booking/login.php');
    exit();
}

// Get quick sales data
$quick_items = getQuickSaleItems();
$quick_sales_stats = getQuickSalesStats();

// Set page title
$page_title = 'Quick Sales POS';

// Include POS-specific header and sidebar
include '../includes/pos-header.php';
include '../includes/pos-sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 lg:mb-8 gap-4">
                <h2 class="text-2xl lg:text-3xl font-semibold text-gray-800">Quick Sales POS System</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Quick Sales Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-bolt text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($quick_sales_stats['today_sales'], 2); ?></h3>
                            <p class="text-gray-600">Today's Sales</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-pink-400 to-red-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-receipt text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $quick_sales_stats['today_transactions']; ?></h3>
                            <p class="text-gray-600">Today's Transactions</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-cyan-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $quick_sales_stats['avg_transaction_time']; ?>s</h3>
                            <p class="text-gray-600">Avg. Transaction Time</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-teal-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($quick_sales_stats['monthly_revenue'], 2); ?></h3>
                            <p class="text-gray-600">Monthly Revenue</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button onclick="openNewQuickSale()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-plus text-xl mr-3"></i>
                        <span class="font-medium">New Sale</span>
                    </button>
                    <button onclick="openExpressCheckout()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-credit-card text-xl mr-3"></i>
                        <span class="font-medium">Express Checkout</span>
                    </button>
                    <button onclick="openCashDrawer()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-cash-register text-xl mr-3"></i>
                        <span class="font-medium">Cash Drawer</span>
                    </button>
                    <button onclick="openQuickReports()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-chart-bar text-xl mr-3"></i>
                        <span class="font-medium">Quick Reports</span>
                    </button>
                </div>
            </div>

            <!-- Quick Sale Interface -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Item Selection -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Items</h3>
                        
                        <!-- Search Bar -->
                        <div class="mb-4">
                            <div class="relative">
                                <input type="text" id="item-search" placeholder="Search items..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Item Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-96 overflow-y-auto">
                            <?php foreach ($quick_items as $item): ?>
                                <div class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer" onclick="addToQuickCart(<?php echo $item['id']; ?>)">
                                    <div class="text-center">
                                        <?php if ($item['image']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-16 h-16 object-cover rounded mx-auto mb-2">
                                        <?php else: ?>
                                            <div class="w-16 h-16 bg-gray-200 rounded mx-auto mb-2 flex items-center justify-center">
                                                <i class="fas fa-tag text-gray-400 text-xl"></i>
                                            </div>
                                        <?php endif; ?>
                                        <h4 class="font-medium text-gray-900 text-sm mb-1"><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <div class="text-sm font-bold text-primary">₱<?php echo number_format($item['price'], 2); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Cart & Checkout -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg p-6 shadow-md">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Cart</h3>
                        
                        <!-- Cart Items -->
                        <div id="quick-cart" class="mb-4 max-h-64 overflow-y-auto">
                            <div class="text-center text-gray-500 py-8">
                                <i class="fas fa-shopping-cart text-2xl mb-2"></i>
                                <p>Cart is empty</p>
                            </div>
                        </div>

                        <!-- Cart Summary -->
                        <div class="border-t border-gray-200 pt-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-medium">₱0.00</span>
                            </div>
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Tax (12%):</span>
                                <span class="font-medium">₱0.00</span>
                            </div>
                            <div class="flex justify-between mb-4">
                                <span class="text-lg font-semibold">Total:</span>
                                <span class="text-lg font-bold text-primary">₱0.00</span>
                            </div>

                            <!-- Payment Buttons -->
                            <div class="space-y-2">
                                <button onclick="processCashPayment()" class="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    <i class="fas fa-money-bill-wave mr-2"></i>Cash Payment
                                </button>
                                <button onclick="processCardPayment()" class="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-credit-card mr-2"></i>Card Payment
                                </button>
                                <button onclick="processMobilePayment()" class="w-full py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                                    <i class="fas fa-mobile-alt mr-2"></i>Mobile Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Quick Sales -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Recent Quick Sales</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No recent quick sales</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Quick Sales POS functionality
        function openNewQuickSale() {
            // Implementation for new quick sale
            console.log('Opening new quick sale');
        }

        function openExpressCheckout() {
            // Implementation for express checkout
            console.log('Opening express checkout');
        }

        function openCashDrawer() {
            // Implementation for cash drawer
            console.log('Opening cash drawer');
        }

        function openQuickReports() {
            // Implementation for quick reports
            console.log('Opening quick reports');
        }

        function addToQuickCart(itemId) {
            // Implementation for adding item to quick cart
            console.log('Adding item to quick cart:', itemId);
        }

        function processCashPayment() {
            // Implementation for cash payment
            console.log('Processing cash payment');
        }

        function processCardPayment() {
            // Implementation for card payment
            console.log('Processing card payment');
        }

        function processMobilePayment() {
            // Implementation for mobile payment
            console.log('Processing mobile payment');
        }

        // Search functionality
        document.getElementById('item-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            // Implementation for item search
            console.log('Searching for:', searchTerm);
        });
    </script>

<?php include '../includes/pos-footer.php'; ?>
