<?php
// 数据库参数
$host = 'localhost';
$dbname = 'eq_probability_c';
$username = 'eq_probability_c';
$password = 'LzbmE7rH8EkTCsHm';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

function fetchEarthquakeData($startTime) {
    $apiUrl = 'https://earthquake.usgs.gov/fdsnws/event/1/query?format=geojson&starttime=' . urlencode($startTime);
    $data = file_get_contents($apiUrl);
    return json_decode($data, true);
}

function checkAndCreateTable($pdo, $year) {
    $tableName = "earthquakes_$year";
    $sql = "CREATE TABLE IF NOT EXISTS $tableName (
        `time` DATETIME NOT NULL,
        `latitude` DECIMAL(10, 7) NOT NULL,
        `longitude` DECIMAL(10, 7) NOT NULL,
        `depth` DECIMAL(6, 2) NOT NULL,
        `mag` DECIMAL(3, 2) NOT NULL,
        `magType` VARCHAR(4),
        `id` VARCHAR(20) NOT NULL,
        `updated` DATETIME NOT NULL,
        `place` VARCHAR(255),
        `type` VARCHAR(50),
        `status` VARCHAR(20),
        `locationSource` VARCHAR(20),
        `magSource` VARCHAR(20),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    $pdo->exec($sql);
    return $tableName;
}

function importData($pdo, $eq, $year) {
    $tableName = checkAndCreateTable($pdo, $year); // 确保对应年份的表存在
    $properties = $eq['properties'];
    $geometry = $eq['geometry'];

    // 检查必需字段
    if (empty($properties['id']) || empty($properties['time']) || !isset($geometry['coordinates'][1]) || !isset($geometry['coordinates'][0])) {
        return; // 跳过缺少必要信息的记录
    }

    $sql = "INSERT INTO $tableName (`time`, `latitude`, `longitude`, `depth`, `mag`, `magType`, `id`, `updated`, `place`, `type`, `status`, `locationSource`, `magSource`) VALUES (:time, :latitude, :longitude, :depth, :mag, :magType, :id, :updated, :place, :type, :status, :locationSource, :magSource) ON DUPLICATE KEY UPDATE `updated` = VALUES(`updated`)";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':time' => date('Y-m-d H:i:s', ($properties['time'] / 1000)),
        ':latitude' => $geometry['coordinates'][1],
        ':longitude' => $geometry['coordinates'][0],
        ':depth' => $geometry['coordinates'][2],
        ':mag' => $properties['mag'],
        ':magType' => $properties['magType'],
        ':id' => $properties['id'],
        ':updated' => date('Y-m-d H:i:s', ($properties['updated'] / 1000)),
        ':place' => $properties['place'],
        ':type' => $properties['type'],
        ':status' => $properties['status'],
        ':locationSource' => $properties['net'],
        ':magSource' => $properties['net'],
    ]);
}

function getLastUpdateInfo($pdo) {
    $stmt = $pdo->query("SELECT last_update, records_updated FROM updates ORDER BY last_update DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveUpdateInfo($pdo, $recordsUpdated) {
    $stmt = $pdo->prepare("INSERT INTO updates (last_update, records_updated) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_update = VALUES(last_update), records_updated = VALUES(records_updated)");
    $stmt->execute([date('Y-m-d H:i:s'), $recordsUpdated]);
}

function fetchAndUpdateEarthquakes($pdo) {
    $lastUpdate = getLastUpdateInfo($pdo);
    $startTime = $lastUpdate ? $lastUpdate['last_update'] : '2024-03-09T00:00:00Z';
    $earthquakes = fetchEarthquakeData($startTime);
    $recordsUpdated = 0;
    
    if (isset($earthquakes['features']) && count($earthquakes['features']) > 0) {
        foreach ($earthquakes['features'] as $eq) {
            $properties = $eq['properties'];
            $year = date('Y', ($properties['time'] / 1000));
            importData($pdo, $eq, $year); // 修正，确保单个事件传递
            $recordsUpdated++;
        }
    }

    saveUpdateInfo($pdo, $recordsUpdated);
    return $recordsUpdated;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordsUpdated = fetchAndUpdateEarthquakes($pdo);
    $updateInfo = getLastUpdateInfo($pdo);
    $lastUpdateTime = $updateInfo ? $updateInfo['last_update'] : 'N/A';
    $recordsUpdated = $updateInfo ? $updateInfo['records_updated'] : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Earthquake Data Update</title>
</head>
<body>
    <h2>Earthquake Data Update</h2>
    <?php if (!empty($updateInfo)): ?>
        <p>Last Update Time: <?php echo htmlspecialchars($lastUpdateTime); ?></p>
        <p>Records Updated Last Time: <?php echo htmlspecialchars($recordsUpdated); ?></p>
    <?php else: ?>
        <p>No updates found. Click refresh to fetch data.</p>
    <?php endif; ?>
    <form method="post">
        <button type="submit">Refresh Data</button>
    </form>
</body>
</html>
