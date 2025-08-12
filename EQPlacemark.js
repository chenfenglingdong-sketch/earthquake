define(['./worldwind.min'],
    function(WorldWind) {
        "use strict";

        var placemarks = []; // 存储所有地震点的数组
        var animations = []; // 存储所有动画的数组

        function EQPlacemark(coordinates, coloring, magnitude, time, query) {
            this.originalScale = magnitude / 1e1;
            var longitude = coordinates[0],
                latitude = coordinates[1],
                depth = coordinates[2];
            this.time = time; // 添加时间属性，用于后续排序

            var placemarkAttributes = new WorldWind.PlacemarkAttributes(null);
            placemarkAttributes.imageSource = new WorldWind.ImageSource(renderCircle(depth));
            placemarkAttributes.imageScale = this.originalScale;
            
            var placemark = new WorldWind.Placemark(new WorldWind.Position(latitude, longitude, -depth * 1000));
            placemark.altitudeMode = WorldWind.RELATIVE_TO_GROUND;
            placemark.attributes = placemarkAttributes;

            this.placemark = placemark;
            placemarks.push(this); // 将地震点添加到数组中
        }

        function renderCircle(depth) {
            var canvas = document.createElement("canvas"),
                ctx2d = canvas.getContext("2d");
            var size = 20, // 假定一个默认大小
                c = size / 2 - 0.5,
                outerRadius = size / 2.2;

            canvas.width = size;
            canvas.height = size;

            // 深度小于等于20km时，颜色从白到红渐变，大于20km时为红色
            var red = 255;
            var greenBlue = depth <= 50 ? Math.round(255 * (1 - (depth / 50))) : 0;
            ctx2d.fillStyle = `rgba(${red}, ${greenBlue}, ${greenBlue}, 0.55)`;
            ctx2d.beginPath();
            ctx2d.arc(c, c, outerRadius, 0, 2 * Math.PI, false);
            ctx2d.fill();

            return canvas;
        }

        function initiateColorTransitionWithGSAP() {
            animations.forEach(animation => animation.kill());
            animations = [];

            placemarks.sort((a, b) => a.time - b.time);

            placemarks.forEach((placemark, index) => {
                let colorProxy = { g: 0 };

                var animation = gsap.to(colorProxy, {
                    g: 255,
                    duration: 1,
                    delay: index * (20 / placemarks.length),
                    onUpdate: function () {
                        placemark.placemark.attributes.imageSource =
                            new WorldWind.ImageSource(renderCircle(placemark.placemark.position.altitude / -1000));
                        if (typeof worldWindow !== 'undefined' && worldWindow.redraw) {
                            worldWindow.redraw();
                        }
                    },
                    repeat: 3,
                    yoyo: true,
                });

                animations.push(animation);
            });
        }

        function stopAnimationsAndReset() {
            animations.forEach(animation => {
                animation.kill();
                animation.targets().forEach(target => {
                    target.g = 0;
                    placemark.placemark.attributes.imageSource =
                        new WorldWind.ImageSource(renderCircle(placemark.placemark.position.altitude / -1000));
                });
            });
            animations = [];

            if (typeof worldWindow !== 'undefined' && worldWindow.redraw) {
                worldWindow.redraw();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "s") {
                initiateColorTransitionWithGSAP();
            } else if (event.key === "e") {
                stopAnimationsAndReset();
            }
        });

        return EQPlacemark;
    });
