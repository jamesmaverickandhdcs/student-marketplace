<?php
include 'db.php';

// Capture filters safely
$search     = isset($_GET['search']) ? $_GET['search'] : "";
$min_price  = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price  = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 1000000;
$category   = isset($_GET['category']) ? $_GET['category'] : "";
$sort       = isset($_GET['sort']) ? $_GET['sort'] : "newest";

// Pagination setup
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Base query
$query = "SELECT listings.*, users.username 
          FROM listings 
          JOIN users ON listings.user_id = users.id 
          WHERE (listings.title LIKE ? OR listings.description LIKE ?) 
            AND listings.price BETWEEN ? AND ?";

$params = ["%$search%", "%$search%", $min_price, $max_price];
$types  = "ssdd";

// Category filter
if (!empty($category)) {
    $query .= " AND listings.category = ?";
    $params[] = $category;
    $types .= "s";
}

// Sorting
switch ($sort) {
    case "oldest":     $query .= " ORDER BY listings.created_at ASC"; break;
    case "low_price":  $query .= " ORDER BY listings.price ASC"; break;
    case "high_price": $query .= " ORDER BY listings.price DESC"; break;
    default:           $query .= " ORDER BY listings.created_at DESC";
}

// Pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Prepare and execute
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Output listings
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        ?>
        <div class="card fade-in">
          <img src="<?= !empty($row['image']) ? htmlspecialchars($row['image']) : 'images/placeholder.png' ?>" alt="Item image">
          <h3><?= htmlspecialchars($row['title']); ?></h3>
          <p><?= htmlspecialchars($row['description']); ?></p>
          <p class="price">Price: $<?= number_format($row['price'], 2); ?></p>
          <p class="category">Category: <?= htmlspecialchars($row['category']); ?></p>
          <p class="posted">Posted by: <?= htmlspecialchars($row['username']); ?><br>
             On: <?= date("F j, Y", strtotime($row['created_at'])); ?></p>
        </div>
        <?php
    }
} else {
    echo "<p>No listings found. Try adjusting your filters.</p>";
}

$stmt->close();
$conn->close();
?>