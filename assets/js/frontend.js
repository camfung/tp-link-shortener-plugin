/**
 * Traffic Portal Link Shortener - Frontend JavaScript
 * Form handling, AJAX submission, and QR code generation
 */

(function($) {
    'use strict';

    // Main plugin object
    const TPLinkShortener = {
        // Elements
        $form: null,
        $submitBtn: null,
        $destinationInput: null,
        $customKeyInput: null,
        $loading: null,
        $errorMessage: null,
        $successMessage: null,
        $resultSection: null,
        $shortUrlOutput: null,
        $copyBtn: null,
        $qrSection: null,
        $qrContainer: null,
        $downloadQrBtn: null,
        $pasteBtn: null,
        $suggestBtn: null,
        $returningVisitorMessage: null,
        $validationMessage: null,

        // State
        qrCode: null,
        lastShortUrl: '',
        isValid: false,
        isReturningVisitor: false,
        countdownTimer: null,
        urlValidator: null,
        debouncedValidate: null,

        // Configuration
        config: {
            maxLength: 2000,
            minLength: 10,
            invalidChars: /[<>"{}|\\^`\[\]]/g,
            urlPattern: /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/i,
            tldPattern: /\.([a-z]{2,6})$/i,
            commonTlds: [
                'com', 'net', 'org', 'edu', 'gov', 'mil', 'int',
                'ca', 'uk', 'us', 'de', 'fr', 'it', 'es', 'nl',
                'au', 'jp', 'cn', 'in', 'br', 'ru', 'za',
                'io', 'co', 'app', 'dev', 'ai', 'tech', 'online'
            ],
        },

        /**
         * Initialize
         */
        init: function() {
            this.cacheElements();
            this.initializeURLValidator();
            this.bindEvents();
            this.checkClipboardSupport();
            this.checkReturningVisitor();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$form = $('#tp-shortener-form');
            this.$submitBtn = $('#tp-submit-btn');
            this.$destinationInput = $('#tp-destination');
            this.$customKeyInput = $('#tp-custom-key');
            this.$loading = $('#tp-loading');
            this.$errorMessage = $('#tp-error-message');
            this.$successMessage = $('#tp-success-message');
            this.$resultSection = $('#tp-result-section');
            this.$shortUrlOutput = $('#tp-short-url-output');
            this.$copyBtn = $('#tp-copy-btn');
            this.$qrSection = $('#tp-qr-section');
            this.$qrContainer = $('#tp-qr-code-container');
            this.$downloadQrBtn = $('#tp-download-qr-btn');
            this.$pasteBtn = $('#tp-paste-btn');
            this.$suggestBtn = $('#tp-suggest-btn');

            // Get validation message element (now exists in template)
            this.$validationMessage = $('#tp-url-validation-message');
            this.$tryItMessage = $('#tp-try-it-message');
        },

        /**
         * Initialize URL Validator
         */
        initializeURLValidator: function() {
            // Check if URLValidator class is available
            if (typeof URLValidator === 'undefined') {
                console.warn('URLValidator library not loaded. Online validation disabled.');
                return;
            }

            // Initialize URLValidator with current user authentication status
            this.urlValidator = new URLValidator({
                isUserRegistered: tpLinkShortener.isLoggedIn || false,
                proxyUrl: tpLinkShortener.ajaxUrl + '?action=tp_validate_url',
                timeout: 10000
            });

            // Create debounced validator function
            this.debouncedValidate = this.urlValidator.createDebouncedValidator(
                this.handleValidationResult.bind(this),
                800 // 800ms delay
            );
        },

        /**
         * Handle URL validation result
         */
        handleValidationResult: function(result, url) {
            // Update UI based on validation result
            if (result.isError) {
                this.isValid = false;
                this.$destinationInput.removeClass('is-valid').addClass('is-invalid');
                // Show error message in validation message area (not main error area)
                this.$validationMessage.html('<i class="fas fa-times-circle me-2"></i>' + result.message);
                this.$validationMessage.removeClass('warning-message success-message text-muted text-success text-warning').addClass('error-message text-danger');
                this.$validationMessage.show();
                // Disable submit button
                this.$submitBtn.prop('disabled', true);
                this.$submitBtn.addClass('disabled');
            } else if (result.isWarning) {
                // Warnings still allow submission but show warning message
                this.isValid = true;
                this.$destinationInput.removeClass('is-invalid').addClass('is-valid');
                // Show warning message in validation message area
                this.$validationMessage.html('<i class="fas fa-exclamation-triangle me-2"></i>' + result.message);
                this.$validationMessage.removeClass('error-message success-message text-muted text-success text-danger').addClass('warning-message text-warning');
                this.$validationMessage.show();
                // Enable submit button (warnings are allowed)
                this.$submitBtn.prop('disabled', false);
                this.$submitBtn.removeClass('disabled');
            } else {
                this.isValid = true;
                this.$destinationInput.removeClass('is-invalid').addClass('is-valid');
                // Show success message in validation message area
                this.$validationMessage.html('<i class="fas fa-check-circle me-2"></i>' + result.message);
                this.$validationMessage.removeClass('error-message warning-message text-muted text-warning text-danger').addClass('success-message text-success');
                this.$validationMessage.show();
                // Enable submit button
                this.$submitBtn.prop('disabled', false);
                this.$submitBtn.removeClass('disabled');
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            this.$form.on('submit', this.handleSubmit.bind(this));
            this.$copyBtn.on('click', this.copyToClipboard.bind(this));
            this.$downloadQrBtn.on('click', this.downloadQRCode.bind(this));

            // Validation events for destination input
            this.$destinationInput.on('input', this.handleInput.bind(this));
            this.$destinationInput.on('paste', this.handlePasteEvent.bind(this));
            this.$destinationInput.on('blur', this.handleBlur.bind(this));
            this.$destinationInput.on('focus', this.handleFocus.bind(this));

            // Paste button
            if (this.$pasteBtn.length) {
                this.$pasteBtn.on('click', this.handlePasteClick.bind(this));
            }

            // Suggest button (lightbulb)
            if (this.$suggestBtn.length) {
                this.$suggestBtn.on('click', this.handleSuggestClick.bind(this));
            }
        },

        /**
         * Handle form submission
         */
        handleSubmit: function(e) {
            e.preventDefault();

            // Get form data
            const destination = this.$destinationInput.val().trim();
            const customKey = this.$customKeyInput.val().trim();
            let uidFromStorage = null;

            try {
                const storedUid = window.localStorage.getItem('tpUid');
                if (storedUid && storedUid.trim() !== '') {
                    uidFromStorage = storedUid;
                }
            } catch (storageError) {
                // Unable to access localStorage (likely disabled or restricted)
                uidFromStorage = null;
            }

            // Validate URL format
            if (!this.validateUrl(destination)) {
                this.showError(tpLinkShortener.strings.invalidUrl);
                return;
            }

            // Check if online validation has been performed and passed
            if (!this.isValid) {
                this.showError('Please enter a valid and accessible URL. The URL validation failed.');
                this.$destinationInput.addClass('is-invalid');
                // Scroll to the error
                this.$destinationInput[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // Show loading
            this.setLoadingState(true);
            this.hideError();
            this.hideResult();

            // Prepare data
            const data = {
                action: 'tp_create_link',
                nonce: tpLinkShortener.nonce,
                destination: destination,
                custom_key: customKey
            };

            if (uidFromStorage !== null) {
                data.uid = uidFromStorage;
            }

            // Send AJAX request
            $.ajax({
                url: tpLinkShortener.ajaxUrl,
                type: 'POST',
                data: data,
                success: this.handleSuccess.bind(this),
                error: this.handleError.bind(this),
                complete: function() {
                    this.setLoadingState(false);
                }.bind(this)
            });
        },

        /**
         * Handle successful response
         */
        handleSuccess: function(response) {
            if (response.success && response.data) {
                const shortUrl = response.data.short_url;
                const key = response.data.key;
                const destination = response.data.destination;

                this.lastShortUrl = shortUrl;

                // Get the UID that was used
                let uid = null;
                try {
                    uid = window.localStorage.getItem('tpUid');
                } catch (error) {
                    // Ignore
                }

                // Save to local storage
                if (window.TPStorageService && window.TPStorageService.isAvailable()) {
                    window.TPStorageService.saveShortcodeData({
                        shortcode: key,
                        destination: destination,
                        expiresInHours: 24,
                        uid: uid
                    });
                }

                // Display result
                this.$shortUrlOutput.val(shortUrl);
                this.showResult();

                // Generate QR code
                this.generateQRCode(shortUrl);

                // Show "Try It Now" message for non-logged-in users
                if (this.$tryItMessage && this.$tryItMessage.length) {
                    this.$tryItMessage.removeClass('d-none');
                }

                // Reset form
                this.$destinationInput.val('');
                this.$customKeyInput.val('');
            } else {
                // Show debug error if available, otherwise show message
                const errorMessage = response.data.debug_error || response.data.message || tpLinkShortener.strings.error;
                this.showError(errorMessage);
            }
        },

        /**
         * Handle error response
         */
        handleError: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            this.showError(tpLinkShortener.strings.error);
        },

        /**
         * Validate URL format
         */
        validateUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Check clipboard API support
         */
        checkClipboardSupport: function() {
            if (this.$pasteBtn.length && (!navigator.clipboard || !navigator.clipboard.readText)) {
                this.$pasteBtn.prop('disabled', true);
                this.$pasteBtn.attr('title', 'Clipboard not supported in this browser');
            }
        },

        /**
         * Handle input event (real-time validation and sanitization)
         */
        handleInput: function(e) {
            let value = e.target.value;

            // Remove invalid characters in real-time
            const cleaned = value.replace(this.config.invalidChars, '');

            if (cleaned !== value) {
                this.$destinationInput.val(cleaned);
                value = cleaned;
            }

            // Check length
            if (value.length > this.config.maxLength) {
                this.$destinationInput.val(value.substring(0, this.config.maxLength));
                this.showError('URL too long (max 2000 characters)');
                return;
            }

            // Remove validation classes while typing
            this.$destinationInput.removeClass('is-invalid is-valid');

            // Hide error while typing
            if (value.length > 0) {
                this.hideError();
                // Hide validation message while typing
                if (this.$validationMessage) {
                    this.$validationMessage.hide();
                }
                // Re-enable submit button while typing (will be disabled again if validation fails)
                this.$submitBtn.prop('disabled', false);
                this.$submitBtn.removeClass('disabled');
            } else {
                // If empty, disable submit button
                this.$submitBtn.prop('disabled', true);
                this.$submitBtn.addClass('disabled');
            }

            // Trigger online validation if URLValidator is available
            if (this.urlValidator && this.debouncedValidate && value.trim().length > 0) {
                // Show validating message
                if (this.$validationMessage) {
                    this.$validationMessage.html('<i class="fas fa-spinner fa-spin me-2"></i>Validating URL...');
                    this.$validationMessage.removeClass('error-message warning-message success-message text-success text-warning text-danger');
                    this.$validationMessage.addClass('text-muted');
                    this.$validationMessage.show();
                }

                // Disable submit button while validating
                this.$submitBtn.prop('disabled', true);
                this.$submitBtn.addClass('disabled');

                // Note: We pass null for the message element because we handle
                // the styling ourselves in handleValidationResult
                this.debouncedValidate(
                    value.trim(),
                    null,  // Don't let URLValidator apply styles directly
                    null   // Don't let URLValidator apply message directly
                );
            }
        },

        /**
         * Handle paste event (from keyboard)
         */
        handlePasteEvent: function(e) {
            setTimeout(function() {
                const value = this.$destinationInput.val().trim();
                if (value) {
                    this.processUrl(value);
                }
            }.bind(this), 100);
        },

        /**
         * Handle paste button click
         */
        handlePasteClick: async function() {
            try {
                const text = await navigator.clipboard.readText();

                if (!text || text.trim() === '') {
                    this.showError('Clipboard is empty');
                    return;
                }

                this.$destinationInput.val(text.trim());
                this.processUrl(text.trim());

            } catch (err) {
                if (err.name === 'NotAllowedError') {
                    this.showError('Clipboard permission denied. Please allow clipboard access or paste manually.');
                } else {
                    this.showError('Unable to read clipboard. Please paste manually (Ctrl+V or Cmd+V).');
                }
                console.warn('Clipboard read failed:', err);
            }
        },

        /**
         * Handle suggest button click (lightbulb)
         */
        handleSuggestClick: function() {
            const randomKey = this.generateRandomKey();
            this.$customKeyInput.val(randomKey);
        },

        /**
         * Generate a random shortcode key
         */
        generateRandomKey: function() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            const length = 8;
            let result = '';

            for (let i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }

            return result;
        },

        /**
         * Handle blur event
         */
        handleBlur: function() {
            const value = this.$destinationInput.val().trim();

            if (value === '') {
                this.$destinationInput.removeClass('is-invalid is-valid');
                this.hideError();
                return;
            }

            this.processUrl(value);
        },

        /**
         * Handle focus event
         */
        handleFocus: function() {
            // Clear error on focus to reduce visual noise
            if (this.$destinationInput.val().trim() === '') {
                this.hideError();
                this.$destinationInput.removeClass('is-invalid');
            }
        },

        /**
         * Process URL (validate and auto-add protocol)
         */
        processUrl: function(url) {
            if (!url || url.length < this.config.minLength) {
                this.showError('URL is too short (min 10 characters)');
                this.setInvalid();
                return;
            }

            // Auto-add protocol if missing
            if (!this.hasProtocol(url)) {
                if (this.hasValidTld(url)) {
                    url = 'https://' + url;
                    this.$destinationInput.val(url);
                } else {
                    this.showError('Invalid URL format. Include protocol (https://) or valid domain.');
                    this.setInvalid();
                    return;
                }
            }

            // Validate URL
            if (this.validateUrl(url)) {
                this.setValid();
                this.hideError();
            } else {
                this.showError('Invalid URL format. Example: https://example.com/page');
                this.setInvalid();
            }
        },

        /**
         * Check if URL has protocol
         */
        hasProtocol: function(url) {
            return /^https?:\/\//i.test(url);
        },

        /**
         * Check if URL has valid TLD
         */
        hasValidTld: function(url) {
            const match = url.match(this.config.tldPattern);

            if (!match) {
                return false;
            }

            const tld = match[1].toLowerCase();
            return this.config.commonTlds.includes(tld);
        },

        /**
         * Set valid state
         */
        setValid: function() {
            this.isValid = true;
            this.$destinationInput.removeClass('is-invalid').addClass('is-valid');
        },

        /**
         * Set invalid state
         */
        setInvalid: function() {
            this.isValid = false;
            this.$destinationInput.removeClass('is-valid').addClass('is-invalid');
        },

        /**
         * Set loading state
         */
        setLoadingState: function(loading) {
            this.$form.attr('aria-busy', loading);
            this.$submitBtn.prop('disabled', loading);
            this.$submitBtn.toggleClass('disabled', loading);
            this.$loading.toggleClass('d-none', !loading);
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.$errorMessage
                .html('<i class="fas fa-exclamation-circle me-2"></i>' + message)
                .removeClass('d-none');
        },

        /**
         * Hide error message
         */
        hideError: function() {
            this.$errorMessage.addClass('d-none').empty();
        },

        /**
         * Show success message
         */
        showSuccessMessage: function() {
            this.$successMessage.removeClass('d-none');
        },

        /**
         * Hide success message
         */
        hideSuccessMessage: function() {
            this.$successMessage.addClass('d-none');
        },

        /**
         * Show result section
         */
        showResult: function() {
            this.showSuccessMessage();
            this.$resultSection.removeClass('d-none');
        },

        /**
         * Hide result section
         */
        hideResult: function() {
            this.hideSuccessMessage();
            this.$resultSection.addClass('d-none');
            this.hideQRSection();
            // Hide "Try It Now" message
            if (this.$tryItMessage && this.$tryItMessage.length) {
                this.$tryItMessage.addClass('d-none');
            }
        },

        /**
         * Show QR section with animation
         */
        showQRSection: function() {
            this.$qrSection.removeClass('d-none');
            // Trigger reflow to ensure animation plays
            this.$qrSection[0].offsetHeight;
            this.$qrSection.addClass('tp-slide-down');
        },

        /**
         * Hide QR section
         */
        hideQRSection: function() {
            this.$qrSection.removeClass('tp-slide-down');
            // Wait for animation to complete before hiding
            setTimeout(function() {
                this.$qrSection.addClass('d-none');
            }.bind(this), 500);
        },

        /**
         * Generate QR Code
         */
        generateQRCode: function(url) {
            // Clear existing QR code
            this.$qrContainer.empty();

            // Create new container div
            const qrDiv = $('<div>').attr('id', 'qr-code-' + Date.now());
            this.$qrContainer.append(qrDiv);

            // Add qr=1 query parameter to the URL
            const separator = url.includes('?') ? '&' : '?';
            const qrUrl = url + separator + 'qr=1';

            // Generate QR code
            try {
                this.qrCode = new QRCode(qrDiv[0], {
                    text: qrUrl,
                    width: 200,
                    height: 200,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });

                // Show QR section with animation after QR code is generated
                setTimeout(function() {
                    this.showQRSection();
                }.bind(this), 100);
            } catch (e) {
                console.error('QR Code generation failed:', e);
            }
        },

        /**
         * Copy short URL to clipboard
         */
        copyToClipboard: function() {
            this.$shortUrlOutput.select();
            document.execCommand('copy');

            // Visual feedback
            const originalText = this.$copyBtn.html();
            const copiedLabel = tpLinkShortener.strings.copied || 'Copied!';
            this.$copyBtn.html('<i class="fas fa-check me-2"></i>' + copiedLabel);

            setTimeout(function() {
                this.$copyBtn.html(originalText);
            }.bind(this), 2000);
        },

        /**
         * Download QR Code
         */
        downloadQRCode: function() {
            if (!this.qrCode) {
                return;
            }

            // Get the canvas
            const canvas = this.$qrContainer.find('canvas')[0];
            if (!canvas) {
                return;
            }

            // Convert to blob and download
            canvas.toBlob(function(blob) {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'qr-code-' + Date.now() + '.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        },

        /**
         * Check for returning visitor
         */
        checkReturningVisitor: function() {
            // Check if storage service is available
            if (!window.TPStorageService || !window.TPStorageService.isAvailable()) {
                return;
            }

            // Get stored data
            const storedData = window.TPStorageService.getShortcodeData();
            if (!storedData) {
                return;
            }

            // Check if expired
            if (storedData.isExpired) {
                // Clear expired data and show normal form
                window.TPStorageService.clearShortcodeData();
                return;
            }

            // Mark as returning visitor
            this.isReturningVisitor = true;

            // Show the stored link (no API validation needed)
            this.showStoredLink(storedData);
        },

        /**
         * Show stored link with countdown
         */
        showStoredLink: function(storedData) {
            const domain = tpLinkShortener.domain || 'tp.local';
            const shortUrl = 'https://' + domain + '/' + storedData.shortcode;

            // Pre-fill form inputs with stored data
            this.$destinationInput.val(storedData.destination);
            if (this.$customKeyInput.length) {
                this.$customKeyInput.val(storedData.shortcode);
            }

            // Display the short URL
            this.$shortUrlOutput.val(shortUrl);
            this.lastShortUrl = shortUrl;

            // Show result section WITHOUT success message (for returning visitors)
            this.$resultSection.removeClass('d-none');

            // Generate QR code
            this.generateQRCode(shortUrl);

            // Only disable the form for non-logged-in users (trial users)
            if (!tpLinkShortener.isLoggedIn) {
                this.disableForm();

                // Show returning visitor message with countdown for trial users
                this.showReturningVisitorMessage(
                    '<i class="fas fa-clock me-2"></i>' +
                    'Your trial key is active! Time remaining: <span id="tp-countdown" class="me-1"></span> ' +
                    '<a href="#" id="tp-register-link">Register to keep it active</a>.'
                );

                // Start countdown
                this.startCountdown();
            }
        },

        /**
         * Show returning visitor message
         */
        showReturningVisitorMessage: function(message) {
            if (!this.$returningVisitorMessage || !this.$returningVisitorMessage.length) {
                // Create message element if it doesn't exist
                this.$returningVisitorMessage = $('<div>')
                    .attr('id', 'tp-returning-visitor-message')
                    .addClass('alert alert-info d-flex align-items-center mb-4')
                    .insertBefore(this.$form.find('.tp-form-group').first());
            }

            this.$returningVisitorMessage.html(message).removeClass('d-none');
        },

        /**
         * Hide returning visitor message
         */
        hideReturningVisitorMessage: function() {
            if (this.$returningVisitorMessage) {
                this.$returningVisitorMessage.addClass('d-none');
            }
        },

        /**
         * Start countdown timer
         */
        startCountdown: function() {
            const updateCountdown = function() {
                const formatted = window.TPStorageService.getFormattedTimeRemaining();
                if (formatted) {
                    $('#tp-countdown').text(formatted);
                } else {
                    // Expired during countdown
                    this.stopCountdown();
                    this.clearStoredKey();
                    location.reload();
                }
            }.bind(this);

            // Update immediately
            updateCountdown();

            // Update every second
            this.countdownTimer = setInterval(updateCountdown, 1000);
        },

        /**
         * Stop countdown timer
         */
        stopCountdown: function() {
            if (this.countdownTimer) {
                clearInterval(this.countdownTimer);
                this.countdownTimer = null;
            }
        },

        /**
         * Disable form inputs
         */
        disableForm: function() {
            this.$destinationInput.prop('disabled', true);
            if (this.$customKeyInput.length) {
                this.$customKeyInput.prop('disabled', true);
            }
            this.$submitBtn.prop('disabled', true);
            this.$submitBtn.addClass('disabled');
            if (this.$pasteBtn.length) {
                this.$pasteBtn.prop('disabled', true);
            }
        },

        /**
         * Enable form inputs
         */
        enableForm: function() {
            this.$destinationInput.prop('disabled', false);
            if (this.$customKeyInput.length) {
                this.$customKeyInput.prop('disabled', false);
            }
            this.$submitBtn.prop('disabled', false);
            this.$submitBtn.removeClass('disabled');
            if (this.$pasteBtn.length) {
                this.$pasteBtn.prop('disabled', false);
            }
        },

        /**
         * Clear stored key and reset form
         */
        clearStoredKey: function() {
            this.stopCountdown();
            window.TPStorageService.clearShortcodeData();
            this.hideReturningVisitorMessage();
            this.hideResult();
            this.enableForm();
            this.isReturningVisitor = false;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#tp-shortener-form').length) {
            TPLinkShortener.init();
        }
    });

})(jQuery);
