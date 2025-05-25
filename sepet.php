<?php
session_start();
include("baglanti.php");

$sepet = $_SESSION['sepet'] ?? [];
$toplam = 0;
foreach ($sepet as $urun) {
    $toplam += $urun['fiyat'] * $urun['adet'];
}

$username = "Kullanıcı";
if (isset($_SESSION['kullanici_id'])) {
    $kullanici_id = $_SESSION['kullanici_id'];
    $sorgu = "SELECT kullanici_adi FROM kullanicilar WHERE id = ?";
    $stmt = mysqli_prepare($baglanti, $sorgu);
    mysqli_stmt_bind_param($stmt, "i", $kullanici_id);
    mysqli_stmt_execute($stmt);
    $sonuc = mysqli_stmt_get_result($stmt);
    if ($sonuc && mysqli_num_rows($sonuc) > 0) {
        $kullanici = mysqli_fetch_assoc($sonuc);
        $username = $kullanici['kullanici_adi'] ?: "Kullanıcı";
    }
}

$sepet_adet = count($sepet);
$hata = "";
$weather_error = "";
$show_confirmation = false;
$purchased_items = [];
$api_key = "4915958acb89eaaa46242fd7321846cc";
$default_city = "Istanbul";

// Initialize weather cache
if (!isset($_SESSION['weather_cache'])) {
    $_SESSION['weather_cache'] = [];
}

// Weather fetch function
function fetch_weather($city, $api_key) {
    $weather_api_url = "http://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=" . $api_key . "&units=metric&lang=tr";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $weather_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $weather_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $weather_data = null;
    $is_bad_weather = false;
    $weather_reason = "";

    if ($http_code == 200) {
        $weather_data = json_decode($weather_response, true);
        if ($weather_data && isset($weather_data['weather'])) {
            $weather_description = strtolower($weather_data['weather'][0]['description']);
            $temperature = $weather_data['main']['temp'];

            $bad_weather_conditions = ['yağmur', 'kar', 'fırtına', 'şiddetli yağmur', 'gök gürültüsü'];
            $is_bad_condition = false;
            foreach ($bad_weather_conditions as $condition) {
                if (strpos($weather_description, $condition) !== false) {
                    $is_bad_condition = true;
                    $weather_reason = "olumsuz hava koşulları ($weather_description)";
                    break;
                }
            }

            $min_temp = 5;
            $max_temp = 35;
            $is_bad_temp = ($temperature < $min_temp || $temperature > $max_temp);
            if ($is_bad_temp) {
                $temp_reason = $temperature < $min_temp ? "çok soğuk (sıcaklık: $temperature°C)" : "çok sıcak (sıcaklık: $temperature°C)";
                $weather_reason = $weather_reason ? "$weather_reason ve $temp_reason" : $temp_reason;
            }

            $is_bad_weather = $is_bad_condition || $is_bad_temp;
        } else {
            error_log("Weather API failed for city $city: Invalid response data");
        }
    } else {
        error_log("Weather API failed for city $city: HTTP $http_code");
    }

    return [
        'data' => $weather_data,
        'is_bad_weather' => $is_bad_weather,
        'weather_reason' => $weather_reason
    ];
}

// Check weather for Konser events in cart
$canceled_events = [];
if (!empty($sepet)) {
    foreach ($sepet as $urun) {
        $sorgu = "SELECT tur, konum FROM etkinlikler WHERE id = ? AND aktif = 1";
        $stmt = mysqli_prepare($baglanti, $sorgu);
        mysqli_stmt_bind_param($stmt, "i", $urun['id']);
        mysqli_stmt_execute($stmt);
        $sonuc = mysqli_stmt_get_result($stmt);
        if ($sonuc && mysqli_num_rows($sonuc) > 0) {
            $etkinlik = mysqli_fetch_assoc($sonuc);
            if ($etkinlik['tur'] === 'Konser') {
                $konum = $etkinlik['konum'];
                $city = $default_city;
                if (!empty($konum)) {
                    $konum_parts = array_map('trim', explode(',', $konum));
                    $city = end($konum_parts);
                }

                if (!isset($_SESSION['weather_cache'][$city])) {
                    $_SESSION['weather_cache'][$city] = fetch_weather($city, $api_key);
                }
                $weather_info = $_SESSION['weather_cache'][$city];

                if ($weather_info['is_bad_weather']) {
                    $canceled_events[] = [
                        'baslik' => $urun['baslik'],
                        'reason' => $weather_info['weather_reason']
                    ];
                }
            }
        }
    }
}

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['clear_cart'])) {
    if (empty($sepet)) {
        $hata = "Sepetiniz boş.";
    } elseif (!empty($canceled_events)) {
        $weather_error = "Aşağıdaki konser etkinlikleri kötü hava koşulları nedeniyle iptal edilmiştir:<ul>";
        foreach ($canceled_events as $event) {
            $weather_error .= "<li>" . htmlspecialchars($event['baslik']) . ": " . $event['reason'] . "</li>";
        }
        $weather_error .= "</ul>Lütfen bu etkinlikleri sepetten kaldırın.";
    } else {
        $payment_method = $_POST['payment_method'] ?? '';
        if ($payment_method === 'credit_card') {
            $ad = trim($_POST['ad'] ?? '');
            $kartno = preg_replace('/\D/', '', $_POST['kartno'] ?? '');
            $tarih = trim($_POST['tarih'] ?? '');
            $cvv = preg_replace('/\D/', '', $_POST['cvv'] ?? '');

            if (empty($ad) || !preg_match('/^[a-zA-Z\s]+$/', $ad)) {
                $hata = "Geçerli bir kart sahibi adı giriniz.";
            } elseif (strlen($kartno) !== 16) {
                $hata = "Kart numarası 16 haneli olmalıdır.";
            } elseif (!preg_match('/^(0[1-9]|1[0-2])\/[0-9]{2}$/', $tarih)) {
                $hata = "Son kullanma tarihi MM/YY formatında olmalıdır.";
            } elseif (strlen($cvv) !== 3) {
                $hata = "CVV 3 haneli olmalıdır.";
            } else {
                list($month, $year) = explode('/', $tarih);
                $current_year = date('y');
                $current_month = date('m');
                if ($year < $current_year || ($year == $current_year && $month < $current_month)) {
                    $hata = "Kartın son kullanma tarihi geçmiş.";
                }
            }
        } elseif ($payment_method === 'bank_transfer') {
            $transaction_id = preg_replace('/\D/', '', $_POST['transaction_id'] ?? '');
            if (strlen($transaction_id) < 6 || strlen($transaction_id) > 10) {
                $hata = "İşlem ID'si 6-10 haneli olmalıdır.";
            }
        } else {
            $hata = "Lütfen bir ödeme yöntemi seçin.";
        }

        if (!$hata) {
            $kullanici_id = $_SESSION['kullanici_id'];
            $success = true;

            foreach ($sepet as $urun) {
                $etkinlik_id = intval($urun['id']);
                $adet = intval($urun['adet']);

                // Check available tickets
                $sql = "SELECT bilet_sayisi, bilet_fiyati FROM etkinlikler WHERE id = ? AND aktif = 1";
                $stmt = mysqli_prepare($baglanti, $sql);
                mysqli_stmt_bind_param($stmt, "i", $etkinlik_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($result && mysqli_num_rows($result) > 0) {
                    $etkinlik = mysqli_fetch_assoc($result);
                    if ($adet <= $etkinlik['bilet_sayisi']) {
                        // Reduce ticket count
                        $yeni_bilet = $etkinlik['bilet_sayisi'] - $adet;
                        $update = mysqli_prepare($baglanti, "UPDATE etkinlikler SET bilet_sayisi = ? WHERE id = ?");
                        mysqli_stmt_bind_param($update, "ii", $yeni_bilet, $etkinlik_id);
                        mysqli_stmt_execute($update);

                        // Record purchase
                        $item_toplam = $etkinlik['bilet_fiyati'] * $adet;
                        $insert = mysqli_prepare($baglanti, "INSERT INTO satin_almalar (kullanici_id, etkinlik_id, adet, toplam_fiyat, satin_alma_tarihi) VALUES (?, ?, ?, ?, NOW())");
                        mysqli_stmt_bind_param($insert, "iiid", $kullanici_id, $etkinlik_id, $adet, $item_toplam);
                        mysqli_stmt_execute($insert);
                    } else {
                        $hata = "Yetersiz bilet sayısı: {$urun['baslik']}.";
                        $success = false;
                        break;
                    }
                } else {
                    $hata = "Etkinlik bulunamadı: {$urun['baslik']}.";
                    $success = false;
                    break;
                }
            }

            if ($success) {
                $purchased_items = $sepet;
                $_SESSION['sepet'] = [];
                $show_confirmation = true;
            }
        }
    }
}

// Clear cart
if (isset($_POST['clear_cart'])) {
    $_SESSION['sepet'] = [];
    header("Location: sepet.php");
    exit;
}

// Fetch announcements for notification dropdown
$duyuru_limit_sorgu = "SELECT * FROM duyurular WHERE aktif = 1 ORDER BY yayin_tarihi DESC LIMIT 5";
$duyuru_limit_sonuc = mysqli_query($baglanti, $duyuru_limit_sorgu);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sepet - Milyon Fest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e9ecef 0%, #d1e7ff 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        .cart-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        .cart-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .sepet-adet {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: bold;
            animation: bounce 1s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .card {
            border: none;
            border-radius: 12px;
            background-color: #ffffff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        .btn-danger {
            border-radius: 6px;
        }
        .navbar-brand {
            font-weight: bold;
            color: #0d6efd !important;
        }
        .nav-link {
            color: #0d6efd !important;
        }
        .nav-link:hover {
            color: #0b5ed7 !important;
        }
        .nav-link.active {
            font-weight: bold;
            border-bottom: 2px solid #0d6efd;
        }
        .dropdown-menu {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 10px;
            max-height: 400px;
            overflow-y: auto;
        }
        .dropdown-item {
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.2s ease;
        }
        .dropdown-item:hover {
            background-color: #f1f3f5;
        }
        .dropdown-item i {
            margin-right: 8px;
            color: #0d6efd;
        }
        .dropdown-greeting {
            padding: 8px 15px;
            font-weight: 600;
            color: #333;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 5px;
        }
        .avatar, .notification-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .avatar:hover, .notification-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        .notification-icon {
            margin-right: 10px;
        }
        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item h6 {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .notification-item p {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .notification-item .duyuru-tarih {
            font-size: 0.8rem;
            color: #999;
        }
        .confirmation-card {
            text-align: center;
            padding: 30px;
        }
        .confirmation-card i {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        .payment-method {
            margin-bottom: 20px;
        }
        .payment-method label {
            cursor: pointer;
            font-weight: 500;
        }
        .payment-method input[type="radio"] {
            margin-right: 10px;
        }
        .payment-form {
            display: none;
        }
        .payment-form.active {
            display: block;
        }
        .bank-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href="anasayfa.php">Milyon Fest</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="etkinliklerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Etkinlikler
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="etkinliklerDropdown">
                        <li><a class="dropdown-item" href="anasayfa.php?tab=etkinlikler"><i class="fas fa-th"></i> Tümü</a></li>
                        <li><a class="dropdown-item" href="anasayfa.php?tab=etkinlikler&tur=Konser"><i class="fas fa-music"></i> Konser</a></li>
                        <li><a class="dropdown-item" href="anasayfa.php?tab=etkinlikler&tur=Tiyatro"><i class="fas fa-theater-masks"></i> Tiyatro</a></li>
                        <li><a class="dropdown-item" href="anasayfa.php?tab=etkinlikler&tur=Futbol"><i class="fas fa-futbol"></i> Futbol</a></li>
                        <li><a class="dropdown-item" href="anasayfa.php?tab=etkinlikler&tur=Sinema"><i class="fas fa-film"></i> Sinema</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="anasayfa.php?tab=duyurular">Duyurular</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['kullanici_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="notification-icon"><i class="fas fa-bell"></i></div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <?php
                            mysqli_data_seek($duyuru_limit_sonuc, 0);
                            if (mysqli_num_rows($duyuru_limit_sonuc) > 0) {
                                while ($duyuru = mysqli_fetch_assoc($duyuru_limit_sonuc)): ?>
                                    <li class="notification-item">
                                        <h6><?= htmlspecialchars($duyuru["baslik"]) ?></h6>
                                        <p><?= htmlspecialchars($duyuru["icerik"]) ?></p>
                                        <div class="duyuru-tarih"><?= htmlspecialchars($duyuru["yayin_tarihi"]) ?> (<?= htmlspecialchars($duyuru["kategori"] ?? 'Genel') ?>)</div>
                                    </li>
                                <?php endwhile;
                            } else { ?>
                                <li class="notification-item">Henüz duyuru bulunmamaktadır.</li>
                            <?php } ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="anasayfa.php?tab=duyurular"><i class="fas fa-bullhorn"></i> Tüm Duyurular</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="avatarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar"><i class="fas fa-user-circle"></i></div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
                            <li><span class="dropdown-greeting">Hoşgeldiniz <?= htmlspecialchars($username) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="cikis.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="giris.php">Giriş Yap</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="kayit.php">Kaydol</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="sepet.php" title="Sepetim">
                        <div class="cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($sepet_adet > 0): ?>
                                <span class="sepet-adet"><?= $sepet_adet ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-5 pt-5">
    <?php if ($show_confirmation): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card confirmation-card">
                    <i class="fas fa-check-circle"></i>
                    <h2>Ödeme Başarıyla Tamamlandı!</h2>
                    <p>Teşekkür ederiz, <?= htmlspecialchars($username) ?>! Biletleriniz başarıyla satın alındı.</p>
                    <h4>Satın Alınan Biletler</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Etkinlik</th>
                                <th>Adet</th>
                                <th>Birim Fiyat</th>
                                <th>Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchased_items as $urun): ?>
                                <tr>
                                    <td><?= htmlspecialchars($urun['baslik']) ?></td>
                                    <td><?= $urun['adet'] ?></td>
                                    <td><?= number_format($urun['fiyat'], 2) ?> TL</td>
                                    <td><?= number_format($urun['fiyat'] * $urun['adet'], 2) ?> TL</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><strong>Toplam Tutar:</strong> <?= number_format($toplam, 2) ?> TL</p>
                    <a href="anasayfa.php" class="btn btn-primary">Anasayfaya Dön</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <h2 class="mb-4">Sepetiniz</h2>
        <?php if (empty($sepet)): ?>
            <div class="alert alert-warning">Sepetiniz boş. <a href="anasayfa.php">Etkinliklere göz at</a></div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Etkinlik</th>
                                <th>Adet</th>
                                <th>Birim Fiyat</th>
                                <th>Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sepet as $urun): ?>
                                <tr>
                                    <td><?= htmlspecialchars($urun['baslik']) ?></td>
                                    <td><?= $urun['adet'] ?></td>
                                    <td><?= number_format($urun['fiyat'], 2) ?> TL</td>
                                    <td><?= number_format($urun['fiyat'] * $urun['adet'], 2) ?> TL</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-end"><strong>Toplam Tutar:</strong> <?= number_format($toplam, 2) ?> TL</p>
                    <form method="post" class="text-end">
                        <input type="hidden" name="clear_cart" value="1">
                        <button type="submit" class="btn btn-danger">Sepeti Temizle</button>
                    </form>
                </div>
            </div>

            <?php if ($weather_error): ?>
                <div class="alert alert-danger"><?= $weather_error ?></div>
            <?php endif; ?>
            <?php if ($hata): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($hata) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <h4>Ödeme Bilgileri</h4>
                    <form method="post">
                        <div class="payment-method">
                            <h5>Ödeme Yöntemi Seçin</h5>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="credit_card" checked>
                                <label class="form-check-label" for="credit_card">Kredi Kartı</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="bank_transfer" value="bank_transfer">
                                <label class="form-check-label" for="bank_transfer">Banka Havalesi</label>
                            </div>
                        </div>

                        <!-- Credit Card Form -->
                        <div id="credit_card_form" class="payment-form active">
                            <div class="mb-3">
                                <label class="form-label">Kart Sahibi</label>
                                <input type="text" name="ad" class="form-control" value="<?= isset($_POST['ad']) ? htmlspecialchars($_POST['ad']) : '' ?>" placeholder="Ad Soyad">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kart Numarası</label>
                                <input type="text" name="kartno" class="form-control" maxlength="16" value="<?= isset($_POST['kartno']) ? htmlspecialchars($_POST['kartno']) : '' ?>" placeholder="16 haneli kart numarası">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Son Kullanma Tarihi (MM/YY)</label>
                                    <input type="text" name="tarih" class="form-control" placeholder="MM/YY" value="<?= isset($_POST['tarih']) ? htmlspecialchars($_POST['tarih']) : '' ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CVV</label>
                                    <input type="text" name="cvv" class="form-control" maxlength="3" value="<?= isset($_POST['cvv']) ? htmlspecialchars($_POST['cvv']) : '' ?>" placeholder="3 haneli CVV">
                                </div>
                            </div>
                        </div>

                        <!-- Bank Transfer Form -->
                        <div id="bank_transfer_form" class="payment-form">
                            <div class="bank-details">
                                <h6>Banka Bilgileri</h6>
                                <p><strong>Banka Adı:</strong> Milyon Bank</p>
                                <p><strong>IBAN:</strong> TR12 3456 7890 1234 5678 9012 34</p>
                                <p><strong>Alıcı:</strong> Milyon Fest Etkinlikleri A.Ş.</p>
                                <p>Lütfen ödemeyi yaptıktan sonra işlem ID'nizi aşağıya girin.</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">İşlem ID'si</label>
                                <input type="text" name="transaction_id" class="form-control" value="<?= isset($_POST['transaction_id']) ? htmlspecialchars($_POST['transaction_id']) : '' ?>" placeholder="6-10 haneli işlem ID'si">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Ödemeyi Tamamla</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const forms = {
            'credit_card': document.getElementById('credit_card_form'),
            'bank_transfer': document.getElementById('bank_transfer_form'),
        };

        function toggleForms(selectedMethod) {
            Object.keys(forms).forEach(method => {
                forms[method].classList.toggle('active', method === selectedMethod);
            });
        }

        paymentMethods.forEach(method => {
            method.addEventListener('change', function () {
                toggleForms(this.value);
            });
        });

        // Initialize with the selected method
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
        toggleForms(selectedMethod);
    });
</script>
</body>
</html>