(function (global) {
    'use strict';

    function isRtlNode(node) {
        if (!node) {
            return false;
        }
        var dir = node.getAttribute('dir') || '';
        if (dir !== '') {
            return dir.toLowerCase() === 'rtl';
        }
        return (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';
    }

    function setCanvasDirection(ctx, rtl) {
        if (ctx && 'direction' in ctx) {
            ctx.direction = rtl ? 'rtl' : 'ltr';
        }
    }

    function drawCenteredText(ctx, text, x, y, rtl, font, color) {
        if (!text) {
            return;
        }
        ctx.font = font;
        ctx.fillStyle = color;
        setCanvasDirection(ctx, rtl);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';
        ctx.fillText(text, x, y);
    }

    function drawFieldRows(ctx, area, width, startY) {
        var rtl = isRtlNode(area);
        var rows = area.querySelectorAll('dl dt');
        var vals = area.querySelectorAll('dl dd');
        var y = startY;
        var pad = 30;
        var labelX = rtl ? width - pad : pad;
        var valueX = rtl ? pad : width - pad;
        ctx.font = '13px Tahoma, Arial, sans-serif';
        for (var i = 0; i < rows.length; i += 1) {
            var label = rows[i].textContent || '';
            var value = vals[i] ? (vals[i].textContent || '') : '';
            setCanvasDirection(ctx, rtl);
            ctx.textAlign = rtl ? 'right' : 'left';
            ctx.fillStyle = '#6c757d';
            ctx.fillText(label, labelX, y);
            ctx.textAlign = rtl ? 'left' : 'right';
            ctx.fillStyle = '#212529';
            ctx.fillText(value, valueX, y);
            y += 20;
        }
        return y;
    }

    global.RatebBarcodeCanvas = {
        isRtlNode: isRtlNode,
        setCanvasDirection: setCanvasDirection,
        drawCenteredText: drawCenteredText,
        drawFieldRows: drawFieldRows
    };
})(typeof window !== 'undefined' ? window : this);
