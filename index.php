<?php
// index.php
session_start();
require_once 'db.php';
$db = Database::getInstance();

// ==========================================
// 1. FITUR HAPUS DATA (BONUS: TRANSAKSI PDO)
// ==========================================
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    try {
        // Memulai Transaksi Database
        $db->beginTransaction();

        // Ambil nama produk dulu untuk pencatatan log
        $stmtGet = $db->prepare("SELECT nama_produk FROM produk WHERE id = ?");
        $stmtGet->execute([$delete_id]);
        $produk = $stmtGet->fetch();

        if ($produk) {
            // 1. Hapus Produk
            $stmtDel = $db->prepare("DELETE FROM produk WHERE id = ?");
            $stmtDel->execute([$delete_id]);

            // 2. Catat ke Log Aktivitas (Transaction Log)
            $logMsg = "Menghapus produk: " . $produk['nama_produk'] . " (ID: $delete_id)";
            $stmtLog = $db->prepare("INSERT INTO log_aktivitas (pesan) VALUES (?)");
            $stmtLog->execute([$logMsg]);

            // Commit transaksi jika semua berhasil
            $db->commit();

            $_SESSION['flash'] = "Produk berhasil dihapus dan dicatat di log!";
            $_SESSION['flash_type'] = "success";
        } else {
            $db->rollBack();
            $_SESSION['flash'] = "Data produk tidak ditemukan.";
            $_SESSION['flash_type'] = "error";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['flash'] = "Gagal menghapus produk: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    
    // Redirect Pattern
    header("Location: index.php");
    exit();
}

// ==========================================
// 2. FITUR TAMBAH & EDIT DATA (CREATE / UPDATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama_produk']);
    $stok     = (int)$_POST['stok'];
    $harga    = (float)$_POST['harga'];
    $kategori = (int)$_POST['kategori_id'];
    $supplier = (int)$_POST['supplier_id'];
    $id       = !empty($_POST['id']) ? (int)$_POST['id'] : null;

    if (!empty($nama) && $kategori > 0 && $supplier > 0) {
        if ($id) {
            // Update Data (Form Pre-filled)
            $stmt = $db->prepare("UPDATE produk SET nama_produk = ?, stok = ?, harga = ?, kategori_id = ?, supplier_id = ? WHERE id = ?");
            $stmt->execute([$nama, $stok, $harga, $kategori, $supplier, $id]);
            $_SESSION['flash'] = "Data produk berhasil diperbarui!";
        } else {
            // Create Data Baru
            $stmt = $db->prepare("INSERT INTO produk (nama_produk, stok, harga, kategori_id, supplier_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $stok, $harga, $kategori, $supplier]);
            $_SESSION['flash'] = "Produk baru berhasil ditambahkan!";
        }
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash'] = "Mohon isi semua data formulir dengan benar!";
        $_SESSION['flash_type'] = "error";
    }

    header("Location: index.php");
    exit();
}

// ==========================================
// 3. FITUR PENCARIAN & PAGINATION (BONUS)
// ==========================================
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 5;
$offset = ($page - 1) * $limit;

// Menghitung Total Data
if (!empty($search)) {
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM produk p 
                               LEFT JOIN kategori k ON p.kategori_id = k.id 
                               LEFT JOIN supplier s ON p.supplier_id = s.id 
                               WHERE p.nama_produk LIKE ? OR k.nama_kategori LIKE ? OR s.nama_supplier LIKE ?");
    $searchTerm = "%{$search}%";
    $stmtCount->execute([$searchTerm, $searchTerm, $searchTerm]);
} else {
    $stmtCount = $db->query("SELECT COUNT(*) FROM produk");
}
$totalData = $stmtCount->fetchColumn();
$totalPages = ceil($totalData / $limit);

// Fetch Data Produk dengan JOIN 2 Tabel
if (!empty($search)) {
    $query = "SELECT p.id, p.nama_produk, p.stok, p.harga, k.nama_kategori, s.nama_supplier 
              FROM produk p 
              LEFT JOIN kategori k ON p.kategori_id = k.id 
              LEFT JOIN supplier s ON p.supplier_id = s.id 
              WHERE p.nama_produk LIKE ? OR k.nama_kategori LIKE ? OR s.nama_supplier LIKE ? 
              ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $produkList = $stmt->fetchAll();
} else {
    $query = "SELECT p.id, p.nama_produk, p.stok, p.harga, k.nama_kategori, s.nama_supplier 
              FROM produk p 
              LEFT JOIN kategori k ON p.kategori_id = k.id 
              LEFT JOIN supplier s ON p.supplier_id = s.id 
              ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
    $produkList = $db->query($query)->fetchAll();
}

// Data Dropdown Kategori & Supplier
$kategoriList = $db->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();
$supplierList = $db->query("SELECT * FROM supplier ORDER BY nama_supplier ASC")->fetchAll();

// Data Pre-filled Form saat Edit
$editData = null;
if (isset($_GET['edit'])) {
    $stmtEdit = $db->prepare("SELECT * FROM produk WHERE id = ?");
    $stmtEdit->execute([(int)$_GET['edit']]);
    $editData = $stmtEdit->fetch();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen Inventaris</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0b0f19;
            --bg-card: #151c2c;
            --bg-input: #0f172a;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --success: #10b981;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #1e293b;
            --ring: rgba(59, 130, 246, 0.3);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-body); 
            color: var(--text-main); 
            padding: 40px 20px;
            margin: 0;
            display: flex;
            justify-content: center;
        }

        .container { 
            width: 100%; 
            max-width: 1100px; 
            background: var(--bg-card); 
            padding: 35px; 
            border-radius: 20px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            border: 1px solid var(--border);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 span { color: var(--primary); }

        /* Form Grid */
        .form-section {
            background: rgba(15, 23, 42, 0.6);
            padding: 24px;
            border-radius: 14px;
            border: 1px solid var(--border);
            margin-bottom: 35px;
        }

        .form-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--text-main);
        }

        form.grid-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .full-width { grid-column: span 2; }

        input, select { 
            width: 100%; 
            padding: 12px 16px; 
            background: var(--bg-input); 
            border: 1px solid var(--border); 
            color: var(--text-main); 
            border-radius: 10px; 
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--ring);
        }

        .btn { 
            padding: 10px 18px; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            text-decoration: none; 
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 14px;
            font-size: 1rem;
        }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }

        .btn-secondary { background: var(--border); color: var(--text-main); }
        .btn-secondary:hover { background: #334155; }

        .btn-edit { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .btn-edit:hover { background: var(--primary); color: white; }

        .btn-delete { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-delete:hover { background: var(--danger); color: white; }

        /* Search Bar & Table Header */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 400px;
        }

        /* Table Design */
        .table-container {
            background: var(--bg-input);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }
        
        th, td { 
            padding: 16px 20px; 
            text-align: left; 
            border-bottom: 1px solid var(--border); 
            font-size: 0.95rem;
        }

        th { 
            background: rgba(30, 41, 59, 0.5); 
            color: var(--text-muted); 
            font-weight: 600; 
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: rgba(30, 41, 59, 0.4); }

        .badge {
            background: rgba(255, 255, 255, 0.08);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.82rem;
            color: #cbd5e1;
        }

        .action-buttons { display: flex; gap: 8px; }

        /* Flash Message */
        .flash { 
            padding: 16px 20px; 
            margin-bottom: 25px; 
            border-radius: 12px; 
            font-weight: 500;
        }
        .flash.success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .flash.error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
        }

        .pagination a {
            padding: 8px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .pagination a.active, .pagination a:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        @media (max-width: 768px) {
            form.grid-form { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>📦 <span>Inventaris</span> - Tugas Rutin 8</h1>
    </div>

    <!-- Flash Message Notification (Redirect Pattern) -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="flash <?= $_SESSION['flash_type'] ?>">
            <?= htmlspecialchars($_SESSION['flash']) ?>
        </div>
        <?php unset($_SESSION['flash']); unset($_SESSION['flash_type']); ?>
    <?php endif; ?>

    <!-- Formulir Tambah / Edit Produk -->
    <div class="form-section">
        <div class="form-title">
            <?= $editData ? '✏️ Edit Data Produk' : '➕ Tambah Produk Baru' ?>
        </div>
        <form method="POST" class="grid-form">
            <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id'] ?? '') ?>">

            <div class="full-width">
                <input type="text" name="nama_produk" placeholder="Nama Produk" required value="<?= htmlspecialchars($editData['nama_produk'] ?? '') ?>">
            </div>

            <div>
                <input type="number" name="stok" placeholder="Jumlah Stok" required min="0" value="<?= htmlspecialchars($editData['stok'] ?? '') ?>">
            </div>

            <div>
                <input type="number" step="0.01" name="harga" placeholder="Harga Produk (Rp)" required min="0" value="<?= htmlspecialchars($editData['harga'] ?? '') ?>">
            </div>

            <div>
                <select name="kategori_id" required>
                    <option value="" disabled <?= !isset($editData) ? 'selected' : '' ?>>-- Pilih Kategori --</option>
                    <?php foreach ($kategoriList as $kat): ?>
                        <option value="<?= $kat['id'] ?>" <?= (isset($editData) && $editData['kategori_id'] == $kat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kat['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="supplier_id" required>
                    <option value="" disabled <?= !isset($editData) ? 'selected' : '' ?>>-- Pilih Supplier --</option>
                    <?php foreach ($supplierList as $sup): ?>
                        <option value="<?= $sup['id'] ?>" <?= (isset($editData) && $editData['supplier_id'] == $sup['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sup['nama_supplier']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="full-width" style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <?= $editData ? '💾 Simpan Perubahan' : '🚀 Tambah Produk' ?>
                </button>
                <?php if ($editData): ?>
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Toolbar Pencarian (Search) -->
    <div class="toolbar">
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Cari nama produk, kategori, supplier..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary">Cari</button>
            <?php if (!empty($search)): ?>
                <a href="index.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabel Data Inventaris dengan JOIN 2 Tabel -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Produk</th>
                    <th>Stok</th>
                    <th>Harga</th>
                    <th>Kategori</th>
                    <th>Supplier</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($produkList) > 0): ?>
                    <?php foreach ($produkList as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td style="font-weight: 600; color: #ffffff;"><?= htmlspecialchars($row['nama_produk']) ?></td>
                        <td><?= htmlspecialchars($row['stok']) ?> unit</td>
                        <td>Rp <?= number_format($row['harga'], 0, ',', '.') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($row['nama_kategori'] ?? 'Tanpa Kategori') ?></span></td>
                        <td><?= htmlspecialchars($row['nama_supplier'] ?? 'Tanpa Supplier') ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="?edit=<?= $row['id'] ?>" class="btn btn-edit">Edit</a>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Data produk tidak ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Navigasi Halaman (Pagination) -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= $i === $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>