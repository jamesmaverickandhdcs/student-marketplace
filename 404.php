<?php
error_log("404 error: " . $_SERVER['REQUEST_URI']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Page Not Found - Student Marketplace</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .error-container {
            text-align: center;
            padding: 60px;
        }

        .error-container h1 {
            font-size: 48px;
            color: #d9534f;
        }

        .error-container p {
            font-size: 18px;
            margin: 20px 0;
        }

        .error-container a {
            display: inline-block;
            padding: 10px 20px;
            background: #007BFF;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
        }

        .error-container a:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>
    <header>
        <h1>Student Marketplace</h1>
    </header>

    <main>
        <div class="error-container">
            <h1>404</h1>
            <p>Oops! The page you’re looking for doesn’t exist.</p>
            <img src="/img/mascot.png" alt="Mascot" class="mascot-img">
            <a href="index.html">Return to Home</a>

            <form action="/student-marketplace/listings.php" method="GET">
                <input type="text" name="search" placeholder="Search listings..." required>
                <button type="submit">Search</button>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Student Marketplace</p>
    </footer>
</body>

</html>