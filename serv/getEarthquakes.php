<?php
header('Content-Type: application/json');

// 数据库连接信息
$host = 'localhost';
$dbname = 'earthquakes';
$username = 'earthquakes';
$password = 'DZtzTwXpKYP1MBMk';

// 创建数据库连接
$conn = new mysqli($host, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 获取并解析日期范围和其他查询参数
$startDate = $_GET['starttime'];
$endDate = $_GET['endtime'];
$minMagnitude = isset($_GET['minmagnitude']) ? $_GET['minmagnitude'] : 0;
$maxMagnitude = isset($_GET['maxmagnitude']) ? $_GET['maxmagnitude'] : 10;
$minDepth = isset($_GET['mindepth']) ? $_GET['mindepth'] : 0;
$maxDepth = isset($_GET['maxdepth']) ? $_GET['maxdepth'] : 1000;
$minLatitude = isset($_GET['minlatitude']) ? $_GET['minlatitude'] : -90;
$maxLatitude = isset($_GET['maxlatitude']) ? $_GET['maxlatitude'] : 90;
$minLongitude = isset($_GET['minlongitude']) ? $_GET['minlongitude'] : -180;
$maxLongitude = isset($_GET['maxlongitude']) ? $_GET['maxlongitude'] : 180;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

$startYear = date('Y', strtotime($startDate));
$endYear = date('Y', strtotime($endDate));

$queries = [];
for ($year = $startYear; $year <= $endYear; $year++) {
    $tableName = "earthquakes_" . $year;
    $queries[] = "SELECT * FROM $tableName
            WHERE time >= '$startDate' AND time <= '$endDate'
            AND mag BETWEEN $minMagnitude AND $maxMagnitude
            AND depth BETWEEN $minDepth AND $maxDepth
            AND latitude BETWEEN $minLatitude AND $maxLatitude
            AND longitude BETWEEN $minLongitude AND $maxLongitude";
}

$sql = implode(' UNION ALL ', $queries) . ' ORDER BY time LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$features = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'mag' => floatval($row['mag']),
                'place' => $row['place'],
                'time' => strtotime($row['time']) * 1000,
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    floatval($row['longitude']),
                    floatval($row['latitude']),
                    floatval($row['depth'])
                ],
            ],
        ];
    }
}

$geojson = [
    'type' => 'FeatureCollection',
    'features' => $features,
];

echo json_encode($geojson);

$conn->close();
?>
