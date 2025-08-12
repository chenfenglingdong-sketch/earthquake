<?php
// 数据库参数
$host = 'localhost'; // 或者你的数据库地址
$dbname = 'eq_probability_c';
$username = 'eq_probability_c';
$password = 'LzbmE7rH8EkTCsHm';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

function getLastUpdateInfo($pdo) {
    $stmt = $pdo->query("SELECT last_update, records_updated FROM updates ORDER BY last_update DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveUpdateInfo($pdo, $recordsUpdated) {
    $stmt = $pdo->prepare("INSERT INTO updates (last_update, records_updated) VALUES (?, ?)");
    $stmt->execute([date('Y-m-d H:i:s'), $recordsUpdated]);
}

function fetchAndUpdateEarthquakes($pdo) {
    // Your fetchEarthquakeData and importData functions here...

    // Initialize update info
    $lastUpdate = getLastUpdateInfo($pdo);
    $startTime = $lastUpdate ? $lastUpdate['last_update'] : '2024-03-09T00:00:00Z';

    // Fetch and import data
    $earthquakes = fetchEarthquakeData($startTime);
    $recordsUpdated = isset($earthquakes['features']) ? count($earthquakes['features']) : 0;
    if ($recordsUpdated > 0) {
        importData($pdo, $earthquakes);
    }

    // Save update info
    saveUpdateInfo($pdo, $recordsUpdated);

    return $recordsUpdated;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordsUpdated = fetchAndUpdateEarthquakes($pdo);
    $lastUpdateTime = date('Y-m-d H:i:s');
} else {
    $updateInfo = getLastUpdateInfo($pdo);
    $lastUpdateTime = $updateInfo['last_update'] ?? 'N/A';
    $recordsUpdated = $updateInfo['records_updated'] ?? 0;
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
    <p>Last Update Time: <?php echo $lastUpdateTime; ?></p>
    <p>Records Updated Last Time: <?php echo $recordsUpdated; ?></p>
    <form method="post">
        <button type="submit">Refresh Data</button>
    </form>
</body>
</html>
