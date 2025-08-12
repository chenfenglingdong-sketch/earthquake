self.onmessage = function(e) {
    const data = e.data.slice();
    data.sort((a, b) => a.time - b.time);
    const BATCH_SIZE = 50;
    let i = 0;
    function sendBatch() {
        const batch = data.slice(i, i + BATCH_SIZE);
        i += BATCH_SIZE;
        self.postMessage({ batch, done: i >= data.length });
        if (i < data.length) {
            setTimeout(sendBatch, 0);
        }
    }
    sendBatch();
};
