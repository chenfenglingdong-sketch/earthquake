define(['./worldwind.min'],
    function(WorldWind) {
        "use strict";

        const placemarkInfos = []; // 使用弱引用存储地震点及其时间
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
            // 仅存储动画所需的信息：时间和对地震点实例的弱引用
            placemarkInfos.push({
                ref: new WeakRef(this),
                time: this.time,
            });
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

            // 收集仍在内存中的地震点
            const active = placemarkInfos
                .map((info, index) => {
                    const obj = info.ref.deref();
                    return obj ? { index, time: info.time, obj } : null;
                })
                .filter(Boolean);

            const infoMap = new Map(active.map(item => [item.index, item.obj]));
            const sorter = new Worker('placemarkSortWorker.js');
            let sortedCount = 0;

            sorter.onmessage = function (e) {
                const { batch, done } = e.data;
                batch.forEach((entry, batchIndex) => {
                    const placemark = infoMap.get(entry.index);
                    if (!placemark) return;
                    const colorProxy = { g: 0 };
                    const animation = gsap.to(colorProxy, {
                        g: 255,
                        duration: 1,
                        delay: (sortedCount + batchIndex) * (20 / active.length),
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
                sortedCount += batch.length;
                if (done) {
                    sorter.terminate();
                }
            };

            sorter.postMessage(active.map(({ index, time }) => ({ index, time })));
        }

        function stopAnimationsAndReset() {
            animations.forEach(animation => animation.kill());
            animations = [];

            // 重置所有仍存在的地震点
            placemarkInfos.forEach(info => {
                const placemark = info.ref.deref();
                if (placemark) {
                    placemark.placemark.attributes.imageSource =
                        new WorldWind.ImageSource(renderCircle(placemark.placemark.position.altitude / -1000));
                }
            });

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
