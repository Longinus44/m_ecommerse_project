-- E-commerce Portal Database Schema
CREATE DATABASE IF NOT EXISTS ecommerce_app;

USE ecommerce_app;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(255),
    stock_quantity INT DEFAULT 0,
    category_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
);

-- Cart table
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM(
        'pending',
        'processing',
        'shipped',
        'delivered',
        'cancelled'
    ) DEFAULT 'pending',
    shipping_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
);

-- Insert sample categories
INSERT INTO
    categories (name, description)
VALUES (
        'Electronics',
        'Electronic devices and gadgets'
    ),
    (
        'Fashion',
        'Clothing, shoes, and accessories'
    ),
    (
        'Home & Garden',
        'Home appliances and garden tools'
    ),
    (
        'Books',
        'Books and educational materials'
    ),
    (
        'Sports',
        'Sports equipment and accessories'
    );

-- Insert sample products
INSERT INTO
    products (
        name,
        description,
        price,
        image_url,
        stock_quantity,
        category_id
    )
VALUES (
        'Smartphone X1',
        'Latest smartphone with advanced features',
        299.99,
        'assets/images/phone1.jpg',
        50,
        1
    ),
    (
        'Laptop Pro',
        'High-performance laptop for professionals',
        899.99,
        'assets/images/laptop1.jpg',
        25,
        1
    ),
    (
        'Wireless Headphones',
        'Premium wireless headphones with noise cancellation',
        149.99,
        'assets/images/headphones1.jpg',
        75,
        1
    ),
    (
        'Men\'s T-Shirt',
        'Comfortable cotton t-shirt',
        19.99,
        'assets/images/tshirt1.jpg',
        100,
        2
    ),
    (
        'Women\'s Dress',
        'Elegant evening dress',
        79.99,
        'assets/images/dress1.jpg',
        30,
        2
    ),
    (
        'Running Shoes',
        'Professional running shoes',
        89.99,
        'assets/images/shoes1.jpg',
        60,
        2
    ),
    (
        'Coffee Maker',
        'Automatic coffee maker with timer',
        59.99,
        'assets/images/coffee1.jpg',
        40,
        3
    ),
    (
        'Vacuum Cleaner',
        'Powerful vacuum cleaner',
        129.99,
        'assets/images/vacuum1.jpg',
        20,
        3
    ),
    (
        'Programming Book',
        'Learn web development',
        29.99,
        'assets/images/book1.jpg',
        80,
        4
    ),
    (
        'Tennis Racket',
        'Professional tennis racket',
        79.99,
        'assets/images/racket1.jpg',
        35,
        5
    );