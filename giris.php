<?php
session_start();
include("baglanti.php");

if (isset($_POST["giris"])) {
    $email = trim($_POST["email"]);
    $sifre = $_POST["sifre"];

    // Kullanıcıyı veritabanından sorgula
    $sorgu = "SELECT * FROM kullanicilar WHERE email = '$email'";
    $sonuc = mysqli_query($baglanti, $sorgu);

    if ($sonuc && mysqli_num_rows($sonuc) > 0) {
        $kullanici = mysqli_fetch_assoc($sonuc);

        // Şifre doğruysa
        if (password_verify($sifre, $kullanici["sifre"])) {
            $_SESSION["email"] = $email;
            $_SESSION["kullanici_id"] = $kullanici["id"];

            // Eğer admin ise, doğrudan admin paneline yönlendir
            if ($kullanici["admin"] == 1) {
                header("Location: yonetici.php");
                exit();
            }

            // Admin değilse ve onay bekliyorsa
            if ($kullanici["onay"] == 0) {
                echo '<div class="alert alert-warning" role="alert">
                        Admin onayı bekliyor. Lütfen yönetici tarafından onaylanmayı bekleyin.
                      </div>';
                exit();
            }

            // Onaylı normal kullanıcı
            header("Location: sifreyenile.php");
            exit();

        } else {
            // Şifre yanlışsa
            echo '<div class="alert alert-danger" role="alert">Email veya şifre yanlış.</div>';
        }
    } else {
        // E-posta bulunamadıysa
        echo '<div class="alert alert-danger" role="alert">Email veya şifre yanlış.</div>';
    }

    mysqli_close($baglanti);
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uye Giris Islemi</title>
  </head>

  <body>
    <div class="container">
        <div class="card">
            

        <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.phptutorial.net/app/css/style.css">
    <title>Register</title>
    <style>/* duman arka planı*/
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
        }/* duman arka planı bi alttaki style dahil*/
    </style>
</head>
<body>
<main>
    <form action="giris.php" method="post">
        <h1>Giriş Yap</h1>
        <div>
           
        </div>

        <div>
            <label for="email">Email</label>
            <input type="email" name="email" id="email">
        </div>

        <div>
            <label for="password">Şifre</label>
            <input type="password" name="sifre" id="password">
        </div>

       

        <div>
            
        </div>
        <button type="submit" name="giris">Giriş Yap</button>
        
        <footer>Hesabın Yok mu? <a href="kayit.php">Kaydol</a></footer>
        
    </form>
</main>
</body>
</html>


        </div>
    </div>
  </body>
</html> 
