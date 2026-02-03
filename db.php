<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "student_marketplace";
$port = 3308; // your MySQL port

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>