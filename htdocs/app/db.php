<?php
require_once __DIR__ . '/config.php';

/** Trả về kết nối PDO dùng chung cho cả request. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // QLBV_DSN cho phép trỏ sang CSDL khác khi chạy thử ở máy cá nhân.
    // Máy chủ thật không đặt biến này và luôn dùng MySQL.
    $dsn  = getenv('QLBV_DSN') ?: sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $user = getenv('QLBV_DSN') ? null : DB_USER;
    $pass = getenv('QLBV_DSN') ? null : DB_PASS;

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 8000');   // chờ khóa tối đa 8s thay vì lỗi ngay
        } else {
            // Máy chủ (InfinityFree) chạy giờ UTC — đặt múi giờ Việt Nam để
            // CURRENT_TIMESTAMP/NOW() ra đúng giờ (đăng nhập cuối, nhật ký, nhập liệu…).
            $pdo->exec("SET time_zone = '+07:00'");
        }
    } catch (PDOException $e) {
        if (CHE_DO_GO_LOI) {
            die('Lỗi kết nối CSDL: ' . htmlspecialchars($e->getMessage()));
        }
        http_response_code(503);
        die('Không kết nối được cơ sở dữ liệu. Vui lòng liên hệ quản trị viên.');
    }

    return $pdo;
}

/** Chạy câu lệnh có tham số, trả về PDOStatement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Lấy 1 dòng, hoặc null. */
function q1(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Lấy nhiều dòng. */
function qAll(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Lấy 1 giá trị đơn. */
function qVal(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}
