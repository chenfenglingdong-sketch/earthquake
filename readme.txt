Control.js   控制图层的显示，调用bing3纬地形数据

Draw.js  实现选区功能，并调用其他js统计选区内的地震数据

EQPlacemark.js  实现地震点的显示效果

LayerManager.js  地球图层显示控制，默认隐藏

worldwind.min.js   worldwind开源地球的核心库文件

TectonicPlateLayer.js  地球板块边界图层代码

gsap.min.js  动画样式库代码辅助EQPlacemark.js

USGS.js   确定数据读取路径，实现初始数据的读取

AnnotationController.js   程序中起着控制用户界面（UI）交互的作用。具体来说，这个模块负责处理各种 UI 组件（如滑块、按钮、下拉菜单等）的事件和数据，并根据用户的操作更新 WorldWind 地图的显示和行为。

Circle.js  绘图中实现圆形的绘制并作对应范围的计算

Cylinder.js   构建三维空间中的圆柱形状，并对其进行视觉表示。

DataGrid.js  用于处理和解析网格形式的地理数据并可视化这些数据的工具。读取经纬度和高度（或深度）数据，然后生成可用于三维渲染的网格。

EQPolygon.js 以多边形的形式展示特定地理位置的数据。这样的功能在地理信息系统（GIS）或地球科学可视化中非常有用，特别是当需要在地图上标记特定区域或展示与特定地点相关的数据时。


Point.js  创建了一个可视化的点标记，它可以用来表示地图上的特定地理位置。

Rectangle.js  创建矩形并显示范围内数据

ui_controls.js  用于控制和管理网页标签（tabs）的 JavaScript 函数，设计用于动态显示和隐藏网页上的不同内容区域。

WorldPoint.js
处理和更新点的三维地理坐标（经度、纬度、高度）和二维屏幕坐标（X, Y）



