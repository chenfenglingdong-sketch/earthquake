define(['./worldwind.min'],
    function (WorldWind) {

        "use strict";

        var chinaLayerLoaded = false;  // 用于追踪中国图层加载状态
        var chinaLayer;  // 用于存储中国图层的引用

        function createShapeConfigurationCallback(color, outlineWidth) {
            return function (geometry, properties) {
                var configuration = {};
                configuration.attributes = new WorldWind.ShapeAttributes(null);
                configuration.attributes.drawOutline = true;
                configuration.attributes.outlineColor = color;
                configuration.attributes.interiorColor = new WorldWind.Color(color.red, color.green, color.blue, 0.5);
                configuration.attributes.outlineWidth = outlineWidth;
                configuration.extrude = true;  // 可以根据需要开启或关闭
                configuration.extrudeHeight = properties.Depth ? properties.Depth * 1000 : 0;  // 假设深度值存在并合适

                return configuration;
            };
        }

        function loadGeoJSON(file, layer, color, outlineWidth) {
            var geoJSONParser = new WorldWind.GeoJSONParser(file);
            var callback = createShapeConfigurationCallback(color, outlineWidth);
            geoJSONParser.load(null, callback, layer, function () {
                console.log(file + " loaded successfully.");
            });
        }

        function TectonicPlateLayer() {
            var plateBoundariesLayer = new WorldWind.RenderableLayer("Tectonic Plates");

            loadGeoJSON("./shuju/plate_boundaries-world.json", plateBoundariesLayer, new WorldWind.Color(1, 0, 0, 1.0), 2);
            loadGeoJSON("./shuju/cn-fau.json", plateBoundariesLayer, new WorldWind.Color(0.5, 0, 0.5, 1.0), 4);
		//	loadGeoJSON("./shuju/world.geojson", plateBoundariesLayer, new WorldWind.Color(0.5, 0, 0.7, 1.0), 3);

            // 监听键盘事件
            window.addEventListener('keydown', function(event) {
                console.log("Key pressed: " + event.key);  // 输出按键信息
                if (event.key === 'V' || event.key === 'v') {
                    if (!chinaLayerLoaded) {
                        console.log("Loading China layer...");
                        chinaLayer = new WorldWind.RenderableLayer("China Layer");
                        loadGeoJSON("./shuju/china.json", chinaLayer, new WorldWind.Color(1, 1, 0, 1.0), 2);
                        plateBoundariesLayer.addRenderable(chinaLayer);
                        chinaLayerLoaded = true;
                    }
                    chinaLayer.enabled = true;  // 显示中国地质图层
                } else if (event.key === 'D' || event.key === 'd') {
                    if (chinaLayer) {
                        chinaLayer.enabled = false;  // 隐藏中国地质图层
                    }
                }
            });

            return plateBoundariesLayer;
        }

        return TectonicPlateLayer;
    });
