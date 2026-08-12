<?php
session_start();

/* =========================================================
   DATABASE
========================================================= */

$host = "localhost";
$user = "root";
$password = "";
$dbname = "sbi_magang";

$conn = new mysqli($host, $user, $password);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/* Buat database */
$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
$conn->select_db($dbname);


/* =========================================================
   BUAT TABEL PENDAFTARAN
========================================================= */

$conn->query("
    CREATE TABLE IF NOT EXISTS pendaftaran_magang (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jenjang_pendidikan VARCHAR(50) NOT NULL,
        nama_sekolah_kampus VARCHAR(200) NOT NULL,
        alamat_sekolah_kampus TEXT NOT NULL,
        nama_lengkap VARCHAR(150) NOT NULL,
        tempat_lahir VARCHAR(100) NOT NULL,
        tanggal_lahir DATE NOT NULL,
        alamat_rumah TEXT NOT NULL,
        nomor_whatsapp VARCHAR(30) NOT NULL,
        jumlah_anggota INT NOT NULL,
        tanggal_mulai_magang DATE NOT NULL,
        tanggal_selesai_magang DATE NOT NULL,
        file_cv VARCHAR(255),
        status VARCHAR(30) DEFAULT 'Menunggu',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");


/* =========================================================
   TAMBAHKAN KOLOM STATUS JIKA BELUM ADA
========================================================= */

$cek_status = $conn->query("
    SHOW COLUMNS FROM pendaftaran_magang LIKE 'status'
");

if ($cek_status && $cek_status->num_rows == 0) {

    $conn->query("
        ALTER TABLE pendaftaran_magang
        ADD status VARCHAR(30) DEFAULT 'Menunggu'
    ");
}


/* =========================================================
   LOGIN ADMIN
========================================================= */

if (isset($_POST["login_admin"])) {

    $username = trim($_POST["username"] ?? "");
    $password_login = $_POST["password"] ?? "";

    /*
       LOGIN ADMIN
       Username : admin
       Password : admin123
    */

    $admin_username = "admin";
    $admin_password = "admin123";

    if (
        $username === $admin_username &&
        $password_login === $admin_password
    ) {

        session_regenerate_id(true);

        $_SESSION["admin_login"] = true;
        $_SESSION["admin_username"] = $username;

        header("Location: ?halaman=admin");
        exit;

    } else {

        $_SESSION["login_error"] =
            "Username atau password yang Anda masukkan salah.";

        header("Location: ?halaman=login");
        exit;
    }
}


/* =========================================================
   LOGOUT ADMIN
========================================================= */

if (isset($_GET["logout"])) {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: index.php");
    exit;
}


/* =========================================================
   UBAH STATUS PENDAFTAR
========================================================= */

if (
    isset($_GET["aksi"]) &&
    isset($_GET["id"])
) {

    if (!isset($_SESSION["admin_login"])) {

        header("Location: ?halaman=login");
        exit;
    }

    $id = intval($_GET["id"]);
    $aksi = $_GET["aksi"];

    $status_baru = "";

    if ($aksi === "terima") {
        $status_baru = "Diterima";
    }

    if ($aksi === "tolak") {
        $status_baru = "Ditolak";
    }

    if ($aksi === "menunggu") {
        $status_baru = "Menunggu";
    }

    if ($status_baru !== "") {

        $stmt = $conn->prepare("
            UPDATE pendaftaran_magang
            SET status = ?
            WHERE id = ?
        ");

        if ($stmt) {

            $stmt->bind_param(
                "si",
                $status_baru,
                $id
            );

            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: ?halaman=admin");
    exit;
}


/* =========================================================
   PROSES PENDAFTARAN MAGANG
========================================================= */

if (isset($_POST["daftar_magang"])) {

    $jenjang =
        trim($_POST["jenjang"] ?? "");

    $nama_sekolah =
        trim($_POST["nama_sekolah"] ?? "");

    $alamat_sekolah =
        trim($_POST["alamat_sekolah"] ?? "");

    $nama_lengkap =
        trim($_POST["nama_lengkap"] ?? "");

    $tempat_lahir =
        trim($_POST["tempat_lahir"] ?? "");

    $tanggal_lahir =
        $_POST["tanggal_lahir"] ?? "";

    $alamat_rumah =
        trim($_POST["alamat_rumah"] ?? "");

    $nomor_whatsapp =
        trim($_POST["nomor_whatsapp"] ?? "");

    $jumlah_anggota =
        intval($_POST["jumlah_anggota"] ?? 1);

    $tanggal_mulai =
        $_POST["tanggal_mulai"] ?? "";

    $tanggal_selesai =
        $_POST["tanggal_selesai"] ?? "";

    $file_cv = "";


    /* =====================================================
       VALIDASI
    ===================================================== */

    if (
        $jenjang === "" ||
        $nama_sekolah === "" ||
        $alamat_sekolah === "" ||
        $nama_lengkap === "" ||
        $tempat_lahir === "" ||
        $tanggal_lahir === "" ||
        $alamat_rumah === "" ||
        $nomor_whatsapp === "" ||
        $jumlah_anggota < 1 ||
        $tanggal_mulai === "" ||
        $tanggal_selesai === ""
    ) {

        $_SESSION["pesan"] =
            "Semua data wajib diisi.";

        $_SESSION["pesan_type"] =
            "error";

        header("Location: index.php");
        exit;
    }


    /* =====================================================
       VALIDASI TANGGAL
    ===================================================== */

    if ($tanggal_selesai < $tanggal_mulai) {

        $_SESSION["pesan"] =
            "Tanggal selesai tidak boleh lebih awal dari tanggal mulai.";

        $_SESSION["pesan_type"] =
            "error";

        header("Location: index.php");
        exit;
    }


    /* =====================================================
       UPLOAD CV
    ===================================================== */

    if (
        !isset($_FILES["file_cv"]) ||
        $_FILES["file_cv"]["error"] !== UPLOAD_ERR_OK
    ) {

        $_SESSION["pesan"] =
            "Silakan upload file CV.";

        $_SESSION["pesan_type"] =
            "error";

        header("Location: index.php");
        exit;
    }


    $folder = "uploads/";

    if (!is_dir($folder)) {

        mkdir(
            $folder,
            0777,
            true
        );
    }


    $nama_asli =
        $_FILES["file_cv"]["name"];

    $tmp =
        $_FILES["file_cv"]["tmp_name"];

    $ukuran =
        $_FILES["file_cv"]["size"];

    $ext =
        strtolower(
            pathinfo(
                $nama_asli,
                PATHINFO_EXTENSION
            )
        );


    $allowed = [
        "pdf",
        "doc",
        "docx"
    ];


    /* Maksimal 5 MB */

    if ($ukuran > 5 * 1024 * 1024) {

        $_SESSION["pesan"] =
            "Ukuran CV maksimal 5 MB.";

        $_SESSION["pesan_type"] =
            "error";

        header("Location: index.php");
        exit;
    }


    if (!in_array($ext, $allowed, true)) {

        $_SESSION["pesan"] =
            "Format CV harus PDF, DOC, atau DOCX.";

        $_SESSION["pesan_type"] =
            "error";

        header("Location: index.php");
        exit;
    }


    $file_cv =
        "CV_" .
        date("YmdHis") .
        "_" .
        bin2hex(random_bytes(5)) .
        "." .
        $ext;


    if (
        !move_uploaded_file(
            $tmp,
            $folder . $file_cv
        )
    ) {

        $_SESSION["pesan"] =
            "File CV gagal diupload.";

        $_SESSION["pesan_type"] =
            "error";

        header("Location: index.php");
        exit;
    }


    /* =====================================================
       SIMPAN DATA
    ===================================================== */

    $stmt = $conn->prepare("
        INSERT INTO pendaftaran_magang
        (
            jenjang_pendidikan,
            nama_sekolah_kampus,
            alamat_sekolah_kampus,
            nama_lengkap,
            tempat_lahir,
            tanggal_lahir,
            alamat_rumah,
            nomor_whatsapp,
            jumlah_anggota,
            tanggal_mulai_magang,
            tanggal_selesai_magang,
            file_cv
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");


    if (!$stmt) {

        $_SESSION["pesan"] =
            "Terjadi kesalahan pada database.";

        $_SESSION["pesan_type"] =
            "error";

        header("Location: index.php");
        exit;
    }


    /*
       12 variabel:
       s s s s s s s s i s s s
    */

    $stmt->bind_param(
        "ssssssssisss",
        $jenjang,
        $nama_sekolah,
        $alamat_sekolah,
        $nama_lengkap,
        $tempat_lahir,
        $tanggal_lahir,
        $alamat_rumah,
        $nomor_whatsapp,
        $jumlah_anggota,
        $tanggal_mulai,
        $tanggal_selesai,
        $file_cv
    );


    if ($stmt->execute()) {

        $_SESSION["pesan"] =
            "Pendaftaran magang berhasil dikirim! Silakan cek status menggunakan nomor WhatsApp Anda.";

        $_SESSION["pesan_type"] =
            "success";

    } else {

        /*
           Jika database gagal menyimpan,
           hapus file CV yang sudah diupload.
        */

        if (file_exists($folder . $file_cv)) {
            unlink($folder . $file_cv);
        }

        $_SESSION["pesan"] =
            "Pendaftaran gagal disimpan.";

        $_SESSION["pesan_type"] =
            "error";
    }


    $stmt->close();

    header("Location: index.php");
    exit;
}


/* =========================================================
   HALAMAN
========================================================= */

$halaman =
    $_GET["halaman"] ?? "form";


/* =========================================================
   PESAN
========================================================= */

$pesan =
    $_SESSION["pesan"] ?? "";

$pesan_type =
    $_SESSION["pesan_type"] ?? "";

unset($_SESSION["pesan"]);
unset($_SESSION["pesan_type"]);


/* =========================================================
   LOGIN ERROR
========================================================= */

$login_error =
    $_SESSION["login_error"] ?? "";

unset($_SESSION["login_error"]);


/* =========================================================
   CEK STATUS
========================================================= */

$status_data = null;
$status_error = "";

if (
    $halaman === "status" &&
    isset($_POST["cek_status"])
) {

    $nomor_wa =
        trim($_POST["nomor_whatsapp"] ?? "");

    if ($nomor_wa === "") {

        $status_error =
            "Silakan masukkan nomor WhatsApp.";

    } else {

        $stmt = $conn->prepare("
            SELECT *
            FROM pendaftaran_magang
            WHERE nomor_whatsapp = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "s",
                $nomor_wa
            );

            $stmt->execute();

            $hasil =
                $stmt->get_result();

            if ($hasil->num_rows > 0) {

                $status_data =
                    $hasil->fetch_assoc();

            } else {

                $status_error =
                    "Data pendaftaran dengan nomor WhatsApp tersebut tidak ditemukan.";
            }

            $stmt->close();

        } else {

            $status_error =
                "Terjadi kesalahan saat mencari data.";
        }
    }
}


/* =========================================================
   ADMIN HARUS LOGIN
========================================================= */

if (
    $halaman === "admin" &&
    !isset($_SESSION["admin_login"])
) {

    header("Location: ?halaman=login");
    exit;
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SBI | Pendaftaran Magang</title>


    <style>
        /* =========================================================
           RESET
        ========================================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background:
                radial-gradient(circle at top right,
                    #403078 0%,
                    transparent 30%),
                linear-gradient(135deg,
                    #172b3a,
                    #263f50 50%,
                    #1c3040);

            color: #ffffff;
            min-height: 100vh;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .header {
            position: relative;

            min-height: 310px;

            display: flex;
            flex-direction: column;

            align-items: center;
            justify-content: center;

            text-align: center;

            padding: 60px 20px 45px;

            overflow: hidden;

            background:
                linear-gradient(135deg,
                    #424242,
                    #424242);

            border-bottom:
                1px solid rgba(255, 255, 255, .08);

            box-shadow:
                0 15px 40px rgba(0, 0, 0, .15);
        }


        .header::before {
            content: "";

            position: absolute;

            width: 350px;
            height: 350px;

            border-radius: 50%;

            background: #693cff;

            opacity: .12;

            filter: blur(50px);

            top: -180px;
            right: -100px;
        }


        .header::after {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            border-radius: 50%;

            background: #00aaff;

            opacity: .08;

            filter: blur(60px);

            bottom: -180px;
            left: -100px;
        }


        .logo {
            position: relative;

            z-index: 2;

            width: 240px;

            max-width: 70%;

            height: auto;

            margin-bottom: 22px;

            filter:
                drop-shadow(0 12px 20px rgba(0, 0, 0, .25));
        }


        .judul {
            position: relative;
            z-index: 2;

            font-size: 28px;
            font-weight: 800;

            letter-spacing: .5px;
        }


        .subjudul {
            position: relative;
            z-index: 2;

            margin-top: 9px;

            color: #cbd6dc;

            font-size: 14px;
        }


        /* =========================================================
           ADMIN MENU
        ========================================================= */

        .admin-menu {
            position: absolute;

            z-index: 5;

            top: 24px;
            right: 30px;

            display: flex;
            gap: 8px;
        }


        .admin-menu a {
            display: flex;

            align-items: center;

            gap: 8px;

            padding: 11px 18px;

            border:
                1px solid rgba(255, 255, 255, .2);

            background:
                rgba(35, 25, 72, .65);

            backdrop-filter: blur(10px);

            color: #ffffff;

            text-decoration: none;

            border-radius: 50px;

            font-size: 12px;

            font-weight: 700;

            box-shadow:
                0 8px 25px rgba(0, 0, 0, .15);

            transition: .25s;
        }


        .admin-menu a:hover {
            background: #5c32dc;

            transform:
                translateY(-2px);

            box-shadow:
                0 10px 25px rgba(80, 40, 200, .35);
        }


        /* =========================================================
           FORM CONTAINER
        ========================================================= */

        .form-container {
            width: 88%;

            max-width: 980px;

            margin: -35px auto 60px;

            position: relative;

            z-index: 3;
        }


        /* =========================================================
           PESAN
        ========================================================= */

        .pesan {
            padding: 16px 20px;

            border-radius: 12px;

            margin-bottom: 20px;

            font-size: 14px;

            font-weight: 600;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, .12);
        }


        .success {
            background:
                rgba(26, 125, 76, .2);

            border:
                1px solid rgba(66, 200, 130, .4);

            color: #76e5a8;
        }


        .error {
            background:
                rgba(180, 40, 55, .2);

            border:
                1px solid rgba(255, 90, 110, .4);

            color: #ffabb5;
        }


        /* =========================================================
           FORM CARD
        ========================================================= */

        .form-card {
            background:
                rgba(45, 67, 81, .94);

            border:
                1px solid rgba(255, 255, 255, .08);

            border-radius: 18px;

            padding: 32px;

            margin-bottom: 22px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .16);

            backdrop-filter: blur(12px);
        }


        .section-header {
            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 27px;
        }


        .section-icon {
            width: 44px;
            height: 44px;

            border-radius: 12px;

            display: flex;

            align-items: center;
            justify-content: center;

            background:
                linear-gradient(135deg,
                    #6842ef,
                    #4320bd);

            font-size: 19px;

            box-shadow:
                0 8px 20px rgba(76, 35, 205, .3);
        }


        .section-title h2 {
            font-size: 18px;

            margin-bottom: 3px;
        }


        .section-title p {
            color: #91a4af;

            font-size: 12px;
        }


        /* =========================================================
           GRID
        ========================================================= */

        .row {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 22px;

            margin-bottom: 22px;
        }


        .field {
            display: flex;

            flex-direction: column;
        }


        .field-full {
            margin-bottom: 22px;
        }


        label {
            color: #cbd6dc;

            font-size: 12px;

            font-weight: 700;

            margin-bottom: 8px;
        }


        .required {
            color: #b18cff;
        }


        /* =========================================================
           INPUT
        ========================================================= */

        input,
        select,
        textarea {
            width: 100%;

            border:
                1px solid #657d8b;

            background:
                rgba(24, 43, 55, .7);

            color: #edf3f6;

            outline: none;

            border-radius: 9px;

            font-family: inherit;

            font-size: 14px;

            transition: .2s;
        }


        input,
        select {
            height: 46px;

            padding:
                0 14px;
        }


        textarea {
            min-height: 125px;

            padding: 13px 14px;

            resize: vertical;
        }


        input::placeholder,
        textarea::placeholder {
            color: #738793;
        }


        input:focus,
        select:focus,
        textarea:focus {
            border-color: #8060f2;

            background:
                rgba(29, 48, 61, .95);

            box-shadow:
                0 0 0 3px rgba(116, 75, 230, .12);
        }


        select option {
            background: #263b4a;

            color: white;
        }


        /* =========================================================
           FILE
        ========================================================= */

        .file-box {
            position: relative;

            border:
                1px dashed #718794;

            border-radius: 12px;

            padding: 18px;

            background:
                rgba(24, 43, 55, .45);

            transition: .2s;
        }


        .file-box:hover {
            border-color: #8567ef;

            background:
                rgba(73, 45, 140, .12);
        }


        .file-box input[type="file"] {
            border: none;

            background: transparent;

            padding: 0;

            height: auto;

            cursor: pointer;
        }


        .file-info {
            margin-top: 8px;

            color: #8296a1;

            font-size: 11px;
        }


        /* =========================================================
           BUTTON
        ========================================================= */

        .button-container {
            display: flex;

            justify-content: flex-end;

            margin-top: 30px;
        }


        .btn-daftar {
            min-width: 220px;

            height: 50px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(135deg,
                    #6a3ff0,
                    #451bc3);

            color: white;

            font-size: 14px;

            font-weight: 800;

            letter-spacing: .5px;

            cursor: pointer;

            box-shadow:
                0 10px 25px rgba(72, 30, 190, .3);

            transition: .25s;
        }


        .btn-daftar:hover {
            transform:
                translateY(-2px);

            box-shadow:
                0 15px 30px rgba(72, 30, 190, .45);
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        footer {
            text-align: center;

            border-top:
                1px solid rgba(255, 255, 255, .08);

            padding: 25px;

            color: #7e929e;

            font-size: 11px;
        }


        /* =========================================================
           LOGIN
        ========================================================= */

        .login-page {
            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;

            background:
                radial-gradient(circle at top right,
                    #4b3280,
                    transparent 35%),
                linear-gradient(135deg,
                    #182d3b,
                    #263f50);
        }


        .login-box {
            width: 410px;

            background: #424242;

            border:
                1px solid rgba(255, 255, 255, .1);

            border-radius: 20px;

            padding: 42px;

            box-shadow:
                0 25px 60px rgba(0, 0, 0, .3);

            backdrop-filter: blur(15px);
        }


        .login-logo {
            text-align: center;

            margin-bottom: 25px;
        }


        .login-logo img {
            width: 190px;

            max-width: 80%;

            filter:
                drop-shadow(0 10px 20px rgba(0, 0, 0, .2));
        }


        .login-title {
            text-align: center;

            font-size: 23px;

            font-weight: 800;

            margin-bottom: 7px;
        }


        .login-subtitle {
            text-align: center;

            color: #91a4af;

            font-size: 12px;

            margin-bottom: 30px;
        }


        .login-group {
            margin-bottom: 20px;
        }


        .login-group input {
            height: 48px;
        }


        .login-button {
            width: 100%;

            height: 48px;

            border: none;

            border-radius: 9px;

            background:
                linear-gradient(135deg,
                    #6a3ff0,
                    #451bc3);

            color: white;

            font-weight: 800;

            cursor: pointer;

            transition: .2s;

            box-shadow:
                0 10px 25px rgba(72, 30, 190, .3);
        }


        .login-button:hover {
            transform:
                translateY(-2px);

            box-shadow:
                0 15px 30px rgba(72, 30, 190, .45);
        }


        .kembali {
            text-align: center;

            margin-top: 25px;
        }


        .kembali a {
            color: #9a82ff;

            text-decoration: none;

            font-size: 12px;
        }


        /* =========================================================
           CEK STATUS
        ========================================================= */

        .status-page {
            min-height: 100vh;

            padding: 50px 20px;

            display: flex;

            justify-content: center;

            align-items: flex-start;
        }


        .status-container {
            width: 100%;

            max-width: 680px;
        }


        .status-card {
            background:
                rgba(45, 67, 81, .96);

            border:
                1px solid rgba(255, 255, 255, .08);

            border-radius: 20px;

            padding: 35px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .2);
        }


        .status-header {
            text-align: center;

            margin-bottom: 30px;
        }


        .status-header .icon {
            width: 65px;
            height: 65px;

            margin:
                0 auto 15px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 18px;

            background:
                linear-gradient(135deg,
                    #6a3ff0,
                    #451bc3);

            font-size: 28px;
        }


        .status-header h1 {
            font-size: 25px;

            margin-bottom: 8px;
        }


        .status-header p {
            color: #91a4af;

            font-size: 13px;
        }


        .status-form {
            margin-bottom: 25px;
        }


        .status-form label {
            display: block;

            margin-bottom: 8px;
        }


        .status-form input {
            width: 100%;

            height: 50px;

            padding: 0 15px;
        }


        .btn-status {
            width: 100%;

            height: 50px;

            margin-top: 15px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(135deg,
                    #6a3ff0,
                    #451bc3);

            color: white;

            font-weight: 800;

            cursor: pointer;

            transition: .2s;
        }


        .btn-status:hover {
            transform:
                translateY(-2px);
        }


        .status-result {
            margin-top: 25px;

            padding: 25px;

            border-radius: 15px;

            background:
                rgba(24, 43, 55, .7);

            border:
                1px solid rgba(255, 255, 255, .08);
        }


        .status-result h2 {
            margin-bottom: 20px;

            font-size: 18px;
        }


        .status-info {
            display: grid;

            grid-template-columns:
                160px 1fr;

            gap: 13px;

            font-size: 13px;
        }


        .status-info .label {
            color: #91a4af;
        }


        .status-info .value {
            color: white;

            font-weight: 600;
        }


        .status-besar {
            display: inline-block;

            padding: 10px 18px;

            border-radius: 30px;

            font-weight: 800;

            font-size: 13px;
        }


        .status-besar.menunggu {
            background:
                rgba(230, 180, 45, .15);

            color: #f4d46a;
        }


        .status-besar.diterima {
            background:
                rgba(43, 190, 113, .15);

            color: #70e2a2;
        }


        .status-besar.ditolak {
            background:
                rgba(235, 60, 80, .15);

            color: #ff9ca7;
        }


        .status-error {
            padding: 15px;

            border-radius: 10px;

            background:
                rgba(180, 40, 55, .15);

            border:
                1px solid rgba(255, 90, 110, .3);

            color: #ffabb5;

            font-size: 13px;
        }


        .kembali-status {
            text-align: center;

            margin-top: 25px;
        }


        .kembali-status a {
            color: #a38aff;

            text-decoration: none;

            font-size: 12px;
        }


        /* =========================================================
           ADMIN DASHBOARD
        ========================================================= */

        .admin-navbar {
            min-height: 72px;

            background:
                rgba(53, 75, 89, .97);

            border-bottom:
                1px solid rgba(255, 255, 255, .08);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 5%;

            position: sticky;

            top: 0;

            z-index: 20;

            backdrop-filter: blur(12px);
        }


        .admin-brand {
            font-size: 20px;

            font-weight: 800;
        }


        .admin-actions {
            display: flex;

            gap: 8px;
        }


        .admin-actions a {
            padding:
                9px 15px;

            border-radius: 7px;

            color: white;

            text-decoration: none;

            font-size: 11px;

            font-weight: 700;
        }


        .form-link {
            background:
                rgba(255, 255, 255, .08);
        }


        .logout-link {
            background:
                linear-gradient(135deg,
                    #6a3ff0,
                    #451bc3);
        }


        .admin-container {
            width: 92%;

            max-width: 1450px;

            margin: auto;

            padding:
                38px 0 60px;
        }


        .admin-title {
            margin-bottom: 28px;
        }


        .admin-title h1 {
            font-size: 28px;

            margin-bottom: 7px;
        }


        .admin-title p {
            color: #91a4af;

            font-size: 13px;
        }


        /* =========================================================
           STAT
        ========================================================= */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 25px;
        }


        .stat {
            position: relative;

            overflow: hidden;

            background:
                rgba(45, 67, 81, .94);

            border:
                1px solid rgba(255, 255, 255, .07);

            border-radius: 15px;

            padding: 23px;

            box-shadow:
                0 12px 30px rgba(0, 0, 0, .12);
        }


        .stat::after {
            content: "";

            position: absolute;

            width: 100px;
            height: 100px;

            border-radius: 50%;

            background: #6942ee;

            opacity: .08;

            right: -30px;

            bottom: -40px;
        }


        .stat-number {
            font-size: 31px;

            font-weight: 800;

            color: #a18aff;

            margin-bottom: 7px;
        }


        .stat-name {
            color: #9dafb8;

            font-size: 12px;
        }


        /* =========================================================
           TABLE
        ========================================================= */

        .table-card {
            background:
                rgba(45, 67, 81, .94);

            border:
                1px solid rgba(255, 255, 255, .07);

            border-radius: 15px;

            padding: 25px;

            box-shadow:
                0 15px 40px rgba(0, 0, 0, .13);
        }


        .table-title {
            font-size: 18px;

            font-weight: 800;

            margin-bottom: 20px;
        }


        .table-wrapper {
            overflow-x: auto;
        }


        table {
            width: 100%;

            min-width: 1300px;

            border-collapse: collapse;
        }


        th {
            background: #203542;

            padding: 14px;

            text-align: left;

            font-size: 11px;

            color: #bdcbd2;

            white-space: nowrap;
        }


        td {
            padding: 14px;

            border-bottom:
                1px solid rgba(255, 255, 255, .07);

            font-size: 11px;

            color: #b7c5cc;

            vertical-align: top;
        }


        tr:hover td {
            background:
                rgba(255, 255, 255, .02);
        }


        .status {
            display: inline-block;

            padding:
                6px 10px;

            border-radius: 30px;

            font-size: 9px;

            font-weight: 800;
        }


        .status-menunggu {
            background:
                rgba(230, 180, 45, .13);

            color: #f4d46a;
        }


        .status-diterima {
            background:
                rgba(43, 190, 113, .13);

            color: #70e2a2;
        }


        .status-ditolak {
            background:
                rgba(235, 60, 80, .13);

            color: #ff9ca7;
        }


        .aksi {
            display: flex;

            gap: 5px;

            flex-wrap: wrap;
        }


        .aksi a {
            padding:
                6px 9px;

            border-radius: 5px;

            color: white;

            text-decoration: none;

            font-size: 9px;

            font-weight: 700;
        }


        .terima {
            background: #16804d;
        }


        .tolak {
            background: #a63746;
        }


        .menunggu-btn {
            background: #555d64;
        }


        .cv-link {
            color: #a38aff;

            text-decoration: none;

            font-weight: 700;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media(max-width:850px) {

            .row {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .admin-navbar {
                padding:
                    15px 4%;

                flex-direction: column;

                gap: 12px;
            }

            .admin-menu {
                flex-wrap: wrap;
            }
        }


        @media(max-width:550px) {

            .header {
                min-height: 280px;

                padding-top: 85px;
            }


            .admin-menu {
                top: 12px;

                right: 12px;

                left: 12px;

                justify-content:
                    center;
            }


            .admin-menu a {
                padding:
                    8px 12px;

                font-size: 9px;
            }


            .logo {
                width: 180px;
            }


            .judul {
                font-size: 20px;
            }


            .subjudul {
                font-size: 12px;
            }


            .form-container {
                width: 94%;

                margin-top: -25px;
            }


            .form-card {
                padding:
                    22px 18px;

                border-radius: 14px;
            }


            .stats {
                grid-template-columns: 1fr;
            }


            .button-container {
                justify-content:
                    stretch;
            }


            .btn-daftar {
                width: 100%;
            }


            .login-box {
                padding:
                    30px 22px;
            }


            .admin-actions {
                width: 100%;

                flex-direction: column;
            }


            .admin-actions a {
                text-align: center;
            }


            .status-card {
                padding:
                    25px 18px;
            }


            .status-info {
                grid-template-columns: 1fr;

                gap: 4px;

                margin-bottom: 15px;
            }

        }
    </style>

</head>


<body>


    <?php

    /* =========================================================
       HALAMAN LOGIN ADMIN
    ========================================================= */

    if ($halaman === "login"):

        ?>

        <div class="login-page">

            <div class="login-box">

                <div class="login-logo">

                    <img src="logo-sbi.png" alt="Logo SBI">

                </div>


                <div class="login-title">
                    LOGIN ADMIN
                </div>


                <div class="login-subtitle">
                    Kelola Pendaftaran Magang SBI
                </div>


                <?php if ($login_error !== ""): ?>

                    <div class="pesan error">

                        <?= htmlspecialchars(
                            $login_error
                        ) ?>

                    </div>

                <?php endif; ?>


                <form method="POST">

                    <div class="login-group">

                        <label>
                            Username
                        </label>

                        <input type="text" name="username" placeholder="Masukkan username admin" required>

                    </div>


                    <div class="login-group">

                        <label>
                            Password
                        </label>

                        <input type="password" name="password" placeholder="Masukkan password" required>

                    </div>


                    <button type="submit" name="login_admin" class="login-button">
                        LOGIN
                    </button>

                </form>


                <div class="kembali">

                    <a href="index.php">
                        ← Kembali ke halaman pendaftaran
                    </a>

                </div>

            </div>

        </div>


        <?php

        /* =========================================================
           HALAMAN CEK STATUS
        ========================================================= */

    elseif ($halaman === "status"):

        ?>

        <div class="status-page">

            <div class="status-container">

                <div class="status-card">

                    <div class="status-header">

                        <div class="icon">
                            🔎
                        </div>

                        <h1>
                            Cek Status Pendaftaran
                        </h1>

                        <p>
                            Masukkan nomor WhatsApp
                            yang digunakan saat mendaftar.
                        </p>

                    </div>


                    <form method="POST" class="status-form">

                        <label>
                            Nomor WhatsApp
                        </label>

                        <input type="tel" name="nomor_whatsapp" placeholder="Contoh: 081234567890" required>

                        <button type="submit" name="cek_status" class="btn-status">
                            🔎 CEK STATUS
                        </button>

                    </form>


                    <?php if ($status_error !== ""): ?>

                        <div class="status-error">

                            <?= htmlspecialchars(
                                $status_error
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if ($status_data): ?>

                        <?php

                        $status =
                            $status_data["status"]
                            ?: "Menunggu";

                        $status_class =
                            strtolower($status);

                        ?>

                        <div class="status-result">

                            <h2>
                                📋 Hasil Pendaftaran
                            </h2>


                            <div class="status-info">

                                <div class="label">
                                    Nama Lengkap
                                </div>

                                <div class="value">

                                    <?= htmlspecialchars(
                                        $status_data[
                                            "nama_lengkap"
                                        ]
                                    ) ?>

                                </div>


                                <div class="label">
                                    Sekolah / Kampus
                                </div>

                                <div class="value">

                                    <?= htmlspecialchars(
                                        $status_data[
                                            "nama_sekolah_kampus"
                                        ]
                                    ) ?>

                                </div>


                                <div class="label">
                                    Jenjang
                                </div>

                                <div class="value">

                                    <?= htmlspecialchars(
                                        $status_data[
                                            "jenjang_pendidikan"
                                        ]
                                    ) ?>

                                </div>


                                <div class="label">
                                    Nomor WhatsApp
                                </div>

                                <div class="value">

                                    <?= htmlspecialchars(
                                        $status_data[
                                            "nomor_whatsapp"
                                        ]
                                    ) ?>

                                </div>


                                <div class="label">
                                    Periode Magang
                                </div>

                                <div class="value">

                                    <?= date(
                                        "d/m/Y",
                                        strtotime(
                                            $status_data[
                                                "tanggal_mulai_magang"
                                            ]
                                        )
                                    ) ?>

                                    -

                                    <?= date(
                                        "d/m/Y",
                                        strtotime(
                                            $status_data[
                                                "tanggal_selesai_magang"
                                            ]
                                        )
                                    ) ?>

                                </div>


                                <div class="label">
                                    Status
                                </div>

                                <div class="value">

                                    <span class="
                                        status-besar
                                        <?= $status_class ?>
                                    ">

                                        <?php

                                        if (
                                            $status === "Diterima"
                                        ) {

                                            echo "✓ DITERIMA";

                                        } elseif (
                                            $status === "Ditolak"
                                        ) {

                                            echo "✕ DITOLAK";

                                        } else {

                                            echo "⏳ MENUNGGU";

                                        }

                                        ?>

                                    </span>

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>


                    <div class="kembali-status">

                        <a href="index.php">
                            ← Kembali ke halaman pendaftaran
                        </a>

                    </div>

                </div>

            </div>

        </div>


        <?php

        /* =========================================================
           DASHBOARD ADMIN
        ========================================================= */

    elseif ($halaman === "admin"):


        $total_result = $conn->query("
        SELECT COUNT(*) jumlah
        FROM pendaftaran_magang
    ");

        $total =
            $total_result
            ? $total_result->fetch_assoc()["jumlah"]
            : 0;


        $menunggu_result = $conn->query("
        SELECT COUNT(*) jumlah
        FROM pendaftaran_magang
        WHERE status = 'Menunggu'
    ");

        $menunggu =
            $menunggu_result
            ? $menunggu_result->fetch_assoc()["jumlah"]
            : 0;


        $diterima_result = $conn->query("
        SELECT COUNT(*) jumlah
        FROM pendaftaran_magang
        WHERE status = 'Diterima'
    ");

        $diterima =
            $diterima_result
            ? $diterima_result->fetch_assoc()["jumlah"]
            : 0;


        $ditolak_result = $conn->query("
        SELECT COUNT(*) jumlah
        FROM pendaftaran_magang
        WHERE status = 'Ditolak'
    ");

        $ditolak =
            $ditolak_result
            ? $ditolak_result->fetch_assoc()["jumlah"]
            : 0;


        $data_pendaftar =
            $conn->query("
            SELECT *
            FROM pendaftaran_magang
            ORDER BY id DESC
        ");

        ?>

        <nav class="admin-navbar">

            <div class="admin-brand">

                SBI
                <span style="color:#9d82ff;">
                    |
                </span>
                ADMIN

            </div>


            <div class="admin-actions">

                <a href="index.php" class="form-link">
                    FORM PENDAFTARAN
                </a>


                <a href="?logout=1" class="logout-link" onclick="
                    return confirm('Yakin ingin logout?');
                ">
                    LOGOUT
                </a>

            </div>

        </nav>


        <div class="admin-container">

            <div class="admin-title">

                <h1>
                    Dashboard Admin
                </h1>

                <p>

                    Selamat datang kembali,

                    <strong>

                        <?= htmlspecialchars(
                            $_SESSION[
                                "admin_username"
                            ]
                        ) ?>

                    </strong>

                </p>

            </div>


            <div class="stats">


                <div class="stat">

                    <div class="stat-number">
                        <?= $total ?>
                    </div>

                    <div class="stat-name">
                        Total Pendaftar
                    </div>

                </div>


                <div class="stat">

                    <div class="stat-number">
                        <?= $menunggu ?>
                    </div>

                    <div class="stat-name">
                        Menunggu Seleksi
                    </div>

                </div>


                <div class="stat">

                    <div class="stat-number">
                        <?= $diterima ?>
                    </div>

                    <div class="stat-name">
                        Pendaftar Diterima
                    </div>

                </div>


                <div class="stat">

                    <div class="stat-number">
                        <?= $ditolak ?>
                    </div>

                    <div class="stat-name">
                        Pendaftar Ditolak
                    </div>

                </div>

            </div>


            <div class="table-card">

                <div class="table-title">
                    📋 Data Pendaftar Magang
                </div>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>No</th>

                                <th>Nama Lengkap</th>

                                <th>Jenjang</th>

                                <th>Sekolah / Kampus</th>

                                <th>WhatsApp</th>

                                <th>Anggota</th>

                                <th>Periode</th>

                                <th>CV</th>

                                <th>Status</th>

                                <th>Aksi</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php

                            $no = 1;

                            if (
                                $data_pendaftar &&
                                $data_pendaftar->num_rows > 0
                            ):

                                while (
                                    $data =
                                    $data_pendaftar->fetch_assoc()
                                ):

                                    $status =
                                        $data["status"]
                                        ?: "Menunggu";


                                    $status_class =
                                        "status-menunggu";


                                    if (
                                        $status === "Diterima"
                                    ) {

                                        $status_class =
                                            "status-diterima";
                                    }


                                    if (
                                        $status === "Ditolak"
                                    ) {

                                        $status_class =
                                            "status-ditolak";
                                    }

                                    ?>

                                    <tr>


                                        <td>
                                            <?= $no++ ?>
                                        </td>


                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $data[
                                                        "nama_lengkap"
                                                    ]
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $data[
                                                    "jenjang_pendidikan"
                                                ]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $data[
                                                    "nama_sekolah_kampus"
                                                ]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $data[
                                                    "nomor_whatsapp"
                                                ]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= intval(
                                                $data[
                                                    "jumlah_anggota"
                                                ]
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= date(
                                                "d/m/Y",
                                                strtotime(
                                                    $data[
                                                        "tanggal_mulai_magang"
                                                    ]
                                                )
                                            ) ?>

                                            -

                                            <?= date(
                                                "d/m/Y",
                                                strtotime(
                                                    $data[
                                                        "tanggal_selesai_magang"
                                                    ]
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                $data["file_cv"]
                                            )
                                            ):

                                                ?>

                                                <a href="uploads/<?= rawurlencode(
                                                    $data["file_cv"]
                                                ) ?>" target="_blank" class="cv-link">
                                                    📎 Lihat CV
                                                </a>

                                            <?php else: ?>

                                                -

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <span class="
                                        status
                                        <?= $status_class ?>
                                    ">

                                                <?= htmlspecialchars(
                                                    $status
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <div class="aksi">


                                                <a href="?halaman=admin&aksi=terima&id=<?= intval($data["id"]) ?>" class="terima"
                                                    onclick="
                                            return confirm(
                                                'Terima pendaftar ini?'
                                            );
                                        ">
                                                    Terima
                                                </a>


                                                <a href="?halaman=admin&aksi=tolak&id=<?= intval($data["id"]) ?>" class="tolak"
                                                    onclick="
                                            return confirm(
                                                'Tolak pendaftar ini?'
                                            );
                                        ">
                                                    Tolak
                                                </a>


                                                <a href="?halaman=admin&aksi=menunggu&id=<?= intval($data["id"]) ?>"
                                                    class="menunggu-btn" onclick="
                                            return confirm(
                                                'Reset status menjadi Menunggu?'
                                            );
                                        ">
                                                    Reset
                                                </a>

                                            </div>

                                        </td>

                                    </tr>


                                    <?php

                                endwhile;

                            else:

                                ?>

                                <tr>

                                    <td colspan="10" style="
                                    text-align:center;
                                    padding:45px;
                                ">
                                        Belum ada data pendaftar.
                                    </td>

                                </tr>

                                <?php

                            endif;

                            ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        <?php

        /* =========================================================
           FORM PENDAFTARAN
        ========================================================= */

    else:

        ?>

        <header class="header">


            <div class="admin-menu">

                <a href="?halaman=status">
                    🔎 CEK STATUS
                </a>


                <a href="?halaman=login">
                    🔐 ADMIN
                </a>

            </div>


            <img src="logo-sbi.png" class="logo" alt="Logo SBI">


            <div class="judul">
                PENGAJUAN PENDAFTARAN MAGANG
            </div>


            <div class="subjudul">
                Bergabung dan kembangkan pengalamanmu bersama SBI
            </div>

        </header>


        <main class="form-container">


            <?php if ($pesan !== ""): ?>

                <div class="
                    pesan
                    <?= $pesan_type === "success"
                        ? "success"
                        : "error"
                        ?>
                ">

                    <?= htmlspecialchars($pesan) ?>

                </div>

            <?php endif; ?>


            <form method="POST" enctype="multipart/form-data">


                <!-- =====================================================
                 DATA PENDIDIKAN
            ====================================================== -->

                <div class="form-card">


                    <div class="section-header">

                        <div class="section-icon">
                            🎓
                        </div>


                        <div class="section-title">

                            <h2>
                                Data Pendidikan
                            </h2>

                            <p>
                                Masukkan informasi sekolah atau kampus Anda
                            </p>

                        </div>

                    </div>


                    <div class="row">


                        <div class="field">

                            <label>

                                Jenjang Pendidikan

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <select name="jenjang" required>

                                <option value="">
                                    Pilih Jenjang Pendidikan
                                </option>

                                <option value="SMK">
                                    SMK
                                </option>

                                <option value="Kuliah">
                                    Kuliah
                                </option>

                            </select>

                        </div>


                        <div class="field">

                            <label>

                                Nama Sekolah / Kampus

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input type="text" name="nama_sekolah" placeholder="Contoh: SMK Negeri 1 Jakarta" required>

                        </div>

                    </div>


                    <div class="field-full">

                        <label>

                            Alamat Sekolah / Kampus

                            <span class="required">
                                *
                            </span>

                        </label>


                        <textarea name="alamat_sekolah" placeholder="Masukkan alamat lengkap sekolah / kampus"
                            required></textarea>

                    </div>

                </div>


                <!-- =====================================================
                 DATA PESERTA
            ====================================================== -->

                <div class="form-card">


                    <div class="section-header">

                        <div class="section-icon">
                            👤
                        </div>


                        <div class="section-title">

                            <h2>
                                Data Peserta
                            </h2>

                            <p>
                                Lengkapi identitas peserta magang
                            </p>

                        </div>

                    </div>


                    <div class="field-full">

                        <label>

                            Nama Lengkap

                            <span class="required">
                                *
                            </span>

                        </label>


                        <input type="text" name="nama_lengkap" placeholder="Masukkan nama lengkap" required>

                    </div>


                    <div class="row">


                        <div class="field">

                            <label>

                                Tempat Lahir

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input type="text" name="tempat_lahir" placeholder="Contoh: Jakarta" required>

                        </div>


                        <div class="field">

                            <label>

                                Tanggal Lahir

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input type="date" name="tanggal_lahir" required>

                        </div>

                    </div>


                    <div class="field-full">

                        <label>

                            Alamat Rumah

                            <span class="required">
                                *
                            </span>

                        </label>


                        <textarea name="alamat_rumah" placeholder="Masukkan alamat tempat tinggal" required></textarea>

                    </div>


                    <div class="row">


                        <div class="field">

                            <label>

                                Nomor WhatsApp

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input type="tel" name="nomor_whatsapp" placeholder="Contoh: 081234567890" required>

                        </div>


                        <div class="field">

                            <label>

                                Jumlah Anggota

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input type="number" name="jumlah_anggota" value="1" min="1" required>

                        </div>

                    </div>

                </div>


                <!-- =====================================================
                 PERIODE MAGANG
            ====================================================== -->

                <div class="form-card">


                    <div class="section-header">

                        <div class="section-icon">
                            📅
                        </div>


                        <div class="section-title">

                            <h2>
                                Periode Magang
                            </h2>

                            <p>
                                Tentukan tanggal pelaksanaan magang
                            </p>

                        </div>

                    </div>


                    <div class="row">


                        <div class="field">

                            <label>

                                Tanggal Mulai Magang

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input type="date" name="tanggal_mulai" required>

                        </div>


                        <div class="field">

                            <label>

                                Tanggal Selesai Magang

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input type="date" name="tanggal_selesai" required>

                        </div>

                    </div>

                </div>


                <!-- =====================================================
                 CV
            ====================================================== -->

                <div class="form-card">


                    <div class="section-header">

                        <div class="section-icon">
                            📎
                        </div>


                        <div class="section-title">

                            <h2>
                                Dokumen Pendaftaran
                            </h2>

                            <p>
                                Upload CV yang berisi data kelompok magang
                            </p>

                        </div>

                    </div>


                    <div class="field-full">

                        <label>

                            File CV

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="file-box">

                            <input type="file" name="file_cv" accept=".pdf,.doc,.docx" required>


                            <div class="file-info">

                                Format yang diperbolehkan:
                                PDF, DOC, DOCX.
                                Maksimal 5 MB.

                            </div>

                        </div>

                    </div>


                    <div class="button-container">

                        <button type="submit" name="daftar_magang" class="btn-daftar">

                            DAFTAR SEKARANG
                            &nbsp; →

                        </button>

                    </div>

                </div>

            </form>

        </main>


        <footer>

            © <?= date("Y") ?> SBI —
            Sistem Pendaftaran Magang

        </footer>


    <?php endif; ?>


</body>

</html>