<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

$search = sanitize_text($_GET['search'] ?? '');
$categoryId = is_numeric($_GET['category'] ?? '') ? (int)$_GET['category'] : 0;
$requestedType = $_GET['type'] ?? 'all';
$requestedSort = $_GET['sort'] ?? 'newest';
$type = in_array($requestedType, ['all', 'sale', 'donation'], true) ? $requestedType : 'all';
$sort = in_array($requestedSort, ['newest', 'price_asc', 'price_desc', 'location'], true) ? $requestedSort : 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$where = ['l.status = "active"'];
$params = [];
if ($search !== '') {
    $where[] = 'l.pickupLocation LIKE :search';
    $params['search'] = '%' . $search . '%';
}
if ($categoryId > 0) {
    $where[] = 'l.categoryID = :categoryID';
    $params['categoryID'] = $categoryId;
}
if ($type === 'sale') {
    $where[] = 'l.isDonation = 0';
} elseif ($type === 'donation') {
    $where[] = 'l.isDonation = 1';
}

$orderMap = [
    'newest' => 'l.createdAt DESC',
    'price_asc' => 'l.price ASC',
    'price_desc' => 'l.price DESC',
    'location' => 'l.pickupLocation ASC',
];
$orderBy = $orderMap[$sort] ?? 'l.createdAt DESC';
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM food_listings l WHERE $whereSql");
$countStmt->execute($params);
$totalResults = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalResults / $limit));

$stmt = $pdo->prepare("SELECT l.*, c.name AS categoryName, u.firstName, u.lastName FROM food_listings l LEFT JOIN categories c ON l.categoryID = c.categoryID LEFT JOIN users u ON l.sellerID = u.userID WHERE $whereSql ORDER BY $orderBy LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();

$categoryStmt = $pdo->query('SELECT categoryID, name FROM categories ORDER BY name ASC');
$categories = $categoryStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="container card">
    <h2>Browse Listings</h2>
    <form method="get" class="card">
        <div class="form-grid">
            <div class="field-group">
                <label for="search">Location</label>
                <input id="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter suburb or city">
            </div>
            <div class="field-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int)$category['categoryID']; ?>" <?php echo $categoryId === (int)$category['categoryID'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="sale" <?php echo $type === 'sale' ? 'selected' : ''; ?>>Food for Sale</option>
                    <option value="donation" <?php echo $type === 'donation' ? 'selected' : ''; ?>>Donations Only</option>
                </select>
            </div>
            <div class="field-group">
                <label for="sort">Sort By</label>
                <select id="sort" name="sort">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price Low-High</option>
                    <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price High-Low</option>
                    <option value="location" <?php echo $sort === 'location' ? 'selected' : ''; ?>>Nearest</option>
                </select>
            </div>
        </div>
        <div class="form-footer">
            <button type="submit" class="submit-button">Search</button>
        </div>
    </form>
    <?php if (empty($listings)): ?>
        <p>No listings match your search. Try a broader location or category.</p>
    <?php else: ?>
        <div class="listing-grid">
            <?php foreach ($listings as $listing): ?>
                <div class="listing-card">
                    <img src="<?php echo htmlspecialchars(listing_image_url($listing['imageURL'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="listing-card-body">
                        <h3><?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="listing-meta">
                            <span><?php echo $listing['isDonation'] ? 'FREE' : 'R' . number_format((float)$listing['price'], 2); ?></span>
                            <span><?php echo htmlspecialchars($listing['pickupLocation'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <p>Seller: <?php echo htmlspecialchars($listing['firstName'] . ' ' . $listing['lastName'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><small>Posted <?php echo date('M j, Y', strtotime($listing['createdAt'])); ?></small></p>
                        <a class="button button-secondary" href="<?php echo htmlspecialchars(app_path('listings/view.php?listingID=' . (int)$listing['listingID']), ENT_QUOTES, 'UTF-8'); ?>">View Listing</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card">
                <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="button <?php echo $i === $page ? 'button-secondary' : ''; ?>" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php';
