<?php
include("baglanti.php");

if (isset($_POST["kaydol"])) {
    $name = $_POST["kullanici_adi"];
    $email = $_POST["email"];
    $password = password_hash($_POST["sifre"], PASSWORD_DEFAULT);

    // Kullanıcının zaten kayıtlı olup olmadığını kontrol et
    $kontrolSorgu = "SELECT * FROM kullanicilar WHERE email = '$email'";
    $kontrolSonuc = mysqli_query($baglanti, $kontrolSorgu);

    if (mysqli_num_rows($kontrolSonuc) > 0) {
        // Kullanıcı zaten kayıtlıysa uyarı ver
        echo '<div class="alert alert-danger">Bu e-posta adresiyle zaten kayıtlısınız. <a href="giris.php">Giriş yap</a></div>';
    } else {
        // Kayıt işlemi
        $ekle = "INSERT INTO kullanicilar (kullanici_adi, email, sifre) VALUES ('$name', '$email', '$password')";
        $calistirekle = mysqli_query($baglanti, $ekle);

        if ($calistirekle) {
            echo '<div class="alert alert-success">Kayıt başarılı! Giriş sayfasına yönlendiriliyorsunuz.</div>';
            header("Refresh:2; url=giris.php");
            exit();
        } else {
            echo '<div class="alert alert-danger">Kayıt başarısız. Lütfen tekrar deneyin.</div>';
        }
    }

    mysqli_close($baglanti);
}
?>


<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Üye Kayıt İşlemi</title>
    <style> /* duman arka planı*/
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

        input[type="text"],
        input[type="email"],
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

        footer {
            text-align: center;
            margin-top: 15px;
        }

        footer a {
            color: #007BFF;
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
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
        } /*duman arka planı bi alttaki style dahil*/
    </style>
</head>
<body>
    <div class="form-container">
        <form action="kayit.php" method="post">
            <h1>Kayıt Ol</h1>

            <div>
                <label for="username">Kullanıcı Adı</label>
                <input type="text" name="kullanici_adi" id="username" required>
            </div>

            <div>
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required>
            </div>

            <div>
                <label for="password">Şifre</label>
                <input type="password" name="sifre" id="password" required>
            </div>

            <button type="submit" name="kaydol">Kaydol</button>

            <footer>
                Kayıtlı mısın? <a href="giris.php">Giriş Yap</a>
            </footer>
        </form>
    </div>
</body>
</html>
