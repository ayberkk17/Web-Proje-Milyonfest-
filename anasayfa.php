<?php
session_start();
include("baglanti.php");

$message = "";
$api_key = "4915958acb89eaaa46242fd7321846cc"; // Update with your valid API key

// Türkiye illeri listesi
$turkey_cities = [
    'Adana', 'Adıyaman', 'Afyon', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya', 'Ardahan',
    'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis',
    'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce',
    'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane',
    'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul', 'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman',
    'Kars', 'Kastamonu', 'Kayseri', 'Kilis', 'Kırıkkale', 'Kırklareli', 'Kırşehir', 'Kocaeli',
    'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir',
    'Niğde', 'Ordu', 'Osmaniye', 'Rize', 'Sakarya', 'Samsun', 'Şanlıurfa', 'Siirt', 'Sinop',
    'Şırnak', 'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova',
    'Yozgat', 'Zonguldak'
];

// Initialize weather cache
if (!isset($_SESSION['weather_cache'])) {
    $_SESSION['weather_cache'] = [];
}

// Weather fetch function
function fetch_weather($city, $api_key, $turkey_cities) {
    if (empty($city)) {
        return [
            'data' => null,
            'is_bad_weather' => false,
            'weather_reason' => '',
            'description' => 'Veri yok',
            'temperature' => null,
            'emoji' => '🌡️'
        ];
    }

    // Şehir adını temizle ve Türkiye illerinden doğrula
    $city = trim($city);
    $city = ucfirst(strtolower($city));
    $matched_city = false;
    foreach ($turkey_cities as $tr_city) {
        if (stripos($city, $tr_city) !== false || $city === $tr_city) {
            $city = $tr_city;
            $matched_city = true;
            break;
        }
    }
    if (!$matched_city) {
        error_log("Invalid city name: $city");
        return [
            'data' => null,
            'is_bad_weather' => false,
            'weather_reason' => '',
            'description' => 'Geçersiz şehir',
            'temperature' => null,
            'emoji' => '🌡️'
        ];
    }

    // Önbelleği kontrol et (1 saatlik geçerlilik)
    $cache_key = md5($city);
    if (isset($_SESSION['weather_cache'][$cache_key]) && (time() - $_SESSION['weather_cache'][$cache_key]['timestamp']) < 3600) {
        return $_SESSION['weather_cache'][$cache_key]['data'];
    }

    $weather_api_url = "http://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . ",TR&appid=" . $api_key . "&units=metric&lang=tr";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $weather_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $weather_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $weather_data = null;
    $is_bad_weather = false;
    $weather_reason = "";
    $weather_description = "Veri alınamadı";
    $temperature = null;
    $emoji = '🌡️';

    if ($http_code == 200) {
        $weather_data = json_decode($weather_response, true);
        if ($weather_data && isset($weather_data['weather'])) {
            $weather_description = strtolower($weather_data['weather'][0]['description']);
            $temperature = $weather_data['main']['temp'];
            $weather_icon = $weather_data['weather'][0]['icon'];

            // Emoji mapping
            $weather_emojis = [
                '01d' => '☀️', '01n' => '🌙', '02d' => '⛅', '02n' => '⛅',
                '03d' => '☁️', '03n' => '☁️', '04d' => '☁️', '04n' => '☁️',
                '09d' => '🌧️', '09n' => '🌧️', '10d' => '🌧️', '10n' => '🌧️',
                '11d' => '⛈️', '11n' => '⛈️', '13d' => '❄️', '13n' => '❄️',
                '50d' => '🌫️', '50n' => '🌫️'
            ];
            $emoji = isset($weather_emojis[$weather_icon]) ? $weather_emojis[$weather_icon] : '🌡️';

            $bad_weather_conditions = ['yağmur', 'kar', 'fırtına', 'şiddetli yağmur', 'gök gürültüsü'];
            $is_bad_condition = false;
            foreach ($bad_weather_conditions as $condition) {
                if (strpos($weather_description, $condition) !== false) {
                    $is_bad_condition = true;
                    $weather_reason = ucfirst($weather_description);
                    break;
                }
            }

            $min_temp = 5;
            $max_temp = 35;
            $is_bad_temp = ($temperature < $min_temp || $temperature > $max_temp);
            if ($is_bad_temp) {
                $temp_reason = $temperature < $min_temp ? "çok soğuk ($temperature°C)" : "çok sıcak ($temperature°C)";
                $weather_reason = $weather_reason ? "$weather_reason ve $temp_reason" : $temp_reason;
            }

            $is_bad_weather = $is_bad_condition || $is_bad_temp;
        } else {
            error_log("Weather API failed for city $city: Invalid response data");
        }
    } else {
        error_log("Weather API failed for city $city: HTTP $http_code");
    }

    $weather_info = [
        'data' => $weather_data,
        'is_bad_weather' => $is_bad_weather,
        'weather_reason' => $weather_reason,
        'description' => $weather_description,
        'temperature' => $temperature,
        'emoji' => $emoji
    ];

    // Önbelleğe kaydet
    $_SESSION['weather_cache'][$cache_key] = [
        'data' => $weather_info,
        'timestamp' => time()
    ];

    return $weather_info;
}

// Fetch username
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

// Handle ticket purchase
if (isset($_POST['bilet_al'])) {
    if (!isset($_SESSION['kullanici_id'])) {
        $message = "<div class='alert alert-danger'>Bilet satın almak için lütfen giriş yapınız.</div>";
    } else {
        $etkinlik_id = intval($_POST['etkinlik_id']);
        $adet = intval($_POST['adet']);

        if ($adet <= 0) {
            $message = "<div class='alert alert-danger'>Lütfen geçerli bir bilet adedi seçiniz.</div>";
        } else {
            $sql = "SELECT * FROM etkinlikler WHERE id = ? AND aktif = 1";
            $stmt = mysqli_prepare($baglanti, $sql);
            mysqli_stmt_bind_param($stmt, "i", $etkinlik_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) > 0) {
                $etkinlik = mysqli_fetch_assoc($result);
                $konum = $etkinlik['konum'];
                $city = "Istanbul";
                if (!empty($konum)) {
                    $konum_parts = array_map('trim', explode(',', $konum));
                    $city = end($konum_parts);
                }

                if ($etkinlik['bilet_sayisi'] < $adet) {
                    $message = "<div class='alert alert-danger'>Yetersiz bilet sayısı. Kalan bilet: {$etkinlik['bilet_sayisi']}, Seçilen: $adet</div>";
                } else {
                    // Check weather for Konser
                    if ($etkinlik['tur'] === 'Konser') {
                        $weather_info = fetch_weather($city, $api_key, $turkey_cities);
                        if ($weather_info['is_bad_weather']) {
                            $message = "<div class='alert alert-danger'>Bu konser, $city için {$weather_info['weather_reason']} nedeniyle iptal edilmiştir. {$weather_info['emoji']}</div>";
                        } else {
                            if (!isset($_SESSION['sepet'])) {
                                $_SESSION['sepet'] = [];
                            }
                            $_SESSION['sepet'][] = [
                                'id' => $etkinlik['id'],
                                'baslik' => $etkinlik['baslik'],
                                'fiyat' => $etkinlik['bilet_fiyati'],
                                'adet' => $adet,
                                'konum' => $konum
                            ];
                            $message = "<div class='alert alert-success'>$adet adet bilet sepete eklendi!</div>";
                        }
                    } else {
                        // Tiyatro, Futbol, Sinema için
                        if (!isset($_SESSION['sepet'])) {
                            $_SESSION['sepet'] = [];
                        }
                        $_SESSION['sepet'][] = [
                            'id' => $etkinlik['id'],
                            'baslik' => $etkinlik['baslik'],
                            'fiyat' => $etkinlik['bilet_fiyati'],
                            'adet' => $adet,
                            'konum' => $konum
                        ];
                        $message = "<div class='alert alert-success'>$adet adet bilet sepete eklendi!</div>";
                    }
                }
            } else {
                $message = "<div class='alert alert-danger'>Etkinlik bulunamadı.</div>";
            }
        }
    }
}

// Tab and category filter
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'etkinlikler';
$allowed_tur = ['Konser', 'Tiyatro', 'Futbol', 'Sinema'];
$tur = isset($_GET['tur']) && in_array($_GET['tur'], $allowed_tur) ? mysqli_real_escape_string($baglanti, $_GET['tur']) : null;
$duyuru_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($baglanti, $_GET['kategori']) : null;
$sort_type = isset($_GET['sort']) && in_array($_GET['sort'], ['tarih_asc', 'tarih_desc']) ? $_GET['sort'] : 'tarih_asc'; // Varsayılan: En erken tarih

// Events query
if ($tab === 'etkinlikler') {
    $sorgu = "SELECT * FROM etkinlikler WHERE aktif = 1";
    if ($tur) {
        $sorgu .= " AND tur = ?";
    }
    $sorgu .= ($sort_type === 'tarih_asc') ? " ORDER BY tarih ASC" : " ORDER BY tarih DESC";
    $stmt = mysqli_prepare($baglanti, $sorgu);
    if ($tur) {
        mysqli_stmt_bind_param($stmt, "s", $tur);
    }
    mysqli_stmt_execute($stmt);
    $sonuc = mysqli_stmt_get_result($stmt);
}

// Announcements queries
$duyuru_limit_sorgu = "SELECT * FROM duyurular WHERE aktif = 1 ORDER BY yayin_tarihi DESC LIMIT 5";
$duyuru_limit_sonuc = mysqli_query($baglanti, $duyuru_limit_sorgu);

if ($tab === 'duyurular') {
    if ($duyuru_kategori) {
        $duyuru_sorgu = "SELECT * FROM duyurular WHERE aktif = 1 AND kategori = ? ORDER BY yayin_tarihi DESC";
        $stmt = mysqli_prepare($baglanti, $duyuru_sorgu);
        mysqli_stmt_bind_param($stmt, "s", $duyuru_kategori);
        mysqli_stmt_execute($stmt);
        $duyuru_sonuc = mysqli_stmt_get_result($stmt);
    }
    $kategoriler = ['Konser', 'Tiyatro', 'Futbol', 'Sinema'];
    $kategori_duyurulari = [];
    foreach ($kategoriler as $kategori) {
        $sorgu = "SELECT * FROM duyurular WHERE aktif = 1 AND kategori = ? ORDER BY yayin_tarihi DESC";
        $stmt = mysqli_prepare($baglanti, $sorgu);
        mysqli_stmt_bind_param($stmt, "s", $kategori);
        mysqli_stmt_execute($stmt);
        $kategori_duyurulari[$kategori] = mysqli_stmt_get_result($stmt);
    }
}

$sepet_adet = isset($_SESSION['sepet']) ? count($_SESSION['sepet']) : 0;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etkinlikler ve Duyurular - Anasayfa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #e9ecef 0%, #d1e7ff 100%); min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar { background-color: #ffffff; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); }
        .cart-icon { width: 38px; height: 38px; border-radius: 50%; background: #0d6efd; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative; }
        .cart-icon:hover { transform: scale(1.1); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        .sepet-adet { position: absolute; top: -8px; right: -8px; background: #dc3545; color: white; border-radius: 50%; padding: 4px 8px; font-size: 0.75rem; font-weight: bold; animation: bounce 1s infinite; }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        .card { border: none; border-radius: 12px; transition: transform 0.3s ease, box-shadow 0.3s ease; background-color: #ffffff; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15); }
        .card-title { font-size: 1.3rem; font-weight: 600; color: #333; }
        .card-subtitle { font-size: 0.95rem; color: #6c757d; }
        .btn-primary { background-color: #0d6efd; border: none; border-radius: 6px; padding: 8px 18px; font-weight: 500; }
        .btn-primary:hover { background-color: #0b5ed7; }
        .navbar-brand { font-weight: bold; color: #0d6efd !important; }
        .nav-link { color: #0d6efd !important; }
        .nav-link:hover { color: #0b5ed7 !important; }
        .nav-link.active { font-weight: bold; border-bottom: 2px solid #0d6efd; }
        .dropdown-menu { border: none; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 10px; max-height: 400px; overflow-y: auto; }
        .dropdown-item { padding: 8px 15px; border-radius: 5px; transition: background-color 0.2s ease; }
        .dropdown-item:hover { background-color: #f1f3f5; }
        .dropdown-item i { margin-right: 8px; color: #0d6efd; }
        .dropdown-greeting { padding: 8px 15px; font-weight: 600; color: #333; border-bottom: 1px solid #e9ecef; margin-bottom: 5px; }
        .kategori-card { background-color: #ffffff; border-radius: 12px; transition: transform 0.2s ease, box-shadow 0.2s ease; text-align: center; padding: 20px; position: relative; overflow: hidden; border: 1px solid rgba(0, 0, 0, 0.05); }
        .kategori-card:hover { transform: scale(1.05); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); }
        .kategori-card.active { background: linear-gradient(45deg, #0d6efd, #1e90ff); color: #ffffff; border: none; }
        .kategori-card.active i, .kategori-card.active h6 { color: #ffffff; }
        .kategori-card i { font-size: 2rem; color: #0d6efd; margin-bottom: 10px; }
        .kategori-card h6 { margin: 0; font-weight: 600; font-size: 1rem; color: #333; }
        .kategori-card.active h6 { color: #ffffff; }
        .kategori-card.tumu { background-color: #f1f3f5; }
        .kategori-card.konser { background-color: #e6f3ff; }
        .kategori-card.tiyatro { background-color: #fff1e6; }
        .kategori-card.futbol { background-color: #e6ffe6; }
        .kategori-card.sinema { background-color: #f3e6ff; }
        .avatar { width: 38px; height: 38px; border-radius: 50%; background: #0d6efd; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .avatar:hover { transform: scale(1.1); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); }
        .notification-icon { width: 38px; height: 38px; border-radius: 50%; background: #0d6efd; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: transform 0.3s ease, box-shadow 0.3s ease; margin-right: 10px; }
        .notification-icon:hover { transform: scale(1.1); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); }
        .notification-item { padding: 10px; border-bottom: 1px solid #e9ecef; }
        .notification-item:last-child { border-bottom: none; }
        .notification-item h6 { font-size: 1rem; font-weight: 600; color: #333; margin-bottom: 5px; }
        .notification-item p { font-size: 0.85rem; color: #6c757d; margin-bottom: 5px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .notification-item .duyuru-tarih { font-size: 0.8rem; color: #999; }
        .duyuru-card { background-color: #ffffff; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 2px solid #0d6efd; transition: box-shadow 0.3s ease, transform 0.3s ease; cursor: pointer; }
        .duyuru-card:hover { box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15); transform: translateY(-3px); }
        .duyuru-card h5 { font-size: 1.1rem; font-weight: 600; color: #0d6efd; margin-bottom: 8px; }
        .duyuru-card p { font-size: 0.9rem; color: #6c757d; margin-bottom: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .duyuru-tarih { font-size: 0.85rem; color: #999; margin-top: 8px; }
        .duyuru-full-card { background-color: #ffffff; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid rgba(0, 0, 0, 0.05); transition: box-shadow 0.3s ease; }
        .duyuru-full-card:hover { box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1); }
        .duyuru-full-card h4 { font-size: 1.4rem; font-weight: 600; color: #333; margin-bottom: 10px; }
        .duyuru-full-card p { font-size: 1rem; color: #333; }
        .duyurular-baslik { font-size: 1.8rem; font-weight: 700; color: #0d6efd; margin-bottom: 20px; display: flex; align-items: center; }
        .duyurular-baslik i { margin-right: 10px; font-size: 2rem; }
        .kategori-baslik { font-size: 1.5rem; font-weight: 600; color: #0d6efd; margin: 20px 0 10px; cursor: pointer; padding: 10px; border-radius: 8px; transition: background-color 0.2s ease; }
        .kategori-baslik:hover { background-color: #e6f3ff; }
        .kategori-baslik.active { background-color: #0d6efd; color: #ffffff; }
        .weather-info { font-size: 0.9rem; color: #6c757d; margin-top: 8px; }
        .weather-info .emoji { font-size: 1.2rem; margin-right: 5px; }
        .sort-button { padding: 8px 15px; margin-right: 5px; background-color: #0d6efd; color: white; border-radius: 6px; text-decoration: none; font-weight: 500; transition: background-color 0.2s ease; }
        .sort-button:hover { background-color: #0b5ed7; color: white; }
        .sort-button.active { background-color: #1e90ff; }
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
                    <a class="nav-link dropdown-toggle <?php echo $tab === 'etkinlikler' ? 'active' : ''; ?>" href="#" id="etkinliklerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                    <a class="nav-link <?php echo $tab === 'duyurular' ? 'active' : ''; ?>" href="anasayfa.php?tab=duyurular">Duyurular</a>
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
                                while ($duyuru = mysqli_fetch_assoc($duyuru_limit_sonuc)) {
                                    echo '<li class="notification-item">';
                                    echo '<h6>' . htmlspecialchars($duyuru["baslik"]) . '</h6>';
                                    echo '<p>' . htmlspecialchars($duyuru["icerik"]) . '</p>';
                                    echo '<div class="duyuru-tarih">' . htmlspecialchars($duyuru["yayin_tarihi"]) . ' (' . htmlspecialchars($duyuru["kategori"] ?? 'Genel') . ')</div>';
                                    echo '</li>';
                                }
                            } else {
                                echo '<li class="notification-item">Henüz duyuru bulunmamaktadır.</li>';
                            }
                            ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="anasayfa.php?tab=duyurular"><i class="fas fa-bullhorn"></i> Tüm Duyurular</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="avatarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar"><i class="fas fa-user-circle"></i></div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
                            <li><span class="dropdown-greeting">Hoşgeldiniz, <?php echo htmlspecialchars($username); ?></span></li>
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
                                <span class="sepet-adet"><?php echo $sepet_adet; ?></span>
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
    <div class="row">
        <!-- Main Content Area -->
        <div class="col-lg-8">
            <?php if ($tab === 'etkinlikler'): ?>
                <h2 class="mb-4 text-center">Yaklaşan Etkinlikler</h2>
                <div class="row mb-4 g-3 justify-content-center">
                    <div class="col-6 col-sm-3"><a href="anasayfa.php?tab=etkinlikler" class="kategori-card tumu d-block text-decoration-none <?php echo !$tur ? 'active' : ''; ?>"><i class="fas fa-th"></i><h6>Tümü</h6></a></div>
                    <div class="col-6 col-sm-3"><a href="anasayfa.php?tab=etkinlikler&tur=Konser" class="kategori-card konser d-block text-decoration-none <?php echo $tur === 'Konser' ? 'active' : ''; ?>"><i class="fas fa-music"></i><h6>Konser</h6></a></div>
                    <div class="col-6 col-sm-3"><a href="anasayfa.php?tab=etkinlikler&tur=Tiyatro" class="kategori-card tiyatro d-block text-decoration-none <?php echo $tur === 'Tiyatro' ? 'active' : ''; ?>"><i class="fas fa-theater-masks"></i><h6>Tiyatro</h6></a></div>
                    <div class="col-6 col-sm-3"><a href="anasayfa.php?tab=etkinlikler&tur=Futbol" class="kategori-card futbol d-block text-decoration-none <?php echo $tur === 'Futbol' ? 'active' : ''; ?>"><i class="fas fa-futbol"></i><h6>Futbol</h6></a></div>
                    <div class="col-6 col-sm-3"><a href="anasayfa.php?tab=etkinlikler&tur=Sinema" class="kategori-card sinema d-block text-decoration-none <?php echo $tur === 'Sinema' ? 'active' : ''; ?>"><i class="fas fa-film"></i><h6>Sinema</h6></a></div>
                </div>
                <!-- Sıralama Butonları -->
                <div class="mb-3 text-center">
                    <a href="anasayfa.php?tab=etkinlikler<?php echo $tur ? '&tur=' . urlencode($tur) : ''; ?>&sort=tarih_asc" class="sort-button <?php echo $sort_type === 'tarih_asc' ? 'active' : ''; ?>">En Erken Tarih</a>
                    <a href="anasayfa.php?tab=etkinlikler<?php echo $tur ? '&tur=' . urlencode($tur) : ''; ?>&sort=tarih_desc" class="sort-button <?php echo $sort_type === 'tarih_desc' ? 'active' : ''; ?>">En Geç Tarih</a>
                </div>
                <?php echo $message; ?>
                <?php if (mysqli_num_rows($sonuc) > 0): ?>
                    <div class="row g-4">
                        <?php while ($etkinlik = mysqli_fetch_assoc($sonuc)): ?>
                            <?php
                            $konum = $etkinlik['konum'];
                            $city = "Istanbul";
                            if (!empty($konum)) {
                                $konum_parts = array_map('trim', explode(',', $konum));
                                $city = end($konum_parts);
                            }
                            $weather_info = fetch_weather($city, $api_key, $turkey_cities);
                            ?>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($etkinlik["baslik"]); ?></h5>
                                        <h6 class="card-subtitle mb-2"><?php echo htmlspecialchars($etkinlik["tarih"]) . ' - ' . htmlspecialchars($etkinlik["saat"]); ?></h6>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($etkinlik["aciklama"])); ?></p>
                                        <p class="card-text"><strong>Yer:</strong> <?php echo htmlspecialchars($etkinlik["konum"]); ?></p>
                                        <p><strong>Tür:</strong> <?php echo htmlspecialchars($etkinlik["tur"]); ?></p>
                                        <p><strong>Fiyat:</strong> <?php echo number_format($etkinlik["bilet_fiyati"], 2); ?> TL</p>
                                        <p><strong>Kalan Bilet:</strong> <?php echo htmlspecialchars($etkinlik["bilet_sayisi"]); ?></p>
                                        <?php if ($weather_info['temperature'] !== null && $weather_info['description'] !== 'Veri alınamadı' && $weather_info['description'] !== 'Geçersiz şehir'): ?>
                                            <p class="weather-info"><span class="emoji"><?php echo $weather_info['emoji']; ?></span> <?php echo htmlspecialchars($city); ?> Hava Durumu: <?php echo ucfirst($weather_info['description']); ?>, <?php echo number_format($weather_info['temperature'], 1); ?>°C</p>
                                        <?php else: ?>
                                            <p class="weather-info"><span class="emoji"><?php echo $weather_info['emoji']; ?></span> <?php echo htmlspecialchars($city); ?> Hava Durumu: <?php echo $weather_info['description']; ?></p>
                                        <?php endif; ?>
                                        <?php if ($etkinlik["bilet_sayisi"] > 0): ?>
                                            <?php if (isset($_SESSION['kullanici_id'])): ?>
                                                <?php if ($etkinlik['tur'] === 'Konser' && $weather_info['is_bad_weather']): ?>
                                                    <p class="text-danger">Bu konser, <?php echo htmlspecialchars($city); ?> için <?php echo htmlspecialchars($weather_info['weather_reason']); ?> nedeniyle iptal edilmiştir. <?php echo $weather_info['emoji']; ?></p>
                                                <?php else: ?>
                                                    <form method="post" class="d-flex align-items-center">
                                                        <input type="hidden" name="etkinlik_id" value="<?php echo $etkinlik['id']; ?>">
                                                        <label for="adet-<?php echo $etkinlik['id']; ?>" class="me-2">Adet:</label>
                                                        <input type="number" name="adet" id="adet-<?php echo $etkinlik['id']; ?>" value="1" min="1" max="<?php echo htmlspecialchars($etkinlik['bilet_sayisi']); ?>" class="form-control me-2" style="width: 80px;" required>
                                                        <button type="submit" name="bilet_al" class="btn btn-primary btn-sm">Sepete Ekle</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="text-warning">Bilet satın almak için lütfen <a href="giris.php">giriş yapınız.</a></p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="text-danger">Bilet kalmadı.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">Seçilen türde etkinlik bulunmamaktadır.</div>
                <?php endif; ?>
            <?php elseif ($tab === 'duyurular'): ?>
                <h2 class="mb-4 text-center">Duyurular</h2>
                <?php if ($duyuru_kategori): ?>
                    <h3 class="kategori-baslik active"><?php echo htmlspecialchars($duyuru_kategori); ?> Duyuruları</h3>
                    <?php if (mysqli_num_rows($duyuru_sonuc) > 0): ?>
                        <div class="row g-4">
                            <?php while ($duyuru = mysqli_fetch_assoc($duyuru_sonuc)): ?>
                                <div class="col-12">
                                    <div class="duyuru-full-card">
                                        <h4><?php echo htmlspecialchars($duyuru["baslik"]); ?></h4>
                                        <p><?php echo nl2br(htmlspecialchars($duyuru["icerik"])); ?></p>
                                        <div class="duyuru-tarih"><?php echo htmlspecialchars($duyuru["yayin_tarihi"]); ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">Bu kategoride duyuru bulunmamaktadır.</div>
                    <?php endif; ?>
                    <a href="anasayfa.php?tab=duyurular" class="btn btn-secondary mt-3">Tüm Duyurulara Geri Dön</a>
                <?php else: ?>
                    <?php foreach ($kategoriler as $kategori): ?>
                        <?php if (mysqli_num_rows($kategori_duyurulari[$kategori]) > 0): ?>
                            <h3 class="kategori-baslik" onclick="window.location.href='anasayfa.php?tab=duyurular&kategori=<?php echo urlencode($kategori); ?>'"><?php echo htmlspecialchars($kategori); ?> Duyuruları</h3>
                            <div class="row g-4">
                                <?php while ($duyuru = mysqli_fetch_assoc($kategori_duyurulari[$kategori])): ?>
                                    <div class="col-12">
                                        <div class="duyuru-full-card">
                                            <h4><?php echo htmlspecialchars($duyuru["baslik"]); ?></h4>
                                            <p><?php echo nl2br(htmlspecialchars($duyuru["icerik"])); ?></p>
                                            <div class="duyuru-tarih"><?php echo htmlspecialchars($duyuru["yayin_tarihi"]); ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                <?php mysqli_data_seek($kategori_duyurulari[$kategori], 0); ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (empty(array_filter($kategori_duyurulari, fn($result) => mysqli_num_rows($result) > 0))): ?>
                        <div class="alert alert-info text-center">Henüz duyuru bulunmamaktadır.</div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <!-- Announcements Sidebar -->
        <div class="col-lg-4">
            <div class="duyurular-baslik" onclick="window.location.href='anasayfa.php?tab=duyurular'">
                <i class="fas fa-bullhorn"></i> Duyurular
            </div>
            <?php
            mysqli_data_seek($duyuru_limit_sonuc, 0);
            if (mysqli_num_rows($duyuru_limit_sonuc) > 0):
                while ($duyuru = mysqli_fetch_assoc($duyuru_limit_sonuc)):
            ?>
                <div class="duyuru-card" data-bs-toggle="modal" data-bs-target="#duyuruModal<?php echo $duyuru['id']; ?>">
                    <h5><?php echo htmlspecialchars($duyuru["baslik"]); ?></h5>
                    <p><?php echo htmlspecialchars($duyuru["icerik"]); ?></p>
                    <div class="duyuru-tarih"><?php echo htmlspecialchars($duyuru["yayin_tarihi"]) . ' (' . htmlspecialchars($duyuru["kategori"] ?? 'Genel') . ')'; ?></div>
                </div>
                <div class="modal fade" id="duyuruModal<?php echo $duyuru['id']; ?>" tabindex="-1" aria-labelledby="duyuruModalLabel<?php echo $duyuru['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="duyuruModalLabel<?php echo $duyuru['id']; ?>"><?php echo htmlspecialchars($duyuru["baslik"]); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p><?php echo nl2br(htmlspecialchars($duyuru["icerik"])); ?></p>
                                <p class="duyuru-tarih"><?php echo htmlspecialchars($duyuru["yayin_tarihi"]) . ' (' . htmlspecialchars($duyuru["kategori"] ?? 'Genel') . ')'; ?></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
                endwhile;
            else:
            ?>
                <div class="alert alert-info">Henüz duyuru bulunmamaktadır.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>