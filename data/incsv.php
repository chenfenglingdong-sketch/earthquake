<?php
session_start(); // 开始会话，用于进度跟踪
// 数据库参数
$host = 'localhost';
$dbname = 'earthquakes';
$username = 'earthquakes';
$password = 'DZtzTwXpKYP1MBMk';

// 创建 PDO 实例
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
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
        `magType` VARCHAR(4) DEFAULT NULL,
        `nst` INT(11) DEFAULT NULL,
        `gap` DECIMAL(5, 2) DEFAULT NULL,
        `dmin` DECIMAL(6, 3) DEFAULT NULL,
        `rms` DECIMAL(3, 2) DEFAULT NULL,
        `net` VARCHAR(2) DEFAULT NULL,
        `id` VARCHAR(15) NOT NULL,
        `updated` DATETIME NOT NULL,
        `place` VARCHAR(255) DEFAULT NULL,
        `type` VARCHAR(50) DEFAULT NULL,
        `horizontalError` DECIMAL(6, 3) DEFAULT NULL,
        `depthError` DECIMAL(6, 3) DEFAULT NULL,
        `magError` DECIMAL(6, 3) DEFAULT NULL,
        `magNst` INT(11) DEFAULT NULL,
        `status` VARCHAR(10) DEFAULT NULL,
        `locationSource` VARCHAR(2) DEFAULT NULL,
        `magSource` VARCHAR(2) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($createTableSQL);
    return $tableName;
}

// 将 ISO 8601 转换为 MySQL DATETIME 格式
function convertToMySQLDateTime($iso8601Date) {
    try {
        $iso8601Date = str_replace('Z', '', $iso8601Date); // 移除 Z
        $iso8601Date = preg_replace('/\.\d+/', '', $iso8601Date); // 去除毫秒
        $date = new DateTime($iso8601Date);
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Date conversion failed: $iso8601Date");
        return null;
    }
}

// 处理数据超出范围的值
function sanitizeValue($value, $type, $maxLength = null) {
    if ($value === '' || $value === null) {
        return null;
    }
    switch ($type) {
        case 'int':
            return is_numeric($value) ? (int)$value : null;
        case 'decimal':
            return is_numeric($value) ? (float)$value : null;
        case 'string':
            return $maxLength ? substr($value, 0, $maxLength) : $value;
        default:
            return $value;
    }
}

// 处理CSV上传和导入逻辑
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_FILES['csv'])) {
    $csvFile = $_FILES['csv']['tmp_name'];
    if (is_file($csvFile)) {
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ","); // 获取 CSV 表头
            $firstData = fgetcsv($handle, 1000, ","); // 获取第一行数据
            $date = new DateTime($firstData[0]);
            $year = $date->format('Y');
            $tableName = checkAndCreateTable($pdo, $year);

            $pdo->beginTransaction();
            try {
                $row = 0;
                $successfulInserts = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row++;
                    $time = convertToMySQLDateTime($data[0]);
                    $latitude = sanitizeValue($data[1], 'decimal');
                    $longitude = sanitizeValue($data[2], 'decimal');
                    $depth = sanitizeValue($data[3], 'decimal');
                    $mag = sanitizeValue($data[4], 'decimal');
                    
                    // 跳过缺失关键信息的记录
                    if (!$time || !$latitude || !$longitude || !$depth || !$mag) {
                        error_log("Skipping row $row due to missing critical values.");
                        continue;
                    }

                    try {
                        $stmt = $pdo->prepare("INSERT INTO $tableName (`time`, `latitude`, `longitude`, `depth`, `mag`, `magType`, `nst`, `gap`, `dmin`, `rms`, `net`, `id`, `updated`, `place`, `type`, `horizontalError`, `depthError`, `magError`, `magNst`, `status`, `locationSource`, `magSource`)
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                                ON DUPLICATE KEY UPDATE `updated` = VALUES(`updated`)");

                        $stmt->execute([
                            $time,
                            $latitude,
                            $longitude,
                            $depth,
                            $mag,
                            sanitizeValue($data[5], 'string', 4), // magType
                            sanitizeValue($data[6], 'int'),
                            sanitizeValue($data[7], 'decimal'),
                            sanitizeValue($data[8], 'decimal'),
                            sanitizeValue($data[9], 'decimal'),
                            sanitizeValue($data[10], 'string', 2), // net
                            sanitizeValue($data[11], 'string', 15), // id
                            convertToMySQLDateTime($data[12]),
                            sanitizeValue($data[13], 'string', 255), // place
                            sanitizeValue($data[14], 'string', 50), // type
                            sanitizeValue($data[15], 'decimal'),
                            sanitizeValue($data[16], 'decimal'),
                            sanitizeValue($data[17], 'decimal'),
                            sanitizeValue($data[18], 'int'),
                            sanitizeValue($data[19], 'string', 10), // status
                            sanitizeValue($data[20], 'string', 2), // locationSource
                            sanitizeValue($data[21], 'string', 2)  // magSource
                        ]);

                        $successfulInserts++;
                    } catch (PDOException $e) {
                        // 如果某列数据出错，自动将其设置为空值并继续
                        error_log("Row $row import error: " . $e->getMessage());
                        continue;
                    }
                }
                $pdo->commit();
                fclose($handle);
                echo "数据已成功导入 $successfulInserts 条记录到年份 $year.";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Error during import: " . $e->getMessage());
                echo "导入过程中发生错误: " . $e->getMessage();
            }
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
