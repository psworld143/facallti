// Management & Reports JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeManagementReports();
    loadCharts();
    loadDailyReports();
});

function initializeManagementReports() {
    switchReportTab('daily');
    
    // Initialize filter change listeners
    document.getElementById('daily-date-filter').addEventListener('change', loadDailyReports);
    document.getElementById('weekly-date-filter').addEventListener('change', loadWeeklyReports);
    document.getElementById('monthly-date-filter').addEventListener('change', loadMonthlyReports);
    document.getElementById('inventory-category-filter').addEventListener('change', loadInventoryReports);
    
    // Initialize form handlers
    document.getElementById('add-item-form').addEventListener('submit', handleAddItemSubmit);
}

// Tab switching functionality
function switchReportTab(tabName) {
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
        case 'daily': loadDailyReports(); break;
        case 'weekly': loadWeeklyReports(); break;
        case 'monthly': loadMonthlyReports(); break;
        case 'inventory': loadInventoryReports(); break;
    }
}

// Chart initialization
function loadCharts() {
    loadOccupancyChart();
    loadRevenueChart();
}

function loadOccupancyChart() {
    fetch('../../api/get-occupancy-data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ctx = document.getElementById('occupancyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Occupancy Rate (%)',
                            data: data.values,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading occupancy chart:', error);
        });
}

function loadRevenueChart() {
    fetch('../../api/get-revenue-data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ctx = document.getElementById('revenueChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Revenue (₱)',
                            data: data.values,
                            backgroundColor: '#10B981',
                            borderColor: '#059669',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₱' + value.toLocaleString();
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading revenue chart:', error);
        });
}

// Report loading functions
function loadDailyReports() {
    const dateFilter = document.getElementById('daily-date-filter').value;
    const params = new URLSearchParams({ date: dateFilter });
    
    fetch(`../../api/get-daily-reports.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayDailyReports(data.reports);
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading daily reports', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading daily reports:', error);
            HotelPMS.Utils.showNotification('Error loading daily reports', 'error');
        });
}

function loadWeeklyReports() {
    const weekFilter = document.getElementById('weekly-date-filter').value;
    const params = new URLSearchParams({ week: weekFilter });
    
    fetch(`../../api/get-weekly-reports.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayWeeklyReports(data.reports);
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading weekly reports', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading weekly reports:', error);
            HotelPMS.Utils.showNotification('Error loading weekly reports', 'error');
        });
}

function loadMonthlyReports() {
    const monthFilter = document.getElementById('monthly-date-filter').value;
    const params = new URLSearchParams({ month: monthFilter });
    
    fetch(`../../api/get-monthly-reports.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMonthlyReports(data.reports);
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading monthly reports', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading monthly reports:', error);
            HotelPMS.Utils.showNotification('Error loading monthly reports', 'error');
        });
}

function loadInventoryReports() {
    const categoryFilter = document.getElementById('inventory-category-filter').value;
    const params = new URLSearchParams({ category: categoryFilter });
    
    fetch(`../../api/get-inventory-reports.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayInventoryReports(data.reports);
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading inventory reports', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading inventory reports:', error);
            HotelPMS.Utils.showNotification('Error loading inventory reports', 'error');
        });
}

// Display functions
function displayDailyReports(reports) {
    const container = document.getElementById('daily-reports-container');
    
    if (!reports || reports.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No daily reports found</h3>
                <p class="text-gray-500">No reports match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupancy</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-ins</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-outs</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Room Rate</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${reports.map(report => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${formatDate(report.date)}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getOccupancyClass(report.occupancy_rate)}">
                                    ${report.occupancy_rate}%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.revenue).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${report.check_ins}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${report.check_outs}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.avg_room_rate).toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function displayWeeklyReports(reports) {
    const container = document.getElementById('weekly-reports-container');
    
    if (!reports || reports.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No weekly reports found</h3>
                <p class="text-gray-500">No reports match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Week</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Occupancy</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Guests</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Room Rate</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RevPAR</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${reports.map(report => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${report.week}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getOccupancyClass(report.avg_occupancy)}">
                                    ${report.avg_occupancy}%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.total_revenue).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${report.total_guests}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.avg_room_rate).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.revpar).toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function displayMonthlyReports(reports) {
    const container = document.getElementById('monthly-reports-container');
    
    if (!reports || reports.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No monthly reports found</h3>
                <p class="text-gray-500">No reports match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Occupancy</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Guests</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg. Room Rate</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RevPAR</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ADR</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${reports.map(report => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${report.month}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getOccupancyClass(report.avg_occupancy)}">
                                    ${report.avg_occupancy}%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.total_revenue).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${report.total_guests}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.avg_room_rate).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.revpar).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">₱${parseFloat(report.adr).toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function displayInventoryReports(reports) {
    const container = document.getElementById('inventory-reports-container');
    
    if (!reports || reports.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-boxes text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No inventory reports found</h3>
                <p class="text-gray-500">No reports match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min. Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${reports.map(report => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${report.item_name}</div>
                                <div class="text-sm text-gray-500">₱${parseFloat(report.unit_price).toFixed(2)}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${report.category_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${report.current_stock}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${report.minimum_stock}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getStockStatusClass(report.current_stock, report.minimum_stock)}">
                                    ${getStockStatusLabel(report.current_stock, report.minimum_stock)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(report.last_updated)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

// Report generation functions
function generateReport(type) {
    const params = new URLSearchParams({ type: type });
    
    fetch(`../../api/generate-report.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                HotelPMS.Utils.showNotification(`${type.charAt(0).toUpperCase() + type.slice(1)} report generated successfully!`, 'success');
                if (data.download_url) {
                    window.open(data.download_url, '_blank');
                }
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error generating report', 'error');
            }
        })
        .catch(error => {
            console.error('Error generating report:', error);
            HotelPMS.Utils.showNotification('Error generating report', 'error');
        });
}

function exportReport(type) {
    let params = new URLSearchParams({ type: type });
    
    switch(type) {
        case 'daily':
            const dailyDate = document.getElementById('daily-date-filter').value;
            if (dailyDate) params.append('date', dailyDate);
            break;
        case 'weekly':
            const weeklyDate = document.getElementById('weekly-date-filter').value;
            if (weeklyDate) params.append('week', weeklyDate);
            break;
        case 'monthly':
            const monthlyDate = document.getElementById('monthly-date-filter').value;
            if (monthlyDate) params.append('month', monthlyDate);
            break;
        case 'inventory':
            const category = document.getElementById('inventory-category-filter').value;
            if (category) params.append('category', category);
            break;
    }
    
    fetch(`../../api/export-report.php?${params}`)
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${type}_report_${new Date().toISOString().split('T')[0]}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            HotelPMS.Utils.showNotification('Report exported successfully!', 'success');
        })
        .catch(error => {
            console.error('Error exporting report:', error);
            HotelPMS.Utils.showNotification('Error exporting report', 'error');
        });
}

// Inventory management functions
function openInventoryModal() {
    document.getElementById('inventory-modal').classList.remove('hidden');
    switchInventoryTab('items');
    loadInventoryItems();
}

function closeInventoryModal() {
    document.getElementById('inventory-modal').classList.add('hidden');
}

function switchInventoryTab(tabName) {
    document.querySelectorAll('.inventory-content').forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('active');
    });
    
    document.querySelectorAll('.inventory-tab-button').forEach(button => {
        button.classList.remove('active');
        button.classList.remove('border-primary', 'text-primary');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    const selectedContent = document.getElementById(`inventory-content-${tabName}`);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('active');
    }
    
    const selectedButton = document.getElementById(`inventory-tab-${tabName}`);
    if (selectedButton) {
        selectedButton.classList.add('active', 'border-primary', 'text-primary');
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
    }
    
    switch(tabName) {
        case 'items': loadInventoryItems(); break;
        case 'categories': loadInventoryCategories(); break;
        case 'transactions': loadInventoryTransactions(); break;
    }
}

function loadInventoryItems() {
    fetch('../../api/get-inventory-items.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayInventoryItems(data.items);
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading inventory items', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading inventory items:', error);
            HotelPMS.Utils.showNotification('Error loading inventory items', 'error');
        });
}

function displayInventoryItems(items) {
    const container = document.getElementById('inventory-items-container');
    
    if (!items || items.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-box text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No inventory items found</h3>
                <p class="text-gray-500">Add some items to get started.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${items.map(item => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${item.item_name}</div>
                                <div class="text-sm text-gray-500">₱${parseFloat(item.unit_price).toFixed(2)}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${item.category_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${item.current_stock} / ${item.minimum_stock}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getStockStatusClass(item.current_stock, item.minimum_stock)}">
                                    ${getStockStatusLabel(item.current_stock, item.minimum_stock)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="editInventoryItem(${item.id})" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="adjustStock(${item.id})" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-plus-minus"></i>
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
function openAddItemModal() {
    loadInventoryCategories('category_id');
    document.getElementById('add-item-modal').classList.remove('hidden');
}

function closeAddItemModal() {
    document.getElementById('add-item-modal').classList.add('hidden');
    document.getElementById('add-item-form').reset();
}

// Form handlers
function handleAddItemSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
    
    fetch('../../api/add-inventory-item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Inventory item added successfully!', 'success');
            closeAddItemModal();
            loadInventoryItems();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error adding inventory item', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding inventory item:', error);
        HotelPMS.Utils.showNotification('Error adding inventory item', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Utility functions
function loadInventoryCategories(selectId) {
    fetch('../../api/get-inventory-categories.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Select Category</option>';
                data.categories.forEach(category => {
                    select.innerHTML += `<option value="${category.id}">${category.name}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading categories:', error);
        });
}

function loadInventoryCategories() {
    fetch('../../api/get-inventory-categories.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayInventoryCategories(data.categories);
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading categories', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading categories:', error);
            HotelPMS.Utils.showNotification('Error loading categories', 'error');
        });
}

function displayInventoryCategories(categories) {
    const container = document.getElementById('inventory-categories-container');
    
    if (!categories || categories.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-tags text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No categories found</h3>
                <p class="text-gray-500">Add some categories to get started.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items Count</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${categories.map(category => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${category.name}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">${category.description || 'N/A'}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${category.items_count}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="editCategory(${category.id})" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteCategory(${category.id})" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
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

function loadInventoryTransactions() {
    fetch('../../api/get-inventory-transactions.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayInventoryTransactions(data.transactions);
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading transactions', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading transactions:', error);
            HotelPMS.Utils.showNotification('Error loading transactions', 'error');
        });
}

function displayInventoryTransactions(transactions) {
    const container = document.getElementById('inventory-transactions-container');
    
    if (!transactions || transactions.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-exchange-alt text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No transactions found</h3>
                <p class="text-gray-500">No inventory transactions recorded.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${transactions.map(transaction => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(transaction.transaction_date)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${transaction.item_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getTransactionTypeClass(transaction.transaction_type)}">
                                    ${getTransactionTypeLabel(transaction.transaction_type)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${transaction.quantity}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">${transaction.reason || 'N/A'}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${transaction.user_name}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

// Helper functions for styling
function getOccupancyClass(rate) {
    if (rate >= 80) return 'bg-green-100 text-green-800';
    if (rate >= 60) return 'bg-yellow-100 text-yellow-800';
    return 'bg-red-100 text-red-800';
}

function getStockStatusClass(current, minimum) {
    if (current <= minimum) return 'bg-red-100 text-red-800';
    if (current <= minimum * 1.5) return 'bg-yellow-100 text-yellow-800';
    return 'bg-green-100 text-green-800';
}

function getStockStatusLabel(current, minimum) {
    if (current <= minimum) return 'Low Stock';
    if (current <= minimum * 1.5) return 'Warning';
    return 'In Stock';
}

function getTransactionTypeClass(type) {
    switch (type) {
        case 'in': return 'bg-green-100 text-green-800';
        case 'out': return 'bg-red-100 text-red-800';
        case 'adjustment': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getTransactionTypeLabel(type) {
    switch (type) {
        case 'in': return 'Stock In';
        case 'out': return 'Stock Out';
        case 'adjustment': return 'Adjustment';
        default: return type;
    }
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

// Action functions (placeholder implementations)
function editInventoryItem(itemId) {
    HotelPMS.Utils.showNotification('Edit inventory item feature coming soon', 'info');
}

function adjustStock(itemId) {
    HotelPMS.Utils.showNotification('Adjust stock feature coming soon', 'info');
}

function editCategory(categoryId) {
    HotelPMS.Utils.showNotification('Edit category feature coming soon', 'info');
}

function deleteCategory(categoryId) {
    HotelPMS.Utils.showNotification('Delete category feature coming soon', 'info');
}

function openAddCategoryModal() {
    HotelPMS.Utils.showNotification('Add category feature coming soon', 'info');
}

function openAddTransactionModal() {
    HotelPMS.Utils.showNotification('Add transaction feature coming soon', 'info');
}
