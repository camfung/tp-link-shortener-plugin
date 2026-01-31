/**
 * QR Code Utilities
 * Shared QR code generation and handling functions
 */

(function(window, $) {
    'use strict';

    /**
     * QR Code Utility Module
     */
    const TPQRUtils = {
        /**
         * Generate a QR code in the specified container
         * @param {jQuery|HTMLElement} container - The container element
         * @param {string} url - The URL to encode
         * @param {Object} options - Optional configuration
         * @returns {QRCode|null} The QRCode instance or null on failure
         */
        generate: function(container, url, options) {
            const $container = $(container);
            const defaults = {
                width: 200,
                height: 200,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
                appendQrParam: true
            };

            const settings = $.extend({}, defaults, options);

            // Clear existing QR code
            $container.empty();

            // Create new container div
            const qrDiv = $('<div>').attr('id', 'qr-code-' + Date.now());
            $container.append(qrDiv);

            // Add qr=1 query parameter to the URL if enabled
            let qrUrl = url;
            if (settings.appendQrParam) {
                const separator = url.includes('?') ? '&' : '?';
                qrUrl = url + separator + 'qr=1';
            }

            // Generate QR code
            try {
                const qrCode = new QRCode(qrDiv[0], {
                    text: qrUrl,
                    width: settings.width,
                    height: settings.height,
                    colorDark: settings.colorDark,
                    colorLight: settings.colorLight,
                    correctLevel: settings.correctLevel
                });

                // Remove the title attribute that QRCode.js adds to the container div
                // (it shows the URL as a tooltip which we don't want)
                setTimeout(function() {
                    qrDiv.removeAttr('title');
                }, 100);

                return qrCode;
            } catch (e) {
                console.error('QR Code generation failed:', e);
                return null;
            }
        },

        /**
         * Download QR code as PNG from a container
         * @param {jQuery|HTMLElement} container - The container with the QR code canvas
         * @param {string} filename - Optional filename (without extension)
         * @returns {boolean} Success status
         */
        download: function(container, filename) {
            const $container = $(container);
            const canvas = $container.find('canvas')[0];

            if (!canvas) {
                console.error('QR Code canvas not found');
                return false;
            }

            const downloadFilename = filename || ('qr-code-' + Date.now());

            canvas.toBlob(function(blob) {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = downloadFilename + '.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            return true;
        },

        /**
         * Copy QR code to clipboard
         * @param {jQuery|HTMLElement} container - The container with the QR code canvas
         * @param {Function} onSuccess - Callback on successful copy
         * @param {Function} onError - Callback on error
         * @returns {boolean} Whether the operation was initiated
         */
        copyToClipboard: function(container, onSuccess, onError) {
            const $container = $(container);
            const canvas = $container.find('canvas')[0];

            if (!canvas) {
                console.error('QR Code canvas not found');
                if (onError) onError(new Error('Canvas not found'));
                return false;
            }

            canvas.toBlob(function(blob) {
                const item = new ClipboardItem({ 'image/png': blob });
                navigator.clipboard.write([item]).then(function() {
                    if (onSuccess) onSuccess();
                }).catch(function(err) {
                    console.error('Failed to copy QR code:', err);
                    if (onError) onError(err);
                });
            });

            return true;
        },

        /**
         * Get the canvas element from a QR container
         * @param {jQuery|HTMLElement} container - The container with the QR code
         * @returns {HTMLCanvasElement|null} The canvas element or null
         */
        getCanvas: function(container) {
            const $container = $(container);
            return $container.find('canvas')[0] || null;
        },

        /**
         * Check if QRCode library is available
         * @returns {boolean}
         */
        isAvailable: function() {
            return typeof QRCode !== 'undefined';
        }
    };

    // Export to window
    window.TPQRUtils = TPQRUtils;

})(window, jQuery);
