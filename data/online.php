<?php
session_start();

// 数据库配置
$pdo = connectToDatabase('localhost', 'earthquakes', 'earthquakes', 'DZtzTwXpKYP1MBMk');

// 地震数据更新逻辑
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = fetchAndUpdateEarthquakes($pdo);
    $_SESSION['lastUpdateTime'] = date('Y-m-d H:i:s');
    $_SESSION['recordsFetched'] = $result['fetched'];
    $_SESSION['recordsImported'] = $result['imported'];
}

$updateInfo = [
    'last_update' => $_SESSION['lastUpdateTime'] ?? '未更新',
    'records_fetched' => $_SESSION['recordsFetched'] ?? 0,
    'records_imported' => $_SESSION['recordsImported'] ?? 0
];

// 连接数据库函数
function connectToDatabase($host, $dbname, $username, $password) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("连接错误: " . $e->getMessage());
        die("错误: 无法连接到数据库。" . $e->getMessage());
    }
}

// 从 USGS API 获取地震数据
function fetchEarthquakeData($startTime, $endTime, $minMagnitude, $maxMagnitude, $minDepth, $maxDepth, $limit, $orderBy) {
    $url = "https://earthquake.usgs.gov/fdsnws/event/1/query?format=geojson"
         . "&starttime=" . urlencode($startTime)
         . "&endtime=" . urlencode($endTime)
         . "&minmagnitude=" . urlencode($minMagnitude)
         . "&maxmagnitude=" . urlencode($maxMagnitude)
         . "&mindepth=" . urlencode($minDepth)
         . "&maxdepth=" . urlencode($maxDepth)
         . "&limit=" . urlencode($limit)
         . "&orderby=" . urlencode($orderBy);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);

    return json_decode($output, true);
}

// 导入数据到数据库
function importData($pdo, $earthquakes) {
    $currentYear = date('Y');
    $tableName = "earthquakes_" . $currentYear;
    ensureTableExists($pdo, $tableName);

    $importedCount = 0;
    try {
        $stmt = $pdo->prepare("INSERT INTO $tableName (time, latitude, longitude, depth, mag, magType, nst, gap, dmin, rms, net, id, updated, place, type, horizontalError, depthError, magError, magNst, status, locationSource, magSource) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($earthquakes['features'] as $feature) {
            $props = $feature['properties'];
            $coords = $feature['geometry']['coordinates'];
            $stmt->execute([
                date('Y-m-d H:i:s', $props['time'] / 1000), // time
                $coords[1], // latitude
                $coords[0], // longitude
                $coords[2], // depth
                $props['mag'], // magnitude
                $props['magType'], // magnitude type
                $props['nst'], // number of stations
                $props['gap'], // gap
                $props['dmin'], // distance to nearest station
                $props['rms'], // root mean square
                $props['net'], // network
                $feature['id'], // id
                date('Y-m-d H:i:s', $props['updated'] / 1000), // updated
                $props['place'], // place
                $props['type'], // type
                $props['horizontalError'], // horizontal error
                $props['depthError'], // depth error
                $props['magError'], // magnitude error
                $props['magNst'], // magnitude station count
                $props['status'], // status
                $props['sources'], // location source
                $props['magSource'] // magnitude source
            ]);
            $importedCount++;
        }
    } catch (PDOException $e) {
        error_log("导入地震数据失败: " . $e->getMessage());
    }
    return $importedCount;
}

// 确保数据表存在
function ensureTableExists($pdo, $tableName) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS $tableName (
        time datetime NOT NULL,
        latitude decimal(10,7) NOT NULL,
        longitude decimal(10,7) NOT NULL,
        depth decimal(6,2) NOT NULL,
        mag decimal(3,2) NOT NULL,
        magType varchar(4) DEFAULT NULL,
        nst int(11) DEFAULT NULL,
        gap decimal(5,2) DEFAULT NULL,
        dmin decimal(6,3) DEFAULT NULL,
        rms decimal(3,2) DEFAULT NULL,
        net varchar(2) DEFAULT NULL,
        id varchar(15) NOT NULL,
        updated datetime NOT NULL,
        place varchar(255) DEFAULT NULL,
        type varchar(50) DEFAULT NULL,
        horizontalError decimal(4,2) DEFAULT NULL,
        depthError decimal(4,2) DEFAULT NULL,
        magError decimal(4,2) DEFAULT NULL,
        magNst int(11) DEFAULT NULL,
        status varchar(10) DEFAULT NULL,
        locationSource varchar(2) DEFAULT NULL,
        magSource varchar(2) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// 更新地震数据
function fetchAndUpdateEarthquakes($pdo) {
    $earthquakes = fetchEarthquakeData(date('Y-m-d H:i:s', strtotime("-30 days")), date('Y-m-d H:i:s'), 1.0, 10.0, 0, 700, 100, 'time');
    $fetchedCount = count($earthquakes['features']);
    $importedCount = importData($pdo, $earthquakes);

    return ['fetched' => $fetchedCount, 'imported' => $importedCount];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>地震数据更新</title>
</head>
<body>
    <h2>地震数据更新</h2>
    <p>上次更新时间: <?php echo $updateInfo['last_update']; ?></p>
    <p>上次获取记录数: <?php echo $updateInfo['records_fetched']; ?></p>
    <p>上次导入记录数: <?php echo $updateInfo['records_imported']; ?></p>
    <form method="post">
        <button type="submit">刷新数据</button>
    </form>
</body>
</html>
