<?php
// 从 USGS API 获取最新地震数据并写入数据库

$host = 'localhost';
$dbname = 'earthquakes';
$username = 'earthquakes';
$password = 'DZtzTwXpKYP1MBMk';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
} catch (PDOException $e) {
    die('ERROR: ' . $e->getMessage());
}

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

function convertFromEpoch($epochMillis) {
    if (!$epochMillis) return null;
    return date('Y-m-d H:i:s', $epochMillis / 1000);
}

// 获取数据库中最新的地震时间
$lastTime = '1900-01-01 00:00:00';
$tables = $pdo->query("SHOW TABLES LIKE 'earthquakes_%'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $row = $pdo->query("SELECT MAX(`time`) AS max_time FROM $table")->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['max_time'] && $row['max_time'] > $lastTime) {
        $lastTime = $row['max_time'];
    }
}

$url = 'https://earthquake.usgs.gov/fdsnws/event/1/query?format=geojson&starttime=' . urlencode($lastTime);
$response = file_get_contents($url);
$data = json_decode($response, true);
if (!isset($data['features'])) {
    echo "No data returned\n";
    exit;
}

$pdo->beginTransaction();
$insertCache = [];
foreach ($data['features'] as $feature) {
    $props = $feature['properties'];
    $geom = $feature['geometry'];
    $time = convertFromEpoch($props['time']);
    $latitude = sanitizeValue($geom['coordinates'][1], 'decimal');
    $longitude = sanitizeValue($geom['coordinates'][0], 'decimal');
    $depth = sanitizeValue($geom['coordinates'][2], 'decimal');
    $mag = sanitizeValue($props['mag'], 'decimal');
    if (!$time || !$latitude || !$longitude || !$depth || !$mag) {
        continue;
    }
    $year = (new DateTime($time))->format('Y');
    $tableName = checkAndCreateTable($pdo, $year);

    if (!isset($insertCache[$tableName])) {
        $insertCache[$tableName] = $pdo->prepare(
            "INSERT INTO $tableName (`time`, `latitude`, `longitude`, `depth`, `mag`, `magType`, `nst`, `gap`, `dmin`, `rms`, `net`, `id`, `updated`, `place`, `type`, `horizontalError`, `depthError`, `magError`, `magNst`, `status`, `locationSource`, `magSource`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `updated` = VALUES(`updated`)"
        );
    }
    $stmt = $insertCache[$tableName];
    $stmt->execute([
        $time,
        $latitude,
        $longitude,
        $depth,
        $mag,
        sanitizeValue($props['magType'], 'string', 4),
        sanitizeValue($props['nst'], 'int'),
        sanitizeValue($props['gap'], 'decimal'),
        sanitizeValue($props['dmin'], 'decimal'),
        sanitizeValue($props['rms'], 'decimal'),
        sanitizeValue($props['net'], 'string', 2),
        sanitizeValue($feature['id'], 'string', 15),
        convertFromEpoch($props['updated']),
        sanitizeValue($props['place'], 'string', 255),
        sanitizeValue($props['type'], 'string', 50),
        sanitizeValue($props['horizontalError'], 'decimal'),
        sanitizeValue($props['depthError'], 'decimal'),
        sanitizeValue($props['magError'], 'decimal'),
        sanitizeValue($props['magNst'], 'int'),
        sanitizeValue($props['status'], 'string', 10),
        sanitizeValue($props['locationSource'], 'string', 2),
        sanitizeValue($props['magSource'], 'string', 2),
    ]);
}
$pdo->commit();

echo "Update completed at " . date('Y-m-d H:i:s') . "\n";
