<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$id_poli = isset($_GET['id_poli']) ? intval($_GET['id_poli']) : 0;

if ($id_poli > 0) {
    $query = "SELECT id_dokter, nama_dokter, spesialisasi FROM dokter WHERE id_poli = '$id_poli'";
    $result = $conn->query($query);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode($data);
} else {
    echo json_encode([]);
}
?>