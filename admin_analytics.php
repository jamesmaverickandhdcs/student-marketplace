<?php
session_start();
include 'db.php';

// Protect page: only admins
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.html");
    exit;
}

/* ---------------------------
   Analytics Queries
---------------------------- */

// Total listings
$totalListings = $conn->query("SELECT COUNT(*) AS total FROM listings")->fetch_assoc()['total'];

// Status breakdown
$statusData = $conn->query("SELECT status, COUNT(*) AS count FROM listings GROUP BY status");

// Category breakdown
$categoryData = $conn->query("SELECT category, COUNT(*) AS count FROM listings GROUP BY category");

// Listings over time (monthly)
$timeData = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count 
                          FROM listings GROUP BY month ORDER BY month ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Analytics - Student Marketplace</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'header.php'; ?>

<main>
  <h2>Admin Analytics Dashboard</h2>

  <!-- Summary Stats -->
  <div class="stats">
    <p>Total Listings: <?= $totalListings ?></p>
  </div>

  <!-- Charts -->
  <section class="charts">
    <h3>Listings by Status</h3>
    <canvas id="statusChart"></canvas>

    <h3>Listings by Category</h3>
    <canvas id="categoryChart"></canvas>

    <h3>Listings Over Time</h3>
    <canvas id="timeChart"></canvas>
  </section>
</main>

<script>
// Status Chart
const statusCtx = document.getElementById('statusChart');
new Chart(statusCtx, {
  type: 'pie',
  data: {
    labels: [<?php while($row=$statusData->fetch_assoc()){echo "'".$row['status']."',";} ?>],
    datasets: [{
      data: [<?php $statusData->data_seek(0); while($row=$statusData->fetch_assoc()){echo $row['count'].",";} ?>],
      backgroundColor: ['#4caf50','#f44336','#ff9800','#2196f3']
    }]
  }
});

// Category Chart
const categoryCtx = document.getElementById('categoryChart');
new Chart(categoryCtx, {
  type: 'bar',
  data: {
    labels: [<?php while($row=$categoryData->fetch_assoc()){echo "'".$row['category']."',";} ?>],
    datasets: [{
      label: 'Listings per Category',
      data: [<?php $categoryData->data_seek(0); while($row=$categoryData->fetch_assoc()){echo $row['count'].",";} ?>],
      backgroundColor: '#2196f3'
    }]
  },
  options: {
    scales: {
      y: { beginAtZero: true }
    }
  }
});

// Time Chart
const timeCtx = document.getElementById('timeChart');
new Chart(timeCtx, {
  type: 'line',
  data: {
    labels: [<?php while($row=$timeData->fetch_assoc()){echo "'".$row['month']."',";} ?>],
    datasets: [{
      label: 'Listings Over Time',
      data: [<?php $timeData->data_seek(0); while($row=$timeData->fetch_assoc()){echo $row['count'].",";} ?>],
      borderColor: '#4caf50',
      fill: false,
      tension: 0.1
    }]
  }
});
</script>

</body>
</html>
<?php
$conn->close();
?>