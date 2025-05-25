<?php
session_start();
session_unset();      // Tüm oturum değişkenlerini temizle
session_destroy();    // Oturumu tamamen bitir

// Giriş sayfasına yönlendir
header("Location: giris.php");
exit();
