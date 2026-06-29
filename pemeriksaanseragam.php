<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost"; $user = "root"; $pass = ""; $db = "db_pemeriksaan";
$koneksi = new mysqli($host, $user, $pass, $db);
if ($koneksi->connect_error) {
    die("<span style='color:#ef4444; font-weight:bold;'>[ERROR] Database Connection Failed</span>");
}

$role = $_SESSION['role'];
$query_siswa = "SELECT * FROM siswa ORDER BY nama ASC";
$result_siswa = $koneksi->query($query_siswa);

// ============================================================
// ANALYTICS (KEPSEK)
// ============================================================
$total_lulus = 0; $total_dihukum = 0;
$v_topi = 0; $v_rambut = 0; $v_sabuk = 0;

// --- RECAP DATA ---
// Mingguan (7 hari terakhir)
$recap_mingguan = [];
$q_week = $koneksi->query("
    SELECT s.nama, s.id as siswa_id,
        SUM(CASE WHEN dp.status_pemeriksaan='dihukum' THEN 1 ELSE 0 END) as pelanggaran,
        COUNT(dp.id) as total_cek
    FROM siswa s
    LEFT JOIN data_pemeriksaan dp ON s.id = dp.siswa_id
        AND dp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY s.id, s.nama ORDER BY pelanggaran DESC
");
if ($q_week) {
    while ($r = $q_week->fetch_assoc()) $recap_mingguan[] = $r;
}

// Bulanan (30 hari terakhir)
$recap_bulanan = [];
$q_month = $koneksi->query("
    SELECT s.nama, s.id as siswa_id,
        SUM(CASE WHEN dp.status_pemeriksaan='dihukum' THEN 1 ELSE 0 END) as pelanggaran,
        COUNT(dp.id) as total_cek
    FROM siswa s
    LEFT JOIN data_pemeriksaan dp ON s.id = dp.siswa_id
        AND dp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY s.id, s.nama ORDER BY pelanggaran DESC
");
if ($q_month) {
    while ($r = $q_month->fetch_assoc()) $recap_bulanan[] = $r;
}

// Tahunan (365 hari terakhir)
$recap_tahunan = [];
$q_year = $koneksi->query("
    SELECT s.nama, s.id as siswa_id,
        SUM(CASE WHEN dp.status_pemeriksaan='dihukum' THEN 1 ELSE 0 END) as pelanggaran,
        COUNT(dp.id) as total_cek
    FROM siswa s
    LEFT JOIN data_pemeriksaan dp ON s.id = dp.siswa_id
        AND dp.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
    GROUP BY s.id, s.nama ORDER BY pelanggaran DESC
");
if ($q_year) {
    while ($r = $q_year->fetch_assoc()) $recap_tahunan[] = $r;
}

// Sistem Poin: 1 pelanggaran = 5 poin. Ambang batas teguran = 30 poin (6 pelanggaran)
$POIN_PER_PELANGGARAN = 5;
$AMBANG_TEGURAN = 30;

// Hitung poin per siswa (akumulasi selamanya)
$poin_siswa = [];
$q_poin = $koneksi->query("
    SELECT s.id, s.nama,
        SUM(CASE WHEN dp.pakai_topi='tidak' THEN 1 ELSE 0 END) as v_topi,
        SUM(CASE WHEN dp.rambut_rapi='tidak' THEN 1 ELSE 0 END) as v_rambut,
        SUM(CASE WHEN dp.pakai_sabuk='tidak' THEN 1 ELSE 0 END) as v_sabuk,
        SUM(CASE WHEN dp.status_pemeriksaan='dihukum' THEN 1 ELSE 0 END) as total_pelanggaran
    FROM siswa s
    LEFT JOIN data_pemeriksaan dp ON s.id = dp.siswa_id
    GROUP BY s.id, s.nama ORDER BY total_pelanggaran DESC
");
if ($q_poin) {
    while ($r = $q_poin->fetch_assoc()) {
        $r['poin'] = $r['total_pelanggaran'] * $POIN_PER_PELANGGARAN;
        $poin_siswa[] = $r;
    }
}

if ($role == 'kepsek') {
    $q_stats = $koneksi->query("SELECT
        SUM(CASE WHEN status_pemeriksaan='lulus' THEN 1 ELSE 0 END) as lulus,
        SUM(CASE WHEN status_pemeriksaan='dihukum' THEN 1 ELSE 0 END) as dihukum
        FROM data_pemeriksaan");
    if ($q_stats) { $r = $q_stats->fetch_assoc(); $total_lulus = $r['lulus']??0; $total_dihukum = $r['dihukum']??0; }

    $q_pln = $koneksi->query("SELECT
        SUM(CASE WHEN pakai_topi='tidak' THEN 1 ELSE 0 END) as t_topi,
        SUM(CASE WHEN rambut_rapi='tidak' THEN 1 ELSE 0 END) as t_rambut,
        SUM(CASE WHEN pakai_sabuk='tidak' THEN 1 ELSE 0 END) as t_sabuk
        FROM data_pemeriksaan");
    if ($q_pln) { $r = $q_pln->fetch_assoc(); $v_topi = $r['t_topi']??0; $v_rambut = $r['t_rambut']??0; $v_sabuk = $r['t_sabuk']??0; }
}

// Fungsi warna poin
function poinStatus($poin, $ambang) {
    $pct = min(100, ($poin / $ambang) * 100);
    if ($pct >= 100) return ['danger',  'TEGURAN', $pct];
    if ($pct >= 60)  return ['warning', 'WASPADA', $pct];
    return ['safe', 'AMAN', $pct];
}
?>
<!DOCTYPE html>
<html lang="id">
    <style>
    body {
        background: url('langit.jpg') no-repeat center center fixed !important;
        background-size: cover !important;
    }
</style>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKADIK 506 — SISTEM DISIPLIN</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="motion-wrapper">
    <div class="graphic-orb orb-1"></div>
    <div class="graphic-orb orb-2"></div>
    <div class="graphic-orb orb-3"></div>
    <div class="graphic-orb orb-4"></div>
</div>

<!-- TOPBAR -->
<div class="user-topbar">
    <span class="topbar-info">
        LOGIN: <strong><?= strtoupper($_SESSION['username']); ?></strong>
        <span class="pill"><?= strtoupper($role); ?></span>
    </span>
    <a href="logout.php" class="btn-logout">DISCONNECT</a>
</div>

<!-- MAIN CARD -->
<div class="main-dashboard">
    <div class="dashboard-header">
        <div class="logo-box"><img src="skadik.png" alt="SKADIK"></div>
        <div class="header-title">
            <h1>MATA PITA JAYA-JAYASTHAM</h1>
            <p><?php
                if ($role=='guru')   echo 'PENGECEKAN KELENGKAPAN SERAGAM';
                elseif ($role=='kepsek') echo 'MONITORING CENTRAL DATA &amp; ANALYTICS';
                else echo 'LOG HISTORY PEMERIKSAAN';
            ?></p>
        </div>
        <div class="logo-box"><img src="tniau.png" alt="TNI AU"></div>
    </div>

    <div class="dashboard-body">

    <!-- ================================================================
         GURU — FORM PEMERIKSAAN
         ================================================================ -->
    <?php if ($role == 'guru'): ?>
        <form method="POST">
            <div class="form-section">
                <div class="form-group">
                    <label>PILIH NAMA SISWA</label>
                    <select name="siswa_id" required>
                        <option value="">-- SILAHKAN PILIH --</option>
                        <?php
                        if ($result_siswa && $result_siswa->num_rows > 0) {
                            while($row = $result_siswa->fetch_assoc()) {
                                echo "<option value='".$row['id']."'>» ".$row['nama']."</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ATRIBUT TOPI</label>
                    <div class="cyber-options">
                        <input type="radio" id="topi_ya" name="topi" value="ya" checked>
                        <label for="topi_ya">✔ PAKAI</label>
                        <input type="radio" id="topi_tidak" name="topi" value="tidak" class="radio-neg">
                        <label for="topi_tidak">✖ TIDAK PAKAI</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>KERAPIAN RAMBUT</label>
                    <div class="cyber-options">
                        <input type="radio" id="rambut_ya" name="rambut" value="ya" checked>
                        <label for="rambut_ya">✔ RAPI</label>
                        <input type="radio" id="rambut_tidak" name="rambut" value="tidak" class="radio-neg">
                        <label for="rambut_tidak">✖ TIDAK SESUAI</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>ATRIBUT SABUK</label>
                    <div class="cyber-options">
                        <input type="radio" id="sabuk_ya" name="sabuk" value="ya" checked>
                        <label for="sabuk_ya">✔ PAKAI</label>
                        <input type="radio" id="sabuk_tidak" name="sabuk" value="tidak" class="radio-neg">
                        <label for="sabuk_tidak">✖ TIDAK PAKAI</label>
                    </div>
                </div>
                <button type="submit" class="btn-submit">ANALISA KELENGKAPAN →</button>
            </div>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['siswa_id'])) {
            $siswa_id = intval($_POST['siswa_id']);
            $topi = $_POST['topi']; $rambut = $_POST['rambut']; $sabuk = $_POST['sabuk'];

            $stmt_nama = $koneksi->prepare("SELECT nama FROM siswa WHERE id = ?");
            $stmt_nama->bind_param("i", $siswa_id); $stmt_nama->execute();
            $res_nama = $stmt_nama->get_result()->fetch_assoc();
            $nama_siswa = $res_nama['nama']; $stmt_nama->close();

            $is_clear = ($topi=="ya" && $rambut=="ya" && $sabuk=="ya");
            $status = $is_clear ? "lulus" : "dihukum";
            $panel_class = $is_clear ? "" : "alert-breach";
            $badge = $is_clear
                ? "<span class='badge badge-clear'>PELANGGARAN TIDAK DITEMUKAN</span>"
                : "<span class='badge badge-violation'>PELANGGARAN DITEMUKAN</span>";

            echo "<div class='monitor-panel $panel_class'>";
            echo "TIMESTAMP: <b>" . date('d/m/Y H:i:s') . "</b><div class='log-divider'></div>";
            echo "SISWA : <b>$nama_siswa</b><br>STATUS : $badge<br><br>";

            if ($is_clear) {
                echo "<span style='font-weight:700;'>✓ Silakan masuk ke ruang kelas.</span><br>";
            } else {
                echo "<b>TINDAKAN DISIPLIN:</b><br><div style='margin-top:6px;'>";
                if ($topi=="tidak")   echo "&nbsp;— SIKAP TOBAT 2 MENIT.<br>";
                if ($rambut=="tidak") echo "&nbsp;— GULING BOTOL.<br>";
                if ($sabuk=="tidak")  echo "&nbsp;— PUSH UP 100 KALI.<br>";
                echo "</div>";
            }

            $stmt_insert = $koneksi->prepare("INSERT INTO data_pemeriksaan (siswa_id, pakai_topi, rambut_rapi, pakai_sabuk, status_pemeriksaan) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("issss", $siswa_id, $topi, $rambut, $sabuk, $status);
            echo "<div class='log-divider'></div>";
            if ($stmt_insert->execute()) {
                echo "<span style='font-size:0.8rem; opacity:0.75;'>[SYSTEM LOG] Data tersinkronisasi ke database.</span>";
            }
            echo "</div>";
            $stmt_insert->close();
        }
        ?>

    <!-- ================================================================
         KEPSEK — ANALYTICS + RECAP + POIN
         ================================================================ -->
    <?php elseif ($role == 'kepsek'): ?>

        <!-- Charts -->
        <div class="analytics-grid">
            <div class="chart-container">
                <h3>Rasio Status Kelulusan</h3>
                <div class="chart-wrapper">
                    <canvas id="chartRasio"></canvas>
                </div>
            </div>
            <div class="chart-container">
                <h3>Kategori Pelanggaran</h3>
                <div class="chart-wrapper">
                    <canvas id="chartTren"></canvas>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- ====================================================
             SISTEM POIN SISWA
             ==================================================== -->
        <p class="section-title">SISTEM POIN PELANGGARAN SISWA</p>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-top:-12px; margin-bottom:16px;">
            1 pelanggaran = <?= $POIN_PER_PELANGGARAN ?> poin &nbsp;|&nbsp; Ambang teguran: <?= $AMBANG_TEGURAN ?> poin
        </p>

        <table class="glass-table">
            <thead>
                <tr>
                    <th>Nama Siswa</th>
                    <th>Poin</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($poin_siswa as $ps):
                    [$lvl, $label, $pct] = poinStatus($ps['poin'], $AMBANG_TEGURAN);
                    $barClass = ($lvl=='danger') ? 'danger' : (($lvl=='warning') ? 'warning' : '');
                ?>
                <tr>
                    <td><b><?= htmlspecialchars($ps['nama']); ?></b>
                        <br><small style="color:var(--text-light); font-size:0.72rem;">
                            Topi:<?= $ps['v_topi'] ?> | Rambut:<?= $ps['v_rambut'] ?> | Sabuk:<?= $ps['v_sabuk'] ?>
                        </small>
                    </td>
                    <td>
                        <b><?= $ps['poin'] ?></b> / <?= $AMBANG_TEGURAN ?>
                        <div class="poin-bar-wrap"><div class="poin-bar <?= $barClass ?>" style="width:<?= min(100,$pct) ?>%"></div></div>
                    </td>
                    <td><span class="poin-badge <?= $lvl ?>"><?= $label ?></span></td>
                    <td>
                        <?php if ($lvl == 'danger'): ?>
                            <a href="cetak_teguran.php?siswa_id=<?= $ps['id'] ?>"
                               class="btn-cetak" target="_blank">
                                🖨 Cetak Teguran
                            </a>
                        <?php else: ?>
                            <span style="font-size:0.78rem; color:var(--text-light);">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <!-- ====================================================
             REKAP LAPORAN
             ==================================================== -->
        <p class="section-title">REKAP LAPORAN PELANGGARAN</p>

        <div class="tab-bar">
            <button class="tab-btn active" onclick="switchTab('minggu', this)">MINGGUAN</button>
            <button class="tab-btn" onclick="switchTab('bulan', this)">BULANAN</button>
            <button class="tab-btn" onclick="switchTab('tahun', this)">TAHUNAN</button>
        </div>

        <!-- TAB: Mingguan -->
        <div class="tab-content active" id="tab-minggu">
            <?php
            $tot_m = array_sum(array_column($recap_mingguan, 'pelanggaran'));
            $tot_mc = array_sum(array_column($recap_mingguan, 'total_cek'));
            ?>
            <div class="stat-chips">
                <div class="stat-chip"><span><?= $tot_mc ?></span>Total Cek</div>
                <div class="stat-chip red"><span><?= $tot_m ?></span>Pelanggaran</div>
                <div class="stat-chip green"><span><?= max(0, $tot_mc - $tot_m) ?></span>Lulus</div>
            </div>
            <?= renderRecapTable($recap_mingguan); ?>
        </div>

        <!-- TAB: Bulanan -->
        <div class="tab-content" id="tab-bulan">
            <?php
            $tot_b = array_sum(array_column($recap_bulanan, 'pelanggaran'));
            $tot_bc = array_sum(array_column($recap_bulanan, 'total_cek'));
            ?>
            <div class="stat-chips">
                <div class="stat-chip"><span><?= $tot_bc ?></span>Total Cek</div>
                <div class="stat-chip red"><span><?= $tot_b ?></span>Pelanggaran</div>
                <div class="stat-chip green"><span><?= max(0, $tot_bc - $tot_b) ?></span>Lulus</div>
            </div>
            <?= renderRecapTable($recap_bulanan); ?>
        </div>

        <!-- TAB: Tahunan -->
        <div class="tab-content" id="tab-tahun">
            <?php
            $tot_t = array_sum(array_column($recap_tahunan, 'pelanggaran'));
            $tot_tc = array_sum(array_column($recap_tahunan, 'total_cek'));
            ?>
            <div class="stat-chips">
                <div class="stat-chip"><span><?= $tot_tc ?></span>Total Cek</div>
                <div class="stat-chip red"><span><?= $tot_t ?></span>Pelanggaran</div>
                <div class="stat-chip green"><span><?= max(0, $tot_tc - $tot_t) ?></span>Lulus</div>
            </div>
            <?= renderRecapTable($recap_tahunan); ?>
        </div>

        <div class="divider"></div>

        <!-- ALL LOG TABLE -->
        <p class="section-title">LOG PEMERIKSAAN TERBARU</p>
        <table class="glass-table">
            <thead>
                <tr>
                    <th>Waktu</th><th>Nama Siswa</th><th>Topi</th><th>Rambut</th><th>Sabuk</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = $koneksi->query("SELECT dp.*, s.nama FROM data_pemeriksaan dp JOIN siswa s ON dp.siswa_id = s.id ORDER BY dp.id DESC LIMIT 50");
                if ($logs && $logs->num_rows > 0) {
                    while($row = $logs->fetch_assoc()) {
                        $sc = ($row['status_pemeriksaan']=='lulus') ? '#10b981' : '#ef4444';
                        echo "<tr>
                            <td>".date('d/m H:i', strtotime($row['created_at']))."</td>
                            <td><b>".htmlspecialchars($row['nama'])."</b></td>
                            <td>".($row['pakai_topi']=='ya'?'✔':'❌')."</td>
                            <td>".($row['rambut_rapi']=='ya'?'✔':'❌')."</td>
                            <td>".($row['pakai_sabuk']=='ya'?'✔':'❌')."</td>
                            <td style='color:$sc; font-weight:700;'>".strtoupper($row['status_pemeriksaan'])."</td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align:center; color:var(--text-muted);'>Belum ada rekaman pemeriksaan.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <script>
        const ctxRasio = document.getElementById('chartRasio').getContext('2d');
        new Chart(ctxRasio, {
            type: 'doughnut',
            data: {
                labels: ['Lulus', 'Dihukum'],
                datasets: [{
                    data: [<?= $total_lulus ?>, <?= $total_dihukum ?>],
                    backgroundColor: ['rgba(16,185,129,0.75)', 'rgba(239,68,68,0.75)'],
                    borderColor: ['#ffffff','#ffffff'], borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { color: '#0f172a', font: { family: 'Plus Jakarta Sans', weight: '600', size: 12 } } } }
            }
        });

        const ctxTren = document.getElementById('chartTren').getContext('2d');
        new Chart(ctxTren, {
            type: 'bar',
            data: {
                labels: ['Topi', 'Rambut', 'Sabuk'],
                datasets: [{
                    label: 'Pelanggaran',
                    data: [<?= $v_topi ?>, <?= $v_rambut ?>, <?= $v_sabuk ?>],
                    backgroundColor: ['rgba(2,132,199,0.7)', 'rgba(14,165,233,0.7)', 'rgba(56,189,248,0.7)'],
                    borderColor: ['#0284c7','#0ea5e9','#38bdf8'],
                    borderWidth: 1, borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, grid: { color:'rgba(0,0,0,0.05)' }, ticks: { color:'#475569', stepSize:1 } },
                    x: { grid: { display:false }, ticks: { color:'#475569', font: { family:'Plus Jakarta Sans', weight:'700' } } }
                },
                plugins: { legend: { display: false } }
            }
        });
        </script>

    <!-- ================================================================
         SISWA — LOG PRIBADI
         ================================================================ -->
    <?php elseif ($role == 'siswa'): ?>
        <p class="section-title">RIWAYAT PEMERIKSAAN SAYA</p>
        <table class="glass-table">
            <thead>
                <tr><th>Waktu</th><th>Topi</th><th>Rambut</th><th>Sabuk</th><th>Hasil</th></tr>
            </thead>
            <tbody>
                <?php
                $sid = $_SESSION['siswa_id'];
                $stmt_s = $koneksi->prepare("SELECT * FROM data_pemeriksaan WHERE siswa_id = ? ORDER BY id DESC");
                $stmt_s->bind_param("i", $sid); $stmt_s->execute();
                $logs_s = $stmt_s->get_result();
                if ($logs_s->num_rows > 0) {
                    while($row = $logs_s->fetch_assoc()) {
                        $sc = ($row['status_pemeriksaan']=='lulus') ? '#10b981' : '#ef4444';
                        echo "<tr>
                            <td>".$row['created_at']."</td>
                            <td>".($row['pakai_topi']=='ya'?'Lengkap':'Alpa')."</td>
                            <td>".($row['rambut_rapi']=='ya'?'Rapi':'Melanggar')."</td>
                            <td>".($row['pakai_sabuk']=='ya'?'Lengkap':'Alpa')."</td>
                            <td style='color:$sc; font-weight:700;'>".strtoupper($row['status_pemeriksaan'])."</td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' style='text-align:center; color:var(--text-muted);'>Catatan Anda bersih! Atribut selalu lengkap.</td></tr>";
                }
                $stmt_s->close();
                ?>
            </tbody>
        </table>
    <?php endif; ?>

    </div><!-- /.dashboard-body -->
</div><!-- /.main-dashboard -->

<script>
function switchTab(id, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
<?php
$koneksi->close();

function renderRecapTable($data) {
    if (empty($data)) return "<p style='color:var(--text-muted); font-size:0.88rem;'>Belum ada data.</p>";
    $html = "<table class='glass-table'><thead><tr><th>#</th><th>Nama Siswa</th><th>Total Cek</th><th>Pelanggaran</th><th>Lulus</th></tr></thead><tbody>";
    foreach ($data as $i => $r) {
        $lulus = max(0, $r['total_cek'] - $r['pelanggaran']);
        $ikon = ($r['pelanggaran'] > 0) ? "❌" : "✔";
        $html .= "<tr>
            <td style='color:var(--text-light); font-size:0.8rem;'>".($i+1)."</td>
            <td><b>".htmlspecialchars($r['nama'])."</b></td>
            <td>".$r['total_cek']."</td>
            <td style='color:".($r['pelanggaran']>0?'#ef4444':'#10b981')."; font-weight:700;'>$ikon ".$r['pelanggaran']."</td>
            <td style='color:#10b981; font-weight:700;'>$lulus</td>
        </tr>";
    }
    $html .= "</tbody></table>";
    return $html;
}
?>
