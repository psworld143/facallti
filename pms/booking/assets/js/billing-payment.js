// Billing & Payment JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeBillingPayment();
    loadBills();
});

function initializeBillingPayment() {
    switchTab('bills');
    
    // Initialize filter change listeners
    document.getElementById('bill-status-filter').addEventListener('change', loadBills);
    document.getElementById('bill-date-filter').addEventListener('change', loadBills);
    document.getElementById('payment-method-filter').addEventListener('change', loadPayments);
    document.getElementById('payment-date-filter').addEventListener('change', loadPayments);
    document.getElementById('discount-type-filter').addEventListener('change', loadDiscounts);
    document.getElementById('voucher-status-filter').addEventListener('change', loadVouchers);
    document.getElementById('loyalty-tier-filter').addEventListener('change', loadLoyalty);
    
    // Initialize form handlers
    document.getElementById('billing-form').addEventListener('submit', handleBillingSubmit);
    document.getElementById('discount-form').addEventListener('submit', handleDiscountSubmit);
    document.getElementById('voucher-form').addEventListener('submit', handleVoucherSubmit);
    document.getElementById('loyalty-form').addEventListener('submit', handleLoyaltySubmit);
}

// Tab switching functionality
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
        button.classList.remove('border-primary', 'text-primary');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    const selectedContent = document.getElementById(`tab-content-${tabName}`);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('active');
    }
    
    const selectedButton = document.getElementById(`tab-${tabName}`);
    if (selectedButton) {
        selectedButton.classList.add('active', 'border-primary', 'text-primary');
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
    }
    
    switch(tabName) {
        case 'bills': loadBills(); break;
        case 'payments': loadPayments(); break;
        case 'discounts': loadDiscounts(); break;
        case 'vouchers': loadVouchers(); break;
        case 'loyalty': loadLoyalty(); break;
    }
}

// Load functions
function loadBills() {
    const statusFilter = document.getElementById('bill-status-filter').value;
    const dateFilter = document.getElementById('bill-date-filter').value;
    
    const params = new URLSearchParams({ status: statusFilter, date: dateFilter });
    
    fetch(`../../api/get-bills.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayBills(data.bills);
            } else {
                console.error('API Error:', data.message);
                displayBills([]); // Show empty state
            }
        })
        .catch(error => {
            console.error('Error loading bills:', error);
            displayBills([]); // Show empty state instead of error notification
        });
}

function loadPayments() {
    const methodFilter = document.getElementById('payment-method-filter').value;
    const dateFilter = document.getElementById('payment-date-filter').value;
    
    const params = new URLSearchParams({ method: methodFilter, date: dateFilter });
    
    fetch(`../../api/get-payments.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayPayments(data.payments);
            } else {
                console.error('API Error:', data.message);
                displayPayments([]); // Show empty state
            }
        })
        .catch(error => {
            console.error('Error loading payments:', error);
            displayPayments([]); // Show empty state instead of error notification
        });
}

function loadDiscounts() {
    const typeFilter = document.getElementById('discount-type-filter').value;
    
    const params = new URLSearchParams({ type: typeFilter });
    
    fetch(`../../api/get-discounts.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayDiscounts(data.discounts);
            } else {
                console.error('API Error:', data.message);
                displayDiscounts([]); // Show empty state
            }
        })
        .catch(error => {
            console.error('Error loading discounts:', error);
            displayDiscounts([]); // Show empty state instead of error notification
        });
}

function loadVouchers() {
    const statusFilter = document.getElementById('voucher-status-filter').value;
    
    const params = new URLSearchParams({ status: statusFilter });
    
    fetch(`../../api/get-vouchers.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayVouchers(data.vouchers);
            } else {
                console.error('API Error:', data.message);
                displayVouchers([]); // Show empty state
            }
        })
        .catch(error => {
            console.error('Error loading vouchers:', error);
            displayVouchers([]); // Show empty state instead of error notification
        });
}

function loadLoyalty() {
    const tierFilter = document.getElementById('loyalty-tier-filter').value;
    
    const params = new URLSearchParams({ tier: tierFilter });
    
    fetch(`../../api/get-loyalty.php?${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayLoyalty(data.loyalty);
            } else {
                console.error('API Error:', data.message);
                displayLoyalty([]); // Show empty state
            }
        })
        .catch(error => {
            console.error('Error loading loyalty data:', error);
            displayLoyalty([]); // Show empty state instead of error notification
        });
}

// Display functions
function displayBills(bills) {
    const container = document.getElementById('bills-container');
    
    if (!bills || bills.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-file-invoice text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No bills found</h3>
                <p class="text-gray-500">No bills match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bill</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${bills.map(bill => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${bill.bill_number}</div>
                                <div class="text-sm text-gray-500">${formatDate(bill.bill_date)}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${bill.guest_name}</div>
                                <div class="text-sm text-gray-500">Room ${bill.room_number}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">₱${parseFloat(bill.total_amount).toFixed(2)}</div>
                                ${bill.discount_amount > 0 ? `<div class="text-sm text-green-600">-₱${parseFloat(bill.discount_amount).toFixed(2)}</div>` : ''}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getBillStatusClass(bill.status)}">
                                    ${getBillStatusLabel(bill.status)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(bill.due_date)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="viewBill(${bill.id})" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="printBill(${bill.id})" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    ${bill.status === 'pending' ? `
                                        <button onclick="processPayment(${bill.id})" class="text-purple-600 hover:text-purple-900">
                                            <i class="fas fa-credit-card"></i>
                                        </button>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function displayPayments(payments) {
    const container = document.getElementById('payments-container');
    
    if (!payments || payments.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-credit-card text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No payments found</h3>
                <p class="text-gray-500">No payments match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${payments.map(payment => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${payment.payment_number}</div>
                                <div class="text-sm text-gray-500">${payment.bill_number}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${payment.guest_name}</div>
                                <div class="text-sm text-gray-500">Room ${payment.room_number}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">₱${parseFloat(payment.amount).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getPaymentMethodClass(payment.payment_method)}">
                                    ${getPaymentMethodLabel(payment.payment_method)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(payment.payment_date)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="viewPayment(${payment.id})" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="printReceipt(${payment.id})" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function displayDiscounts(discounts) {
    const container = document.getElementById('discounts-container');
    
    if (!discounts || discounts.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-percentage text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No discounts found</h3>
                <p class="text-gray-500">No discounts match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bill</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${discounts.map(discount => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${discount.bill_number}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${discount.guest_name}</div>
                                <div class="text-sm text-gray-500">Room ${discount.room_number}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getDiscountTypeClass(discount.discount_type)}">
                                    ${getDiscountTypeLabel(discount.discount_type)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                ${discount.discount_type === 'percentage' ? `${discount.discount_value}%` : `₱${parseFloat(discount.discount_value).toFixed(2)}`}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">${discount.reason || 'N/A'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(discount.created_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function displayVouchers(vouchers) {
    const container = document.getElementById('vouchers-container');
    
    if (!vouchers || vouchers.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-ticket-alt text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No vouchers found</h3>
                <p class="text-gray-500">No vouchers match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voucher Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valid Until</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${vouchers.map(voucher => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${voucher.voucher_code}</div>
                                <div class="text-sm text-gray-500">${voucher.description || 'No description'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getVoucherTypeClass(voucher.voucher_type)}">
                                    ${getVoucherTypeLabel(voucher.voucher_type)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                ${voucher.voucher_type === 'percentage' ? `${voucher.voucher_value}%` : `₱${parseFloat(voucher.voucher_value).toFixed(2)}`}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${voucher.used_count}/${voucher.usage_limit}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getVoucherStatusClass(voucher.status)}">
                                    ${getVoucherStatusLabel(voucher.status)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(voucher.valid_until)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function displayLoyalty(loyalty) {
    const container = document.getElementById('loyalty-container');
    
    if (!loyalty || loyalty.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-star text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No loyalty data found</h3>
                <p class="text-gray-500">No loyalty information matches your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${loyalty.map(member => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${member.guest_name}</div>
                                <div class="text-sm text-gray-500">${member.email || 'N/A'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getLoyaltyTierClass(member.tier)}">
                                    ${getLoyaltyTierLabel(member.tier)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${member.points.toLocaleString()}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(member.total_spent).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(member.last_activity)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="viewLoyaltyHistory(${member.guest_id})" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    <button onclick="manageLoyaltyPoints(${member.guest_id})" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

// Modal functions
function openBillingModal() {
    loadActiveReservations('billing_reservation_id');
    document.getElementById('billing-modal').classList.remove('hidden');
}

function closeBillingModal() {
    document.getElementById('billing-modal').classList.add('hidden');
    document.getElementById('billing-form').reset();
}

function openDiscountModal() {
    loadPendingBills('discount_bill_id');
    document.getElementById('discount-modal').classList.remove('hidden');
}

function closeDiscountModal() {
    document.getElementById('discount-modal').classList.add('hidden');
    document.getElementById('discount-form').reset();
}

function openVoucherModal() {
    document.getElementById('voucher-modal').classList.remove('hidden');
}

function closeVoucherModal() {
    document.getElementById('voucher-modal').classList.add('hidden');
    document.getElementById('voucher-form').reset();
}

function openLoyaltyModal() {
    loadAllGuests('loyalty_guest_id');
    document.getElementById('loyalty-modal').classList.remove('hidden');
}

function closeLoyaltyModal() {
    document.getElementById('loyalty-modal').classList.add('hidden');
    document.getElementById('loyalty-form').reset();
}

// Form handlers
function handleBillingSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    fetch('../../api/create-bill.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Bill created successfully!', 'success');
            closeBillingModal();
            loadBills();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error creating bill', 'error');
        }
    })
    .catch(error => {
        console.error('Error creating bill:', error);
        HotelPMS.Utils.showNotification('Error creating bill', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function handleDiscountSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Applying...';
    
    fetch('../../api/apply-discount.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Discount applied successfully!', 'success');
            closeDiscountModal();
            loadDiscounts();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error applying discount', 'error');
        }
    })
    .catch(error => {
        console.error('Error applying discount:', error);
        HotelPMS.Utils.showNotification('Error applying discount', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function handleVoucherSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    
    fetch('../../api/create-voucher.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Voucher created successfully!', 'success');
            closeVoucherModal();
            loadVouchers();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error creating voucher', 'error');
        }
    })
    .catch(error => {
        console.error('Error creating voucher:', error);
        HotelPMS.Utils.showNotification('Error creating voucher', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function handleLoyaltySubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    fetch('../../api/process-loyalty.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Loyalty points processed successfully!', 'success');
            closeLoyaltyModal();
            loadLoyalty();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error processing loyalty points', 'error');
        }
    })
    .catch(error => {
        console.error('Error processing loyalty points:', error);
        HotelPMS.Utils.showNotification('Error processing loyalty points', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Utility functions
function loadActiveReservations(selectId) {
    fetch('../../api/get-active-reservations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Select Reservation</option>';
                data.reservations.forEach(reservation => {
                    select.innerHTML += `<option value="${reservation.id}">${reservation.guest_name} - Room ${reservation.room_number}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading reservations:', error);
        });
}

function loadPendingBills(selectId) {
    fetch('../../api/get-pending-bills.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Select Bill</option>';
                data.bills.forEach(bill => {
                    select.innerHTML += `<option value="${bill.id}">${bill.bill_number} - ${bill.guest_name} - ₱${parseFloat(bill.total_amount).toFixed(2)}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading bills:', error);
        });
}

function loadAllGuests(selectId) {
    fetch('../../api/get-all-guests.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Select Guest</option>';
                data.guests.forEach(guest => {
                    select.innerHTML += `<option value="${guest.id}">${guest.first_name} ${guest.last_name}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading guests:', error);
        });
}

// Helper functions for styling
function getBillStatusClass(status) {
    switch (status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'paid': return 'bg-green-100 text-green-800';
        case 'overdue': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getBillStatusLabel(status) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function getPaymentMethodClass(method) {
    switch (method) {
        case 'cash': return 'bg-green-100 text-green-800';
        case 'credit_card': return 'bg-blue-100 text-blue-800';
        case 'debit_card': return 'bg-purple-100 text-purple-800';
        case 'bank_transfer': return 'bg-orange-100 text-orange-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getPaymentMethodLabel(method) {
    return method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function getDiscountTypeClass(type) {
    switch (type) {
        case 'percentage': return 'bg-blue-100 text-blue-800';
        case 'fixed': return 'bg-green-100 text-green-800';
        case 'loyalty': return 'bg-purple-100 text-purple-800';
        case 'promotional': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getDiscountTypeLabel(type) {
    return type.charAt(0).toUpperCase() + type.slice(1);
}

function getVoucherTypeClass(type) {
    switch (type) {
        case 'percentage': return 'bg-blue-100 text-blue-800';
        case 'fixed': return 'bg-green-100 text-green-800';
        case 'free_night': return 'bg-purple-100 text-purple-800';
        case 'upgrade': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getVoucherTypeLabel(type) {
    return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function getVoucherStatusClass(status) {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'used': return 'bg-blue-100 text-blue-800';
        case 'expired': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getVoucherStatusLabel(status) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function getLoyaltyTierClass(tier) {
    switch (tier) {
        case 'bronze': return 'bg-orange-100 text-orange-800';
        case 'silver': return 'bg-gray-100 text-gray-800';
        case 'gold': return 'bg-yellow-100 text-yellow-800';
        case 'platinum': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getLoyaltyTierLabel(tier) {
    return tier.charAt(0).toUpperCase() + tier.slice(1);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Action functions
function viewBill(billId) {
    window.open(`../../api/generate-bill-pdf.php?id=${billId}`, '_blank');
}

function printBill(billId) {
    window.open(`../../api/generate-bill-pdf.php?id=${billId}`, '_blank');
}

function processPayment(billId) {
    HotelPMS.Utils.showNotification('Payment processing feature coming soon', 'info');
}

function viewPayment(paymentId) {
    window.open(`../../api/generate-receipt-pdf.php?id=${paymentId}`, '_blank');
}

function printReceipt(paymentId) {
    window.open(`../../api/print-receipt.php?id=${paymentId}`, '_blank');
}

function viewLoyaltyHistory(guestId) {
    HotelPMS.Utils.showNotification('Loyalty history feature coming soon', 'info');
}

function manageLoyaltyPoints(guestId) {
    document.getElementById('loyalty_guest_id').value = guestId;
    openLoyaltyModal();
}
