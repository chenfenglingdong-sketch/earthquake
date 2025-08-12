地球脉动-地震监测与预测系统
这是一个基于WorldWind引擎上构建的地震可视化应用程序
全球100年内的地震数据，存储至本地读取，并调用第三方地图的3维高程地形数据。

地球脉动是了解地球板块最新运动趋势及相互作用的可视化工具。以3D形式可视化了全球范围内近100年的全部地震数据。 本程序旨在动态呈现区域板块运动趋势，海量和详细的数据为区域地震密度分布和概率预测提供支持。
## 定时更新 USGS 数据

使用 `serv/update_from_usgs.php` 脚本可以从 USGS 接口增量获取地震数据并写入数据库。
建议使用 cron 定时执行该脚本以保持数据最新，例如：

```cron
0 * * * * /usr/bin/php /path/to/earthquake/serv/update_from_usgs.php >> /var/log/earthquake_update.log 2>&1
```

