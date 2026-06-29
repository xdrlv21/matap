<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'kepsek') {
    header("Location: login.php"); exit();
}

$host="localhost"; $user="root"; $pass=""; $db="db_pemeriksaan";
$koneksi = new mysqli($host, $user, $pass, $db);

$siswa_id = intval($_GET['siswa_id'] ?? 0);
if (!$siswa_id) { die("ID Siswa tidak valid."); }

// Data siswa
$stmt = $koneksi->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->bind_param("i", $siswa_id); $stmt->execute();
$siswa = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$siswa) { die("Siswa tidak ditemukan."); }

// Statistik pelanggaran
$stmt2 = $koneksi->prepare("SELECT
    COUNT(*) as total_cek,
    SUM(CASE WHEN status_pemeriksaan='dihukum' THEN 1 ELSE 0 END) as total_pelanggaran,
    SUM(CASE WHEN pakai_topi='tidak' THEN 1 ELSE 0 END) as v_topi,
    SUM(CASE WHEN rambut_rapi='tidak' THEN 1 ELSE 0 END) as v_rambut,
    SUM(CASE WHEN pakai_sabuk='tidak' THEN 1 ELSE 0 END) as v_sabuk
    FROM data_pemeriksaan WHERE siswa_id = ?");
$stmt2->bind_param("i", $siswa_id); $stmt2->execute();
$stat = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$POIN_PER = 5;
$poin = $stat['total_pelanggaran'] * $POIN_PER;

// Riwayat pelanggaran terakhir (10 entri)
$stmt3 = $koneksi->prepare("SELECT * FROM data_pemeriksaan WHERE siswa_id = ? AND status_pemeriksaan='dihukum' ORDER BY id DESC LIMIT 10");
$stmt3->bind_param("i", $siswa_id); $stmt3->execute();
$riwayat = $stmt3->get_result();

$no_surat = "ST/506/".date('Y')."/".str_pad($siswa_id, 3, '0', STR_PAD_LEFT)."/".date('m');
$tanggal = date('d F Y');
$koneksi->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Teguran — <?= htmlspecialchars($siswa['nama']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Times+New+Roman&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f5f5f5;
            display: flex; flex-direction: column;
            align-items: center; padding: 30px 20px;
            color: #111;
        }

        .print-btn {
            position: fixed; top: 20px; right: 20px; z-index: 999;
            background: #0284c7; color: white;
            border: none; padding: 12px 24px;
            border-radius: 10px; cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem; font-weight: 700;
            box-shadow: 0 4px 14px rgba(2,132,199,0.4);
            transition: all 0.25s ease;
        }
        .print-btn:hover { background: #0369a1; transform: translateY(-2px); }

        .back-btn {
            position: fixed; top: 20px; left: 20px; z-index: 999;
            background: rgba(255,255,255,0.9); color: #475569;
            border: 1px solid #e2e8f0; padding: 12px 20px;
            border-radius: 10px; cursor: pointer; text-decoration: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.85rem; font-weight: 600;
        }

        /* ============ KERTAS SURAT ============ */
        .surat {
            width: 210mm; min-height: 297mm;
            background: white;
            padding: 25mm 20mm 20mm;
            box-shadow: 0 8px 40px rgba(0,0,0,0.12);
            position: relative;
        }

        /* KOP SURAT */
        .kop {
            display: flex; align-items: center;
            gap: 18px; border-bottom: 3px solid #0284c7;
            padding-bottom: 14px; margin-bottom: 18px;
        }
        .kop img { width: 70px; height: 70px; object-fit: contain; }
        .kop-text { flex: 1; text-align: center; }
        .kop-text h1 { font-size: 14pt; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; color: #0f172a; letter-spacing: 0.5px; }
        .kop-text p  { font-size: 9.5pt; color: #475569; font-family: 'Plus Jakarta Sans', sans-serif; margin-top: 2px; }

        /* JUDUL */
        .judul-surat {
            text-align: center; margin: 20px 0 8px;
        }
        .judul-surat h2 {
            font-size: 13pt; font-weight: bold;
            text-decoration: underline; text-transform: uppercase;
            letter-spacing: 1px;
        }
        .no-surat {
            text-align: center; font-size: 10pt; margin-bottom: 22px;
            color: #374151;
        }

        /* ISI */
        .isi p { font-size: 11pt; line-height: 1.9; text-align: justify; margin-bottom: 10px; }
        .isi .indent { text-indent: 40px; }

        /* TABEL DATA SISWA */
        .tabel-identitas { width: 100%; border-collapse: collapse; margin: 14px 0 18px; }
        .tabel-identitas td { padding: 5px 8px; font-size: 10.5pt; vertical-align: top; }
        .tabel-identitas td:first-child { width: 140px; font-weight: bold; }
        .tabel-identitas td:nth-child(2) { width: 12px; }

        /* TABEL RIWAYAT */
        .tabel-riwayat { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 9.5pt; }
        .tabel-riwayat th { background: #0284c7; color: white; padding: 7px 10px; text-align: left; }
        .tabel-riwayat td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; }
        .tabel-riwayat tr:nth-child(even) td { background: #f8fafc; }

        /* STATISTIK BOX */
        .stat-box {
            display: flex; gap: 14px; margin: 14px 0 18px;
            flex-wrap: wrap;
        }
        .stat-item {
            flex: 1; min-width: 80px;
            background: #f0f9ff; border: 1px solid #bae6fd;
            border-radius: 8px; padding: 10px 14px; text-align: center;
        }
        .stat-item strong { display: block; font-size: 18pt; color: #0284c7; }
        .stat-item span { font-size: 8pt; color: #475569; font-family: 'Plus Jakarta Sans', sans-serif; }

        .stat-item.red { background: #fff1f2; border-color: #fecaca; }
        .stat-item.red strong { color: #dc2626; }

        /* PENUTUP & TTD */
        .penutup { margin-top: 20px; }
        .ttd-section {
            display: flex; justify-content: space-between;
            margin-top: 30px;
        }
        .ttd-box { text-align: center; width: 200px; }
        .ttd-box .nama-ttd { margin-top: 60px; font-weight: bold; font-size: 11pt; border-top: 1px solid #333; padding-top: 6px; }
        .ttd-box .jabatan { font-size: 9.5pt; }

        .watermark-teguran {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 72pt; font-weight: 900;
            color: rgba(239, 68, 68, 0.06);
            white-space: nowrap; pointer-events: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            letter-spacing: 4px;
        }

        @media print {
            body { background: white; padding: 0; }
            .print-btn, .back-btn { display: none; }
            .surat { box-shadow: none; width: 100%; padding: 15mm 18mm 15mm; }
        }
    </style>
</head>
<body>

<a href="pemeriksaanseragam.php" class="back-btn">← Kembali</a>
<button class="print-btn" onclick="window.print()">🖨 Cetak / Simpan PDF</button>

<div class="surat">
    <div class="watermark-teguran">SURAT TEGURAN</div>

    <!-- KOP SURAT -->
    <div class="kop">
        <img src="skadik.png" alt="SKADIK">
        <div class="kop-text">
            <h1>SKADRON PENDIDIKAN 506</h1>
            <p>Sistem Disiplin & Monitoring Kelengkapan Seragam Siswa</p>
            <p style="font-size:8.5pt; margin-top:2px;">TNI ANGKATAN UDARA</p>
        </div>
        <img src="tniau.png" alt="TNI AU">
    </div>

    <!-- JUDUL -->
    <div class="judul-surat">
        <h2>SURAT TEGURAN RESMI</h2>
    </div>
    <div class="no-surat">Nomor: <?= $no_surat ?></div>

    <!-- ISI SURAT -->
    <div class="isi">
        <p class="indent">Yang bertanda tangan di bawah ini, Kepala Sekolah SKADIK 506, dengan ini memberikan <strong>Surat Teguran Resmi</strong> kepada siswa yang tersebut di bawah ini:</p>

        <table class="tabel-identitas">
            <tr><td>Nama Siswa</td><td>:</td><td><strong><?= htmlspecialchars($siswa['nama']) ?></strong></td></tr>
            <tr><td>ID Siswa</td><td>:</td><td><?= htmlspecialchars($siswa['id']) ?></td></tr>
            <tr><td>Total Pemeriksaan</td><td>:</td><td><?= $stat['total_cek'] ?> kali</td></tr>
            <tr><td>Total Pelanggaran</td><td>:</td><td><strong style="color:#dc2626;"><?= $stat['total_pelanggaran'] ?> kali</strong></td></tr>
            <tr><td>Akumulasi Poin</td><td>:</td><td><strong style="color:#dc2626;"><?= $poin ?> poin</strong> (Ambang batas: 30 poin)</td></tr>
        </table>

        <p>Statistik pelanggaran per kategori atribut:</p>
        <div class="stat-box">
            <div class="stat-item red"><strong><?= $stat['v_topi'] ?></strong><span>Topi Alpa</span></div>
            <div class="stat-item red"><strong><?= $stat['v_rambut'] ?></strong><span>Rambut Tidak Rapi</span></div>
            <div class="stat-item red"><strong><?= $stat['v_sabuk'] ?></strong><span>Sabuk Alpa</span></div>
            <div class="stat-item"><strong><?= $poin ?></strong><span>Total Poin</span></div>
        </div>

        <p class="indent">Sehubungan dengan akumulasi poin pelanggaran kelengkapan seragam yang telah <strong>melewati batas toleransi sistem</strong> (≥ 30 poin), siswa yang bersangkutan dinyatakan mendapatkan teguran formal. Riwayat pelanggaran terakhir tercatat sebagai berikut:</p>

        <table class="tabel-riwayat">
            <thead>
                <tr><th>#</th><th>Tanggal</th><th>Topi</th><th>Rambut</th><th>Sabuk</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php $no=1; while($r = $riwayat->fetch_assoc()): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                    <td><?= $r['pakai_topi']=='ya'?'✔':'✗ Alpa' ?></td>
                    <td><?= $r['rambut_rapi']=='ya'?'✔':'✗ Melanggar' ?></td>
                    <td><?= $r['pakai_sabuk']=='ya'?'✔':'✗ Alpa' ?></td>
                    <td style="color:#dc2626; font-weight:bold;">DIHUKUM</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="penutup">
            <p class="indent">Dengan dikeluarkannya surat teguran ini, siswa yang bersangkutan <strong>diwajibkan untuk segera memperbaiki</strong> kedisiplinan dalam hal kelengkapan atribut seragam dan tidak mengulangi pelanggaran serupa. Apabila pelanggaran terus berlanjut, maka akan dilakukan tindakan pembinaan lebih lanjut sesuai peraturan yang berlaku.</p>
            <p class="indent">Demikian surat teguran ini dibuat untuk menjadi perhatian dan dilaksanakan sebagaimana mestinya.</p>
        </div>
    </div>

    <!-- TTD -->
    <div class="ttd-section">
        <div class="ttd-box">
            <p>Orang Tua / Wali</p>
            <div class="nama-ttd">(________________________)</div>
            <div class="jabatan">Tanda Tangan & Nama Jelas</div>
        </div>
        <div class="ttd-box" style="text-align:center;">
            <p>Bekasi, <?= $tanggal ?></p>
            <p>Kepala Sekolah SKADIK 506</p>
            <div class="nama-ttd">(________________________)</div>
            <div class="jabatan">Kepala Sekolah</div>
        </div>
    </div>

    <hr style="margin-top:30px; border:none; border-top:1px solid #e5e7eb;">
    <p style="font-size:7.5pt; color:#94a3b8; text-align:center; margin-top:8px; font-family:'Plus Jakarta Sans',sans-serif;">
        Dokumen ini digenerate otomatis oleh Sistem Informasi Disiplin SKADIK 506 • <?= date('d/m/Y H:i:s') ?>
    </p>
</div>

</body>
</html>
