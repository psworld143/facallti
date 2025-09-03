# Hotel PMS System - Independent Module Architecture

## 🏗️ **System Overview**

The Hotel PMS (Property Management System) is designed with a **modular, independent architecture** where each module has its own complete structure while sharing the same database. This approach provides:

- **Maintainability**: Each module can be developed and maintained independently
- **Scalability**: Easy to add new modules without affecting existing ones
- **Security**: Isolated access controls and error handling per module
- **Performance**: Optimized resources for each module's specific needs

## 📁 **Directory Structure**

```
pms/
├── includes/                     # Shared PMS configuration
│   ├── database.php             # Main database connection
│   └── error_handler.php        # Main error handling
├── pos/                         # POS (Point of Sale) Module
│   ├── config/                  # Module-specific configuration
│   │   └── database.php         # POS database extensions
│   ├── assets/                  # Module-specific assets
│   │   ├── css/                 # POS-specific styles
│   │   ├── js/                  # POS-specific scripts
│   │   └── images/              # POS-specific images
│   ├── includes/                # Module-specific includes
│   │   ├── pos-error-handler.php # POS error handling
│   │   ├── pos-functions.php    # POS business logic
│   │   ├── pos-header.php       # POS header component
│   │   ├── pos-sidebar.php      # POS navigation
│   │   └── pos-footer.php       # POS footer component
│   ├── modules/                 # POS sub-modules
│   │   ├── restaurant/          # Restaurant POS
│   │   ├── room-service/        # Room Service POS
│   │   ├── spa/                 # Spa & Wellness
│   │   ├── gift-shop/           # Gift Shop
│   │   ├── events/              # Event Services
│   │   └── quick-sales/         # Quick Sales
│   ├── api/                     # POS API endpoints
│   ├── index.php                # POS main dashboard
│   └── login.php                # POS login system
├── booking/                     # Booking & Reservations Module
│   ├── config/                  # Module-specific configuration
│   │   └── database.php         # Booking database extensions
│   ├── assets/                  # Module-specific assets
│   │   ├── css/                 # Booking-specific styles
│   │   ├── js/                  # Booking-specific scripts
│   │   └── images/              # Booking-specific images
│   ├── includes/                # Module-specific includes
│   ├── modules/                 # Booking sub-modules
│   ├── api/                     # Booking API endpoints
│   └── index.php                # Booking main dashboard
├── inventory/                   # Inventory Management Module
│   ├── config/                  # Module-specific configuration
│   │   └── database.php         # Inventory database extensions
│   ├── assets/                  # Module-specific assets
│   │   ├── css/                 # Inventory-specific styles
│   │   ├── js/                  # Inventory-specific scripts
│   │   └── images/              # Inventory-specific images
│   ├── includes/                # Module-specific includes
│   ├── modules/                 # Inventory sub-modules
│   ├── api/                     # Inventory API endpoints
│   └── index.php                # Inventory main dashboard
└── error_handler.php            # Main PMS error handler
```

## 🔗 **Module Independence**

### **Each Module Has:**

1. **Own Configuration** (`config/database.php`)
   - Extends main PMS database connection
   - Module-specific database operations
   - Table validation and initialization

2. **Own Assets** (`assets/`)
   - **CSS**: Module-specific styling
   - **JavaScript**: Module-specific functionality
   - **Images**: Module-specific graphics

3. **Own Includes** (`includes/`)
   - **Error Handling**: Module-specific error management
   - **Functions**: Module-specific business logic
   - **Components**: Header, sidebar, footer

4. **Own API** (`api/`)
   - Module-specific endpoints
   - Independent request handling
   - Module-specific responses

5. **Own Sub-modules** (`modules/`)
   - Organized functionality
   - Independent routing
   - Specific features

## 🗄️ **Database Architecture**

### **Shared Database: `hotel_pms_clean`**

- **Single Connection**: All modules use the same database instance
- **Shared Tables**: Common data like users, rooms, guests
- **Module Tables**: Each module has its own tables
- **Data Integrity**: Foreign key relationships between modules

### **Module-Specific Tables:**

#### **POS Module:**
- `pos_transactions` - Sales transactions
- `pos_menu_items` - Menu items and pricing
- `pos_orders` - Order details
- `pos_payments` - Payment information
- `pos_activity_log` - User activity tracking

#### **Booking Module:**
- `reservations` - Room reservations
- `check_ins` - Guest check-in records
- `billing` - Guest billing information
- `guest_feedback` - Guest reviews

#### **Inventory Module:**
- `inventory_items` - Stock items
- `inventory_categories` - Item categories
- `inventory_transactions` - Stock movements

## 🎨 **Styling Architecture**

### **Each Module Has Independent CSS:**

- **No Shared Styles**: Each module maintains its own design
- **Consistent Theme**: Similar color schemes and layouts
- **Responsive Design**: Mobile-first approach per module
- **Custom Components**: Module-specific UI elements

### **CSS Organization:**

```css
/* Module-specific classes */
.pos-dashboard { /* POS styles */ }
.booking-calendar { /* Booking styles */ }
.inventory-grid { /* Inventory styles */ }

/* Shared utility classes (if needed) */
.btn-primary { /* Common button styles */ }
.card { /* Common card styles */ }
```

## ⚡ **JavaScript Architecture**

### **Each Module Has Independent JavaScript:**

- **No Shared Scripts**: Each module manages its own functionality
- **Modular Classes**: Organized JavaScript classes per module
- **Event Handling**: Module-specific event management
- **API Integration**: Direct communication with module APIs

### **JavaScript Organization:**

```javascript
// POS Module
class POSSystem { /* POS functionality */ }

// Booking Module  
class BookingSystem { /* Booking functionality */ }

// Inventory Module
class InventorySystem { /* Inventory functionality */ }
```

## 🔐 **Security & Access Control**

### **Module-Level Security:**

- **Independent Sessions**: Each module can have its own session variables
- **Role-Based Access**: Different permissions per module
- **Error Isolation**: Module errors don't affect other modules
- **Audit Logging**: Independent activity tracking per module

### **Session Management:**

```php
// POS Module
$_SESSION['pos_user_id']
$_SESSION['pos_user_role']
$_SESSION['pos_demo_mode']

// Booking Module
$_SESSION['booking_user_id']
$_SESSION['booking_user_role']

// Inventory Module
$_SESSION['inventory_user_id']
$_SESSION['inventory_user_role']
```

## 🚀 **Benefits of This Architecture**

### **For Developers:**
- **Clear Separation**: Easy to understand what belongs where
- **Independent Development**: Work on modules without conflicts
- **Reusable Code**: Module patterns can be replicated
- **Easy Testing**: Test modules in isolation

### **For Users:**
- **Focused Interface**: Each module has its own purpose
- **Better Performance**: Optimized for specific tasks
- **Easier Navigation**: Clear module boundaries
- **Consistent Experience**: Similar patterns across modules

### **For Maintenance:**
- **Isolated Issues**: Problems in one module don't affect others
- **Easy Updates**: Update modules independently
- **Version Control**: Track changes per module
- **Backup Strategy**: Module-specific backups possible

## 📋 **Module Development Guidelines**

### **When Creating a New Module:**

1. **Create Directory Structure**
   ```bash
   mkdir -p new-module/{config,assets/{css,js,images},includes,modules,api}
   ```

2. **Extend Main Database**
   ```php
   // new-module/config/database.php
   require_once __DIR__ . '/../../includes/database.php';
   class NewModuleDatabase { /* ... */ }
   ```

3. **Create Module Assets**
   - CSS: `assets/css/module-styles.css`
   - JS: `assets/js/module-scripts.js`
   - Images: `assets/images/`

4. **Implement Error Handling**
   ```php
   // new-module/includes/module-error-handler.php
   require_once __DIR__ . '/../../includes/error_handler.php';
   class NewModuleErrorHandler { /* ... */ }
   ```

5. **Add to Main Navigation**
   - Update main PMS navigation
   - Add module links
   - Configure access permissions

## 🔧 **Configuration Examples**

### **Module Database Extension:**
```php
class POSDatabase {
    public function getPOSTables() {
        // POS-specific table operations
    }
    
    public function createTransaction($data) {
        // POS transaction creation
    }
}
```

### **Module Error Handler:**
```php
class POSErrorHandler {
    public static function handlePOSError($error, $context = '') {
        // POS-specific error handling
        // Only redirect to 505.php for critical errors
    }
}
```

### **Module-Specific Functions:**
```php
// pos/includes/pos-functions.php
function getPOSStats() {
    // POS-specific statistics
}

function processPOSPayment($data) {
    // POS payment processing
}
```

## 📱 **Responsive Design**

### **Each Module Maintains:**
- **Mobile-First Approach**: Responsive design from the start
- **Touch-Friendly Interface**: Optimized for mobile devices
- **Consistent Breakpoints**: Similar responsive behavior
- **Performance Optimization**: Fast loading on all devices

## 🎯 **Future Module Ideas**

### **Potential New Modules:**
- **Housekeeping**: Room status management
- **Maintenance**: Facility maintenance tracking
- **HR Management**: Staff management and scheduling
- **Financial**: Accounting and reporting
- **Marketing**: Promotional campaigns
- **Analytics**: Business intelligence and reporting

## 🚀 **Getting Started**

1. **Choose a Module**: Start with POS, Booking, or Inventory
2. **Explore Structure**: Understand the module organization
3. **Customize Assets**: Modify CSS and JavaScript as needed
4. **Add Features**: Extend functionality within the module
5. **Test Independently**: Ensure module works in isolation
6. **Integrate**: Connect with other modules as needed

This architecture ensures that each PMS module is **completely independent** while maintaining **consistency** and **sharing resources** where appropriate. Each module can be developed, tested, and deployed independently, making the system highly maintainable and scalable.
