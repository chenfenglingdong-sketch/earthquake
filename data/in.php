<?php
session_start(); // 开始会话，用于进度跟踪
// 数据库参数
$host = 'localhost';
$dbname = 'earthquakes';
$username = 'earthquakes';
$password = 'DZtzTwXpKYP1MBMk';

// 创建 PDO 实例
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// 动态添加缺失的字段
function addMissingColumns($pdo, $tableName, $csvHeader) {
    $stmt = $pdo->query("SHOW COLUMNS FROM $tableName");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($csvHeader as $column) {
        if (!in_array($column, $existingColumns)) {
            $pdo->exec("ALTER TABLE $tableName ADD $column TEXT NOT NULL");
        }
    }
}

// 检查并创建年份表
function checkAndCreateTable($pdo, $year) {
    $tableName = "earthquakes_$year";
    $createTableSQL = "CREATE TABLE IF NOT EXISTS $tableName (
        `time` DATETIME NOT NULL,
        `latitude` DECIMAL(10, 7) NOT NULL,
        `longitude` DECIMAL(10, 7) NOT NULL,
        `depth` DECIMAL(6, 2) NOT NULL,
        `mag` DECIMAL(3, 2) NOT NULL,
        `nst` INT,
        `gap` DECIMAL(5, 2),
        `dmin` DECIMAL(6, 3),
        `rms` DECIMAL(3, 2),
        `net` VARCHAR(2),
        `id` VARCHAR(15) NOT NULL,
        `updated` DATETIME NOT NULL,
        `place` VARCHAR(255),
        `type` VARCHAR(50),
        `horizontalError` DECIMAL(5, 2),
        `magError` DECIMAL(5, 2),
        `magNst` INT,
        `status` VARCHAR(10),
        `locationSource` VARCHAR(2),
        `magSource` VARCHAR(2),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $pdo->exec($createTableSQL);
    return $tableName;
}

// 将 ISO 8601 转换为 MySQL DATETIME 格式
function convertToMySQLDateTime($iso8601Date) {
    try {
        $date = new DateTime($iso8601Date);
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

// 将空字符串转换为NULL或数字
function sanitizeDecimal($value) {
    return (!empty($value) || $value === '0') ? $value : null;
}

// 将空字符串转换为NULL或整数
function sanitizeInteger($value) {
    return (is_numeric($value)) ? (int)$value : null;
}

// 处理CSV上传和导入逻辑
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_FILES['csv'])) {
    $csvFile = $_FILES['csv']['tmp_name'];
    if (is_file($csvFile)) {
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            $firstData = fgetcsv($handle, 1000, ",");
            $date = new DateTime($firstData[0]);
            $year = $date->format('Y');
            $tableName = checkAndCreateTable($pdo, $year);
            addMissingColumns($pdo, $tableName, $header);

            $pdo->beginTransaction();
            $row = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                if ($row == 1) { continue; } // Skip header row

                $time = convertToMySQLDateTime($data[0]);
                if (!$time) {
                    continue;
                }

                $stmt = $pdo->prepare("INSERT INTO $tableName (`time`, `latitude`, `longitude`, `depth`, `mag`, `nst`, `gap`, `dmin`, `rms`, `net`, `id`, `updated`, `place`, `type`, `horizontalError`, `magError`, `magNst`, `status`, `locationSource`, `magSource`)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE `updated` = VALUES(`updated`)");

                $stmt->execute([
                    $time, $data[1], $data[2], $data[3], $data[4], 
                    sanitizeInteger($data[6]), sanitizeDecimal($data[7]), sanitizeDecimal($data[8]),
                    sanitizeDecimal($data[9]), $data[10], $data[11],
                    convertToMySQLDateTime($data[12]), $data[13], $data[14], 
                    sanitizeDecimal($data[15]), sanitizeDecimal($data[17]),
                    sanitizeInteger($data[18]), $data[19], $data[20], $data[21]
                ]);

                $_SESSION['progress'] = "已成功添加 $year 年份的 $row 条地震信息";
            }
            $pdo->commit();
            fclose($handle);
            echo "数据已成功导入 for year $year.";
        } else {
            echo "打开文件错误.";
        }
    } else {
        echo "File not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV Import</title>
    <style>
        body { text-align: center; }
        form { margin-top: 20px; }
        button { background-color: #4CAF50;
                 color: white;
                 padding: 14px 20px;
                 margin: 8px 0;
                 border: none;
                 cursor: pointer; }
        button:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <h2>Import CSV file</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv" accept=".csv" />
        <button type="submit">Import</button>
    </form>

    <div id="progress">Progress will display here...</div>

    <script>
        function updateProgress() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'session.php', true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    document.getElementById('progress').textContent = xhr.responseText;
                } else {
                    console.error('Error fetching progress');
                }
            };
            xhr.send();
        }
        setInterval(updateProgress, 1000);
    </script>
</body>
</html>
