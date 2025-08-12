<?php
// 数据库参数
$host = 'localhost'; // 或者你的数据库地址
$dbname = 'earthquakes';
$username = 'earthquakes';
$password = 'DZtzTwXpKYP1MBMk';

$statusMessages = []; // 用于存储状态信息的数组

// 尝试连接数据库
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $statusMessages[] = "数据库连接成功。";
} catch (PDOException $e) {
    $statusMessages[] = "连接错误: " . $e->getMessage();
    die("错误: 无法连接到数据库。" . $e->getMessage());
}

// 从USGS API获取地震数据
function fetchEarthquakeData($startTime, $endTime, $minMagnitude, $maxMagnitude, $minDepth, $maxDepth, $limit, $orderBy) {
    global $statusMessages;
    $url = "https://earthquake.usgs.gov/fdsnws/event/1/query?format=geojson"
        . "&starttime=" . urlencode($startTime)
        . "&endtime=" . urlencode($endTime)
        . "&minmagnitude=" . urlencode($minMagnitude)
        . "&maxmagnitude=" . urlencode($maxMagnitude)
        . "&mindepth=" . urlencode($minDepth)
        . "&maxdepth=" . urlencode($maxDepth)
        . "&limit=" . urlencode($limit)
        . "&orderby=" . urlencode($orderBy);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);

    if (!$output) {
        $statusMessages[] = "API调用失败，没有获取到数据。";
        return null;
    } else {
        $statusMessages[] = "API调用成功，数据已获取。";
        return json_decode($output, true);
    }
}

// 将数据导入到相应年份的表中
function importData($pdo, $earthquakes) {
    global $statusMessages;
    $currentYear = date('Y');
    $tableName = "earthquakes_" . $currentYear; // 根据当前年份确定表名

    try {
        $stmt = $pdo->prepare("INSERT INTO $tableName (event_id, location, magnitude, depth, event_time) VALUES (?, ?, ?, ?, ?)");
        foreach ($earthquakes['features'] as $feature) {
            $props = $feature['properties'];
            $stmt->execute([
                $feature['id'],
                $props['place'],
                $props['mag'],
                $props['geometry']['coordinates'][2],
                date('Y-m-d H:i:s', $props['time'] / 1000)
            ]);
        }
        $statusMessages[] = "数据导入成功。";
        return true;
    } catch (PDOException $e) {
        $statusMessages[] = "导入数据失败: " . $e->getMessage();
        return false;
    }
}

// 更新地震数据
function fetchAndUpdateEarthquakes($pdo) {
    global $statusMessages;
    $endTime = date('Y-m-d H:i:s'); // 当前时间
    $startTime = date('Y-m-d H:i:s', strtotime("-30 days")); // 从现在起30天前
    $minMagnitude = 0.1; // 最小震级
    $maxMagnitude = 10.0; // 最大震级
    $minDepth = 0; // 最浅深度
    $maxDepth = 1000; // 最深深度
    $limit = 100000; // 获取的记录限制
    $orderBy = 'time'; // 排序方式

    $earthquakes = fetchEarthquakeData($startTime, $endTime, $minMagnitude, $maxMagnitude, $minDepth, $maxDepth, $limit, $orderBy);
    $recordsUpdated = isset($earthquakes['features']) ? count($earthquakes['features']) : 0;
    if ($recordsUpdated > 0) {
        $success = importData($pdo, $earthquakes);
        if (!$success) {
            $statusMessages[] = "数据导入数据库时出错。";
        }
    } else {
        $statusMessages[] = "没有新数据可导入。";
    }

    return $recordsUpdated;
}

// PHP变量传递到JavaScript
echo "<script>var statusMessages = " . json_encode($statusMessages) . ";</script>";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>地震数据更新</title>
</head>
<body>
    <h2>地震数据更新</h2>
    <form method="post">
        <button type="submit">刷新数据</button>
    </form>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // 显示所有状态消息
            statusMessages.forEach(function(message) {
                var p = document.createElement("p");
                p.textContent = message;
                document.body.appendChild(p);
            });
        });
    </script>
</body>
</html>
