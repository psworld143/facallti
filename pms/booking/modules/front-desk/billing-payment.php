<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

// Get billing statistics
$billing_stats = getBillingStatistics();

// Set page title
$page_title = 'Billing & Payment';

// Include unified header (automatically selects appropriate navbar)
include '../../includes/header-unified.php';
// Include unified sidebar (automatically selects appropriate sidebar)
include '../../includes/sidebar-unified.php';
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Payment - Hotel PMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        'primary-dark': '#2563EB',
                        secondary: '#6B7280',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Today's Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($billing_stats['today_revenue'], 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending Bills</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($billing_stats['pending_bills']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-percentage text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Discounts</p>
                        <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($billing_stats['total_discounts'], 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-star text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Loyalty Points</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($billing_stats['total_loyalty_points']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <button onclick="openBillingModal()" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                    <i class="fas fa-file-invoice text-blue-600 text-xl mr-3"></i>
                    <span class="font-medium text-blue-800">Create Bill</span>
                </button>
                <button onclick="openDiscountModal()" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                    <i class="fas fa-percentage text-green-600 text-xl mr-3"></i>
                    <span class="font-medium text-green-800">Apply Discount</span>
                </button>
                <button onclick="openVoucherModal()" class="flex items-center p-4 bg-yellow-50 border-2 border-yellow-200 rounded-lg hover:bg-yellow-100 hover:border-yellow-300 transition-all duration-300">
                    <i class="fas fa-ticket-alt text-yellow-600 text-xl mr-3"></i>
                    <span class="font-medium text-yellow-800">Create Voucher</span>
                </button>
                <button onclick="openLoyaltyModal()" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                    <i class="fas fa-star text-purple-600 text-xl mr-3"></i>
                    <span class="font-medium text-purple-800">Loyalty Points</span>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6">
                    <button onclick="switchTab('bills')" id="tab-bills" class="tab-button active py-4 px-1 border-b-2 border-primary font-medium text-sm text-primary">
                        <i class="fas fa-file-invoice mr-2"></i>Bills
                    </button>
                    <button onclick="switchTab('payments')" id="tab-payments" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-credit-card mr-2"></i>Payments
                    </button>
                    <button onclick="switchTab('discounts')" id="tab-discounts" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-percentage mr-2"></i>Discounts
                    </button>
                    <button onclick="switchTab('vouchers')" id="tab-vouchers" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-ticket-alt mr-2"></i>Vouchers
                    </button>
                    <button onclick="switchTab('loyalty')" id="tab-loyalty" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-star mr-2"></i>Loyalty
                    </button>
                </nav>
            </div>
            
            <!-- Bills Tab -->
            <div id="tab-content-bills" class="tab-content active p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Bills & Invoices</h3>
                    <div class="flex space-x-2">
                        <select id="bill-status-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                        <input type="date" id="bill-date-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                    </div>
                </div>
                
                <div id="bills-container">
                    <!-- Bills will be loaded here -->
                </div>
            </div>
            
            <!-- Payments Tab -->
            <div id="tab-content-payments" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Payment History</h3>
                    <div class="flex space-x-2">
                        <select id="payment-method-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                        <input type="date" id="payment-date-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                    </div>
                </div>
                
                <div id="payments-container">
                    <!-- Payments will be loaded here -->
                </div>
            </div>
            
            <!-- Discounts Tab -->
            <div id="tab-content-discounts" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Discounts Applied</h3>
                    <div class="flex space-x-2">
                        <select id="discount-type-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Types</option>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                            <option value="loyalty">Loyalty</option>
                            <option value="promotional">Promotional</option>
                        </select>
                    </div>
                </div>
                
                <div id="discounts-container">
                    <!-- Discounts will be loaded here -->
                </div>
            </div>
            
            <!-- Vouchers Tab -->
            <div id="tab-content-vouchers" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Vouchers</h3>
                    <div class="flex space-x-2">
                        <select id="voucher-status-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="used">Used</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>
                
                <div id="vouchers-container">
                    <!-- Vouchers will be loaded here -->
                </div>
            </div>
            
            <!-- Loyalty Tab -->
            <div id="tab-content-loyalty" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Loyalty Program</h3>
                    <div class="flex space-x-2">
                        <select id="loyalty-tier-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Tiers</option>
                            <option value="bronze">Bronze</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                            <option value="platinum">Platinum</option>
                        </select>
                    </div>
                </div>
                
                <div id="loyalty-container">
                    <!-- Loyalty information will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Billing Modal -->
    <div id="billing-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Create Bill</h3>
                <button onclick="closeBillingModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="billing-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reservation *</label>
                        <select name="reservation_id" id="billing_reservation_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Reservation</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bill Date</label>
                        <input type="date" name="bill_date" id="bill_date" 
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-4">Bill Items</label>
                    <div id="bill-items-container" class="space-y-3">
                        <!-- Bill items will be loaded here -->
                    </div>
                    <button type="button" onclick="addBillItem()" class="mt-3 px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Item
                    </button>
                </div>
                
                <div class="border-t pt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal</label>
                            <input type="text" id="bill_subtotal" readonly 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tax (10%)</label>
                            <input type="text" id="bill_tax" readonly 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Discount</label>
                            <input type="text" id="bill_discount" readonly 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total</label>
                            <input type="text" id="bill_total" readonly 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 font-semibold">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeBillingModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Create Bill
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Discount Modal -->
    <div id="discount-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Apply Discount</h3>
                <button onclick="closeDiscountModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="discount-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bill *</label>
                        <select name="bill_id" id="discount_bill_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Bill</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type *</label>
                        <select name="discount_type" id="discount_type" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Type</option>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                            <option value="loyalty">Loyalty Points</option>
                            <option value="promotional">Promotional</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value *</label>
                        <input type="number" name="discount_value" id="discount_value" step="0.01" min="0" required 
                               placeholder="Enter discount value"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <input type="text" name="discount_reason" id="discount_reason" 
                               placeholder="Reason for discount"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="discount_description" id="discount_description" rows="3" 
                              placeholder="Additional details about the discount..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeDiscountModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Apply Discount
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Voucher Modal -->
    <div id="voucher-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Create Voucher</h3>
                <button onclick="closeVoucherModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="voucher-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Voucher Code *</label>
                        <input type="text" name="voucher_code" id="voucher_code" required 
                               placeholder="Enter voucher code"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Voucher Type *</label>
                        <select name="voucher_type" id="voucher_type" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Type</option>
                            <option value="percentage">Percentage Discount</option>
                            <option value="fixed">Fixed Amount</option>
                            <option value="free_night">Free Night</option>
                            <option value="upgrade">Room Upgrade</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Value *</label>
                        <input type="number" name="voucher_value" id="voucher_value" step="0.01" min="0" required 
                               placeholder="Enter voucher value"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Usage Limit</label>
                        <input type="number" name="usage_limit" id="usage_limit" min="1" value="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valid From</label>
                        <input type="date" name="valid_from" id="voucher_valid_from" 
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until</label>
                        <input type="date" name="valid_until" id="voucher_valid_until" 
                               value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="voucher_description" id="voucher_description" rows="3" 
                              placeholder="Description of the voucher..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeVoucherModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Create Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loyalty Modal -->
    <div id="loyalty-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Loyalty Points Management</h3>
                <button onclick="closeLoyaltyModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="loyalty-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guest *</label>
                        <select name="guest_id" id="loyalty_guest_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Guest</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Action *</label>
                        <select name="loyalty_action" id="loyalty_action" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Action</option>
                            <option value="earn">Earn Points</option>
                            <option value="redeem">Redeem Points</option>
                            <option value="adjust">Adjust Points</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Points *</label>
                        <input type="number" name="loyalty_points" id="loyalty_points" min="0" required 
                               placeholder="Enter points"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <input type="text" name="loyalty_reason" id="loyalty_reason" 
                               placeholder="Reason for points action"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="loyalty_description" id="loyalty_description" rows="3" 
                              placeholder="Additional details..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeLoyaltyModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Process Points
                    </button>
                </div>
            </form>
        </div>
    </div>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/billing-payment.js"></script>
    
    <?php include '../../includes/footer.php'; ?>
