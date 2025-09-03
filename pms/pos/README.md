# Hotel POS System

A comprehensive Point of Sale (POS) system designed specifically for hotel operations, integrated with the Hotel Property Management System (PMS).

## 🏨 **System Overview**

The Hotel POS System is a modern, web-based point of sale solution that handles all revenue-generating services within the hotel, including:

- **Restaurant & Dining Services**
- **Room Service & In-Room Dining**
- **Spa & Wellness Services**
- **Gift Shop & Retail Operations**
- **Event & Conference Services**
- **Quick Sales & Express Checkout**

## 🚀 **Key Features**

### **Multi-Service POS**
- **Restaurant POS**: Table management, menu ordering, kitchen coordination
- **Room Service POS**: In-room dining, delivery tracking, guest preferences
- **Spa POS**: Treatment booking, wellness services, appointment management
- **Gift Shop POS**: Retail inventory, souvenir sales, local products
- **Event Services POS**: Conference catering, banquet services, audio-visual

### **Advanced Order Management**
- Real-time order tracking
- Status updates (pending → preparing → ready → completed)
- Priority handling for VIP guests
- Special requests and allergy management
- Delivery time scheduling

### **Guest Integration**
- Seamless integration with hotel guest database
- Room charge capabilities
- Guest preference tracking
- Loyalty program integration
- Multi-language support

### **Financial Management**
- Multiple payment methods (cash, card, mobile, room charge)
- Automatic tax calculation (12% VAT)
- Discount management (senior citizen, PWD, bulk orders)
- Service fee handling
- Detailed financial reporting

### **Inventory Management**
- Real-time stock tracking
- Low stock alerts
- Supplier management
- Cost analysis
- Waste tracking

## 🏗️ **System Architecture**

### **Database Structure**
```
pos_transactions      - Main transaction records
pos_menu_items       - Menu and service items
pos_tables           - Restaurant table management
pos_orders           - Order details and status
pos_payments         - Payment processing records
pos_inventory        - Stock and inventory management
pos_categories       - Service and item categories
pos_discounts        - Discount rules and promotions
pos_tax_rates        - Tax configuration
```

### **File Structure**
```
pms/pos/
├── index.php                    # Main POS dashboard
├── includes/
│   └── pos-functions.php       # Core POS functions
├── restaurant/                  # Restaurant POS module
│   └── index.php               # Restaurant operations
├── room-service/                # Room service POS module
│   └── index.php               # Room service operations
├── spa/                         # Spa & wellness POS
├── gift-shop/                   # Gift shop POS
├── events/                      # Event services POS
├── quick-sales/                 # Quick sales POS
├── database/
│   └── pos_schema.sql          # Database schema
└── README.md                    # This documentation
```

## 🎯 **Getting Started**

### **Prerequisites**
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

### **Installation**

1. **Database Setup**
   ```sql
   -- Import the POS schema
   mysql -u username -p database_name < pms/pos/database/pos_schema.sql
   ```

2. **Configuration**
   - Ensure database connection is configured in `../booking/includes/config.php`
   - Verify user permissions and roles

3. **Access the System**
   - Navigate to: `http://localhost/seait/pms/pos/`
   - Login with your hotel PMS credentials

### **Default Data**
The system comes pre-loaded with:
- Sample menu items (appetizers, main courses, desserts, beverages)
- Restaurant table configuration
- Tax rates (12% VAT)
- Sample discounts (senior citizen, PWD, bulk orders)

## 📱 **User Interface**

### **Dashboard Features**
- **Statistics Cards**: Today's sales, transactions, active orders, monthly revenue
- **Service Categories**: Quick access to different POS modules
- **Recent Transactions**: Latest order activity
- **Real-time Updates**: Live data refresh

### **Responsive Design**
- Mobile-friendly interface
- Touch-optimized controls
- Adaptive layouts for different screen sizes
- Modern UI with smooth animations

## 🔧 **Configuration**

### **Tax Rates**
```sql
-- Update tax rates in pos_tax_rates table
UPDATE pos_tax_rates SET rate = 12.00 WHERE name = 'VAT';
```

### **Service Fees**
```php
// Modify service fees in pos-functions.php
const ROOM_SERVICE_FEE = 150.00;
const DELIVERY_FEE = 50.00;
```

### **Payment Methods**
```sql
-- Add new payment methods
ALTER TABLE pos_transactions 
MODIFY COLUMN payment_method ENUM('cash','credit-card','debit-card','mobile-payment','room-charge','gift-card');
```

## 📊 **Reporting & Analytics**

### **Sales Reports**
- Daily, weekly, monthly sales summaries
- Service type performance analysis
- Payment method distribution
- Guest spending patterns

### **Operational Reports**
- Order volume by time period
- Table utilization rates
- Kitchen performance metrics
- Delivery time analysis

### **Financial Reports**
- Revenue by service category
- Cost of goods sold
- Profit margin analysis
- Tax reporting

## 🔐 **Security Features**

### **User Authentication**
- Session-based authentication
- Role-based access control
- Secure password handling
- Activity logging

### **Data Protection**
- SQL injection prevention
- XSS protection
- CSRF token validation
- Input sanitization

### **Audit Trail**
- Complete transaction logging
- User action tracking
- Change history
- Compliance reporting

## 🚀 **API Integration**

### **External Systems**
- Payment gateway integration
- Inventory management systems
- Accounting software
- Customer relationship management

### **Webhook Support**
- Order status notifications
- Payment confirmations
- Inventory updates
- Guest communication

## 🧪 **Testing & Development**

### **Development Environment**
```bash
# Clone the repository
git clone [repository-url]

# Set up local database
mysql -u root -p < pms/pos/database/pos_schema.sql

# Configure virtual host
# Point to pms/pos/ directory

# Access system
http://localhost/seait/pms/pos/
```

### **Testing Scenarios**
- **Order Processing**: Complete order lifecycle testing
- **Payment Processing**: Multiple payment method testing
- **Guest Management**: Guest search and selection
- **Inventory Management**: Stock updates and alerts

## 📈 **Performance Optimization**

### **Database Optimization**
- Indexed queries for fast searches
- Connection pooling
- Query optimization
- Caching strategies

### **Frontend Optimization**
- Lazy loading of menu items
- Debounced search inputs
- Efficient DOM manipulation
- Minimal API calls

## 🆘 **Troubleshooting**

### **Common Issues**

1. **Database Connection Errors**
   - Verify database credentials
   - Check database server status
   - Ensure proper permissions

2. **Menu Items Not Loading**
   - Check database connection
   - Verify menu items exist
   - Check category filtering

3. **Payment Processing Issues**
   - Verify payment method configuration
   - Check transaction logging
   - Review error logs

### **Error Logs**
- Check PHP error logs
- Review database error logs
- Monitor application logs
- Debug console output

## 🔄 **Updates & Maintenance**

### **Regular Maintenance**
- Database optimization
- Log file cleanup
- Performance monitoring
- Security updates

### **Backup Procedures**
- Daily database backups
- Configuration file backups
- Code repository backups
- Disaster recovery planning

## 📞 **Support & Contact**

### **Technical Support**
- Check error logs first
- Review this documentation
- Contact system administrator
- Submit bug reports

### **Feature Requests**
- Submit enhancement requests
- Provide use case scenarios
- Suggest improvements
- Vote on existing requests

## 📄 **License & Legal**

### **Usage Rights**
- Educational and training purposes
- Hotel operations management
- Commercial hotel use
- Customization and modification allowed

### **Attribution**
- Maintain system branding
- Credit original developers
- Respect intellectual property
- Follow license terms

---

## 🎉 **Quick Start Checklist**

- [ ] Database schema imported
- [ ] Configuration files updated
- [ ] User permissions set
- [ ] Sample data loaded
- [ ] System accessed successfully
- [ ] Test order created
- [ ] Payment processed
- [ ] Reports generated

---

**Hotel POS System** - Streamlining hotel operations with modern point of sale technology.

*For technical support or questions, please refer to the troubleshooting section or contact your system administrator.*
