<?php
$page_title = "Welcome to Your Shopping Portal";
include 'includes/header.php';

// Get category filter if set
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;

// Get search query if set
$search_query = isset($_GET['q']) ? sanitize_input($_GET['q']) : null;

// Fetch products based on filters
if ($search_query) {
    $products = search_products($conn, $search_query, 20);
    $page_subtitle = "Search results for: \"" . htmlspecialchars($search_query) . "\"";
} else {
    $products = get_products_by_category($conn, $category_filter, 20);
    if ($category_filter) {
        $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->bind_param("i", $category_filter);
        $stmt->execute();
        $category_result = $stmt->get_result();
        $category_name = $category_result->fetch_assoc()['name'] ?? 'Unknown Category';
        $page_subtitle = "Products in: " . htmlspecialchars($category_name);
    } else {
        $page_subtitle = "Discover amazing products at great prices";
    }
}
?>

<div class="container">
    <!-- Hero Section -->
    <?php if (!$search_query && !$category_filter): ?>
    <section class="hero-section">
        <div class="page-header">
            <h1 class="page-title">Welcome to <?php echo SITE_NAME; ?></h1>
            <p class="page-subtitle"><?php echo $page_subtitle; ?></p>
        </div>
        
        <!-- Featured Categories (Optional) -->
        <div class="featured-categories" style="margin-bottom: 3rem;">
            <h2 style="text-align: center; margin-bottom: 2rem; color: var(--text-color);">Shop by Category</h2>
            <div class="categories-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <?php
                $categories = get_categories($conn);
                foreach ($categories as $category):
                ?>
                    <a href="index.php?category=<?php echo $category['id']; ?>" class="category-card" style="background: var(--white); padding: 1.5rem; border-radius: 0.5rem; box-shadow: var(--shadow); text-decoration: none; text-align: center; transition: transform 0.2s; color: var(--text-color);">
                        <h3 style="margin-bottom: 0.5rem; color: var(--primary-color);"><?php echo htmlspecialchars($category['name']); ?></h3>
                        <p style="color: var(--text-muted); font-size: 0.875rem;"><?php echo htmlspecialchars($category['description']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php else: ?>
    <div class="page-header">
        <h1 class="page-title">
            <?php echo $search_query ? 'Search Results' : 'Products'; ?>
        </h1>
        <p class="page-subtitle"><?php echo $page_subtitle; ?></p>
    </div>
    <?php endif; ?>

    <!-- Products Section -->
    <section class="products-section">
        <div class="products-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="color: var(--text-color); margin: 0;">
                <?php echo $search_query ? 'Results' : ($category_filter ? 'Category Products' : 'Featured Products'); ?>
            </h2>
            
            <!-- Sort/Filter Options (can be expanded later) -->
            <div class="products-actions" style="display: flex; gap: 1rem; align-items: center;">
                <span style="color: var(--text-muted); font-size: 0.875rem;">
                    <?php echo count($products); ?> products found
                </span>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="no-products" style="text-align: center; padding: 3rem 1rem; background: var(--white); border-radius: 0.5rem; box-shadow: var(--shadow);">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="color: var(--text-muted); margin-bottom: 1rem;">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="21 21l-4.35-4.35"></path>
                </svg>
                <h3 style="color: var(--text-color); margin-bottom: 0.5rem;">No Products Found</h3>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                    <?php echo $search_query ? "No products match your search criteria." : "No products available in this category."; ?>
                </p>
                <?php if ($search_query || $category_filter): ?>
                    <a href="index.php" class="btn btn-primary">View All Products</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if (!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     style="width: 100%; height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 200px; background-color: var(--background-color); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                                    No Image Available
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-description">
                                <?php 
                                $description = htmlspecialchars($product['description']);
                                echo strlen($description) > 100 ? substr($description, 0, 97) . '...' : $description;
                                ?>
                            </p>
                            <div class="product-price"><?php echo format_price($product['price']); ?></div>
                            
                            <!-- Stock Status -->
                            <div class="product-stock" style="margin-bottom: 0.75rem;">
                                <?php if ($product['stock_quantity'] > 0): ?>
                                    <span style="color: var(--success-color); font-size: 0.8125rem;">
                                        ✓ In Stock (<?php echo $product['stock_quantity']; ?> available)
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--error-color); font-size: 0.8125rem;">
                                        ✗ Out of Stock
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-actions">
                                <?php if ($product['stock_quantity'] > 0): ?>
                                    <?php if (is_logged_in()): ?>
                                        <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                                                class="btn btn-primary btn-small add-to-cart-btn"
                                                data-product-id="<?php echo $product['id']; ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="9" cy="21" r="1"></circle>
                                                <circle cx="20" cy="21" r="1"></circle>
                                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                            </svg>
                                            Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <a href="auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                           class="btn btn-primary btn-small">
                                            Login to Buy
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-small" disabled>
                                        Out of Stock
                                    </button>
                                <?php endif; ?>
                                
                                <a href="product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-outline btn-small">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Call to Action Section (for non-logged in users) -->
    <?php if (!is_logged_in() && !empty($products)): ?>
    <section class="cta-section" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: var(--white); padding: 3rem 2rem; border-radius: 1rem; text-align: center; margin-top: 4rem;">
        <h2 style="font-size: 2rem; margin-bottom: 1rem;">Ready to Start Shopping?</h2>
        <p style="font-size: 1.125rem; margin-bottom: 2rem; opacity: 0.9;">
            Join thousands of satisfied customers and enjoy exclusive deals!
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="auth/register.php" class="btn btn-large" style="background: var(--white); color: var(--primary-color);">
                Sign Up Now
            </a>
            <a href="auth/login.php" class="btn btn-large btn-outline" style="border-color: var(--white); color: var(--white);">
                Already have an account?
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features Section -->
    <?php if (!$search_query && !$category_filter): ?>
    <section class="features-section" style="margin-top: 4rem; padding: 3rem 0;">
        <h2 style="text-align: center; margin-bottom: 3rem; color: var(--text-color);">Why Choose <?php echo SITE_NAME; ?>?</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div style="text-align: center; padding: 2rem; background: var(--white); border-radius: 0.5rem; box-shadow: var(--shadow);">
                <div style="width: 60px; height: 60px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--white);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <h3 style="margin-bottom: 1rem; color: var(--text-color);">Fast Delivery</h3>
                <p style="color: var(--text-muted);">Quick and reliable delivery to your doorstep within Lagos and nationwide.</p>
            </div>
            
            <div style="text-align: center; padding: 2rem; background: var(--white); border-radius: 0.5rem; box-shadow: var(--shadow);">
                <div style="width: 60px; height: 60px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--white);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4"/>
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                </div>
                <h3 style="margin-bottom: 1rem; color: var(--text-color);">Quality Products</h3>
                <p style="color: var(--text-muted);">Carefully curated products from trusted sellers and brands.</p>
            </div>
            
            <div style="text-align: center; padding: 2rem; background: var(--white); border-radius: 0.5rem; box-shadow: var(--shadow);">
                <div style="width: 60px; height: 60px; background: var(--warning-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--white);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="3" width="15" height="13"/>
                        <polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/>
                        <circle cx="5.5" cy="18.5" r="2.5"/>
                        <circle cx="18.5" cy="18.5" r="2.5"/>
                    </svg>
                </div>
                <h3 style="margin-bottom: 1rem; color: var(--text-color);">Secure Payment</h3>
                <p style="color: var(--text-muted);">Safe and secure payment options with buyer protection.</p>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Additional JavaScript for this page -->
<script>
// Add any page-specific JavaScript here
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to category cards
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add hover effects to product cards
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = 'var(--shadow-lg)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'var(--shadow)';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>