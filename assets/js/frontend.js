/**
 * Traffic Portal Link Shortener - Frontend JavaScript
 * Form handling, AJAX submission, and QR code generation
 */

(function($) {
    'use strict';

    // LocalStorage-driven debug toggle utility
    const TPDebug = {
        featureKeys: [
            'all',
            'init',
            'fingerprint',
            'validation',
            'submit',
            'update',
            'suggestion',
            'clipboard',
            'process',
            'search',
            'qr',
            'screenshot',
            'storage',
            'ui',
            'returning'
        ],
        flags: {},
        init() {
            this.seedDefaults();
            this.featureKeys.forEach((key) => {
                this.flags[key] = this.readFlag(key);
            });
        },
        seedDefaults() {
            try {
                this.featureKeys.forEach((key) => {
                    const storageKey = 'tpDebug:' + key;
                    if (window.localStorage.getItem(storageKey) === null) {
                        window.localStorage.setItem(storageKey, 'off');
                    }
                });
            } catch (error) {
                // localStorage may be unavailable; fail silently
            }
        },
        readFlag(key) {
            try {
                const raw = window.localStorage.getItem('tpDebug:' + key);
                if (!raw) {
                    return false;
                }
                const normalized = raw.toString().trim().toLowerCase();
                return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
            } catch (error) {
                return false;
            }
        },
        isEnabled(feature) {
            if (this.flags.all) {
                return true;
            }
            return !!this.flags[feature];
        },
        log(feature, ...args) {
            if (this.isEnabled(feature)) {
                console.log(...args);
            }
        },
        warn(feature, ...args) {
            if (this.isEnabled(feature)) {
                console.warn(...args);
            }
        },
        error(feature, ...args) {
            if (this.isEnabled(feature)) {
                console.error(...args);
            }
        }
    };

    TPDebug.init();

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
        errorDismissTimer: null,

        // State
        qrCode: null,
        lastShortUrl: '',
        isValid: false,
        isReturningVisitor: false,
        countdownTimer: null,
        expiryTimer: null,
        urlValidator: null,
        debouncedValidate: null,
        lastValidatedUrl: '', // Track last successfully validated URL to avoid redundant validations
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
            TPDebug.log('init', '=== TP LINK SHORTENER INITIALIZATION ===');
            TPDebug.log('init', 'User logged in:', tpAjax.isLoggedIn);
            TPDebug.log('init', 'Domain:', tpAjax.domain);
            TPDebug.log('init', 'AJAX URL:', tpAjax.ajaxUrl);

            // Suggestion state
            this.suggestionCandidates = [];
            this.suggestionIndex = -1;
            this.suggestionSourceUrl = '';
            this.suggestionApplied = false;

            this.cacheElements();
            TPDebug.log('init', 'DOM elements cached');

            this.initializeURLValidator();
            TPDebug.log('init', 'URL validator initialized');

            await this.initializeFingerprintJS();
            TPDebug.log('init', 'FingerprintJS initialized');

            this.bindEvents();
            TPDebug.log('init', 'Events bound');

            this.checkClipboardSupport();
            TPDebug.log('init', 'Clipboard support checked');
            // this.checkReturningVisitor(); // Disabled: using fingerprint-based detection only

            // Search for existing links by fingerprint for anonymous users
            if (!tpAjax.isLoggedIn) {
                TPDebug.log('init', 'User is anonymous - will search for existing links by fingerprint after FP loads...');
                // Wait for fingerprint to be ready before searching
                this.waitForFingerprintThenSearch();
            } else {
                TPDebug.log('init', 'User is logged in - skipping fingerprint search');
            }

            TPDebug.log('init', '=== TP LINK SHORTENER INITIALIZATION COMPLETE ===');
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
            this.$qrDialogOverlay = $('#tp-qr-dialog-overlay');
            this.$qrDialogClose = $('#tp-qr-dialog-close');
            this.$qrDownloadBtn = $('#tp-qr-download-btn');
            this.$qrOpenBtn = $('#tp-qr-open-btn');
            this.$qrCopyBtn = $('#tp-qr-copy-btn');
            this.$pasteBtn = $('#tp-paste-btn');
            this.$suggestBtn = $('#tp-suggest-btn');
            this.$saveLinkReminder = $('#tp-save-link-reminder');

            // Get validation message element (now exists in template)
            this.$validationMessage = $('#tp-url-validation-message');
            this.$suggestionMessage = $('#tp-suggestion-message');
            this.$suggestIcon = $('#tp-suggest-icon');
            this.$tryItMessage = $('#tp-try-it-message');

            // Update mode elements
            this.$submitText = $('#tp-submit-text');
            this.$submitIcon = $('#tp-submit-icon');
        },

        /**
         * Initialize URL Validator
         */
        initializeURLValidator: function() {
            // Check if URLValidator class is available
            if (typeof URLValidator === 'undefined') {
                TPDebug.warn('validation', 'URLValidator library not loaded. Online validation disabled.');
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
            TPDebug.log('fingerprint', '=== FINGERPRINT JS INITIALIZATION ===');
            // Check if FingerprintJS is loaded
            try {
                await this.ensureFingerprintScript();

                if (typeof FingerprintJS === 'undefined') {
                    TPDebug.warn('fingerprint', 'FingerprintJS still undefined after CDN load. Fingerprinting disabled.');
                    TPDebug.log('fingerprint', '=== FINGERPRINT JS INITIALIZATION FAILED ===');
                    return;
                }

                TPDebug.log('fingerprint', 'FingerprintJS library loaded successfully');
                // Initialize FingerprintJS
                this.fpPromise = FingerprintJS.load();
                TPDebug.log('fingerprint', 'FingerprintJS.load() called - promise created');
                TPDebug.log('fingerprint', 'fpPromise is now:', !!this.fpPromise);
                TPDebug.log('fingerprint', '=== FINGERPRINT JS INITIALIZATION COMPLETE ===');
            } catch (err) {
                TPDebug.warn('fingerprint', 'Failed to load FingerprintJS from CDN:', err);
                TPDebug.log('fingerprint', '=== FINGERPRINT JS INITIALIZATION FAILED ===');
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

            TPDebug.log('fingerprint', 'FingerprintJS not found - injecting self-hosted script...');
            this.fpScriptPromise = new Promise(function(resolve, reject) {
                const primarySrc = (window.tpAjax && tpAjax.fingerprintUrl) ?
                    tpAjax.fingerprintUrl :
                    'https://openfpcdn.io/fingerprintjs/v4/iife.min.js';
                const fallbackSrc = 'https://openfpcdn.io/fingerprintjs/v4/iife.min.js';

                const script = document.createElement('script');
                script.src = primarySrc;
                script.async = true;
                script.onload = function() {
                    TPDebug.log('fingerprint', 'FingerprintJS script loaded from', primarySrc);
                    resolve();
                };
                script.onerror = function(event) {
                    TPDebug.warn('fingerprint', 'Primary FingerprintJS script failed, trying fallback...', event);
                    // Try fallback CDN once
                    const fallbackScript = document.createElement('script');
                    fallbackScript.src = fallbackSrc;
                    fallbackScript.async = true;
                    fallbackScript.onload = function() {
                        TPDebug.log('fingerprint', 'FingerprintJS fallback script loaded from', fallbackSrc);
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
            TPDebug.log('fingerprint', '=== GET FINGERPRINT START ===');
            if (!this.fpPromise) {
                TPDebug.warn('fingerprint', 'FingerprintJS not initialized - promise is null');
                TPDebug.log('fingerprint', '=== GET FINGERPRINT END (NOT INITIALIZED) ===');
                return null;
            }

            try {
                TPDebug.log('fingerprint', 'Awaiting FingerprintJS agent...');
                const fp = await this.fpPromise;
                TPDebug.log('fingerprint', 'FingerprintJS agent loaded:', fp);

                TPDebug.log('fingerprint', 'Getting fingerprint result...');
                const result = await fp.get();
                TPDebug.log('fingerprint', 'Full fingerprint result:', result);
                TPDebug.log('fingerprint', 'Visitor ID:', result.visitorId);
                TPDebug.log('fingerprint', 'Confidence score:', result.confidence);
                TPDebug.log('fingerprint', 'Components count:', result.components ? Object.keys(result.components).length : 0);
                TPDebug.log('fingerprint', '=== GET FINGERPRINT END (SUCCESS) ===');
                return result.visitorId;
            } catch (error) {
                TPDebug.error('fingerprint', 'Error getting fingerprint:', error);
                TPDebug.error('fingerprint', 'Error stack:', error.stack);
                TPDebug.log('fingerprint', '=== GET FINGERPRINT END (ERROR) ===');
                return null;
            }
        },

        /**
         * Handle URL validation result
         */
        handleValidationResult: function(result, url) {
            TPDebug.log('validation', '🎯 [UI-CALLBACK] === VALIDATION RESULT RECEIVED ===');
            TPDebug.log('validation', '🎯 [UI-CALLBACK] URL:', url);
            TPDebug.log('validation', '🎯 [UI-CALLBACK] Result:', result);
            TPDebug.log('validation', '🎯 [UI-CALLBACK] isError:', result.isError);
            TPDebug.log('validation', '🎯 [UI-CALLBACK] isWarning:', result.isWarning);
            TPDebug.log('validation', '🎯 [UI-CALLBACK] Message:', result.message);

            // Check if protocol was updated (HTTPS -> HTTP fallback)
            if (result.protocolUpdated && result.updatedUrl) {
                // Update the input field with the HTTP URL
                this.$destinationInput.val(result.updatedUrl);
                TPDebug.log('validation', '🎯 [UI-CALLBACK] URL protocol updated from HTTPS to HTTP:', result.updatedUrl);
            }

            // Update UI based on validation result
            if (result.isError) {
                TPDebug.log('validation', '🎯 [UI-CALLBACK] ❌ Showing ERROR UI for:', url);
                this.isValid = false;
                this.lastValidatedUrl = ''; // Clear last validated URL on error
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
                TPDebug.log('validation', '🎯 [UI-CALLBACK] ⚠️  Showing WARNING UI for:', url);
                // Warnings still allow submission but show warning message
                this.isValid = true;
                this.lastValidatedUrl = url; // Store last validated URL to avoid redundant validations
                this.$destinationInput.removeClass('is-invalid').addClass('is-valid');
                // Show warning message in validation message area
                // Check if this is a redirect with a suggested URL
                let warningHtml = '<i class="fas fa-exclamation-triangle me-2"></i>' + result.message;
                if (result.redirectLocation) {
                    warningHtml += ' <a href="#" class="tp-replace-url-link" data-url="' + this.escapeHtml(result.redirectLocation) + '">Replace with ' + this.escapeHtml(result.redirectLocation) + '</a>';
                }
                this.$validationMessage.html(warningHtml);
                this.$validationMessage.removeClass('error-message success-message text-muted text-success text-danger').addClass('warning-message text-warning');
                this.$validationMessage.show();

                // Bind click handler for replace URL link
                if (result.redirectLocation) {
                    const self = this;
                    this.$validationMessage.find('.tp-replace-url-link').on('click', function(e) {
                        e.preventDefault();
                        const newUrl = $(this).data('url');
                        self.$destinationInput.val(newUrl);
                        self.processUrl(newUrl);
                    });
                }
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
                    TPDebug.log('suggestion', '🎯 [UI-CALLBACK] Fetching shortcode suggestion (warning case)');
                    this.fetchShortcodeSuggestion(url);
                } else {
                    TPDebug.log('suggestion', '🎯 [UI-CALLBACK] Skipping suggestion - in update mode');
                }
            } else {
                TPDebug.log('validation', '🎯 [UI-CALLBACK] ✅ Showing SUCCESS UI for:', url);
                this.isValid = true;
                this.lastValidatedUrl = url; // Store last validated URL to avoid redundant validations
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
                    TPDebug.log('suggestion', '🎯 [UI-CALLBACK] Fetching shortcode suggestion');
                    this.fetchShortcodeSuggestion(url);
                } else {
                    TPDebug.log('suggestion', '🎯 [UI-CALLBACK] Skipping suggestion - in update mode');
                }
            }
            TPDebug.log('validation', '🎯 [UI-CALLBACK] === VALIDATION RESULT HANDLED ===');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            this.$form.on('submit', this.handleSubmit.bind(this));
            this.$copyBtn.on('click', this.copyToClipboard.bind(this));
            this.$qrContainer.on('click', this.showQRDialog.bind(this));

            // QR Dialog events
            this.$qrDialogOverlay.on('click', this.handleQRDialogOverlayClick.bind(this));
            this.$qrDialogClose.on('click', this.hideQRDialog.bind(this));
            this.$qrDownloadBtn.on('click', this.downloadQRCode.bind(this));
            this.$qrOpenBtn.on('click', this.openQRCode.bind(this));
            this.$qrCopyBtn.on('click', this.copyQRCode.bind(this));

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

            // Magic Keyword input - filter invalid characters and set custom validation message
            if (this.$customKeyInput.length) {
                this.$customKeyInput.on('input', this.handleCustomKeyInput.bind(this));
            }

            // Listen for edit item events from dashboard (only for logged-in users)
            if (tpAjax.isLoggedIn) {
                $(document).on('tp:editItem', this.handleDashboardEditItem.bind(this));
                $(document).on('tp:resetForm', this.switchToCreateMode.bind(this));
                TPDebug.log('init', 'Dashboard edit item event listener bound');
            }
        },

        /**
         * Handle Magic Keyword input - filter invalid characters
         */
        handleCustomKeyInput: function(e) {
            const input = e.target;
            const originalValue = input.value;
            // Replace spaces with hyphens, then strip any remaining invalid characters
            const filteredValue = originalValue.replace(/ /g, '-').replace(/[^a-zA-Z0-9.\-_]/g, '');

            if (filteredValue !== originalValue) {
                input.value = filteredValue;
                // Move cursor to end after filtering
                input.setSelectionRange(filteredValue.length, filteredValue.length);
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
            TPDebug.log('submit', '=== SUBMIT CREATE LINK START ===');

            // Get form data
            const destination = this.$destinationInput.val().trim();
            const customKey = this.$customKeyInput.val().trim();
            TPDebug.log('submit', 'Form data:', { destination, customKey });
            TPDebug.log('submit', 'User logged in:', tpAjax.isLoggedIn);

            // Validate URL format
            if (!this.validateUrl(destination)) {
                TPDebug.error('validation', 'URL validation failed:', destination);
                this.showError(tpAjax.strings.invalidUrl);
                return;
            }

            // Check if online validation has been performed and passed
            if (!this.isValid) {
                TPDebug.error('validation', 'Online validation not passed');
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
                TPDebug.log('fingerprint', 'User is anonymous - generating fingerprint...');
                fingerprint = await this.getFingerprint();
                TPDebug.log('fingerprint', 'Fingerprint generated:', fingerprint);
                TPDebug.log('fingerprint', 'Fingerprint type:', typeof fingerprint);
                TPDebug.log('fingerprint', 'Fingerprint length:', fingerprint ? fingerprint.length : 0);
            } else {
                TPDebug.log('fingerprint', 'User is logged in - skipping fingerprint generation');
            }

            // Prepare data
            const data = {
                action: 'tp_create_link',
                nonce: tpAjax.nonce,
                destination: destination,
                custom_key: customKey
            };

            if (fingerprint !== null) {
                data.fingerprint = fingerprint;
                TPDebug.log('submit', 'Added fingerprint to request data:', fingerprint);
            }

            TPDebug.log('submit', 'Complete AJAX request data:', JSON.stringify(data, null, 2));

            // Send AJAX request
            TPDebug.log('submit', 'Sending AJAX request to:', tpAjax.ajaxUrl);
            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    TPDebug.log('submit', 'AJAX request being sent...');
                },
                success: function(response) {
                    TPDebug.log('submit', 'AJAX response received:', response);
                    TPDebug.log('submit', '=== SUBMIT CREATE LINK END ===');
                    this.handleCreateSuccess(response);
                }.bind(this),
                error: function(xhr, status, error) {
                    TPDebug.error('submit', 'AJAX error:', { xhr, status, error });
                    TPDebug.error('submit', 'Response text:', xhr.responseText);
                    TPDebug.log('submit', '=== SUBMIT CREATE LINK END (ERROR) ===');
                    this.handleError(xhr, status, error);
                }.bind(this),
                complete: function() {
                    TPDebug.log('submit', 'AJAX request complete');
                    this.setLoadingState(false);
                }.bind(this)
            });
        },

        /**
         * Submit update link request
         */
        submitUpdate: function() {
            TPDebug.log('update', 'TP Update: submitUpdate called');
            TPDebug.log('update', 'TP Update: currentRecord:', this.currentRecord);

            if (!this.currentRecord || !this.currentRecord.mid) {
                TPDebug.error('update', 'TP Update: No current record or mid', this.currentRecord);
                this.showSnackbar('No link to update.', 'error');
                return;
            }

            const newDestination = this.$destinationInput.val().trim();

            if (!newDestination) {
                TPDebug.error('update', 'TP Update: Empty destination');
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
                TPDebug.error('update', 'TP Update: No key found', this.currentRecord);
                this.showSnackbar('Unable to find link key.', 'error');
                return;
            }

            TPDebug.log('update', 'TP Update: currentRecord.mid:', this.currentRecord.mid);
            TPDebug.log('update', 'TP Update: currentRecord.domain:', this.currentRecord.domain);
            TPDebug.log('update', 'TP Update: tpKey:', tpKey);
            TPDebug.log('update', 'TP Update: All currentRecord keys:', Object.keys(this.currentRecord));

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

            TPDebug.log('update', 'TP Update: Sending request with data:', updateData);
            TPDebug.log('update', 'TP Update: Data as JSON:', JSON.stringify(updateData, null, 2));
            TPDebug.log('update', 'TP Update: tpKey value type:', typeof tpKey, 'value:', tpKey);

            // Show loading state (spinner + disable button)
            this.setLoadingState(true);

            const self = this;

            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: updateData,
                beforeSend: function(xhr, settings) {
                    TPDebug.log('update', 'TP Update: AJAX beforeSend - data being sent:', settings.data);
                },
                success: function(response) {
                    TPDebug.log('update', 'TP Update: Success response:', response);

                    if (response.success) {
                        self.showSnackbar('Link updated successfully!', 'success');

                        // Update the stored record with new values
                        self.currentRecord.destination = newDestination;
                        self.currentRecord.tpKey = tpKey;

                        // Check if destination changed - regenerate screenshot (if enabled)
                        if (oldDestination !== newDestination && tpAjax.enableScreenshot) {
                            TPDebug.log('update', 'TP Update: Destination changed, regenerating screenshot');
                            self.captureScreenshot(newDestination);
                        }

                        // Check if tpKey changed - update short URL and regenerate QR code (if enabled)
                        if (oldTpKey !== tpKey) {
                            TPDebug.log('update', 'TP Update: Key changed, updating short URL and QR code');
                            const newShortUrl = 'https://' + self.currentRecord.domain + '/' + tpKey;
                            self.$shortUrlOutput.attr('href', newShortUrl).text(newShortUrl);
                            if (tpAjax.enableQRCode) {
                                self.generateQRCode(newShortUrl);
                            }
                        }
                    } else {
                        TPDebug.error('update', 'TP Update: Server returned success=false', response);
                        const errorMsg = response.data ? response.data.message : 'Failed to update link.';
                        const debugInfo = response.data ? JSON.stringify(response.data, null, 2) : '';
                        TPDebug.error('update', 'TP Update: Debug info:', debugInfo);
                        self.showSnackbar(errorMsg, 'error');
                        // Show debug info in console
                        if (response.data && response.data.debug) {
                            TPDebug.error('update', 'TP Update: Debug details:', response.data.debug);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    TPDebug.error('update', 'TP Update: AJAX error', {xhr: xhr, status: status, error: error});
                    TPDebug.error('update', 'TP Update: Response text:', xhr.responseText);

                    let errorMessage = 'An error occurred while updating the link.';
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        if (errorData && errorData.data && errorData.data.message) {
                            errorMessage = errorData.data.message;
                        }
                        TPDebug.error('update', 'TP Update: Parsed error data:', errorData);
                    } catch(e) {
                        TPDebug.error('update', 'TP Update: Could not parse error response');
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

                // Show usage stats with 0/0 for newly created links and start polling
                this.displayUsageStats({ qr: 0, regular: 0 });

                // Start usage polling (every 5 seconds) for anonymous users
                if (!tpAjax.isLoggedIn) {
                    this.startUsagePolling();
                }

                // Show "Try It Now" message for non-logged-in users
                if (this.$tryItMessage && this.$tryItMessage.length) {
                    this.$tryItMessage.removeClass('d-none');
                }

                // Start expiry countdown for non-logged-in users (if enabled)
                if (!tpAjax.isLoggedIn && tpAjax.enableExpiryTimer && response.data.expires_at) {
                    this.startExpiryCountdown(response.data.expires_at);
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
            TPDebug.error('submit', 'AJAX Error:', error);
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
            TPDebug.log('clipboard', '[PASTE DEBUG] handlePasteClick called');
            try {
                TPDebug.log('clipboard', '[PASTE DEBUG] Attempting to read clipboard...');
                const text = await navigator.clipboard.readText();
                TPDebug.log('clipboard', '[PASTE DEBUG] Clipboard read successful, text:', text);

                if (!text || text.trim() === '') {
                    TPDebug.log('clipboard', '[PASTE DEBUG] Clipboard is empty');
                    this.showError('Clipboard is empty');
                    return;
                }

                TPDebug.log('clipboard', '[PASTE DEBUG] Setting input value to:', text.trim());
                this.$destinationInput.val(text.trim());

                TPDebug.log('clipboard', '[PASTE DEBUG] Calling processUrl with:', text.trim());
                this.processUrl(text.trim());
                TPDebug.log('clipboard', '[PASTE DEBUG] processUrl completed');

            } catch (err) {
                TPDebug.log('clipboard', '[PASTE DEBUG] Clipboard read failed with error:', err);
                if (err.name === 'NotAllowedError') {
                    this.showError('Clipboard permission denied. Please allow clipboard access or paste manually. <a href="https://trafficportal.dev/help/" target="_blank">Read more…</a>');
                } else {
                    this.showError('Unable to read clipboard. Please paste manually (Ctrl+V or Cmd+V).');
                }
                TPDebug.warn('clipboard', 'Clipboard read failed:', err);
            }
        },

        /**
         * Handle suggest button click (lightbulb)
         */
        handleSuggestClick: async function() {
            const destination = this.$destinationInput.val().trim();

            // Reset suggestion cache when destination changes
            if (destination !== this.suggestionSourceUrl) {
                this.suggestionCandidates = [];
                this.suggestionIndex = -1;
                this.suggestionSourceUrl = destination;
                this.suggestionApplied = false;
            }

            // If URL is valid, fetch AI suggestion; otherwise generate random
            if (this.isValid && destination) {
                TPDebug.log('suggestion', 'Current suggestion candidates before click:', this.suggestionCandidates);
                // If we already have candidates, just cycle without showing loading
                if (this.suggestionCandidates.length > 0) {
                    this.cycleSuggestion();
                } else {
                    await this.fetchShortcodeSuggestion(destination);
                }
            } else {
                const randomKey = this.generateRandomKey();
                this.$customKeyInput.val(randomKey);
            }
        },

        /**
         * Fetch AI-powered shortcode suggestion from backend
         */
        fetchShortcodeSuggestion: async function(destination) {
            TPDebug.log('suggestion', '=== FETCH SHORTCODE SUGGESTION START ===');
            TPDebug.log('suggestion', 'Destination URL:', destination);

            const self = this;

            // Check if custom key input exists
            if (!this.$customKeyInput || !this.$customKeyInput.length) {
                TPDebug.log('suggestion', 'ERROR: Custom key input element not found');
                TPDebug.log('suggestion', '=== FETCH SHORTCODE SUGGESTION END ===');
                return;
            }

            const currentValue = this.$customKeyInput.val().trim();
            TPDebug.log('suggestion', 'Current custom key value:', currentValue);
            TPDebug.log('suggestion', 'Proceeding with suggestion fetch...');

            // Show loading state with spinning icon and message (non-blocking)
            this.$suggestIcon.removeClass('fa-lightbulb').addClass('fa-spinner fa-spin');
            this.$suggestionMessage.html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Generating suggestion...');
            this.$suggestionMessage.removeClass('text-success text-danger text-warning').addClass('text-muted');
            this.$suggestionMessage.show();
            TPDebug.log('suggestion', 'Showing suggestion loading message (non-blocking)');

            try {
                // Send AJAX request for FAST suggestion only
                TPDebug.log('suggestion', 'Sending FAST suggestion request to:', tpAjax.ajaxUrl);
                const response = await $.ajax({
                    url: tpAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'tp_suggest_shortcode_fast',
                        nonce: tpAjax.nonce,
                        destination: destination
                    }
                });

                TPDebug.log('suggestion', 'FAST suggestion response:', response);

                if (response.success && response.data && response.data.shortcode) {
                    // Take up to 5 candidates (including primary shortcode)
                    const candidates = Array.isArray(response.data.candidates) ? response.data.candidates.slice(0, 5) : [];
                    const primary = response.data.shortcode;
                    if (!candidates.includes(primary)) {
                        candidates.unshift(primary);
                    }
                    this.suggestionCandidates = candidates.length ? candidates : [primary];
                    this.suggestionIndex = 0;
                    this.suggestionSourceUrl = destination;
                    TPDebug.log('suggestion', 'Stored suggestion candidates:', this.suggestionCandidates);

                    // Only populate input if it's empty
                    const inputValue = this.$customKeyInput.val().trim();
                    if (!inputValue) {
                        this.$customKeyInput.val(this.suggestionCandidates[this.suggestionIndex]);
                        this.suggestionApplied = true;
                        this.$suggestionMessage.html('<i class="fa-solid fa-check-circle me-2"></i>Suggestion applied');
                        this.$suggestionMessage.removeClass('text-muted text-danger text-warning').addClass('text-success');
                        TPDebug.log('suggestion', 'Input was empty, applied suggestion:', this.suggestionCandidates[this.suggestionIndex]);
                    } else {
                        this.suggestionApplied = false;
                        this.$suggestionMessage.html('<i class="fa-solid fa-lightbulb me-2"></i>Suggestion ready - click lightbulb to apply');
                        this.$suggestionMessage.removeClass('text-muted text-danger text-warning').addClass('text-success');
                        TPDebug.log('suggestion', 'Input has value, suggestion ready but not applied');
                    }

                    // Kick off SMART request in the background to enrich the cycle
                    this.fetchSmartSuggestions(destination, this.suggestionIndex);
                } else {
                    TPDebug.log('suggestion', 'FAST suggestion failed, leaving custom key unchanged');
                    this.suggestionCandidates = [];
                    this.suggestionIndex = -1;
                    this.suggestionSourceUrl = destination;
                    this.$suggestionMessage.hide();
                }
            } catch (xhr) {
                TPDebug.error('suggestion', 'FAST suggestion AJAX error:', xhr);
                this.suggestionCandidates = [];
                this.suggestionIndex = -1;
                this.suggestionSourceUrl = destination;
                this.$suggestionMessage.hide();
            } finally {
                // Restore lightbulb icon
                self.$suggestIcon.removeClass('fa-spinner fa-spin').addClass('fa-lightbulb');

                TPDebug.log('suggestion', '=== FETCH SHORTCODE SUGGESTION END ===');
            }
        },

        /**
         * Fetch SMART suggestions and insert them right after the current index.
         */
        fetchSmartSuggestions: function(destination, currentIndexSnapshot) {
            // Only proceed if we're still on the same destination
            if (destination !== this.suggestionSourceUrl) {
                return;
            }

            const self = this;
            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_suggest_shortcode_smart',
                    nonce: tpAjax.nonce,
                    destination: destination
                },
                success: function(response) {
                    TPDebug.log('suggestion', 'SMART suggestion response:', response);

                    if (!(response && response.success && response.data && response.data.shortcode)) {
                        return;
                    }

                    // Build candidate list (primary + up to 5 total)
                    const smartCandidatesRaw = Array.isArray(response.data.candidates) ? response.data.candidates.slice(0, 5) : [];
                    const smartPrimary = response.data.shortcode;
                    if (!smartCandidatesRaw.includes(smartPrimary)) {
                        smartCandidatesRaw.unshift(smartPrimary);
                    }

                    // Deduplicate against existing candidates
                    const existing = new Set(self.suggestionCandidates);
                    const smartCandidates = smartCandidatesRaw.filter(c => !existing.has(c));

                    if (!smartCandidates.length) {
                        return;
                    }

                    // Insert right after the current index snapshot (so they appear next in cycle)
                    const insertPos = Math.min((currentIndexSnapshot || 0) + 1, self.suggestionCandidates.length);
                    self.suggestionCandidates.splice(insertPos, 0, ...smartCandidates);
                    TPDebug.log('suggestion', 'Inserted SMART candidates at', insertPos, 'Updated list:', self.suggestionCandidates);
                },
                error: function(xhr, status, error) {
                    TPDebug.error('suggestion', 'SMART suggestion AJAX error', { status, error, xhr });
                }
            });
        },

        /**
         * Cycle through cached candidates without hitting the API.
         */
        cycleSuggestion: function() {
            if (!this.suggestionCandidates.length) {
                return;
            }

            // If current suggestion hasn't been applied yet, apply it first
            if (!this.suggestionApplied) {
                const current = this.suggestionCandidates[this.suggestionIndex];
                this.$customKeyInput.val(current);
                this.suggestionApplied = true;
                TPDebug.log('suggestion', 'Applied pending suggestion at index', this.suggestionIndex, 'value:', current);
            } else {
                // Advance index and wrap
                this.suggestionIndex = (this.suggestionIndex + 1) % this.suggestionCandidates.length;
                const next = this.suggestionCandidates[this.suggestionIndex];
                this.$customKeyInput.val(next);
                TPDebug.log('suggestion', 'Cycled suggestion to index', this.suggestionIndex, 'value:', next);
            }

            // Show hint to keep clicking for more suggestions
            this.$suggestionMessage.html('<i class="fa-solid fa-lightbulb me-2"></i>Keep clicking to see more suggestions');
            this.$suggestionMessage.removeClass('text-muted text-danger text-warning').addClass('text-success');
            this.$suggestionMessage.show();
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
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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

            // Skip re-validation if URL hasn't changed and is already valid
            if (value === this.lastValidatedUrl && this.isValid) {
                TPDebug.log('validation', '🔵 [BLUR] Skipping validation - URL unchanged and already valid:', value);
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
            TPDebug.log('process', '[PASTE DEBUG] processUrl called with:', value);

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

            // Check if URL has a domain structure (contains a dot followed by TLD, optionally followed by path/query)
            const hasDomainStructure = /\.[a-z]{2,}(\/|$|\?|#|:)/i.test(value.trim());

            // Trigger online validation if URLValidator is available and URL has valid domain structure
            if (this.urlValidator && this.debouncedValidate && value.trim().length > 0 && hasDomainStructure) {
                TPDebug.log('validation', 'Triggering URL validation for:', value.trim());
                TPDebug.log('validation', 'URLValidator exists:', !!this.urlValidator);
                TPDebug.log('validation', 'debouncedValidate exists:', !!this.debouncedValidate);

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
                TPDebug.log('validation', 'Calling debouncedValidate...');
                this.debouncedValidate(
                    value.trim(),
                    null,  // Don't let URLValidator apply styles directly
                    null   // Don't let URLValidator apply message directly
                );
            } else {
                TPDebug.log('validation', 'Skipping validation - urlValidator:', !!this.urlValidator, 'debouncedValidate:', !!this.debouncedValidate, 'valueLength:', value.trim().length, 'hasDomainStructure:', hasDomainStructure);
            }

            TPDebug.log('process', '[PASTE DEBUG] processUrl completed');
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
            if (this.errorDismissTimer) {
                clearTimeout(this.errorDismissTimer);
                this.errorDismissTimer = null;
            }

            const errorHtml = `
                <div class="d-flex align-items-start gap-2">
                    <i class="fas fa-exclamation-circle mt-1"></i>
                    <div class="flex-grow-1">${message}</div>
                    <button type="button" class="btn-close ms-auto" aria-label="Close"></button>
                </div>
            `;

            const self = this;
            this.$errorMessage
                .html(errorHtml)
                .removeClass('d-none')
                .hide()
                .fadeIn(300);

            const dismissError = function() {
                if (self.errorDismissTimer) {
                    clearTimeout(self.errorDismissTimer);
                    self.errorDismissTimer = null;
                }
                self.$errorMessage.fadeOut(300, function() {
                    self.hideError();
                });
            };

            this.$errorMessage
                .find('.btn-close')
                .off('click.tpErrorDismiss')
                .on('click.tpErrorDismiss', function() {
                    dismissError();
                });

            this.errorDismissTimer = setTimeout(dismissError, 10000);
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
            this.$submitIcon.removeClass('fa-link').addClass('fa-save');

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
            this.$submitIcon.removeClass('fa-edit').addClass('fa-save');

            this.$destinationInput.val('');
            this.$customKeyInput.val('');
            this.$destinationInput.removeClass('is-valid is-invalid');

            this.hideResult();

            // Hide usage stats when switching to create mode
            $('#tp-usage-stats').hide();

            if (this.$validationMessage) {
                this.$validationMessage.hide();
            }

            this.currentRecord = null;
            this.isValid = false;
            this.lastValidatedUrl = '';
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
            this.stopUsagePolling();
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
            const self = this;

            // Generate QR code using shared utility
            this.qrCode = window.TPQRUtils.generate(this.$qrContainer, url);

            if (this.qrCode) {
                // Show QR section after generation
                setTimeout(function() {
                    self.showQRSection();
                }, 100);
            } else {
                TPDebug.error('qr', 'QR Code generation failed');
            }
        },

        /**
         * Capture screenshot of destination URL
         */
        captureScreenshot: function(url) {
            TPDebug.log('screenshot', 'TP Link Shortener: Capturing screenshot for:', url);

            // Find the screenshot preview element
            const $screenshotPreview = $('.tp-screenshot-preview');
            const $screenshotImg = $screenshotPreview.find('.tp-screenshot-img');

            if (!$screenshotImg.length) {
                TPDebug.warn('screenshot', 'TP Link Shortener: Screenshot preview element not found');
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
                    TPDebug.log('screenshot', 'TP Link Shortener: Screenshot capture response:', response);

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
                            TPDebug.log('screenshot', 'TP Link Shortener: Screenshot loaded from cache');
                        }
                        if (response.data.response_time_ms) {
                            TPDebug.log('screenshot', 'TP Link Shortener: Screenshot response time:', response.data.response_time_ms + 'ms');
                        }
                    } else {
                        TPDebug.error('screenshot', 'TP Link Shortener: Screenshot capture failed:', response.data ? response.data.message : 'Unknown error');
                        // Keep spinner on error - no fallback image
                        $screenshotPreview.addClass('tp-screenshot-error');
                    }
                },
                error: function(xhr, status, error) {
                    TPDebug.error('screenshot', 'TP Link Shortener: Screenshot AJAX error:', error);
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
                    TPDebug.error('clipboard', 'Failed to copy:', err);
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
         * Show QR Code options dialog
         */
        showQRDialog: function() {
            if (!this.qrCode) {
                return;
            }
            this.$qrDialogOverlay.show();
        },

        /**
         * Hide QR Code options dialog
         */
        hideQRDialog: function() {
            this.$qrDialogOverlay.hide();
        },

        /**
         * Handle click on dialog overlay (close if clicking outside dialog)
         */
        handleQRDialogOverlayClick: function(e) {
            if (e.target === this.$qrDialogOverlay[0]) {
                this.hideQRDialog();
            }
        },

        /**
         * Download QR Code
         */
        downloadQRCode: function() {
            if (!this.qrCode) {
                return;
            }

            window.TPQRUtils.download(this.$qrContainer);
            this.hideQRDialog();
        },

        /**
         * Open short link in new tab
         */
        openQRCode: function() {
            const shortUrl = this.$shortUrlOutput.attr('href');
            if (!shortUrl) {
                return;
            }

            window.open(shortUrl, '_blank');
            this.hideQRDialog();
        },

        /**
         * Copy QR Code to clipboard
         */
        copyQRCode: function() {
            const self = this;

            if (!this.qrCode) {
                return;
            }

            window.TPQRUtils.copyToClipboard(
                this.$qrContainer,
                function() {
                    // Show success feedback
                    const $btn = self.$qrCopyBtn;
                    const originalHtml = $btn.html();
                    $btn.html('<i class="fas fa-check"></i><span>Copied!</span>');
                    setTimeout(function() {
                        $btn.html(originalHtml);
                        self.hideQRDialog();
                    }, 1000);
                },
                function(err) {
                    TPDebug.error('qr', 'Failed to copy QR code:', err);
                    self.hideQRDialog();
                }
            );
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
                if (tpAjax.enableExpiryTimer && storedData.expiration) {
                    this.startExpiryCountdown(storedData.expiration);
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
            TPDebug.log('search', '=== WAIT FOR FINGERPRINT THEN SEARCH START ===');
            TPDebug.log('search', 'fpPromise exists:', !!this.fpPromise);

            try {
                // Wait for fingerprint to be ready
                if (!this.fpPromise) {
                    TPDebug.warn('search', 'FingerprintJS not initialized, cannot search');
                    TPDebug.log('search', '=== WAIT FOR FINGERPRINT THEN SEARCH END (NOT INITIALIZED) ===');
                    return;
                }

                TPDebug.log('search', 'Waiting for fingerprint to load...');
                const fp = await this.fpPromise;
                TPDebug.log('search', 'FingerprintJS loaded successfully:', fp);

                // Now get the actual fingerprint
                TPDebug.log('search', 'Getting visitor fingerprint...');
                const result = await fp.get();
                const fingerprint = result.visitorId;
                TPDebug.log('search', 'Fingerprint obtained:', fingerprint);

                // Now search with the fingerprint
                this.searchByFingerprint(fingerprint);
                TPDebug.log('search', '=== WAIT FOR FINGERPRINT THEN SEARCH END (SUCCESS) ===');
            } catch (error) {
                TPDebug.error('search', 'Error waiting for fingerprint:', error);
                TPDebug.log('search', '=== WAIT FOR FINGERPRINT THEN SEARCH END (ERROR) ===');
            }
        },

        /**
         * Search for user's most recent link by fingerprint
         */
        searchByFingerprint: function(fingerprint) {
            TPDebug.log('search', '=== SEARCH BY FINGERPRINT START ===');
            const self = this;

            TPDebug.log('search', 'Fingerprint provided:', fingerprint);
            TPDebug.log('search', 'Fingerprint type:', typeof fingerprint);
            TPDebug.log('search', 'Fingerprint length:', fingerprint ? fingerprint.length : 0);

            if (!fingerprint) {
                TPDebug.error('search', 'ERROR: Fingerprint not provided, skipping search');
                TPDebug.log('search', '=== SEARCH BY FINGERPRINT END (NO FINGERPRINT) ===');
                return;
            }

            TPDebug.log('search', 'Preparing AJAX request');
            TPDebug.log('search', 'AJAX URL:', tpAjax.ajaxUrl);
            TPDebug.log('search', 'Nonce:', tpAjax.nonce);
            TPDebug.log('search', 'Action: tp_search_by_fingerprint');

            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_search_by_fingerprint',
                    nonce: tpAjax.nonce,
                    fingerprint: fingerprint
                },
                beforeSend: function() {
                    TPDebug.log('search', 'AJAX request being sent...');
                },
                success: function(response) {
                    TPDebug.log('search', 'AJAX response received');
                    TPDebug.log('search', 'Response:', JSON.stringify(response, null, 2));
                    TPDebug.log('search', 'Response success:', response.success);
                    TPDebug.log('search', 'Response has data:', !!response.data);
                    TPDebug.log('search', 'Response has record:', response.data ? !!response.data.record : false);

                    if (response.success && response.data.record) {
                        TPDebug.log('search', 'Record found, displaying existing link');
                        const record = response.data.record;
                        TPDebug.log('search', 'Record details:', JSON.stringify(record, null, 2));
                        self.displayExistingLink(record);
                    } else {
                        TPDebug.log('search', 'No existing links found for this fingerprint');
                        if (response.data) {
                            TPDebug.log('search', 'Response data:', response.data);
                        }
                    }
                    TPDebug.log('search', '=== SEARCH BY FINGERPRINT END (SUCCESS) ===');
                },
                error: function(xhr, status, error) {
                    TPDebug.error('search', 'AJAX ERROR');
                    TPDebug.error('search', 'Status:', status);
                    TPDebug.error('search', 'Error:', error);
                    TPDebug.error('search', 'XHR status:', xhr.status);
                    TPDebug.error('search', 'Response text:', xhr.responseText);
                    TPDebug.log('search', '=== SEARCH BY FINGERPRINT END (ERROR) ===');
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

            // Display usage stats if available
            TPDebug.log('ui', 'Record usage data:', record.usage);
            TPDebug.log('ui', 'Full record:', record);
            this.displayUsageStats(record.usage || { qr: 0, regular: 0 });

            // Start usage polling for returning visitors
            this.startUsagePolling();

            // If link has expiry, start countdown
            if (record.expires_at) {
                this.startExpiryCountdown(record.expires_at);
            }

            // Switch to update mode
            this.switchToUpdateMode();
        },

        /**
         * Handle edit item event from dashboard
         * Populates the form with the clicked item's data
         */
        handleDashboardEditItem: function(event, item) {
            TPDebug.log('ui', '=== DASHBOARD EDIT ITEM EVENT ===');
            TPDebug.log('ui', 'Item received:', item);

            if (!item) {
                TPDebug.error('ui', 'No item data received');
                return;
            }

            // Transform dashboard item to match the record format expected by displayExistingLink
            const record = {
                mid: item.mid,
                domain: item.domain,
                tpKey: item.tpKey,
                destination: item.destination,
                usage: item.usage || { qr: 0, regular: 0 },
                expires_at: item.expires_at || null,
                notes: item.notes || ''
            };

            TPDebug.log('ui', 'Transformed record:', record);

            // Use the existing displayExistingLink method to populate the form
            this.displayExistingLink(record);

            TPDebug.log('ui', '=== DASHBOARD EDIT ITEM COMPLETE ===');
        },

        /**
         * Display usage statistics (QR scans and direct clicks)
         */
        displayUsageStats: function(usage) {
            const $statsContainer = $('#tp-usage-stats');
            const $qrValue = $('#tp-usage-qr');
            const $regularValue = $('#tp-usage-regular');

            // Always show stats for returning visitors, even if values are 0
            if (usage) {
                // Update the values
                $qrValue.text(usage.qr || 0);
                $regularValue.text(usage.regular || 0);

                // Show the stats container
                $statsContainer.show();

                TPDebug.log('ui', 'Usage stats displayed:', usage);
            } else {
                // Hide only if no usage object at all
                $statsContainer.hide();
                TPDebug.log('ui', 'No usage stats object to display');
            }
        },

        /**
         * Start polling for usage stats updates (interval configurable in admin)
         */
        startUsagePolling: function() {
            // Clear any existing polling timer
            this.stopUsagePolling();

            const self = this;
            const pollingInterval = tpAjax.usagePollingInterval || 5000;
            TPDebug.log('ui', 'Starting usage stats polling (every ' + (pollingInterval / 1000) + ' seconds)');

            this.usagePollingTimer = setInterval(async function() {
                TPDebug.log('ui', 'Usage polling tick - fetching fingerprint...');

                // Get fingerprint and search for updated stats
                const fingerprint = await self.getFingerprint();
                if (fingerprint) {
                    self.pollUsageByFingerprint(fingerprint);
                } else {
                    TPDebug.warn('ui', 'Usage polling: could not get fingerprint');
                }
            }, pollingInterval);
        },

        /**
         * Stop usage stats polling
         */
        stopUsagePolling: function() {
            if (this.usagePollingTimer) {
                clearInterval(this.usagePollingTimer);
                this.usagePollingTimer = null;
                TPDebug.log('ui', 'Usage stats polling stopped');
            }
        },

        /**
         * Poll for usage stats by fingerprint (only updates usage, doesn't redisplay link)
         */
        pollUsageByFingerprint: function(fingerprint) {
            TPDebug.log('ui', 'Polling usage for fingerprint:', fingerprint);
            const self = this;

            $.ajax({
                url: tpAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_search_by_fingerprint',
                    nonce: tpAjax.nonce,
                    fingerprint: fingerprint
                },
                success: function(response) {
                    TPDebug.log('ui', 'Usage poll response:', response);
                    if (response.success && response.data.record && response.data.record.usage) {
                        self.displayUsageStats(response.data.record.usage);
                    }
                },
                error: function(xhr, status, error) {
                    TPDebug.error('ui', 'Usage poll error:', error);
                }
            });
        },

        /**
         * Start expiry countdown timer
         */
        startExpiryCountdown: function(expiresAt) {
            // Clear any existing timer
            this.stopExpiryCountdown();

            const expiryDate = new Date(expiresAt + 'Z');
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
