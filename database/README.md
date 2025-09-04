# NorthVia Database

This directory contains the database schema and installation scripts for the NorthVia E-commerce Platform.

## Files

- **`northvia_complete_schema.sql`** - Complete database schema with all tables, indexes, and sample data
- **`install.php`** - Interactive PHP script to install/reinstall the database
- **`README.md`** - This documentation file

## Database Schema Overview

The NorthVia database contains **47 tables** organized into the following modules:

### Core Modules
- **User Management** (7 tables) - Users, addresses, verification, password resets
- **Vendor Management** (3 tables) - Vendor profiles, locations, analytics
- **Product Catalog** (8 tables) - Products, categories, brands, variants, images, reviews
- **Order Management** (3 tables) - Orders, order items, order history
- **Payment & Financial** (6 tables) - Payment methods, transactions, invoices, receipts
- **Shopping Experience** (4 tables) - Cart items, wishlists, wishlist items

### Advanced Features
- **Inventory Management** (1 table) - Stock movements and tracking
- **Discount System** (2 tables) - Coupons and usage tracking
- **Shipping & Tax** (3 tables) - Shipping zones, rates, tax rates
- **Localization** (1 table) - Currency management
- **Media Management** (1 table) - Centralized file storage
- **Search & Analytics** (1 table) - Search terms tracking
- **Marketing** (1 table) - Campaign management
- **Communication** (2 tables) - Notifications, support tickets
- **Fraud Prevention** (3 tables) - Rules, event logs, reports

## Installation

### Method 1: Using PHP Script (Recommended)

```bash
cd database
php install.php
```

This interactive script will:
- Drop the existing database (if exists)
- Create a fresh database with all tables
- Insert sample data and default accounts
- Verify the installation

### Method 2: Manual Installation

```bash
mysql -u root -p < northvia_complete_schema.sql
```

## Default Accounts

After installation, these accounts will be available:

### Admin Accounts
- **Super Admin**: `admin@northvia.com` / `password123`
- **Admin**: `manager@northvia.com` / `password123` 
- **Support**: `support@northvia.com` / `password123`

### Demo Accounts
- **Customer**: `customer@demo.com` / `password123`
- **Vendor**: `vendor@demo.com` / `password123`

## Sample Data Included

- **4 currencies** (NGN, USD, EUR, GBP)
- **2 tax rates** (Nigerian VAT, Lagos State Tax)
- **3 shipping zones** (Lagos/Abuja, Other States, West Africa)
- **4 payment methods** (Paystack, Flutterwave, Bank Transfer, USSD)
- **8 product categories** with subcategories
- **6 brands** (Samsung, Apple, Nike, Adidas, LG, Sony)
- **5 admin/demo users** with proper roles
- **3 fraud prevention rules**
- **5 sample search terms**

## Database Features

### Performance Optimizations
- Comprehensive indexing strategy
- Full-text search on products
- Optimized queries for common operations
- Proper foreign key constraints

### Security Features
- JSON validation for JSON fields
- CHECK constraints for data validation
- Proper CASCADE rules for data integrity
- Audit trails for sensitive operations

### Scalability Features
- Partitioning-ready design for large tables
- Efficient pagination support
- Analytics tables for reporting
- Flexible metadata storage using JSON

## Environment Configuration

Make sure your `.env` file contains:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=northvia
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_general_ci
```

## Requirements

- MySQL 8.0+ or MariaDB 10.4+
- PHP 8.1+
- At least 100MB free disk space
- UTF-8 MB4 character set support

## Backup and Restore

### Create Backup
```bash
mysqldump -u root -p northvia > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restore Backup
```bash
mysql -u root -p northvia < backup_file.sql
```

## Maintenance

### Regular Tasks
- Monitor table sizes and optimize as needed
- Update search indexes periodically  
- Clean up old verification tokens
- Archive old order history records
- Update currency exchange rates

### Performance Monitoring
- Monitor slow queries
- Check index usage
- Optimize frequently accessed tables
- Consider partitioning for large tables (orders, analytics)

## Support

For database-related issues:
1. Check the installation logs for errors
2. Verify all tables were created successfully
3. Ensure proper MySQL version and configuration
4. Check foreign key constraints are working

## Schema Version

**Current Version**: 1.0.0  
**Created**: 2025-09-04  
**MySQL Version**: 8.0+  
**Charset**: utf8mb4_general_ci