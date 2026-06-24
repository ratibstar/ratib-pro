(function () {
    'use strict';

    if (window.__RATEB_CONSOLE_QUIET) {
        return;
    }
    window.__RATEB_CONSOLE_QUIET = true;

    if (window.RATEB_DEBUG === true) {
        return;
    }

    var noop = function () {};
    console.log = noop;
    console.info = noop;
    console.debug = noop;
    console.warn = noop;
    console.error = noop;
    console.trace = noop;
    console.table = noop;
    console.group = noop;
    console.groupCollapsed = noop;
    console.groupEnd = noop;
})();
