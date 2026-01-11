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
        $resultSection: null,
        $shortUrlOutput: null,
        $copyBtn: null,
        $qrSection: null,
        $qrContainer: null,
        $pasteBtn: null,
        $suggestBtn: null,
        $returningVisitorMessage: null,
        $validationMessage: null,
        $saveLinkReminder: null,
        snackbarTimer: null,

        // State
        qrCode: null,
        lastShortUrl: '',
        isValid: false,
        isReturningVisitor: false,
        countdownTimer: null,
        expiryTimer: null,
        urlValidator: null,
        debouncedValidate: null,
        formMode: 'create', // 'create' or 'update'
        fpPromise: null, // FingerprintJS promise

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
        init: async function() {
            console.log('=== TP LINK SHORTENER INITIALIZATION ===');
            console.log('User logged in:', tpAjax.isLoggedIn);
            console.log('Domain:', tpAjax.domain);
            console.log('AJAX URL:', tpAjax.ajaxUrl);

            this.cacheElements();
            console.log('DOM elements cached');

            this.initializeURLValidator();
            console.log('URL validator initialized');

            await this.initializeFingerprintJS();
            console.log('FingerprintJS initialized');

            this.bindEvents();
            console.log('Events bound');

            this.checkClipboardSupport();
            console.log('Clipboard support checked');
            // this.checkReturningVisitor(); // Disabled: using fingerprint-based detection only

            // Search for existing links by fingerprint for anonymous users
            if (!tpAjax.isLoggedIn) {
                console.log('User is anonymous - will search for existing links by fingerprint after FP loads...');
                // Wait for fingerprint to be ready before searching
                this.waitForFingerprintThenSearch();
            } else {
                console.log('User is logged in - skipping fingerprint search');
            }

            console.log('=== TP LINK SHORTENER INITIALIZATION COMPLETE ===');
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$form = $('#tp-shortener-form');
            this.$submitBtn = $('#tp-submit-btn');
            this.$destinationInput = $('#tp-destination');
            this.$customKeyInput = $('#tp-custom-key');
            this.$customKeyGroup = $('.tp-custom-key-group');
            this.$loading = $('#tp-loading');
            this.$errorMessage = $('#tp-error-message');
            this.$resultSection = $('#tp-result-section');
            this.$shortUrlOutput = $('#tp-short-url-output');
            this.$copyBtn = $('#tp-copy-btn');
            this.$qrSection = $('#tp-qr-section');
            this.$qrContainer = $('#tp-qr-code-container');
            this.$pasteBtn = $('#tp-paste-btn');
            this.$suggestBtn = $('#tp-suggest-btn');
            this.$saveLinkReminder = $('#tp-save-link-reminder');

            // Get validation message element (now exists in template)
            this.$validationMessage = $('#tp-url-validation-message');
            this.$tryItMessage = $('#tp-try-it-message');

            // Update mode elements
            this.$updateModeMessage = $('#tp-update-mode-message');
            this.$submitText = $('#tp-submit-text');
            this.$submitIcon = $('#tp-submit-icon');
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
                isUserRegistered: tpAjax.isLoggedIn || false,
                proxyUrl: tpAjax.ajaxUrl + '?action=tp_validate_url',
                timeout: 10000
            });

            // Create debounced validator function
            this.debouncedValidate = this.urlValidator.createDebouncedValidator(
                this.handleValidationResult.bind(this),
                800 // 800ms delay
            );
        },

        /**
         * Initialize FingerprintJS
         */
        initializeFingerprintJS: async function() {
            console.log('=== FINGERPRINT JS INITIALIZATION ===');
            // Check if FingerprintJS is loaded
            try {
                await this.ensureFingerprintScript();

                if (typeof FingerprintJS === 'undefined') {
                    console.warn('FingerprintJS still undefined after CDN load. Fingerprinting disabled.');
                    console.log('=== FINGERPRINT JS INITIALIZATION FAILED ===');
                    return;
                }

                console.log('FingerprintJS library loaded successfully');
                // Initialize FingerprintJS
                this.fpPromise = FingerprintJS.load();
                console.log('FingerprintJS.load() called - promise created');
                console.log('fpPromise is now:', !!this.fpPromise);
                console.log('=== FINGERPRINT JS INITIALIZATION COMPLETE ===');
            } catch (err) {
                console.warn('Failed to load FingerprintJS from CDN:', err);
                console.log('=== FINGERPRINT JS INITIALIZATION FAILED ===');
            }
        },

        /**
         * Ensure FingerprintJS script is present by loading from CDN if missing
         */
        ensureFingerprintScript: function() {
            // If already present, resolve immediately
            if (typeof FingerprintJS !== 'undefined') {
                return Promise.resolve();
            }

            // Prevent multiple injections
            if (this.fpScriptPromise) {
                return this.fpScriptPromise;
            }

            console.log('FingerprintJS not found - injecting self-hosted script...');
            this.fpScriptPromise = new Promise(function(resolve, reject) {
                const primarySrc = (window.tpAjax && tpAjax.fingerprintUrl) ?
                    tpAjax.fingerprintUrl :
                    'https://openfpcdn.io/fingerprintjs/v4/iife.min.js';
                const fallbackSrc = 'https://openfpcdn.io/fingerprintjs/v4/iife.min.js';

                const script = document.createElement('script');
                script.src = primarySrc;
                script.async = true;
                script.onload = function() {
                    console.log('FingerprintJS script loaded from', primarySrc);
                    resolve();
                };
                script.onerror = function(event) {
                    console.warn('Primary FingerprintJS script failed, trying fallback...', event);
                    // Try fallback CDN once
                    const fallbackScript = document.createElement('script');
                    fallbackScript.src = fallbackSrc;
                    fallbackScript.async = true;
                    fallbackScript.onload = function() {
                        console.log('FingerprintJS fallback script loaded from', fallbackSrc);
                        resolve();
                    };
                    fallbackScript.onerror = function(fallbackEvent) {
                        reject(new Error('FingerprintJS scripts failed to load. Primary error: ' + (event && event.message ? event.message : 'unknown') + '. Fallback error: ' + (fallbackEvent && fallbackEvent.message ? fallbackEvent.message : 'unknown error')));
                    };
                    document.head.appendChild(fallbackScript);
                };
                document.head.appendChild(script);
            });

            return this.fpScriptPromise;
        },

        /**
         * Get browser fingerprint
         */
        getFingerprint: async function() {
            console.log('=== GET FINGERPRINT START ===');
            if (!this.fpPromise) {
                console.warn('FingerprintJS not initialized - promise is null');
                console.log('=== GET FINGERPRINT END (NOT INITIALIZED) ===');
                return null;
            }

            try {
                console.log('Awaiting FingerprintJS agent...');
                const fp = await this.fpPromise;
                console.log('FingerprintJS agent loaded:', fp);

                console.log('Getting fingerprint result...');
                const result = await fp.get();
                console.log('Full fingerprint result:', result);
                console.log('Visitor ID:', result.visitorId);
                console.log('Confidence score:', result.confidence);
                console.log('Components count:', result.components ? Object.keys(result.components).length : 0);
                console.log('=== GET FINGERPRINT END (SUCCESS) ===');
                return result.visitorId;
            } catch (error) {
                console.error('Error getting fingerprint:', error);
                console.error('Error stack:', error.stack);
                console.log('=== GET FINGERPRINT END (ERROR) ===');
                return null;
            }
        },

        /**
         * Handle URL validation result
         */
        handleValidationResult: function(result, url) {
            console.log('🎯 [UI-CALLBACK] === VALIDATION RESULT RECEIVED ===');
            console.log('🎯 [UI-CALLBACK] URL:', url);
            console.log('🎯 [UI-CALLBACK] Result:', result);
            console.log('🎯 [UI-CALLBACK] isError:', result.isError);
            console.log('🎯 [UI-CALLBACK] isWarning:', result.isWarning);
            console.log('🎯 [UI-CALLBACK] Message:', result.message);

            // Check if protocol was updated (HTTPS -> HTTP fallback)
            if (result.protocolUpdated && result.updatedUrl) {
                // Update the input field with the HTTP URL
                this.$destinationInput.val(result.updatedUrl);
                console.log('🎯 [UI-CALLBACK] URL protocol updated from HTTPS to HTTP:', result.updatedUrl);
            }

            // Update UI based on validation result
            if (result.isError) {
                console.log('🎯 [UI-CALLBACK] ❌ Showing ERROR UI for:', url);
                this.isValid = false;
                this.$destinationInput.removeClass('is-valid').addClass('is-invalid');
                // Show error message in validation message area (not main error area)
                this.$validationMessage.html('<i class="fas fa-times-circle me-2"></i>' + result.message);
                this.$validationMessage.removeClass('warning-message success-message text-muted text-success text-warning').addClass('error-message text-danger');
                this.$validationMessage.show();
                // Disable submit button
                this.$submitBtn.prop('disabled', true);
                this.$submitBtn.addClass('disabled');
                // Hide custom key group on error
                if (this.$customKeyGroup && this.$customKeyGroup.length) {
                    this.$customKeyGroup.slideUp(300);
                }
            } else if (result.isWarning) {
                console.log('🎯 [UI-CALLBACK] ⚠️  Showing WARNING UI for:', url);
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
                // Show custom key group on warning (still valid for submission)
                if (this.$customKeyGroup && this.$customKeyGroup.length) {
                    this.$customKeyGroup.slideDown(300);
                }

                // After validation succeeds with warning, get Gemini shortcode suggestion
                // Only fetch suggestion in create mode (not update mode)
                if (this.formMode === 'create') {
                    console.log('🎯 [UI-CALLBACK] Fetching shortcode suggestion (warning case)');
                    this.fetchShortcodeSuggestion(url);
                } else {
                    console.log('🎯 [UI-CALLBACK] Skipping suggestion - in update mode');
                }
            } else {
                console.log('🎯 [UI-CALLBACK] ✅ Showing SUCCESS UI for:', url);
                this.isValid = true;
                this.$destinationInput.removeClass('is-invalid').addClass('is-valid');
                // Show success message in validation message area
                this.$validationMessage.html('<i class="fas fa-check-circle me-2"></i>' + result.message);
                this.$validationMessage.removeClass('error-message warning-message text-muted text-warning text-danger').addClass('success-message text-success');
                this.$validationMessage.show();
                // Enable submit button
                this.$submitBtn.prop('disabled', false);
                this.$submitBtn.removeClass('disabled');
                // Show custom key group on success
                if (this.$customKeyGroup && this.$customKeyGroup.length) {
                    this.$customKeyGroup.slideDown(300);
                }

                // After validation succeeds, get Gemini shortcode suggestion
                // Only fetch suggestion in create mode (not update mode)
                if (this.formMode === 'create') {
                    console.log('🎯 [UI-CALLBACK] Fetching shortcode suggestion');
                    this.fetchShortcodeSuggestion(url);
                } else {
                    console.log('🎯 [UI-CALLBACK] Skipping suggestion - in update mode');
                }
            }
            console.log('🎯 [UI-CALLBACK] === VALIDATION RESULT HANDLED ===');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            this.$form.on('submit', this.handleSubmit.bind(this));
            this.$copyBtn.on('click', this.copyToClipboard.bind(this));
            this.$qrContainer.on('click', this.downloadQRCode.bind(this));

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

            // Route based on form mode
            if (this.formMode === 'update') {
                this.submitUpdate();
            } else {
                this.submitCreate();
            }
        },

        /**
         * Submit create link request
         */
        submitCreate: async function() {
            console.log('=== SUBMIT CREATE LINK START ===');

            // Get form data
            const destination = this.$destinationInput.val().trim();
            const customKey = this.$customKeyInput.val().trim();
            let uidFromStorage = null;

            console.log('Form data:', { destination, customKey });
            console.log('User logged in:', tpAjax.isLoggedIn);

            try {
                const storedUid = window.localStorage.getItem('tpUid');
                if (storedUid && storedUid.trim() !== '') {
                    uidFromStorage = storedUid;
                }
                console.log('UID from storage:', uidFromStorage);
            } catch (storageError) {
                // Unable to access localStorage (likely disabled or restricted)
                console.warn('localStorage not available:', storageError);
                uidFromStorage = null;
            }

            // Validate URL format
            if (!this.validateUrl(destination)) {
                console.error('URL validation failed:', destination);
                this.showError(tpAjax.strings.invalidUrl);
                return;
            }

            // Check if online validation has been performed and passed
            if (!this.isValid) {
                console.error('Online validation not passed');
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
            this.showSaveLinkReminder();

            // Get fingerprint for anonymous users
            let fingerprint = null;
            if (!tpAjax.isLoggedIn) {
                console.log('User is anonymous - generating fingerprint...');
                fingerprint = await this.getFingerprint();
                console.log('Fingerprint generated:', fingerprint);
                console.log('Fingerprint type:', typeof fingerprint);
                console.log('Fingerprint length:', fingerprint ? fingerprint.length : 0);
            } else {
                console.log('User is logged in - skipping fingerprint generation');
            }

            // Prepare data
            const data = {
                action: 'tp_create_link',
                nonce: tpAjax.nonce,
                destination: destination,
                custom_key: customKey
            };

            if (uidFromStorage !== null) {
                data.uid = uidFromStorage;
                console.log('Added UID to request data:', uidFromStorage);
            }

            if (fingerprint !== null) {
                data.fingerprint = fingerprint;
                console.log('Added fingerprint to request data:', fingerprint);
            }

            console.log('Complete AJAX request data:', JSON.stringify(data, null, 2));

            // Send AJAX request
            console.log('Sending AJAX request to:', tpAjax.ajaxUrl);
            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    console.log('AJAX request being sent...');
                },
                success: function(response) {
                    console.log('AJAX response received:', response);
                    console.log('=== SUBMIT CREATE LINK END ===');
                    this.handleCreateSuccess(response);
                }.bind(this),
                error: function(xhr, status, error) {
                    console.error('AJAX error:', { xhr, status, error });
                    console.error('Response text:', xhr.responseText);
                    console.log('=== SUBMIT CREATE LINK END (ERROR) ===');
                    this.handleError(xhr, status, error);
                }.bind(this),
                complete: function() {
                    console.log('AJAX request complete');
                    this.setLoadingState(false);
                }.bind(this)
            });
        },

        /**
         * Submit update link request
         */
        submitUpdate: function() {
            console.log('TP Update: submitUpdate called');
            console.log('TP Update: currentRecord:', this.currentRecord);

            if (!this.currentRecord || !this.currentRecord.mid) {
                console.error('TP Update: No current record or mid', this.currentRecord);
                this.showSnackbar('No link to update.', 'error');
                return;
            }

            const newDestination = this.$destinationInput.val().trim();

            if (!newDestination) {
                console.error('TP Update: Empty destination');
                this.showSnackbar('Please enter a destination URL.', 'error');
                return;
            }

            // Get the tpKey from the custom key input (Magic Keyword box)
            // If empty, use the current key from the record
            let tpKey = this.$customKeyInput.val().trim();
            if (!tpKey) {
                tpKey = this.currentRecord.tpKey || this.currentRecord.key;
            }

            if (!tpKey) {
                console.error('TP Update: No key found', this.currentRecord);
                this.showSnackbar('Unable to find link key.', 'error');
                return;
            }

            console.log('TP Update: currentRecord.mid:', this.currentRecord.mid);
            console.log('TP Update: currentRecord.domain:', this.currentRecord.domain);
            console.log('TP Update: tpKey:', tpKey);
            console.log('TP Update: All currentRecord keys:', Object.keys(this.currentRecord));

            // Store old values to detect changes
            const oldDestination = this.currentRecord.destination;
            const oldTpKey = this.currentRecord.tpKey || this.currentRecord.key;

            const updateData = {
                action: 'tp_update_link',
                nonce: tpAjax.nonce,
                mid: this.currentRecord.mid,
                destination: newDestination,
                domain: this.currentRecord.domain,
                tpKey: tpKey
            };

            console.log('TP Update: Sending request with data:', updateData);
            console.log('TP Update: Data as JSON:', JSON.stringify(updateData, null, 2));
            console.log('TP Update: tpKey value type:', typeof tpKey, 'value:', tpKey);

            // Show loading state (spinner + disable button)
            this.setLoadingState(true);

            const self = this;

            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: updateData,
                beforeSend: function(xhr, settings) {
                    console.log('TP Update: AJAX beforeSend - data being sent:', settings.data);
                },
                success: function(response) {
                    console.log('TP Update: Success response:', response);

                    if (response.success) {
                        self.showSnackbar('Link updated successfully!', 'success');

                        // Update the stored record with new values
                        self.currentRecord.destination = newDestination;
                        self.currentRecord.tpKey = tpKey;

                        // Check if destination changed - regenerate screenshot (if enabled)
                        if (oldDestination !== newDestination && tpAjax.enableScreenshot) {
                            console.log('TP Update: Destination changed, regenerating screenshot');
                            self.captureScreenshot(newDestination);
                        }

                        // Check if tpKey changed - update short URL and regenerate QR code (if enabled)
                        if (oldTpKey !== tpKey) {
                            console.log('TP Update: Key changed, updating short URL and QR code');
                            const newShortUrl = 'https://' + self.currentRecord.domain + '/' + tpKey;
                            self.$shortUrlOutput.attr('href', newShortUrl).text(newShortUrl);
                            if (tpAjax.enableQRCode) {
                                self.generateQRCode(newShortUrl);
                            }
                        }
                    } else {
                        console.error('TP Update: Server returned success=false', response);
                        const errorMsg = response.data ? response.data.message : 'Failed to update link.';
                        const debugInfo = response.data ? JSON.stringify(response.data, null, 2) : '';
                        console.error('TP Update: Debug info:', debugInfo);
                        self.showSnackbar(errorMsg, 'error');
                        // Show debug info in console
                        if (response.data && response.data.debug) {
                            console.error('TP Update: Debug details:', response.data.debug);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('TP Update: AJAX error', {xhr: xhr, status: status, error: error});
                    console.error('TP Update: Response text:', xhr.responseText);

                    let errorMessage = 'An error occurred while updating the link.';
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData && errorData.data && errorData.data.message) {
                            errorMessage = errorData.data.message;
                        }
                        console.error('TP Update: Parsed error data:', errorData);
                    } catch(e) {
                        console.error('TP Update: Could not parse error response');
                    }

                    self.showSnackbar(errorMessage, 'error');
                },
                complete: function() {
                    // Hide loading state (re-enable button)
                    self.setLoadingState(false);
                }
            });
        },

        /**
         * Handle successful create response
         */
        handleCreateSuccess: function(response) {
            if (response.success && response.data) {
                const shortUrl = response.data.short_url;
                const key = response.data.key;
                const destination = response.data.destination;
                const mid = response.data.mid;
                const domain = response.data.domain;

                this.lastShortUrl = shortUrl;

                // Store current record for updates
                this.currentRecord = {
                    mid: mid,
                    domain: domain,
                    destination: destination,
                    key: key,
                    shortUrl: shortUrl
                };

                // Get the UID that was used
                let uid = null;
                try {
                    uid = window.localStorage.getItem('tpUid');
                } catch (error) {
                    // Ignore
                }

                // Disabled: using IP-based detection only, not localStorage
                // if (window.TPStorageService && window.TPStorageService.isAvailable()) {
                //     window.TPStorageService.saveShortcodeData({
                //         shortcode: key,
                //         destination: destination,
                //         expiresInHours: 24,
                //         uid: uid
                //     });
                //     console.log('TP Link Shortener: Shortcode data saved to localStorage');
                // }

                // Display result
                this.$shortUrlOutput.attr('href', shortUrl).text(shortUrl);
                this.showResult();

                // Generate QR code (if enabled)
                if (tpAjax.enableQRCode) {
                    this.generateQRCode(shortUrl);
                }

                // Capture screenshot of destination URL (if enabled)
                if (tpAjax.enableScreenshot) {
                    this.captureScreenshot(destination);
                }

                // Show "Try It Now" message for non-logged-in users
                if (this.$tryItMessage && this.$tryItMessage.length) {
                    this.$tryItMessage.removeClass('d-none');
                }

                // Start expiry countdown for non-logged-in users (if enabled)
                if (!tpAjax.isLoggedIn && tpAjax.enableExpiryTimer) {
                    this.startExpiryCountdown();
                }

                // Switch to update mode (this will populate the custom key input with the current key)
                this.switchToUpdateMode();
            } else {
                // Check if this is a rate limit error (429)
                if (response.data && response.data.error_type === 'rate_limit') {
                    this.showRateLimitError(response.data.message);
                } else {
                    this.showError(response.data.message || tpAjax.strings.error);
                }
            }
        },

        /**
         * Handle error response
         */
        handleError: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            this.showError(tpAjax.strings.error);
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
         * Handle input event (keystroke-specific UX, then delegates to processUrl)
         */
        handleInput: function(e) {
            const value = e.target.value;

            // Keystroke-specific UX: Remove validation classes while typing
            this.$destinationInput.removeClass('is-invalid is-valid');

            // Keystroke-specific UX: Hide error/validation messages while typing
            if (value.length > 0) {
                this.hideError();
                // Hide validation message while typing
                if (this.$validationMessage) {
                    this.$validationMessage.hide();
                }
                // Re-enable submit button optimistically while typing (processUrl will disable if invalid)
                this.$submitBtn.prop('disabled', false);
                this.$submitBtn.removeClass('disabled');
            }

            // Delegate all validation logic to processUrl
            this.processUrl(value);
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
            console.log('[PASTE DEBUG] handlePasteClick called');
            try {
                console.log('[PASTE DEBUG] Attempting to read clipboard...');
                const text = await navigator.clipboard.readText();
                console.log('[PASTE DEBUG] Clipboard read successful, text:', text);

                if (!text || text.trim() === '') {
                    console.log('[PASTE DEBUG] Clipboard is empty');
                    this.showError('Clipboard is empty');
                    return;
                }

                console.log('[PASTE DEBUG] Setting input value to:', text.trim());
                this.$destinationInput.val(text.trim());

                console.log('[PASTE DEBUG] Calling processUrl with:', text.trim());
                this.processUrl(text.trim());
                console.log('[PASTE DEBUG] processUrl completed');

            } catch (err) {
                console.log('[PASTE DEBUG] Clipboard read failed with error:', err);
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
            const destination = this.$destinationInput.val().trim();

            // If URL is valid, fetch AI suggestion; otherwise generate random
            if (this.isValid && destination) {
                this.fetchShortcodeSuggestion(destination);
            } else {
                const randomKey = this.generateRandomKey();
                this.$customKeyInput.val(randomKey);
            }
        },

        /**
         * Fetch AI-powered shortcode suggestion from backend
         */
        fetchShortcodeSuggestion: function(destination) {
            console.log('=== FETCH SHORTCODE SUGGESTION START ===');
            console.log('Destination URL:', destination);

            const self = this;

            // Check if custom key input exists
            if (!this.$customKeyInput || !this.$customKeyInput.length) {
                console.log('ERROR: Custom key input element not found');
                console.log('=== FETCH SHORTCODE SUGGESTION END ===');
                return;
            }

            const currentValue = this.$customKeyInput.val().trim();
            console.log('Current custom key value (will be replaced):', currentValue);
            console.log('Proceeding with suggestion fetch...');

            // Show loading state in custom key input
            const originalPlaceholder = this.$customKeyInput.attr('placeholder');
            console.log('Original placeholder:', originalPlaceholder);

            // Clear the input first so placeholder is visible
            this.$customKeyInput.val('');
            this.$customKeyInput.attr('placeholder', 'Generating suggestion...');
            this.$customKeyInput.prop('disabled', true);

            // Disable submit button while generating suggestion
            this.$submitBtn.prop('disabled', true);
            this.$submitBtn.addClass('disabled');
            console.log('Submit button disabled while generating suggestion');

            // Send AJAX request for suggestion
            console.log('Sending AJAX request to:', tpAjax.ajaxUrl);
            console.log('Request data:', {
                action: 'tp_suggest_shortcode',
                nonce: tpAjax.nonce,
                destination: destination
            });

            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_suggest_shortcode',
                    nonce: tpAjax.nonce,
                    destination: destination
                },
                beforeSend: function() {
                    console.log('AJAX request being sent...');
                },
                success: function(response) {
                    console.log('AJAX success response:', response);

                    if (response.success && response.data && response.data.shortcode) {
                        // Auto-populate the custom key input with suggestion
                        console.log('Setting custom key to:', response.data.shortcode);
                        self.$customKeyInput.val(response.data.shortcode);

                        // Log source for debugging
                        if (response.data.source === 'gemini') {
                            console.log('TP Link Shortener: Gemini suggestion:', response.data.shortcode);
                        } else {
                            console.log('TP Link Shortener: Random suggestion:', response.data.shortcode);
                        }
                    } else {
                        // Fallback to client-side random generation
                        console.log('TP Link Shortener: Suggestion failed, using random key');
                        console.log('Response data:', response.data);
                        const randomKey = self.generateRandomKey();
                        console.log('Generated random key:', randomKey);
                        self.$customKeyInput.val(randomKey);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('TP Link Shortener: Suggestion AJAX error');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('XHR:', xhr);
                    console.error('Response text:', xhr.responseText);

                    // Fallback to client-side random generation
                    const randomKey = self.generateRandomKey();
                    console.log('Using fallback random key:', randomKey);
                    self.$customKeyInput.val(randomKey);
                },
                complete: function() {
                    console.log('AJAX request complete');
                    // Restore placeholder and enable input
                    self.$customKeyInput.attr('placeholder', originalPlaceholder);
                    self.$customKeyInput.prop('disabled', false);

                    // Re-enable submit button after suggestion is complete
                    self.$submitBtn.prop('disabled', false);
                    self.$submitBtn.removeClass('disabled');
                    console.log('Submit button re-enabled after suggestion complete');

                    console.log('=== FETCH SHORTCODE SUGGESTION END ===');
                }
            });
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
         * Shared validation logic used by typing, paste, and blur events
         */
        processUrl: function(value) {
            console.log('[PASTE DEBUG] processUrl called with:', value);

            // Remove invalid characters
            const cleaned = value.replace(this.config.invalidChars, '');
            if (cleaned !== value) {
                this.$destinationInput.val(cleaned);
                value = cleaned;
            }

            // Attempt to auto-add protocol when we can identify a valid TLD
            if (value.trim().length > 0) {
                const normalizedValue = this.ensureProtocol(value.trim());
                if (normalizedValue !== value.trim()) {
                    value = normalizedValue;
                    this.$destinationInput.val(normalizedValue);
                } else {
                    value = value.trim();
                }
            }

            // Check max length
            if (value.length > this.config.maxLength) {
                this.$destinationInput.val(value.substring(0, this.config.maxLength));
                this.showError('URL too long (max 2000 characters)');
                return;
            }

            // Handle empty input
            if (value.length === 0) {
                this.$destinationInput.removeClass('is-invalid is-valid');
                this.hideError();
                this.$submitBtn.prop('disabled', true);
                this.$submitBtn.addClass('disabled');
                // Hide custom key group when input is empty
                if (this.$customKeyGroup && this.$customKeyGroup.length) {
                    this.$customKeyGroup.slideUp(300);
                }
                return;
            }

            // Trigger online validation if URLValidator is available
            if (this.urlValidator && this.debouncedValidate && value.trim().length > 0) {
                console.log('Triggering URL validation for:', value.trim());
                console.log('URLValidator exists:', !!this.urlValidator);
                console.log('debouncedValidate exists:', !!this.debouncedValidate);

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
                console.log('Calling debouncedValidate...');
                this.debouncedValidate(
                    value.trim(),
                    null,  // Don't let URLValidator apply styles directly
                    null   // Don't let URLValidator apply message directly
                );
            } else {
                console.log('Skipping validation - urlValidator:', !!this.urlValidator, 'debouncedValidate:', !!this.debouncedValidate, 'valueLength:', value.trim().length);
            }

            console.log('[PASTE DEBUG] processUrl completed');
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
         * Ensure the URL has a protocol when a valid TLD is detected
         */
        ensureProtocol: function(url) {
            if (!url || this.hasProtocol(url)) {
                return url;
            }

            if (this.hasValidTld(url)) {
                return 'https://' + url;
            }

            return url;
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
         * Show rate limit error with registration prompt
         */
        showRateLimitError: function(message) {
            // Enhanced error message with call to action
            const errorHtml = `
                <div class="d-flex align-items-start gap-3 mb-3">
                    <i class="fas fa-exclamation-triangle fs-5 mt-1 flex-shrink-0"></i>
                    <strong>${message}</strong>
                </div>
                <div class="ms-0 ms-md-5">
                    <p class="mb-2 fw-semibold">Create an account to get:</p>
                    <ul class="mb-0 ps-3">
                        <li>Unlimited short URLs</li>
                        <li>Analytics and tracking</li>
                        <li>Custom domains</li>
                        <li>URL management</li>
                    </ul>
                </div>
            `;

            this.$errorMessage
                .html(errorHtml)
                .removeClass('d-none');
        },

        /**
         * Hide error message
         */
        hideError: function() {
            this.$errorMessage.addClass('d-none').empty();
        },

        /**
         * Switch to update mode
         */
        switchToUpdateMode: function() {
            this.formMode = 'update';

            const updateLabel = 'Update Link';
            this.$submitText.text(updateLabel);
            this.$submitBtn.attr('aria-label', updateLabel);
            this.$submitBtn.attr('title', updateLabel);
            this.$submitIcon.removeClass('fa-link').addClass('fa-edit');

            this.$updateModeMessage.removeClass('d-none');

            // Ensure the destination input is enabled in update mode
            this.$destinationInput.prop('disabled', false);

            // Show custom key group and populate with current key
            if (this.$customKeyGroup && this.$customKeyGroup.length) {
                const currentKey = this.currentRecord.tpKey || this.currentRecord.key;
                if (currentKey) {
                    this.$customKeyInput.val(currentKey);
                }
                this.$customKeyGroup.slideDown(300);
            }

            // Trigger validation for pre-filled URL
            if (this.urlValidator && this.debouncedValidate) {
                const currentUrl = this.$destinationInput.val().trim();
                if (currentUrl) {
                    this.debouncedValidate(currentUrl, null, null);
                }
            }
        },

        /**
         * Switch to create mode
         */
        switchToCreateMode: function() {
            this.formMode = 'create';

            const createLabel = 'Save the link and it never expires';
            this.$submitText.text(createLabel);
            this.$submitBtn.attr('aria-label', createLabel);
            this.$submitBtn.attr('title', createLabel);
            this.$submitIcon.removeClass('fa-edit').addClass('fa-link');

            this.$updateModeMessage.addClass('d-none');

            this.$destinationInput.val('');
            this.$customKeyInput.val('');
            this.$destinationInput.removeClass('is-valid is-invalid');

            this.hideResult();

            if (this.$validationMessage) {
                this.$validationMessage.hide();
            }

            this.currentRecord = null;
            this.isValid = false;
            this.$submitBtn.prop('disabled', true);
        },

        /**
         * Show snackbar notification
         */
        showSnackbar: function(message, type, duration) {
            // Handle parameters - second param could be type or duration
            if (typeof type === 'number') {
                duration = type;
                type = 'success';
            } else {
                type = type || 'success';
                duration = duration || 3000; // Default 3 seconds
            }

            // Remove any existing snackbar
            $('.tp-snackbar').remove();

            // Clear any existing timer
            if (this.snackbarTimer) {
                clearTimeout(this.snackbarTimer);
                this.snackbarTimer = null;
            }

            // Determine icon and class based on type
            let icon = 'fas fa-check-circle';
            let typeClass = 'tp-snackbar-success';

            if (type === 'error') {
                icon = 'fas fa-exclamation-circle';
                typeClass = 'tp-snackbar-error';
            } else if (type === 'warning') {
                icon = 'fas fa-exclamation-triangle';
                typeClass = 'tp-snackbar-warning';
            } else if (type === 'info') {
                icon = 'fas fa-info-circle';
                typeClass = 'tp-snackbar-info';
            }

            // Create snackbar element
            const $snackbar = $('<div>')
                .addClass('tp-snackbar ' + typeClass)
                .html('<i class="' + icon + '"></i><span>' + message + '</span>')
                .appendTo('body');

            // Trigger reflow to ensure animation plays
            $snackbar[0].offsetHeight;

            // Show snackbar
            $snackbar.addClass('tp-snackbar-show');

            // Auto-hide after duration
            this.snackbarTimer = setTimeout(function() {
                $snackbar.removeClass('tp-snackbar-show');
                // Remove from DOM after animation completes
                setTimeout(function() {
                    $snackbar.remove();
                }, 400); // Match transition duration
            }, duration);
        },

        /**
         * Show result section
         */
        showResult: function() {
            this.showSnackbar('Link created successfully!');
            this.$resultSection.removeClass('d-none');
        },

        /**
         * Hide result section
         */
        hideResult: function() {
            this.$resultSection.addClass('d-none');
            this.hideQRSection();
            this.stopExpiryCountdown();
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

        showSaveLinkReminder: function() {
            if (this.$saveLinkReminder && this.$saveLinkReminder.length) {
                this.$saveLinkReminder.removeClass('d-none');
            }
        },

        hideSaveLinkReminder: function() {
            if (this.$saveLinkReminder && this.$saveLinkReminder.length) {
                this.$saveLinkReminder.addClass('d-none');
            }
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
         * Capture screenshot of destination URL
         */
        captureScreenshot: function(url) {
            console.log('TP Link Shortener: Capturing screenshot for:', url);

            // Find the screenshot preview element
            const $screenshotPreview = $('.tp-screenshot-preview');
            const $screenshotImg = $screenshotPreview.find('.tp-screenshot-img');

            if (!$screenshotImg.length) {
                console.warn('TP Link Shortener: Screenshot preview element not found');
                return;
            }

            // Keep the initial loading spinner active during capture
            // No need to add 'loading' class as tp-screenshot-loading is already there

            // Send AJAX request to capture screenshot
            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_capture_screenshot',
                    nonce: tpAjax.nonce,
                    url: url
                },
                success: function(response) {
                    console.log('TP Link Shortener: Screenshot capture response:', response);

                    if (response.success && response.data && response.data.data_uri) {
                        // Update the image src with the data URI
                        $screenshotImg.attr('src', response.data.data_uri);
                        $screenshotImg.attr('alt', 'Screenshot of ' + url);

                        // Show the image and hide spinner
                        $screenshotImg.show();
                        $screenshotPreview.removeClass('tp-screenshot-loading').addClass('tp-screenshot-loaded');

                        // Screenshot caching removed - always fetch fresh from API

                        // Log additional info
                        if (response.data.cached) {
                            console.log('TP Link Shortener: Screenshot loaded from cache');
                        }
                        if (response.data.response_time_ms) {
                            console.log('TP Link Shortener: Screenshot response time:', response.data.response_time_ms + 'ms');
                        }
                    } else {
                        console.error('TP Link Shortener: Screenshot capture failed:', response.data ? response.data.message : 'Unknown error');
                        // Keep spinner on error - no fallback image
                        $screenshotPreview.addClass('tp-screenshot-error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('TP Link Shortener: Screenshot AJAX error:', error);
                    // Keep spinner on error - no fallback image
                    $screenshotPreview.addClass('tp-screenshot-error');
                },
                complete: function() {
                    // Complete function intentionally minimal
                    // Spinner state is managed in success/error handlers
                }
            });
        },

        /**
         * Copy short URL to clipboard
         */
        copyToClipboard: function() {
            const shortUrl = this.$shortUrlOutput.attr('href');

            // Use modern clipboard API if available, fallback to old method
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shortUrl).then(function() {
                    // Success - show feedback
                }.bind(this)).catch(function(err) {
                    console.error('Failed to copy:', err);
                });
            } else {
                // Fallback: create temporary input, select and copy
                const tempInput = $('<input>');
                $('body').append(tempInput);
                tempInput.val(shortUrl).select();
                document.execCommand('copy');
                tempInput.remove();
            }

            // Visual feedback
            const originalText = this.$copyBtn.html();
            const copiedLabel = tpAjax.strings.copied || 'Copied!';
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
            const domain = tpAjax.domain || 'tp.local';
            const shortUrl = 'https://' + domain + '/' + storedData.shortcode;

            // Pre-fill form inputs with stored data
            this.$destinationInput.val(storedData.destination);
            if (this.$customKeyInput.length) {
                this.$customKeyInput.val(storedData.shortcode);
                // Show custom key group for returning visitors
                if (this.$customKeyGroup && this.$customKeyGroup.length) {
                    this.$customKeyGroup.show();
                }
            }

            // Display the short URL
            this.$shortUrlOutput.attr('href', shortUrl).text(shortUrl);
            this.lastShortUrl = shortUrl;

            // Show result section WITHOUT success message (for returning visitors)
            this.$resultSection.removeClass('d-none');

            // Generate QR code (if enabled)
            if (tpAjax.enableQRCode) {
                this.generateQRCode(shortUrl);
            }

            // Always capture fresh screenshot from API (if enabled)
            if (tpAjax.enableScreenshot) {
                this.captureScreenshot(storedData.destination);
            }

            // Only disable the form for non-logged-in users (trial users)
            if (!tpAjax.isLoggedIn) {
                this.disableForm();

                // Show returning visitor message with countdown for trial users
                this.showReturningVisitorMessage(
                    '<i class="fas fa-clock me-2"></i>' +
                    'Your trial key is active! Time remaining: <span id="tp-countdown" class="me-1"></span> ' +
                    '<a href="#" id="tp-register-link">Register to keep it active</a>.'
                );

                // Start countdown (for returning visitor message)
                this.startCountdown();

                // Start expiry countdown (for expiry counter in result section) (if enabled)
                if (tpAjax.enableExpiryTimer) {
                    this.startExpiryCountdown();
                }
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
         * Start expiry countdown timer for new links
         */
        startExpiryCountdown: function() {
            const updateExpiry = function() {
                if (!window.TPStorageService || !window.TPStorageService.isAvailable()) {
                    this.stopExpiryCountdown();
                    return;
                }

                const timeRemaining = window.TPStorageService.getTimeRemaining();
                if (timeRemaining === null || timeRemaining <= 0) {
                    // Expired
                    $('.tp-expiry-counter').text('Expired');
                    this.stopExpiryCountdown();
                    return;
                }

                // Format time as HH:MM:SS
                const hours = Math.floor(timeRemaining / (1000 * 60 * 60));
                const minutes = Math.floor((timeRemaining % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);

                const formatted =
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');

                $('.tp-expiry-counter').text(formatted);
            }.bind(this);

            // Update immediately
            updateExpiry();

            // Stop any existing timer
            this.stopExpiryCountdown();

            // Update every second
            this.expiryTimer = setInterval(updateExpiry, 1000);
        },

        /**
         * Stop expiry countdown timer
         */
        stopExpiryCountdown: function() {
            if (this.expiryTimer) {
                clearInterval(this.expiryTimer);
                this.expiryTimer = null;
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
            this.stopExpiryCountdown();
            window.TPStorageService.clearShortcodeData();
            this.hideReturningVisitorMessage();
            this.hideSaveLinkReminder();
            this.hideResult();
            this.enableForm();
            this.isReturningVisitor = false;
        },

        /**
         * Wait for fingerprint to be ready, then search
         */
        waitForFingerprintThenSearch: async function() {
            console.log('=== WAIT FOR FINGERPRINT THEN SEARCH START ===');
            console.log('fpPromise exists:', !!this.fpPromise);

            try {
                // Wait for fingerprint to be ready
                if (!this.fpPromise) {
                    console.warn('FingerprintJS not initialized, cannot search');
                    console.log('=== WAIT FOR FINGERPRINT THEN SEARCH END (NOT INITIALIZED) ===');
                    return;
                }

                console.log('Waiting for fingerprint to load...');
                const fp = await this.fpPromise;
                console.log('FingerprintJS loaded successfully:', fp);

                // Now get the actual fingerprint
                console.log('Getting visitor fingerprint...');
                const result = await fp.get();
                const fingerprint = result.visitorId;
                console.log('Fingerprint obtained:', fingerprint);

                // Now search with the fingerprint
                this.searchByFingerprint(fingerprint);
                console.log('=== WAIT FOR FINGERPRINT THEN SEARCH END (SUCCESS) ===');
            } catch (error) {
                console.error('Error waiting for fingerprint:', error);
                console.log('=== WAIT FOR FINGERPRINT THEN SEARCH END (ERROR) ===');
            }
        },

        /**
         * Search for user's most recent link by fingerprint
         */
        searchByFingerprint: function(fingerprint) {
            console.log('=== SEARCH BY FINGERPRINT START ===');
            const self = this;

            console.log('Fingerprint provided:', fingerprint);
            console.log('Fingerprint type:', typeof fingerprint);
            console.log('Fingerprint length:', fingerprint ? fingerprint.length : 0);

            if (!fingerprint) {
                console.error('ERROR: Fingerprint not provided, skipping search');
                console.log('=== SEARCH BY FINGERPRINT END (NO FINGERPRINT) ===');
                return;
            }

            console.log('Preparing AJAX request');
            console.log('AJAX URL:', tpAjax.ajaxUrl);
            console.log('Nonce:', tpAjax.nonce);
            console.log('Action: tp_search_by_fingerprint');

            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_search_by_fingerprint',
                    nonce: tpAjax.nonce,
                    fingerprint: fingerprint
                },
                beforeSend: function() {
                    console.log('AJAX request being sent...');
                },
                success: function(response) {
                    console.log('AJAX response received');
                    console.log('Response:', JSON.stringify(response, null, 2));
                    console.log('Response success:', response.success);
                    console.log('Response has data:', !!response.data);
                    console.log('Response has record:', response.data ? !!response.data.record : false);

                    if (response.success && response.data.record) {
                        console.log('Record found, displaying existing link');
                        const record = response.data.record;
                        console.log('Record details:', JSON.stringify(record, null, 2));
                        self.displayExistingLink(record);
                    } else {
                        console.log('No existing links found for this fingerprint');
                        if (response.data) {
                            console.log('Response data:', response.data);
                        }
                    }
                    console.log('=== SEARCH BY FINGERPRINT END (SUCCESS) ===');
                },
                error: function(xhr, status, error) {
                    console.error('AJAX ERROR');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('XHR status:', xhr.status);
                    console.error('Response text:', xhr.responseText);
                    console.log('=== SEARCH BY FINGERPRINT END (ERROR) ===');
                }
            });
        },

        /**
         * Display existing link with countdown
         */
        displayExistingLink: function(record) {
            // Build the short URL
            const shortUrl = 'https://' + record.domain + '/' + record.tpKey;

            // Store data
            this.lastShortUrl = shortUrl;
            this.$shortUrlOutput.attr('href', shortUrl).text(shortUrl);

            // Set destination in form for updating
            this.$destinationInput.val(record.destination);

            // Store record details for updates
            this.currentRecord = record;

            // Show result section
            this.showResult();

            // Generate QR code
            this.generateQRCode(shortUrl);

            // Always capture fresh screenshot from API
            this.captureScreenshot(record.destination);

            // If link has expiry, start countdown
            if (record.expires_at) {
                this.startExpiryCountdown(record.expires_at);
            }

            // Switch to update mode
            this.switchToUpdateMode();
        },

        /**
         * Start expiry countdown timer
         */
        startExpiryCountdown: function(expiresAt) {
            // Clear any existing timer
            this.stopExpiryCountdown();

            const expiryDate = new Date(expiresAt);
            const self = this;

            // Show screenshot expiry timer
            $('#tp-screenshot-expiry-timer').show();

            function updateCountdown() {
                const now = new Date();
                const timeLeft = expiryDate - now;

                if (timeLeft <= 0) {
                    $('#tp-screenshot-expiry-countdown').text('Expired');
                    self.stopExpiryCountdown();
                    self.showError('This link has expired.');
                    return;
                }

                const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

                const formatted =
                    String(hours).padStart(2, '0') + ':' +
                    String(minutes).padStart(2, '0') + ':' +
                    String(seconds).padStart(2, '0');

                $('#tp-screenshot-expiry-countdown').text(formatted);
            }

            // Update immediately
            updateCountdown();

            // Update every second
            this.expiryTimer = setInterval(updateCountdown, 1000);
        },

        /**
         * Stop expiry countdown timer
         */
        stopExpiryCountdown: function() {
            if (this.expiryTimer) {
                clearInterval(this.expiryTimer);
                this.expiryTimer = null;
            }
            $('#tp-screenshot-expiry-timer').hide();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#tp-shortener-form').length) {
            TPLinkShortener.init();
        }
    });

})(jQuery);
