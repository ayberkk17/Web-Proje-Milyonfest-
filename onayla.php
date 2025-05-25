<?php
session_start();
include("baglanti.php");

// Admin kontrolü
if (!isset($_SESSION["email"])) {
    header("Location: giris.php");
    exit();
}

if (isset($_GET["id"])) {
    $kullanici_id = $_GET["id"];

    // Sadece belirli bir kullanıcıyı onaylamak için id kullanıyoruz.
    $guncelle = "UPDATE kullanicilar SET onay = 1 WHERE id = '$kullanici_id' AND admin = 0"; // Admin değilse onay ver

    // Sorguyu çalıştır
    if (mysqli_query($baglanti, $guncelle)) {
        echo '<div class="alert alert-success" role="alert">
                Kullanıcı başarıyla onaylandı.
              </div>';
    } else {
        echo '<div class="alert alert-danger" role="alert">
                Kullanıcı onaylanamadı. Lütfen tekrar deneyin.
              </div>';
    }

    // Onay sonrası yönlendir
    header("Location: yonetici.php");
    exit();
}

mysqli_close($baglanti);
?>
