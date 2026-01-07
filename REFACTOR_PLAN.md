# Plan: Refactor Update Link to Use Main Form

## Goal
Change the link update functionality to reuse the main form instead of showing a separate update section. When an anonymous user returns and has an existing link (found via IP search), the form should switch to "update mode" with a message asking if they want to update.

## Current Understanding

### IP Search Flow (TrafficPortalApiClient.php:396-454)
- Backend calls `searchByIp($ipAddress, 0, '')` - sends uid=0, empty token
- Makes GET request to `/items/by-ip/{ipAddress}`
- Returns array with 'source' containing 'records' array
- Most recent record is at index 0

### Frontend Flow (frontend.js)
1. **Page load** (non-logged-in): `searchByIP()` called at line 68
2. **If record found**: `displayExistingLink(record)` at line 1193-1224
   - Pre-fills destination input with current URL
   - Shows result section
   - Stores in `this.currentRecord`
   - Shows separate update button
3. **Current update**: Separate `#tp-update-section` with own input field

### Current Issues
- Two form sections: main form + separate update section
- Update section has duplicate destination input (`#tp-update-destination`)
- No explicit form mode tracking
- Inconsistent UX between create and update

## Solution Design

### State Management
- Add `formMode` property: 'create' or 'update'
- Reuse existing `currentRecord` to store link being updated

### UI Changes
**Remove:**
- Entire separate update section (`#tp-update-section`)

**Add:**
- Update mode message with info and "Create new link instead" button
- Dynamic submit button text/icon that changes based on mode

### Key Function Changes

**Mode Switching:**
- `switchToUpdateMode()` - Changes button, shows message, hides custom key
- `switchToCreateMode()` - Resets form, clears data, switches button back

**Form Submission:**
- `handleSubmit()` - Routes to submitCreate() or submitUpdate() based on mode
- `submitCreate()` - Extracted create logic (calls tp_create_link)
- `submitUpdate()` - New function (calls tp_update_link) using main form input
- `handleCreateSuccess()` - Renamed, switches to update mode after creation
- `handleUpdateSuccess()` - New function, stays in update mode

**Modified:**
- `displayExistingLink()` - Switches to update mode (not separate button)

### User Flows

**New user creates link:**
1. Enters URL → Submit → Success
2. Form switches to update mode
3. Can modify URL and update, or click "Create new link"

**Returning user (IP finds existing):**
1. Form pre-filled, switched to update mode
2. Can update or switch to create mode

## Implementation Plan

### Files to Modify

**1. templates/shortcode-template.php**
- **Remove**: Lines 200-216 (entire `#tp-update-section`)
- **Add** (after line 123, before submit button):
  ```html
  <!-- Update Mode Message (hidden by default) -->
  <div id="tp-update-mode-message" class="alert alert-info d-none mb-4">
      <div class="d-flex align-items-start gap-3">
          <i class="fas fa-info-circle fs-5 mt-1"></i>
          <div class="flex-grow-1">
              <strong>Update your existing link</strong>
              <p class="mb-0 mt-1">You can update the destination URL for your existing short link below.</p>
          </div>
      </div>
      <button type="button" class="btn btn-sm tp-btn tp-btn-secondary mt-2" id="tp-switch-to-create-btn">
          <i class="fas fa-plus me-2"></i>
          Create new link instead
      </button>
  </div>
  ```
- **Modify** submit button (line ~126) to make text dynamic:
  ```html
  <button type="submit" class="btn tp-btn tp-btn-primary tp-cta-button w-100" id="tp-submit-btn">
      <i class="fas fa-link me-2" id="tp-submit-icon"></i>
      <span id="tp-submit-text">Save the link and it never expires</span>
  </button>
  ```

**2. assets/js/frontend.js**

**Add to state (around line 31):**
```javascript
formMode: 'create', // 'create' or 'update'
```

**Modify `cacheElements()` (around line 75):**
- Add: `this.$updateModeMessage = $('#tp-update-mode-message');`
- Add: `this.$switchToCreateBtn = $('#tp-switch-to-create-btn');`
- Add: `this.$submitText = $('#tp-submit-text');`
- Add: `this.$submitIcon = $('#tp-submit-icon');`
- Remove: `this.$updateSection`, `this.$updateDestinationInput`, `this.$updateBtn`

**Modify `bindEvents()` (around line 185):**
- Add: `this.$switchToCreateBtn.on('click', this.switchToCreateMode.bind(this));`
- Remove: `this.$updateBtn.on('click', this.updateLink.bind(this));`

**Replace `handleSubmit()` (line 218):**
```javascript
handleSubmit: function(e) {
    e.preventDefault();

    // Route based on form mode
    if (this.formMode === 'update') {
        this.submitUpdate();
    } else {
        this.submitCreate();
    }
}
```

**Extract create logic into new `submitCreate()` function:**
- Move all logic from current `handleSubmit()` into this new function
- Keep same AJAX call to `tp_create_link`

**Add new `submitUpdate()` function:**
```javascript
submitUpdate: function() {
    if (!this.currentRecord || !this.currentRecord.mid) {
        this.showError('No link to update.');
        return;
    }

    const newDestination = this.$destinationInput.val().trim();

    if (!newDestination) {
        this.showError('Please enter a destination URL.');
        return;
    }

    if (!this.validateUrl(newDestination)) {
        this.showError(tpAjax.strings.invalidUrl);
        return;
    }

    if (!this.isValid) {
        this.showError('Please enter a valid and accessible URL.');
        this.$destinationInput.addClass('is-invalid');
        return;
    }

    this.setLoadingState(true);
    this.hideError();

    $.ajax({
        url: tpAjax.ajaxUrl,
        type: 'POST',
        data: {
            action: 'tp_update_link',
            nonce: tpAjax.nonce,
            mid: this.currentRecord.mid,
            destination: newDestination,
            domain: this.currentRecord.domain
        },
        success: this.handleUpdateSuccess.bind(this),
        error: this.handleError.bind(this),
        complete: function() {
            this.setLoadingState(false);
        }.bind(this)
    });
}
```

**Rename `handleSuccess()` to `handleCreateSuccess()` and modify:**
- Change: Remove form clearing (keep destination in input)
- Change: Call `this.switchToUpdateMode()` instead of `this.showUpdateSection()`

**Add new `handleUpdateSuccess()` function:**
```javascript
handleUpdateSuccess: function(response) {
    if (response.success) {
        this.showSnackbar('Link updated successfully!');

        const newDestination = this.$destinationInput.val().trim();
        this.currentRecord.destination = newDestination;

        this.captureScreenshot(newDestination);

        // Update localStorage if applicable
        if (window.TPStorageService && window.TPStorageService.isAvailable()) {
            const storedData = window.TPStorageService.getShortcodeData();
            if (storedData && storedData.shortcode === this.currentRecord.key) {
                window.TPStorageService.saveShortcodeData({
                    ...storedData,
                    destination: newDestination
                });
            }
        }
    } else {
        this.showError(response.data.message || 'Failed to update link.');
    }
}
```

**Add new `switchToUpdateMode()` function:**
```javascript
switchToUpdateMode: function() {
    this.formMode = 'update';

    this.$submitText.text('Update Link');
    this.$submitIcon.removeClass('fa-link').addClass('fa-edit');

    this.$updateModeMessage.removeClass('d-none');

    if (this.$customKeyGroup && this.$customKeyGroup.length) {
        this.$customKeyGroup.slideUp(300);
    }

    // Trigger validation for pre-filled URL
    if (this.urlValidator && this.debouncedValidate) {
        const currentUrl = this.$destinationInput.val().trim();
        if (currentUrl) {
            this.debouncedValidate(currentUrl, null, null);
        }
    }
}
```

**Add new `switchToCreateMode()` function:**
```javascript
switchToCreateMode: function() {
    this.formMode = 'create';

    this.$submitText.text('Save the link and it never expires');
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
}
```

**Modify `displayExistingLink()` (line 1193):**
- Change: Call `this.switchToUpdateMode()` instead of `this.showUpdateButton()`

**Modify `hideResult()` (line 745):**
- Add: Check if in update mode and switch to create mode when hiding result

**Remove these functions:**
- `showUpdateSection()`
- `hideUpdateSection()`
- `updateLink()`
- `showUpdateButton()`

**3. assets/css/frontend.css** (if it exists)
- Add styles for secondary button
- Add styles for update mode message

### Critical Behavioral Changes

1. **After link creation**: Form stays populated, switches to update mode
2. **After link update**: Form stays in update mode with updated data
3. **IP search finds link**: Form pre-filled, switched to update mode
4. **Click "Create new link"**: Form clears, switches to create mode
5. **Custom key field**: Hidden in update mode, shown in create mode (when valid URL)

### Testing Checklist
- [ ] Create link → form switches to update mode
- [ ] Update link → stays in update mode, screenshot updates
- [ ] Click "Create new link" → switches to create mode
- [ ] IP search finds link → starts in update mode
- [ ] URL validation works in both modes
- [ ] Submit button text/icon changes correctly
- [ ] localStorage syncs after updates
