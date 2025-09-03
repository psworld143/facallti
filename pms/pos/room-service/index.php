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

// Get room service data
$menu_items = getMenuItems(); // Get all menu items
$active_orders = getActiveOrders('room-service');

// Set page title
$page_title = 'Room Service POS';

// Include POS-specific header and sidebar
include '../includes/pos-header.php';
include '../includes/pos-sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 lg:mb-8 gap-4">
                <h2 class="text-2xl lg:text-3xl font-semibold text-gray-800">Room Service POS System</h2>
                <div class="flex items-center space-x-4">
                    <button id="newRoomServiceBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>New Order
                    </button>
                    <button id="deliveryTrackingBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-truck mr-2"></i>Delivery Tracking
                    </button>
                </div>
            </div>

            <!-- Room Service Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column: Menu Items -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Room Service Menu</h3>
                        
                        <!-- Menu Categories -->
                        <div class="flex space-x-2 mb-4 overflow-x-auto">
                            <button class="category-btn active bg-blue-500 text-white px-4 py-2 rounded-lg whitespace-nowrap" data-category="all">
                                All Items
                            </button>
                            <button class="category-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg whitespace-nowrap" data-category="breakfast">
                                Breakfast
                            </button>
                            <button class="category-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg whitespace-nowrap" data-category="lunch">
                                Lunch
                            </button>
                            <button class="category-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg whitespace-nowrap" data-category="dinner">
                                Dinner
                            </button>
                            <button class="category-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg whitespace-nowrap" data-category="snacks">
                                Snacks
                            </button>
                            <button class="category-btn bg-gray-200 text-gray-700 px-4 py-2 rounded-lg whitespace-nowrap" data-category="beverages">
                                Beverages
                            </button>
                        </div>
                        
                        <!-- Menu Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="menuGrid">
                            <?php foreach ($menu_items as $item): ?>
                            <div class="menu-item bg-gray-50 rounded-lg p-4 cursor-pointer hover:bg-blue-50 border-2 border-transparent hover:border-blue-200 transition-all" 
                                 data-category="<?php echo $item['category']; ?>"
                                 data-item-id="<?php echo $item['id']; ?>"
                                 data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                 data-price="<?php echo $item['price']; ?>">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <span class="text-blue-600 font-semibold">₱<?php echo number_format($item['price'], 2); ?></span>
                                </div>
                                <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-500"><?php echo ucfirst($item['category']); ?></span>
                                    <button class="add-to-order bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition-colors">
                                        Add
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Order Management -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Room Service Order</h3>
                        
                        <!-- Guest/Room Selection -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Guest/Room</label>
                            <div class="flex space-x-2">
                                <input type="text" id="guestSearch" placeholder="Search guest or room..." 
                                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <button id="searchGuestBtn" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <div id="guestResults" class="hidden mt-2 bg-gray-50 rounded-lg p-2 max-h-32 overflow-y-auto"></div>
                        </div>
                        
                        <!-- Delivery Time -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Delivery Time</label>
                            <select id="deliveryTime" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="asap">As Soon As Possible</option>
                                <option value="15min">In 15 minutes</option>
                                <option value="30min">In 30 minutes</option>
                                <option value="1hour">In 1 hour</option>
                                <option value="custom">Custom time</option>
                            </select>
                        </div>
                        
                        <!-- Custom Time Input -->
                        <div id="customTimeInput" class="mb-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Custom Delivery Time</label>
                            <input type="datetime-local" id="customDeliveryTime" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <!-- Special Instructions -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Special Instructions</label>
                            <textarea id="specialInstructions" rows="3" 
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Any special requests, allergies, or delivery instructions..."></textarea>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Order Items</label>
                            <div id="orderItems" class="bg-gray-50 rounded-lg p-3 min-h-32">
                                <p class="text-gray-500 text-center py-8">No items added yet</p>
                            </div>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="border-t pt-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Subtotal:</span>
                                <span id="subtotal">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Room Service Fee:</span>
                                <span id="serviceFee">₱150.00</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Tax (12%):</span>
                                <span id="taxAmount">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-lg font-semibold border-t pt-2">
                                <span>Total:</span>
                                <span id="totalAmount">₱0.00</span>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-6 space-y-2">
                            <button id="placeOrderBtn" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg font-medium transition-colors">
                                Place Room Service Order
                            </button>
                            <button id="clearOrderBtn" class="w-full bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-colors">
                                Clear Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Room Service Orders -->
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Active Room Service Orders</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="activeOrdersGrid">
                    <?php foreach ($active_orders as $order): ?>
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-medium">Order #<?php echo $order['id']; ?></h4>
                            <span class="px-2 py-1 text-xs rounded-full 
                                <?php echo $order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                      ($order['status'] === 'preparing' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'); ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-2">Room <?php echo $order['room_number'] ?? 'N/A'; ?></p>
                        <p class="text-sm text-gray-600 mb-2">₱<?php echo number_format($order['total_amount'], 2); ?></p>
                        <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($order['created_at'])); ?></p>
                        <div class="mt-3 space-y-1">
                            <button class="w-full bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition-colors">
                                Update Status
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>

        <!-- New Order Modal -->
        <div id="newOrderModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">New Room Service Order</h3>
                    <form id="newOrderForm">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Order Type</label>
                            <select id="orderType" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="breakfast">Breakfast</option>
                                <option value="lunch">Lunch</option>
                                <option value="dinner">Dinner</option>
                                <option value="snack">Snack</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Guest Count</label>
                            <input type="number" id="guestCount" min="1" max="10" value="1" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority Level</label>
                            <select id="priorityLevel" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="normal">Normal</option>
                                <option value="high">High Priority</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-lg transition-colors">
                                Create Order
                            </button>
                            <button type="button" id="cancelNewOrder" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 rounded-lg transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Room Service POS System JavaScript
            let currentOrder = {
                items: [],
                guest_id: null,
                room_number: null,
                delivery_time: 'asap',
                custom_delivery_time: null,
                special_instructions: '',
                order_type: 'breakfast',
                guest_count: 1,
                priority_level: 'normal'
            };

            // Initialize POS system
            document.addEventListener('DOMContentLoaded', function() {
                initializeRoomServicePOS();
            });

            function initializeRoomServicePOS() {
                // Category filtering
                document.querySelectorAll('.category-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const category = this.dataset.category;
                        filterMenuItems(category);
                        
                        // Update active button
                        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active', 'bg-blue-500', 'text-white'));
                        document.querySelectorAll('.category-btn').forEach(b => b.classList.add('bg-gray-200', 'text-gray-700'));
                        this.classList.remove('bg-gray-200', 'text-gray-700');
                        this.classList.add('active', 'bg-blue-500', 'text-white');
                    });
                });

                // Add items to order
                document.querySelectorAll('.add-to-order').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const menuItem = this.closest('.menu-item');
                        addItemToOrder(menuItem);
                    });
                });

                // Modal controls
                document.getElementById('newRoomServiceBtn').addEventListener('click', showNewOrderModal);
                document.getElementById('cancelNewOrder').addEventListener('click', hideNewOrderModal);
                document.getElementById('newOrderForm').addEventListener('submit', createNewOrder);

                // Order management
                document.getElementById('clearOrderBtn').addEventListener('click', clearOrder);
                document.getElementById('placeOrderBtn').addEventListener('click', placeOrder);

                // Guest search
                document.getElementById('searchGuestBtn').addEventListener('click', searchGuests);
                document.getElementById('guestSearch').addEventListener('input', debounce(searchGuests, 300));

                // Delivery time handling
                document.getElementById('deliveryTime').addEventListener('change', handleDeliveryTimeChange);
            }

            function filterMenuItems(category) {
                const menuItems = document.querySelectorAll('.menu-item');
                menuItems.forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            function addItemToOrder(menuItem) {
                const itemId = menuItem.dataset.itemId;
                const itemName = menuItem.dataset.name;
                const itemPrice = parseFloat(menuItem.dataset.price);

                // Check if item already exists in order
                const existingItem = currentOrder.items.find(item => item.id === itemId);
                if (existingItem) {
                    existingItem.quantity += 1;
                } else {
                    currentOrder.items.push({
                        id: itemId,
                        name: itemName,
                        price: itemPrice,
                        quantity: 1
                    });
                }

                updateOrderDisplay();
                calculateTotals();
            }

            function updateOrderDisplay() {
                const orderItems = document.getElementById('orderItems');
                
                if (currentOrder.items.length === 0) {
                    orderItems.innerHTML = '<p class="text-gray-500 text-center py-8">No items added yet</p>';
                    return;
                }

                let html = '';
                currentOrder.items.forEach((item, index) => {
                    html += `
                        <div class="flex items-center justify-between py-2 border-b border-gray-200 last:border-b-0">
                            <div class="flex-1">
                                <div class="font-medium text-gray-800">${item.name}</div>
                                <div class="text-sm text-gray-600">₱${item.price.toFixed(2)} x ${item.quantity}</div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="font-semibold text-gray-800">₱${(item.price * item.quantity).toFixed(2)}</span>
                                <button onclick="removeItemFromOrder(${index})" class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                orderItems.innerHTML = html;
            }

            function removeItemFromOrder(index) {
                currentOrder.items.splice(index, 1);
                updateOrderDisplay();
                calculateTotals();
            }

            function calculateTotals() {
                const subtotal = currentOrder.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const serviceFee = 150.00; // Fixed room service fee
                const taxAmount = (subtotal + serviceFee) * 0.12;
                const totalAmount = subtotal + serviceFee + taxAmount;

                document.getElementById('subtotal').textContent = `₱${subtotal.toFixed(2)}`;
                document.getElementById('serviceFee').textContent = `₱${serviceFee.toFixed(2)}`;
                document.getElementById('taxAmount').textContent = `₱${taxAmount.toFixed(2)}`;
                document.getElementById('totalAmount').textContent = `₱${totalAmount.toFixed(2)}`;
            }

            function clearOrder() {
                currentOrder = {
                    items: [],
                    guest_id: null,
                    room_number: null,
                    delivery_time: 'asap',
                    custom_delivery_time: null,
                    special_instructions: '',
                    order_type: 'breakfast',
                    guest_count: 1,
                    priority_level: 'normal'
                };
                updateOrderDisplay();
                calculateTotals();
                document.getElementById('guestSearch').value = '';
                document.getElementById('deliveryTime').value = 'asap';
                document.getElementById('customTimeInput').classList.add('hidden');
                document.getElementById('specialInstructions').value = '';
            }

            function showNewOrderModal() {
                document.getElementById('newOrderModal').classList.remove('hidden');
            }

            function hideNewOrderModal() {
                document.getElementById('newOrderModal').classList.add('hidden');
            }

            function createNewOrder(e) {
                e.preventDefault();
                
                currentOrder.order_type = document.getElementById('orderType').value;
                currentOrder.guest_count = parseInt(document.getElementById('guestCount').value);
                currentOrder.priority_level = document.getElementById('priorityLevel').value;
                
                hideNewOrderModal();
                // Reset form
                document.getElementById('newOrderForm').reset();
            }

            function placeOrder() {
                if (currentOrder.items.length === 0) {
                    alert('Please add items to the order first.');
                    return;
                }

                if (!currentOrder.room_number) {
                    alert('Please select a guest/room for delivery.');
                    return;
                }

                // Here you would send the order to the server
                console.log('Placing room service order:', currentOrder);
                alert('Room service order placed successfully!');
                clearOrder();
            }

            function handleDeliveryTimeChange() {
                const deliveryTime = document.getElementById('deliveryTime').value;
                const customTimeInput = document.getElementById('customTimeInput');
                
                if (deliveryTime === 'custom') {
                    customTimeInput.classList.remove('hidden');
                } else {
                    customTimeInput.classList.add('hidden');
                }
            }

            function searchGuests() {
                const searchTerm = document.getElementById('guestSearch').value.trim();
                if (searchTerm.length < 2) {
                    document.getElementById('guestResults').classList.add('hidden');
                    return;
                }

                // Simulate guest search - replace with actual API call
                const mockGuests = [
                    { id: 1, name: 'John Doe', room: '101' },
                    { id: 2, name: 'Jane Smith', room: '205' },
                    { id: 3, name: 'Mike Johnson', room: '312' }
                ];

                displayGuestResults(mockGuests.filter(guest => 
                    guest.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    guest.room.includes(searchTerm)
                ));
            }

            function displayGuestResults(guests) {
                const resultsDiv = document.getElementById('guestResults');
                
                if (guests.length === 0) {
                    resultsDiv.innerHTML = '<p class="text-gray-500 text-sm">No guests found</p>';
                } else {
                    let html = '';
                    guests.forEach(guest => {
                        html += `
                            <div class="guest-result p-2 hover:bg-gray-200 cursor-pointer rounded" 
                                 onclick="selectGuest(${guest.id}, '${guest.name}', '${guest.room}')">
                                <div class="font-medium">${guest.name}</div>
                                <div class="text-sm text-gray-600">Room ${guest.room}</div>
                            </div>
                        `;
                    });
                    resultsDiv.innerHTML = html;
                }
                
                resultsDiv.classList.remove('hidden');
            }

            function selectGuest(guestId, guestName, roomNumber) {
                currentOrder.guest_id = guestId;
                currentOrder.room_number = roomNumber;
                document.getElementById('guestSearch').value = `${guestName} (Room ${roomNumber})`;
                document.getElementById('guestResults').classList.add('hidden');
            }

            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        </script>

<?php include '../includes/pos-footer.php'; ?>
