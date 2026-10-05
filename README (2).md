# 🛒 Online Shopping Management System

A web-based **Online Shopping Management System** built with **Core PHP, MySQL/MariaDB, HTML5, CSS3, and JavaScript**. The system provides separate functionality for customers, sellers, and administrators, with product management, shopping cart, order processing, payment integration, delivery tracking, reviews, inventory management, and an AI-powered shopping assistant.

---

## 📌 Overview

The **Online Shopping Management System** is an academic web application designed to provide a complete online shopping workflow.

Customers can register, browse products, manage their shopping cart, place orders, make payments, submit reviews, and track deliveries.

Sellers can manage products, inventory, orders, sales, and commissions, while administrators can manage users, sellers, products, categories, orders, and other system activities.

The project also integrates external APIs for **AI-assisted shopping support** and **online payment processing**.

---

## ✨ Key Features

### 👤 Customer

- User registration and login
- Session-based authentication
- Product browsing
- Product search and filtering
- Category-based product browsing
- Shopping cart management
- Increase/decrease product quantity
- Remove items from cart
- Checkout
- Order placement
- Order history
- Payment processing
- Delivery tracking
- Product reviews
- AI-powered shopping assistant

### 🏪 Seller

- Seller registration
- Seller authentication
- Seller dashboard
- Product upload
- Product editing and management
- Product stock/inventory management
- Order management
- Sales information
- Commission-related management

### 🛡️ Administrator

- Admin dashboard
- User management
- Seller management
- Product management
- Category management
- Order management
- Inventory oversight
- System-level management

### 💳 Payment Integration

The project includes payment integrations for:

- **SSLCommerz**
- **bKash**

Payment-related functionality includes payment initiation and handling of success, failure, and cancellation flows.

### 🤖 AI Shopping Assistant

The project includes an AI-based shopping assistant that can help users with product-related questions.

AI integrations include:

- **Google Gemini API**
- **OpenRouter API**

The application communicates with these external services through server-side PHP API requests.

---

## 🧰 Technology Stack

| Category | Technology |
|---|---|
| Backend | Core PHP |
| Frontend | HTML5, CSS3, JavaScript |
| Database | MySQL / MariaDB |
| Database Connectivity | PHP MySQLi |
| Local Server | Apache |
| Development Environment | XAMPP |
| Client-side Requests | JavaScript Fetch API / AJAX |
| AI Integration | Google Gemini API, OpenRouter API |
| Payment Integration | SSLCommerz, bKash |
| Styling | CSS, Google Fonts, Normalize.css |
| Web Server Configuration | Apache `.htaccess` |
| Version Control | Git, GitHub |

> **Note:** The project is built using Core/Native PHP. It does not use Laravel, CodeIgniter, Zend Framework, or another PHP MVC framework.

---

## 🏗️ Application Architecture

The application follows a traditional server-side web application structure:

```text
                    ┌──────────────────────┐
                    │       Browser        │
                    │  HTML / CSS / JS     │
                    └──────────┬───────────┘
                               │
                         HTTP Requests
                               │
                               ▼
                    ┌──────────────────────┐
                    │      Core PHP        │
                    │ Application Logic    │
                    └───────┬───────┬──────┘
                            │       │
                  SQL / DB   │       │   External APIs
                            │       │
                            ▼       ▼
                 ┌──────────────┐  ┌─────────────────────┐
                 │ MySQL/MariaDB│  │ AI & Payment APIs   │
                 │   Database   │  │ Gemini / OpenRouter │
                 └──────────────┘  │ SSLCommerz / bKash │
                                   └─────────────────────┘
```

---

## 🔄 Main Shopping Flow

```text
User Registration / Login
          ↓
    Browse Products
          ↓
     Search / Filter
          ↓
      Add to Cart
          ↓
       Checkout
          ↓
 Select Payment Method
          ↓
    Create Order
          ↓
   Payment Processing
          ↓
  Order Confirmation
          ↓
   Delivery Tracking
          ↓
      Review
```

---

## 📦 Main System Modules

```text
Online Shopping Management System
│
├── Customer Module
│   ├── Registration
│   ├── Login
│   ├── Product Browsing
│   ├── Search / Filter
│   ├── Shopping Cart
│   ├── Checkout
│   ├── Orders
│   ├── Payments
│   ├── Reviews
│   └── Delivery Tracking
│
├── Seller Module
│   ├── Seller Registration
│   ├── Seller Dashboard
│   ├── Product Management
│   ├── Inventory Management
│   ├── Order Management
│   └── Sales / Commission
│
├── Admin Module
│   ├── Admin Dashboard
│   ├── User Management
│   ├── Seller Management
│   ├── Product Management
│   ├── Category Management
│   └── Order Management
│
└── External Integrations
    ├── Google Gemini API
    ├── OpenRouter API
    ├── SSLCommerz
    └── bKash
```

---

## 🗄️ Database

The system uses a relational **MySQL/MariaDB** database.

The database stores and manages information related to areas such as:

- Users
- Sellers
- Products
- Categories
- Shopping Cart
- Orders
- Order Items
- Payments
- Reviews
- Addresses
- Delivery information
- Inventory / stock

Relationships between users, products, orders, and order items allow the system to maintain structured e-commerce data.

### Database Connectivity

PHP communicates with MySQL/MariaDB using the **MySQLi extension**.

Prepared statements are used in relevant database operations to reduce the risk of SQL injection.

---

## 🔐 Security Features

The project includes several application-level security practices:

- Session-based authentication
- Password hashing
- Password verification
- Role-based access control
- Prepared SQL statements
- CSRF token protection
- Input validation
- Database transactions
- Protected configuration files

### Password Security

Passwords are handled using PHP password hashing and verification functions rather than storing plain-text passwords.

### SQL Injection Protection

Prepared statements are used for user-controlled database queries where applicable.

### CSRF Protection

CSRF tokens are used in sensitive request flows to help prevent unauthorized cross-site requests.

---

## 🌐 JavaScript and Asynchronous Requests

JavaScript is used for client-side interactions and asynchronous communication.

The project uses the browser's **Fetch API** for requests such as:

- Cart operations
- Updating cart quantities
- Removing cart items
- Communicating with the AI chat handler

Example flow:

```text
Browser
   │
   │ JavaScript Fetch()
   ▼
PHP Handler
   │
   ├── Database
   │
   └── External API
   │
   ▼
JSON Response
   │
   ▼
Browser UI
```

---

## 🤖 AI Integration

The AI shopping assistant uses external AI APIs through PHP.

### Google Gemini

The application communicates with Google's Gemini API for AI-generated responses.

### OpenRouter

The application also supports communication with the OpenRouter API.

The AI assistant is designed to provide product-related assistance and respond to user shopping queries.

> API credentials should always be stored securely and must not be committed to a public repository.

---

## 💳 Payment Integration

### SSLCommerz

SSLCommerz is integrated as an online payment gateway.

The project includes payment processing and separate handling for:

- Successful payments
- Failed payments
- Cancelled payments

### bKash

The project also contains bKash payment integration and related payment handling.

> Payment credentials must be configured locally and should never be exposed in the source code or Git repository.

---

## 🌐 Apache and URL Rewriting

The project runs on Apache and includes `.htaccess` configuration.

Apache URL rewriting is used to support cleaner application URLs.

Example:

```text
/all-products
```

can be routed to the corresponding PHP application page.

---

# ⚙️ Installation & Setup

## Prerequisites

Before running the project locally, install:

- [XAMPP](https://www.apachefriends.org/)
- PHP
- MySQL / MariaDB
- Git
- A modern web browser
- Visual Studio Code or another code editor

---

## 1. Clone the Repository

```bash
git clone https://github.com/tamanna-rifah/Online-Shopping-Management-System.git
```

Move into the project directory:

```bash
cd Online-Shopping-Management-System
```

---

## 2. Move the Project to XAMPP

Copy the project folder into:

```text
C:\xampp\htdocs\
```

For example:

```text
C:\xampp\htdocs\new_onlineshopping\
```

---

## 3. Start XAMPP

Open the XAMPP Control Panel and start:

```text
Apache
MySQL
```

Both services should be running before launching the application.

---

## 4. Create the Database

Open:

```text
http://localhost/phpmyadmin
```

Create a database:

```text
onlineshopping
```

Import the project's SQL database file into the newly created database.

If the SQL file specifies a database name, follow the name defined by the project SQL script.

---

## 5. Configure Database Connection

The local database configuration follows this structure:

```php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "onlineshopping";
```

Update these values if your local MySQL/MariaDB configuration is different.

---

## 6. Configure API and Payment Credentials

The project uses external services that require credentials, including:

- Google Gemini
- OpenRouter
- SSLCommerz
- bKash

Configure the required credentials in the appropriate **local configuration files**.

Do not upload real credentials to GitHub.

Recommended production approach:

```text
Environment Variables
        ↓
Secure Configuration
        ↓
PHP Application
        ↓
External API / Payment Gateway
```

---

## 7. Run the Application

Open the project in your browser using a URL similar to:

```text
http://localhost/new_onlineshopping/
```

The exact URL depends on the project folder name inside `htdocs`.

---

# 🔒 GitHub Security

Sensitive configuration files should remain local and should not be committed to the repository.

Examples include:

```text
.env
.env.*
ai_config.php
sslcommerz_config.php
bkash_credentials.php
bkash_config.php
```

The repository uses `.gitignore` rules to prevent sensitive/local files from being tracked.

### Important

Never publish:

- API keys
- Payment gateway credentials
- Client secrets
- Private access tokens
- Production database passwords

If a secret is accidentally pushed to a public repository, revoke/rotate it immediately.

---

# 🧪 Testing

Important application flows to test include:

### Authentication
- User registration
- User login
- Invalid login
- Logout
- Role-based access

### Products
- Product browsing
- Product search
- Category filtering
- Product management

### Cart
- Add item
- Increase quantity
- Decrease quantity
- Remove item
- Cart total calculation

### Orders
- Checkout
- Order creation
- Order history
- Order status
- Delivery tracking

### Payments
- Payment initiation
- Successful payment
- Failed payment
- Cancelled payment

### Seller
- Seller registration
- Product upload
- Product update
- Stock management
- Order management

### Admin
- User management
- Seller management
- Product management
- Category management
- Order management

### AI
- Product-related questions
- AI response generation
- API error handling

---

# 🚀 Future Improvements

Possible future improvements include:

- RESTful API architecture
- Better separation of frontend and backend
- Centralized environment configuration
- Automated unit and integration testing
- Docker support
- Cloud deployment
- Email/SMS notifications
- Advanced product recommendation
- Improved search and filtering
- Redis caching
- Centralized logging and monitoring
- Stronger API rate limiting
- More robust payment verification
- Improved secret management

---

# 📁 Project Structure

The project is organized around PHP application pages and modules.

A simplified conceptual structure is:

```text
Online-Shopping-Management-System/
│
├── Customer / User Pages
├── Seller Pages
├── Admin Pages
├── Product Management
├── Cart & Checkout
├── Order Management
├── Payment Integration
├── AI Integration
├── Database / SQL Files
├── Assets
├── .htaccess
├── .gitignore
└── README.md
```

The exact file structure may change as the project is updated.

---

# 🛠️ Development Environment

The project was developed and tested in a local PHP environment using:

```text
Operating Environment
        ↓
      XAMPP
        ↓
     Apache
        ↓
       PHP
        ↓
 MySQL / MariaDB
```

Visual Studio Code can be used as the primary development environment.

---

# 🎓 Academic Project

This project was developed as an academic **Online Shopping Management System** to apply practical concepts in:

- Web application development
- Core PHP
- Database management
- SQL
- Authentication
- Authorization
- CRUD operations
- E-commerce workflows
- Payment gateway integration
- API integration
- AI integration
- Web security
- Git and GitHub

---

# 👩‍💻 Author

**Rifah Tamanna**

Computer Science & Engineering Student  
IUBAT – International University of Business Agriculture and Technology

### GitHub

https://github.com/tamanna-rifah

### Repository

https://github.com/tamanna-rifah/Online-Shopping-Management-System

---

# 📄 License

This project is primarily intended for academic and educational purposes.

If you reuse or modify this project, appropriate attribution is appreciated.

---

## ⭐ Support

If you find the project useful, consider giving the repository a ⭐ on GitHub.
