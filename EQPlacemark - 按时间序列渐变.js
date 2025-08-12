define(['./worldwind.min'],
    function(WorldWind) {
        "use strict";

        var placemarks = []; // 存储所有地震点的数组
        var animations = []; // 存储所有动画的数组

        function EQPlacemark(coordinates, coloring, magnitude, time, query) {
            // 调整显示大小比例，使得较大震级显示更大
            this.originalScale = magnitude / 7;
            var longitude = coordinates[0],
                latitude = coordinates[1],
                depth = coordinates[2];
            this.time = time; // 添加时间属性，用于后续排序

            var placemarkAttributes = new WorldWind.PlacemarkAttributes(null);
            // 默认使用黄色
            placemarkAttributes.imageSource = new WorldWind.ImageSource(renderCircle(depth, 255, 255));
            placemarkAttributes.imageScale = this.originalScale;

            var placemark = new WorldWind.Placemark(new WorldWind.Position(latitude, longitude, -depth * 1000));
            placemark.altitudeMode = WorldWind.RELATIVE_TO_GROUND;
            placemark.attributes = placemarkAttributes;

            this.placemark = placemark;
            placemarks.push(this);
        }

        function renderCircle(depth, red, green) {
            var canvas = document.createElement("canvas"),
                ctx2d = canvas.getContext("2d");
            var size = 20, // 默认大小
                c = size / 2 - 0.5,
                outerRadius = size / 2.2;

            canvas.width = size;
            canvas.height = size;

            ctx2d.fillStyle = `rgba(${red}, ${green}, 0, 0.55)`; // 动态颜色调整
            ctx2d.beginPath();
            ctx2d.arc(c, c, outerRadius, 0, 2 * Math.PI, false);
            ctx2d.fill();

            return canvas;
        }

        function initiateDepthBasedColorTransition() {
            placemarks.forEach(placemark => {
                let depth = placemark.placemark.position.altitude / -1000;
                let greenValue = depth <= 50 ? Math.round(255 * (1 - (depth / 50))) : 255;
                placemark.placemark.attributes.imageSource = new WorldWind.ImageSource(renderCircle(depth, 255, greenValue));
            });
            if (typeof worldWindow !== 'undefined' && worldWindow.redraw) {
                worldWindow.redraw();
            }
        }

        function initiateTimeBasedColorTransition() {
            animations.forEach(animation => animation.kill());
            animations = [];

            // 确保地震点按时间排序
            placemarks.sort((a, b) => a.time - b.time);
            let maxTime = placemarks[placemarks.length - 1].time;
            let minTime = placemarks[0].time;
            let totalDuration = 10; // 总动画时间为10秒

            placemarks.forEach((placemark, index) => {
                let timeFraction = (placemark.time - minTime) / (maxTime - minTime);
                let delayTime = totalDuration * timeFraction;

                let animation = gsap.to({ red: 255, green: 0 }, {
                    red: 255,
                    green: 255,
                    duration: 1,
                    delay: delayTime,
                    onUpdate: function () {
                        placemark.placemark.attributes.imageSource = new WorldWind.ImageSource(renderCircle(placemark.placemark.position.altitude / -1000, this.targets()[0].red, this.targets()[0].green));
                    },
                    onComplete: () => {
                        if (typeof worldWindow !== 'undefined' && worldWindow.redraw) {
                            worldWindow.redraw();
                        }
                    }
                });

                animations.push(animation);
            });
        }

        function stopAnimationsAndReset() {
            animations.forEach(animation => animation.kill());
            animations = [];
            placemarks.forEach(placemark => {
                // 重置为黄色
                placemark.placemark.attributes.imageSource = new WorldWind.ImageSource(renderCircle(placemark.placemark.position.altitude / -1000, 255, 255));
            });
            if (typeof worldWindow !== 'undefined' && worldWindow.redraw) {
                worldWindow.redraw();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "s") {
                initiateTimeBasedColorTransition();
            } else if (event.key === "c") {
                initiateDepthBasedColorTransition();
            } else if (event.key === "e") {
                stopAnimationsAndReset();
            }
        });

        return EQPlacemark;
    });
