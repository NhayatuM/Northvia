# NorthVia E-commerce Platform

![NorthVia Logo](https://via.placeholder.com/200x80/007bff/ffffff?text=NorthVia)

**NorthVia** is a comprehensive, scalable multi-vendor e-commerce marketplace built specifically for the Nigerian market with advanced fraud prevention, vendor analytics, and comprehensive order management.

## 🚀 Features

### 🛍️ **Multi-Vendor Marketplace**
- Complete vendor registration and verification system
- Vendor dashboard with analytics and reporting
- Commission-based revenue sharing
- Multiple vendor locations support

### 🛒 **Advanced E-commerce**
- Product catalog with variants and categories
- Advanced search and filtering
- Shopping cart and wishlist
- Comprehensive order management
- Multi-currency support (NGN primary)

### 💳 **Payment Integration**
- Paystack integration (Nigerian gateway)
- Flutterwave integration
- Bank transfer support
- USSD payment options
- Comprehensive payment tracking

### 🔒 **Security & Fraud Prevention**
- Advanced fraud detection rules
- Real-time fraud monitoring
- KYC verification system
- JWT-based authentication
- Rate limiting and DDoS protection

### 📊 **Analytics & Reporting**
- Vendor performance analytics
- Sales reporting and insights
- Customer behavior tracking
- Search analytics
- Fraud reports

### 🌍 **Nigerian Market Features**
- Nigerian Naira (NGN) as primary currency
- Local payment gateways integration
- Nigerian states and cities support
- VAT and local tax calculation
- Multi-language support (English, Hausa, Yoruba, Igbo)

## 📋 Requirements

- **PHP**: 8.1 or higher
- **MySQL**: 8.0+ or MariaDB 10.4+
- **Web Server**: Apache/Nginx
- **Composer**: For dependency management
- **Node.js**: 16+ (for asset building)

## 🚀 Quick Start

### 1. Clone Repository
```bash
git clone https://github.com/yourusername/northvia.git
cd northvia
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Setup
```bash
cp .env.example .env
# Edit .env with your database credentials
```

### 4. Database Installation
```bash
cd database
php install.php
```

### 5. Start Development Server
```bash
php -S localhost:8000 -t public public/router.php
```

### 6. Access Application
- **Frontend**: http://localhost:8000
- **Admin Panel**: http://localhost:8000/login (admin@northvia.com / password123)

## 📁 Project Structure

```
northvia/
├── 📂 public/                 # Web root directory
│   ├── 📄 index.html         # Landing page
│   ├── 📄 login.html         # Login page
│   ├── 📄 register.html      # Registration page
│   ├── 📄 products.html      # Product catalog
│   ├── 📄 dashboard.html     # User dashboard
│   ├── 📄 router.php         # Custom PHP router
│   └── 📂 assets/            # CSS, JS, images
├── 📂 src/                   # Backend PHP application
│   ├── 📂 Core/              # Core framework classes
│   └── 📂 Modules/           # Feature modules
├── 📂 config/                # Configuration files
├── 📂 database/              # Database schema and scripts
├── 📂 storage/               # File storage
└── 📂 vendor/                # Composer dependencies
```

## 🔐 Default Accounts

### Admin Accounts
- **Super Admin**: admin@northvia.com / password123
- **Admin Manager**: manager@northvia.com / password123
- **Support Agent**: support@northvia.com / password123

### Demo Accounts  
- **Customer**: customer@demo.com / password123
- **Vendor**: vendor@demo.com / password123

## 🛠️ Development

### Running Tests
```bash
composer test
```

### Code Quality
```bash
composer cs-check    # Check code style
composer cs-fix      # Fix code style
composer analyze     # Static analysis
```

### Database Management
```bash
# Reinstall database
cd database && php install.php

# Create backup
mysqldump -u root -p northvia > backup.sql

# Restore backup  
mysql -u root -p northvia < backup.sql
```

## 🔧 Configuration

### Environment Variables
Key environment variables in `.env`:

```env
# Application
APP_NAME="NorthVia"
APP_ENV=development
APP_URL=http://localhost:8000

# Database
DB_DATABASE=northvia
DB_USERNAME=root
DB_PASSWORD=

# Payment Gateways
PAYSTACK_PUBLIC_KEY=pk_test_xxx
PAYSTACK_SECRET_KEY=sk_test_xxx
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST_xxx
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST_xxx

# Security
JWT_SECRET=your-super-secret-key
FRAUD_DETECTION_ENABLED=true
```

## 🎯 API Endpoints

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/me` - Get user profile

### Products
- `GET /api/products` - List products (with filtering)
- `GET /api/products/{id}` - Get product details
- `GET /api/products/featured` - Get featured products
- `GET /api/products/categories` - Get categories

### Orders
- `POST /api/orders` - Create order
- `GET /api/orders` - List user orders
- `GET /api/orders/{id}` - Get order details

### Vendors
- `GET /api/vendors` - List vendors
- `GET /api/vendor/products` - Vendor products
- `GET /api/vendor/analytics` - Vendor analytics

## 🏗️ Architecture

### Backend Architecture
- **Framework**: Custom PHP framework with dependency injection
- **Database**: MySQL with comprehensive schema design  
- **Authentication**: JWT-based with refresh tokens
- **API**: RESTful API with JSON responses
- **Security**: Multi-layer security with fraud prevention

### Frontend Architecture
- **Framework**: Vanilla JavaScript with modular design
- **CSS**: Custom responsive framework
- **UI/UX**: Mobile-first, Nigerian-focused design
- **State Management**: Local storage and session management

## 🔒 Security Features

- **Authentication**: JWT tokens with refresh mechanism
- **Authorization**: Role-based access control (RBAC)
- **Input Validation**: Server and client-side validation
- **SQL Injection**: Parameterized queries and prepared statements
- **XSS Protection**: Input sanitization and output encoding
- **CSRF Protection**: Token-based CSRF prevention
- **Rate Limiting**: API and authentication rate limiting
- **Fraud Detection**: Real-time fraud monitoring and prevention

## 📊 Database Schema

The platform uses **47 tables** organized into logical modules:

- **User Management**: 7 tables
- **Vendor System**: 3 tables  
- **Product Catalog**: 8 tables
- **Order Processing**: 3 tables
- **Payment System**: 6 tables
- **Fraud Prevention**: 3 tables
- **Marketing**: 3 tables
- **Communication**: 2 tables
- **Localization**: 4 tables
- **Analytics**: 8 tables

See [database/README.md](database/README.md) for detailed schema documentation.

## 🚀 Deployment

### Production Deployment
1. Set up production server (Ubuntu/CentOS)
2. Install PHP 8.1+, MySQL 8.0+, Nginx
3. Configure SSL certificates
4. Set production environment variables
5. Run database installation
6. Configure payment gateways
7. Set up monitoring and backups

### Docker Deployment
```bash
# Coming soon - Docker configuration
docker-compose up -d
```

## 🧪 Testing

### Manual Testing
- User registration and login
- Product browsing and search
- Shopping cart functionality
- Order placement and tracking
- Vendor registration and management
- Payment processing (test mode)
- Admin panel functionality

### Automated Testing
```bash
# Unit tests
composer test

# Integration tests  
composer test:integration

# E2E tests
npm run test:e2e
```

## 📈 Performance

- **Database**: Optimized indexes and queries
- **Caching**: Redis for session and API caching
- **CDN**: CloudFlare integration for static assets  
- **Images**: Optimized image delivery
- **API**: Response caching and pagination
- **Monitoring**: Performance monitoring and alerting

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)  
5. Open Pull Request

### Development Guidelines
- Follow PSR-12 coding standards
- Write comprehensive tests
- Document new features
- Follow semantic versioning

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Nigerian developers community
- Paystack and Flutterwave for payment processing
- Bootstrap for UI components
- All open-source contributors

## 📞 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/yourusername/northvia/issues)
- **Email**: support@northvia.com
- **Discord**: [NorthVia Community](https://discord.gg/northvia)

---

**Built with ❤️ for Nigeria's e-commerce ecosystem**

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)](https://mysql.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=flat&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)