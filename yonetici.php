<?php
session_start();
include("baglanti.php");

if (!isset($_SESSION["email"])) {
    header("Location: giris.php");
    exit();
}

$apiKey = "23EO3U53znRAho40kKxeXdsCNIWzwno7"; // Ticketmaster API
$cinemaApiKey = "3df937616853c13f516147451a8b0c5d"; // TMDb API
$tab = $_GET['tab'] ?? 'etkinlik';
$message = "";

// Sabit bilet fiyatları
$fiyatlar = [
    'Konser' => 1000.00,
    'Tiyatro' => 800.00,
    'Sinema' => 550.00,
    'Futbol' => 400.00
];

if ($tab === 'etkinlik') {
    $subtab = $_GET['subtab'] ?? 'konser';

    switch ($subtab) {
        case 'futbol':
            $classification = "sports";
            $isCinema = false;
            break;
        case 'tiyatro':
            $classification = "theatre";
            $isCinema = false;
            break;
        case 'sinema':
            $classification = "movie";
            $isCinema = true;
            break;
        case 'konser':
        default:
            $classification = "music";
            $isCinema = false;
    }

    // Fetch events
    if ($isCinema) {
        $url = "https://api.themoviedb.org/3/movie/now_playing?api_key=$cinemaApiKey&language=tr-TR&region=TR";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        $etkinlikler = $data["results"] ?? [];
    } else {
        $url = "https://app.ticketmaster.com/discovery/v2/events.json?classificationName=$classification&countryCode=TR&apikey=$apiKey";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        $etkinlikler = $data["_embedded"]["events"] ?? [];
    }

    // Get existing event_ids from database
    $mevcut_sorgu = mysqli_query($baglanti, "SELECT event_id FROM etkinlikler WHERE event_id IS NOT NULL");
    $mevcut_event_ids = [];
    while ($row = mysqli_fetch_assoc($mevcut_sorgu)) {
        $mevcut_event_ids[] = $row["event_id"];
    }

    // Add event form
    if (isset($_GET["ekle_event"])) {
        $event_id = $_GET["ekle_event"];

        foreach ($etkinlikler as $etkinlik) {
            if ($isCinema) {
                if ($etkinlik["id"] == $event_id) {
                    $baslik = $etkinlik["title"];
                    $aciklama = $etkinlik["overview"] ?? "Açıklama yok.";
                    $tarih = $etkinlik["release_date"] ?? date('Y-m-d');
                    $saat = "20:00";
                    $konum = "İstanbul Cinemaximum";
                    $tur = "Sinema";
                    $hava_gereklilik = 0;
                    break;
                }
            } else {
                if ($etkinlik["id"] === $event_id) {
                    $baslik = $etkinlik["name"];
                    $aciklama = $etkinlik["info"] ?? "Açıklama yok.";
                    $tarih = substr($etkinlik["dates"]["start"]["localDate"], 0, 10);
                    $saat = substr($etkinlik["dates"]["start"]["localTime"] ?? "20:00", 0, 5);
                    $konum = $etkinlik["_embedded"]["venues"][0]["name"] ?? "Bilinmiyor";
                    $tur = ucfirst($subtab);
                    $hava_gereklilik = 0;
                    break;
                }
            }
        }

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $bilet_sayisi = $_POST["bilet_sayisi"];
            $bilet_fiyati = $fiyatlar[$tur]; // Sabit fiyat etkinlik türüne göre atanır

            $ekle = mysqli_prepare($baglanti, "INSERT INTO etkinlikler (baslik, aciklama, tarih, saat, konum, tur, hava_gereklilik, aktif, event_id, bilet_fiyati, bilet_sayisi) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)");
            mysqli_stmt_bind_param($ekle, "ssssssisdi", $baslik, $aciklama, $tarih, $saat, $konum, $tur, $hava_gereklilik, $event_id, $bilet_fiyati, $bilet_sayisi);
            mysqli_stmt_execute($ekle);

            $message = "<div class='alert alert-success'>Etkinlik eklendi!</div>";
            header("Location: yonetici.php?tab=etkinlik&subtab=$subtab");
            exit();
        }
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Etkinlik Ekle - Yönetici Paneli</title>
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
                .card {
                    border: none;
                    border-radius: 12px;
                    background-color: #ffffff;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
                }
                .btn-primary {
                    background-color: #0d6efd;
                    border: none;
                    border-radius: 6px;
                    padding: 8px 18px;
                }
                .btn-primary:hover {
                    background-color: #0b5ed7;
                }
                .form-control {
                    border-radius: 6px;
                    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
                }
                .navbar-brand, .nav-link {
                    color: #0d6efd !important;
                }
                .nav-link:hover {
                    color: #0b5ed7 !important;
                }
                .nav-link.active {
                    font-weight: bold;
                    border-bottom: 2px solid #0d6efd;
                }
                .form-label {
                    font-weight: 500;
                    color: #333;
                }
            </style>
        </head>
        <body>
            <nav class="navbar navbar-expand-lg navbar-light fixed-top">
                <div class="container">
                    <a class="navbar-brand" href="yonetici.php">Yönetici Paneli</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link active" href="?tab=etkinlik&subtab=<?= $subtab ?>">Etkinlikler</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="?tab=kullanici">Kullanıcı Onayı</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="?tab=duyurular">Duyurular</a>
                            </li>
                        </ul>
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="cikis.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <div class="container mt-5 pt-5">
                <div class="card p-4">
                    <h3 class="mb-4"><?= htmlspecialchars($baslik) ?> Etkinliğine Bilet Bilgisi Ekle</h3>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Bilet Fiyatı (TL)</label>
                            <p class="form-control-plaintext"><?= number_format($fiyatlar[$tur], 2) ?> TL (Sabit)</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bilet Sayısı</label>
                            <input type="number" min="0" name="bilet_sayisi" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Etkinliği Ekle</button>
                        <a href="?tab=etkinlik&subtab=<?= $subtab ?>" class="btn btn-secondary">İptal</a>
                    </form>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit();
    }

    // Delete event
    if (isset($_GET["sil_event"])) {
        $event_id = $_GET["sil_event"];
        $sil = mysqli_prepare($baglanti, "DELETE FROM etkinlikler WHERE event_id = ?");
        mysqli_stmt_bind_param($sil, "s", $event_id);
        mysqli_stmt_execute($sil);
        $message = "<div class='alert alert-success'>Etkinlik silindi!</div>";
        header("Location: yonetici.php?tab=etkinlik&subtab=$subtab");
        exit();
    }
}

if ($tab === 'kullanici') {
    if (isset($_GET["onayla"])) {
        $id = $_GET["onayla"];
        $guncelle = mysqli_prepare($baglanti, "UPDATE kullanicilar SET onay = 1 WHERE id = ? AND admin = 0");
        mysqli_stmt_bind_param($guncelle, "i", $id);
        mysqli_stmt_execute($guncelle);
        $message = "<div class='alert alert-success'>Kullanıcı onaylandı!</div>";
        header("Location: yonetici.php?tab=kullanici");
        exit();
    }

    $kullanici_sorgu = "SELECT * FROM kullanicilar WHERE admin = 0";
    $kullanici_sonuc = mysqli_query($baglanti, $kullanici_sorgu);
}

if ($tab === 'duyurular') {
    // Add announcement
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ekle_duyuru'])) {
        $baslik = $_POST['baslik'];
        $icerik = $_POST['icerik'];
        $kategori = $_POST['kategori'];
        $yayin_tarihi = date('Y-m-d H:i:s');

        $ekle = mysqli_prepare($baglanti, "INSERT INTO duyurular (baslik, icerik, kategori, yayin_tarihi, aktif) VALUES (?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($ekle, "ssss", $baslik, $icerik, $kategori, $yayin_tarihi);
        mysqli_stmt_execute($ekle);

        $message = "<div class='alert alert-success'>Duyuru eklendi!</div>";
    }

    // Edit announcement
    if (isset($_GET["duzenle_duyuru"])) {
        $duyuru_id = $_GET["duzenle_duyuru"];
        $sorgu = mysqli_prepare($baglanti, "SELECT * FROM duyurular WHERE id = ?");
        mysqli_stmt_bind_param($sorgu, "i", $duyuru_id);
        mysqli_stmt_execute($sorgu);
        $result = mysqli_stmt_get_result($sorgu);
        $duyuru = mysqli_fetch_assoc($result);

        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['duzenle_duyuru'])) {
            $baslik = $_POST['baslik'];
            $icerik = $_POST['icerik'];
            $kategori = $_POST['kategori'];
            $aktif = isset($_POST['aktif']) ? 1 : 0;

            $guncelle = mysqli_prepare($baglanti, "UPDATE duyurular SET baslik = ?, icerik = ?, kategori = ?, aktif = ? WHERE id = ?");
            mysqli_stmt_bind_param($guncelle, "ssssi", $baslik, $icerik, $kategori, $aktif, $duyuru_id);
            mysqli_stmt_execute($guncelle);

            $message = "<div class='alert alert-success'>Duyuru güncellendi!</div>";
            header("Location: yonetici.php?tab=duyurular");
            exit();
        }
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Duyuru Düzenle - Yönetici Paneli</title>
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
                .card {
                    border: none;
                    border-radius: 12px;
                    background-color: #ffffff;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
                }
                .btn-primary {
                    background-color: #0d6efd;
                    border: none;
                    border-radius: 6px;
                    padding: 8px 18px;
                }
                .btn-primary:hover {
                    background-color: #0b5ed7;
                }
                .form-control {
                    border-radius: 6px;
                    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
                }
                .navbar-brand, .nav-link {
                    color: #0d6efd !important;
                }
                .nav-link:hover {
                    color: #0b5ed7 !important;
                }
                .nav-link.active {
                    font-weight: bold;
                    border-bottom: 2px solid #0d6efd;
                }
                .form-label {
                    font-weight: 500;
                    color: #333;
                }
            </style>
        </head>
        <body>
            <nav class="navbar navbar-expand-lg navbar-light fixed-top">
                <div class="container">
                    <a class="navbar-brand" href="yonetici.php">Yönetici Paneli</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="?tab=etkinlik">Etkinlikler</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="?tab=kullanici">Kullanıcı Onayı</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="?tab=duyurular">Duyurular</a>
                            </li>
                        </ul>
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="cikis.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <div class="container mt-5 pt-5">
                <div class="card p-4">
                    <h3 class="mb-4">Duyuru Düzenle</h3>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" name="baslik" class="form-control" value="<?= htmlspecialchars($duyuru['baslik']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">İçerik</label>
                            <textarea name="icerik" class="form-control" rows="5" required><?= htmlspecialchars($duyuru['icerik']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" class="form-control" required>
                                <option value="Genel" <?= $duyuru['kategori'] === 'Genel' ? 'selected' : '' ?>>Genel</option>
                                <option value="Konser" <?= $duyuru['kategori'] === 'Konser' ? 'selected' : '' ?>>Konser</option>
                                <option value="Tiyatro" <?= $duyuru['kategori'] === 'Tiyatro' ? 'selected' : '' ?>>Tiyatro</option>
                                <option value="Futbol" <?= $duyuru['kategori'] === 'Futbol' ? 'selected' : '' ?>>Futbol</option>
                                <option value="Sinema" <?= $duyuru['kategori'] === 'Sinema' ? 'selected' : '' ?>>Sinema</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-check-label">
                                <input type="checkbox" name="aktif" value="1" <?= $duyuru['aktif'] ? 'checked' : '' ?>> Aktif (Onaylı)
                            </label>
                        </div>
                        <button type="submit" name="duzenle_duyuru" class="btn btn-primary">Kaydet</button>
                        <a href="?tab=duyurular" class="btn btn-secondary">İptal</a>
                    </form>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit();
    }

    // Delete announcement
    if (isset($_GET["sil_duyuru"])) {
        $duyuru_id = $_GET["sil_duyuru"];
        $sil = mysqli_prepare($baglanti, "DELETE FROM duyurular WHERE id = ?");
        mysqli_stmt_bind_param($sil, "i", $duyuru_id);
        mysqli_stmt_execute($sil);
        $message = "<div class='alert alert-success'>Duyuru silindi!</div>";
        header("Location: yonetici.php?tab=duyurular");
        exit();
    }

    // List announcements
    $duyuru_sorgu = "SELECT * FROM duyurular ORDER BY yayin_tarihi DESC";
    $duyuru_sonuc = mysqli_query($baglanti, $duyuru_sorgu);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Paneli</title>
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
        .card {
            border: none;
            border-radius: 12px;
            background-color: #ffffff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }
        .table {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .table th {
            background-color: #0d6efd;
            color: #ffffff;
            border: none;
        }
        .table td {
            vertical-align: middle;
        }
        .btn-primary {
            background-color: #0d6efd;
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
            transition: background-color 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        .btn-success {
            background-color: #28a745;
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-danger {
            background-color: #dc3545;
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-warning {
            background-color: #ffc107;
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .form-control {
            border-radius: 6px;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: border-color 0.2s ease;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .form-label {
            font-weight: 500;
            color: #333;
        }
        .alert {
            border-radius: 8px;
        }
        .nav-tabs, .nav-pills {
            border-bottom: 2px solid #0d6efd;
        }
        .nav-tabs .nav-link, .nav-pills .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            color: #6c757d !important;
        }
        .nav-tabs .nav-link.active, .nav-pills .nav-link.active {
            background-color: #0d6efd;
            color: #ffffff !important;
            border-bottom: none;
        }
        .nav-tabs .nav-link:hover, .nav-pills .nav-link:hover {
            background-color: #e6f3ff;
            color: #0b5ed7 !important;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href="yonetici.php">Yönetici Paneli</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'etkinlik' ? 'active' : '' ?>" href="?tab=etkinlik">Etkinlikler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'kullanici' ? 'active' : '' ?>" href="?tab=kullanici">Kullanıcı Onayı</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'duyurular' ? 'active' : '' ?>" href="?tab=duyurular">Duyurular</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="cikis.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-5 pt-5">
    <h2 class="mb-4 text-center">Yönetici Paneli</h2>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'etkinlik' ? 'active' : '' ?>" href="?tab=etkinlik">Etkinlikler</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'kullanici' ? 'active' : '' ?>" href="?tab=kullanici">Kullanıcı Onayı</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'duyurular' ? 'active' : '' ?>" href="?tab=duyurular">Duyurular</a>
        </li>
    </ul>

    <!-- Etkinlikler Sekmesi -->
    <?php if ($tab === 'etkinlik'): ?>
        <div class="card p-4 mb-4">
            <h3 class="mb-4">Etkinlikler</h3>
            <ul class="nav nav-pills mb-3">
                <li class="nav-item">
                    <a class="nav-link <?= $subtab === 'konser' ? 'active' : '' ?>" href="?tab=etkinlik&subtab=konser">Konserler</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $subtab === 'futbol' ? 'active' : '' ?>" href="?tab=etkinlik&subtab=futbol">Futbol Maçı</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $subtab === 'tiyatro' ? 'active' : '' ?>" href="?tab=etkinlik&subtab=tiyatro">Tiyatro</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $subtab === 'sinema' ? 'active' : '' ?>" href="?tab=etkinlik&subtab=sinema">Sinema</a>
                </li>
            </ul>
            <?= $message ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Başlık</th>
                        <th>Tarih</th>
                        <th>Saat</th>
                        <th>Yer</th>
                        <th>Bilet Fiyatı (TL)</th>
                        <th>Kalan Bilet</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etkinlikler as $etkinlik):
                        $id = $etkinlik["id"];
                        if ($isCinema) {
                            $baslik = $etkinlik["title"];
                            $tarih = $etkinlik["release_date"] ?? "-";
                            $saat = "20:00";
                            $mekan = "İstanbul Cinemaximum";
                        } else {
                            $baslik = $etkinlik["name"];
                            $tarih = $etkinlik["dates"]["start"]["localDate"] ?? "-";
                            $saat = $etkinlik["dates"]["start"]["localTime"] ?? "-";
                            $mekan = $etkinlik["_embedded"]["venues"][0]["name"] ?? "Bilinmiyor";
                        }
                        $eklenmis = in_array($id, $mevcut_event_ids);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($baslik) ?></td>
                        <td><?= htmlspecialchars($tarih) ?></td>
                        <td><?= htmlspecialchars($saat) ?></td>
                        <td><?= htmlspecialchars($mekan) ?></td>
                        <td>
                            <?php
                            $sql = "SELECT bilet_fiyati FROM etkinlikler WHERE event_id = ?";
                            $stmt = mysqli_prepare($baglanti, $sql);
                            mysqli_stmt_bind_param($stmt, "s", $id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            if ($row = mysqli_fetch_assoc($result)) {
                                echo htmlspecialchars(number_format($row['bilet_fiyati'], 2));
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $sql = "SELECT bilet_sayisi FROM etkinlikler WHERE event_id = ?";
                            $stmt = mysqli_prepare($baglanti, $sql);
                            mysqli_stmt_bind_param($stmt, "s", $id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            if ($row = mysqli_fetch_assoc($result)) {
                                echo htmlspecialchars($row['bilet_sayisi']);
                            } else {
                                echo "-";
                            }
                            ?>
                        </td>
                        <td>
                            <?php if (!$eklenmis): ?>
                                <a href="?tab=etkinlik&subtab=<?= $subtab ?>&ekle_event=<?= $id ?>" class="btn btn-sm btn-success">Ekle</a>
                            <?php else: ?>
                                <a href="?tab=etkinlik&subtab=<?= $subtab ?>&sil_event=<?= $id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Etkinliği silmek istediğinize emin misiniz?')">Sil</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($etkinlikler)): ?>
                <div class="alert alert-info">Seçilen kategoride etkinlik bulunamadı.</div>
            <?php endif; ?>
        </div>

    <!-- Kullanıcı Onayı Sekmesi -->
    <?php elseif ($tab === 'kullanici'): ?>
        <div class="card p-4">
            <h3 class="mb-4">Kullanıcı Onayı</h3>
            <?= $message ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>İsim</th>
                        <th>Email</th>
                        <th>Onay Durumu</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($kullanici = mysqli_fetch_assoc($kullanici_sonuc)): ?>
                        <tr>
                            <td><?= htmlspecialchars($kullanici["kullanici_adi"]) ?></td>
                            <td><?= htmlspecialchars($kullanici["email"]) ?></td>
                            <td><?= $kullanici["onay"] ? "Onaylı" : "Onaysız" ?></td>
                            <td>
                                <?php if (!$kullanici["onay"]): ?>
                                    <a href="?tab=kullanici&onayla=<?= $kullanici["id"] ?>" class="btn btn-sm btn-primary">Onayla</a>
                                <?php else: ?>
                                    <span>--</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if (mysqli_num_rows($kullanici_sonuc) == 0): ?>
                <div class="alert alert-info">Henüz kullanıcı bulunmamaktadır.</div>
            <?php endif; ?>
        </div>

    <!-- Duyurular Sekmesi -->
    <?php elseif ($tab === 'duyurular'): ?>
        <div class="card p-4 mb-4">
            <h3 class="mb-4">Yeni Duyuru Ekle</h3>
            <?= $message ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Başlık</label>
                    <input type="text" name="baslik" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">İçerik</label>
                    <textarea name="icerik" class="form-control" rows="5" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" class="form-control" required>
                        <option value="Genel">Genel</option>
                        <option value="Konser">Konser</option>
                        <option value="Tiyatro">Tiyatro</option>
                        <option value="Futbol">Futbol</option>
                        <option value="Sinema">Sinema</option>
                    </select>
                </div>
                <button type="submit" name="ekle_duyuru" class="btn btn-primary">Duyuru Ekle</button>
            </form>
        </div>
        <div class="card p-4">
            <h3 class="mb-4">Duyurular</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Başlık</th>
                        <th>Kategori</th>
                        <th>Yayın Tarihi</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($duyuru = mysqli_fetch_assoc($duyuru_sonuc)): ?>
                        <tr>
                            <td><?= htmlspecialchars($duyuru["baslik"]) ?></td>
                            <td><?= htmlspecialchars($duyuru["kategori"]) ?></td>
                            <td><?= htmlspecialchars($duyuru["yayin_tarihi"]) ?></td>
                            <td><?= $duyuru["aktif"] ? 'Onaylı' : 'Onay Bekliyor' ?></td>
                            <td>
                                <a href="?tab=duyurular&duzenle_duyuru=<?= $duyuru["id"] ?>" class="btn btn-sm btn-warning">Düzenle</a>
                                <a href="?tab=duyurular&sil_duyuru=<?= $duyuru["id"] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Duyuruyu silmek istediğinize emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if (mysqli_num_rows($duyuru_sonuc) == 0): ?>
                <div class="alert alert-info">Henüz duyuru bulunmamaktadır.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>