<?php
include("baglanti.php");
header("Content-Type: application/json");

$sorgu = "SELECT * FROM etkinlikler WHERE aktif = 1 ORDER BY tarih ASC";
$sonuc = mysqli_query($baglanti, $sorgu);

$etkinlikler = [];

while ($row = mysqli_fetch_assoc($sonuc)) {
    $row["hava_gereklilik"] = (int)$row["hava_gereklilik"];
    $etkinlikler[] = $row;
}

echo json_encode($etkinlikler);
