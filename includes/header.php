<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';



$current_user = get_current_user_info($conn);
$cart_count = get_cart_count($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- <?php if (isset($page_title) && $page_title === 'Sign In' || $page_title === 'Sign Up'): ?>
        <link rel="stylesheet" href="assets/css/authstyles.css">
    <?php endif; ?> -->
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <!-- Logo -->
                <div class="navbar-brand">
                    <a href="index.php" class="brand-link">
                        <h1><?php echo SITE_NAME; ?></h1>
                    </a>
                </div>

                <!-- Search Bar -->
                <div class="search-container">
                    <form action="search.php" method="GET" class="search-form">
                        <input type="text" name="q" placeholder="Search products..." class="search-input" 
                               value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                        <button type="submit" class="search-btn">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="21 21l-4.35-4.35"></path>
                            </svg>
                        </button>
                    </form>
                </div>

                <!-- Navigation Links -->
                <div class="navbar-nav">
                    <a href="/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a>
                    
                    <?php if (is_logged_in()): ?>
                        <!-- Logged in user navigation -->
                        <div class="nav-dropdown">
                            <button class="nav-link dropdown-toggle">
                                Welcome, <?php echo htmlspecialchars($current_user['full_name']); ?>
                            </button>
                            <div class="dropdown-menu">
                                <a href="profile.php" class="dropdown-item">My Profile</a>
                                <a href="orders.php" class="dropdown-item">My Orders</a>
                                <div class="dropdown-divider"></div>
                                <a href="auth/logout.php" class="dropdown-item">Logout</a>
                            </div>
                        </div>
                        
                        <!-- Cart -->
                        <a href="cart/cart.php" class="nav-link cart-link">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            Cart <span class="cart-count"><?php echo $cart_count; ?></span>
                        </a>
                    <?php else: ?>
                        <!-- Guest user navigation -->
                        <a href="/auth/login.php" class="nav-link">Login</a>
                        <a href="/auth/register.php" class="nav-link btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </nav>

            <!-- Categories Navigation -->
            <div class="categories-nav">
                <div class="categories-container">
                    <a href="index.php" class="category-link <?php echo !isset($_GET['category']) ? 'active' : ''; ?>">All Products</a>
                    <?php
                    $categories = get_categories($conn);
                    foreach ($categories as $category):
                    ?>
                        <a href="index.php?category=<?php echo $category['id']; ?>" 
                           class="category-link <?php echo isset($_GET['category']) && $_GET['category'] == $category['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-content">
            <a href="index.php" class="mobile-nav-link">Home</a>
            
            <?php if (is_logged_in()): ?>
                <a href="profile.php" class="mobile-nav-link">My Profile</a>
                <a href="orders.php" class="mobile-nav-link">My Orders</a>
                <a href="cart/cart.php" class="mobile-nav-link">Cart (<?php echo $cart_count; ?>)</a>
                <a href="auth/logout.php" class="mobile-nav-link">Logout</a>
            <?php else: ?>
                <a href="auth/login.php" class="mobile-nav-link">Login</a>
                <a href="auth/register.php" class="mobile-nav-link">Sign Up</a>
            <?php endif; ?>
            
            <div class="mobile-categories">
                <h3>Categories</h3>
                <a href="index.php" class="mobile-category-link">All Products</a>
                <?php foreach ($categories as $category): ?>
                    <a href="index.php?category=<?php echo $category['id']; ?>" class="mobile-category-link">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <main class="main-content"></main>