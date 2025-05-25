<?php
session_start();
include("baglanti.php");

// Kullanıcı giriş kontrolü
if (!isset($_SESSION["email"])) {
    echo '<div class="alert alert-danger">Yetkisiz erişim.</div>';
    exit();
}

// Kullanıcı bilgilerini al (prepared statement ile)
$email = $_SESSION["email"];
$sorgu = "SELECT * FROM kullanicilar WHERE email = ?";
$stmt = mysqli_prepare($baglanti, $sorgu);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$sonuc = mysqli_stmt_get_result($stmt);
$kullanici = mysqli_fetch_assoc($sonuc);

if (!$kullanici) {
    echo '<div class="alert alert-danger">Kullanıcı bulunamadı.</div>';
    exit();
}

// Eğer ilk giriş değilse, ana sayfaya yönlendir
if ($kullanici["ilk_giris"] != 1) {
    header("Location: anasayfa.php");
    exit();
}

// Şifre yenileme işlemi
if (isset($_POST["sifreyenile"])) {
    $yeniSifre = $_POST["sifre2"];
    $yeniSifreTekrar = $_POST["sifre2_tekrar"];

    // Yeni şifre ve tekrarı uyuşuyor mu?
    if ($yeniSifre !== $yeniSifreTekrar) {
        echo '<div class="alert alert-danger">Şifreler uyuşmuyor.</div>';
    } else {
        // Yeni şifre eski şifreyle aynı mı?
        if (password_verify($yeniSifre, $kullanici["sifre"])) {
            echo '<div class="alert alert-danger">Yeni şifre eski şifreyle aynı olamaz.</div>';
        } else {
            // Şifreyi hashle ve güncelle
            $hashedPassword = password_hash($yeniSifre, PASSWORD_DEFAULT);
            $guncelle = "UPDATE kullanicilar SET sifre = ?, ilk_giris = 0 WHERE email = ?";
            $stmt = mysqli_prepare($baglanti, $guncelle);
            mysqli_stmt_bind_param($stmt, "ss", $hashedPassword, $email);
            $calistir = mysqli_stmt_execute($stmt);

            if ($calistir) {
                echo '<div class="alert alert-success">Şifre başarıyla güncellendi. Yönlendiriliyorsunuz...</div>';
                header("Refresh: 2; url=giris.php");
                exit();
            } else {
                echo '<div class="alert alert-danger">Şifre güncellenemedi, lütfen tekrar deneyin.</div>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Yenileme</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body {
            background-image: url('duman.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            font-family: Arial, sans-serif;
        }

        .form-container {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 15px;
            max-width: 400px;
            width: 90%;
            margin: 80px auto;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
        }

        form h1 {
            text-align: center;
            margin-bottom: 25px;
        }

        form div {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #007BFF;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 17px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }

        .alert {
            max-width: 400px;
            margin: 20px auto;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <form action="sifreyenile.php" method="post">
            <h1>Şifre Yenile</h1>
            <div>
                <label for="sifre">Yeni Şifre</label>
                <input type="password" name="sifre2" id="sifre" required>
            </div>
            <div>
                <label for="sifre2">Yeni Şifre Tekrarı</label>
                <input type="password" name="sifre2_tekrar" id="sifre2" required>
            </div>
            <button type="submit" name="sifreyenile">Şifreyi Yenile</button>
        </form>
    </div>
</body>
</html>