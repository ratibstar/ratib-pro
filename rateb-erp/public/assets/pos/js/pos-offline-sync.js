(function () {
    'use strict';
    window.RatebPosOffline = {
        queueDepth: 0,
        push: function () { return Promise.resolve({ scaffold: true }); },
        sync: function () { return Promise.resolve({ scaffold: true }); }
    };
})();
