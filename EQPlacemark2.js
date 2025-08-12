define(['./worldwind.min'],
    function(WorldWind) {
        "use strict";

        var placemarks = []; // 存储所有地震点的数组
        var animations = []; // 存储所有动画的数组
        var mouseEventInterval; // 用于持续触发鼠标移动的定时器

        function EQPlacemark(coordinates, coloring, magnitude, time, query) {
            this.originalScale = magnitude / 7; // 根据震级调整地震点大小
            var longitude = coordinates[0],
                latitude = coordinates[1],
                depth = coordinates[2];
            this.time = time;

            var placemarkAttributes = new WorldWind.PlacemarkAttributes(null);
            placemarkAttributes.imageSource = new WorldWind.ImageSource(renderCircle(depth, magnitude, 255, 255));
            placemarkAttributes.imageScale = magnitude >= 6 ? this.originalScale * 1.2 : this.originalScale;

            var placemark = new WorldWind.Placemark(new WorldWind.Position(latitude, longitude, -depth * 1000));
            placemark.altitudeMode = WorldWind.RELATIVE_TO_GROUND;
            placemark.attributes = placemarkAttributes;

            this.placemark = placemark;
            placemarks.push(this);
        }

        function renderCircle(depth, magnitude, red, green) {
            var scale = magnitude >= 6 ? 1.2 : 1;
            var canvas = document.createElement("canvas"),
                ctx2d = canvas.getContext("2d");
            var size = 20 * scale,
                c = size / 2 - 0.5,
                outerRadius = size / 2.2;

            canvas.width = size;
            canvas.height = size;

            ctx2d.fillStyle = magnitude >= 6 ? 'rgba(255, 0, 0, 0.55)' : `rgba(${red}, ${green}, 0, 0.55)`;
            ctx2d.beginPath();
            ctx2d.arc(c, c, outerRadius, 0, 2 * Math.PI, false);
            ctx2d.fill();

            return canvas;
        }

        function simulateMouseEvent() {
            var evt = new MouseEvent("mousemove", {
                view: window,
                bubbles: true,
                cancelable: true,
                clientX: 100,
                clientY: 100
            });
            document.dispatchEvent(evt); // 模拟鼠标移动以尝试触发重绘
        }

        function startMouseEventSimulation() {
            if (mouseEventInterval) clearInterval(mouseEventInterval);
            mouseEventInterval = setInterval(simulateMouseEvent, 1000 / 60); // 模拟每秒60次的鼠标移动
        }

        function stopMouseEventSimulation() {
            if (mouseEventInterval) clearInterval(mouseEventInterval);
        }

        function initiateTimeBasedColorTransition() {
            animations.forEach(animation => animation.kill());
            animations = [];

            placemarks.sort((a, b) => a.time - b.time); // 根据时间排序
            let maxTime = placemarks[placemarks.length - 1].time;
            let minTime = placemarks[0].time;
            let totalDuration = 10; // 总动画时间为10秒

            startMouseEventSimulation(); // 启动模拟鼠标移动

            placemarks.forEach((placemark, index) => {
                let timeFraction = (placemark.time - minTime) / (maxTime - minTime);
                let delayTime = totalDuration * timeFraction;

                let animation = gsap.to({ red: 255, green: 0 }, {
                    red: 255,
                    green: 255,
                    duration: 1,
                    delay: delayTime,
                    onUpdate: function () {
                        placemark.placemark.attributes.imageSource = new WorldWind.ImageSource(renderCircle(placemark.placemark.position.altitude / -1000, placemark.originalScale * 5, this.targets()[0].red, this.targets()[0].green));
                    }
                });

                animations.push(animation);
            });
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "s") {
                initiateTimeBasedColorTransition();
            } else if (event.key === "e") {
                stopAnimationsAndReset();
            }
        });

        function stopAnimationsAndReset() {
            animations.forEach(animation => animation.kill());
            animations = [];
            stopMouseEventSimulation(); // 停止模拟鼠标移动
            placemarks.forEach(placemark => {
                placemark.placemark.attributes.imageSource = new WorldWind.ImageSource(renderCircle(placemark.placemark.position.altitude / -1000, placemark.originalScale * 5, 255, 255));
            });
            if (typeof worldWindow !== 'undefined' && worldWindow.redraw) {
                worldWindow.redraw();
            }
        }

        return EQPlacemark;
    });
