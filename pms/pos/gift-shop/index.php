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

// Get gift shop data
$gift_items = getGiftShopItems();
$inventory_status = getInventoryStatus('gift-shop');
$gift_shop_stats = getGiftShopStats();

// Set page title
$page_title = 'Gift Shop POS';

// Include POS-specific header and sidebar
include '../includes/pos-header.php';
include '../includes/pos-sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 lg:mb-8 gap-4">
                <h2 class="text-2xl lg:text-3xl font-semibold text-gray-800">Gift Shop POS System</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Gift Shop Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-gift text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($gift_shop_stats['today_sales'], 2); ?></h3>
                            <p class="text-gray-600">Today's Sales</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-pink-400 to-red-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-shopping-cart text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $gift_shop_stats['today_transactions']; ?></h3>
                            <p class="text-gray-600">Today's Transactions</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-cyan-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-boxes text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $gift_shop_stats['total_items']; ?></h3>
                            <p class="text-gray-600">Total Items</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-teal-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $gift_shop_stats['low_stock_items']; ?></h3>
                            <p class="text-gray-600">Low Stock Items</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button onclick="openNewSale()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-plus text-xl mr-3"></i>
                        <span class="font-medium">New Sale</span>
                    </button>
                    <button onclick="openInventory()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-boxes text-xl mr-3"></i>
                        <span class="font-medium">Inventory</span>
                    </button>
                    <button onclick="openReturns()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-undo text-xl mr-3"></i>
                        <span class="font-medium">Returns</span>
                    </button>
                    <button onclick="openGiftShopReports()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-chart-bar text-xl mr-3"></i>
                        <span class="font-medium">Reports</span>
                    </button>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <?php if ($gift_shop_stats['low_stock_items'] > 0): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-yellow-800">Low Stock Alert</h4>
                        <p class="text-sm text-yellow-700"><?php echo $gift_shop_stats['low_stock_items']; ?> items are running low on stock. Please check inventory.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Popular Items -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Popular Items</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php 
                    $popular_items = array_slice($gift_items, 0, 8); // Show first 8 items
                    foreach ($popular_items as $item): 
                    ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="addToCart(<?php echo $item['id']; ?>)">
                            <div class="text-center">
                                <?php if ($item['image']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-20 h-20 object-cover rounded mx-auto mb-3">
                                <?php else: ?>
                                    <div class="w-20 h-20 bg-gray-200 rounded mx-auto mb-3 flex items-center justify-center">
                                        <i class="fas fa-gift text-gray-400 text-2xl"></i>
                                    </div>
                                <?php endif; ?>
                                <h4 class="font-medium text-gray-900 text-sm mb-2"><?php echo htmlspecialchars($item['name']); ?></h4>
                                <div class="text-lg font-bold text-primary mb-2">₱<?php echo number_format($item['price'], 2); ?></div>
                                <div class="text-xs text-gray-500 mb-2">Stock: <?php echo $item['stock_quantity']; ?></div>
                                <button class="w-full px-3 py-1 bg-primary text-white text-xs rounded hover:bg-primary-dark transition-colors">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Recent Transactions</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No recent transactions</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Gift Shop POS functionality
        function openNewSale() {
            // Implementation for new sale
            console.log('Opening new sale');
        }

        function openInventory() {
            // Implementation for inventory management
            console.log('Opening inventory');
        }

        function openReturns() {
            // Implementation for returns
            console.log('Opening returns');
        }

        function openGiftShopReports() {
            // Implementation for gift shop reports
            console.log('Opening gift shop reports');
        }

        function addToCart(itemId) {
            // Implementation for adding item to cart
            console.log('Adding item to cart:', itemId);
        }
    </script>

<?php include '../includes/pos-footer.php'; ?>
