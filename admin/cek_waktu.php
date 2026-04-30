<?php
echo "Server date: " . date('d/m/Y') . "<br>";
echo "Server time: " . date('H:i:s') . "<br>";
echo "CURDATE() MySQL: ";
$conn = new mysqli('localhost', 'root', '', 'db_medklik');
$result = $conn->query("SELECT CURDATE() as today");
$row = $result->fetch_assoc();
echo $row['today'];
?>