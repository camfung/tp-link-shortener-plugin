# Development Guide

## Git Commit Guidelines

### Commit Strategy
We follow a **"commit after each change"** approach for clear project history and easier debugging:

1. **One Change Per Commit**
   - Each commit should represent a single, logical change
   - Makes it easier to understand what changed and why
   - Simplifies reverting changes if needed

2. **Commit Message Format**
   ```
   Brief summary of change (50 chars or less)

   Detailed explanation of what changed and why:
   - Bullet points for multiple changes
   - Explain the reasoning
   - Note any side effects or dependencies

   🤖 Generated with [Claude Code](https://claude.com/claude-code)

   Co-Authored-By: Claude <noreply@anthropic.com>
   ```

3. **When to Commit**
   - After implementing a new feature component
   - After fixing a bug
   - After refactoring code
   - After updating documentation
   - After adding/updating tests
   - Before switching to a different task

4. **Commit Examples from This Project**
   - ✅ Good: "Add Vitest testing framework configuration"
   - ✅ Good: "Fix UID handling to preserve local storage on page refresh"
   - ✅ Good: "Move result and QR sections above naming guidance"
   - ❌ Bad: "Various changes and fixes"
   - ❌ Bad: "WIP"

### Branch Naming
- Use descriptive branch names: `feature/feature-name`
- Example: `feature/local-storage-returning-visitors`

---

## Local Storage & Returning Visitors Feature

### Overview
This feature implements local storage for trial users' shortcodes and provides a seamless returning visitor experience.

### Requirements Implemented

#### 1. Local Storage
Stores the following data locally:
- **Active shortcode** (the generated key)
- **Destination URL** (where the shortcode redirects)
- **Expiration time** (24-hour countdown for trial users)
- **Unique session ID** (for tracking)
- **User ID** (if available, for validation)

#### 2. Returning Visitor Behavior
When the IntroForm loads and local storage data exists:

**For Non-Logged-In (Trial) Users:**
- Form inputs are pre-filled with stored data
- Form is disabled (prevents duplicate link creation)
- Shows countdown timer with remaining time
- Displays message: "Your trial key is active! Time remaining: [countdown]. Register to keep it active."
- Result section and QR code are displayed
- When link expires, storage is cleared and form re-enables

**For Logged-In Users:**
- Form inputs are pre-filled with stored data
- Form remains **enabled** (can create new links)
- No countdown timer shown
- No restrictions on creating new links
- Full access to all features

#### 3. UI/UX Enhancements
- **Animated QR Code Section**: Slides down smoothly below the result section
- **Success Message**: Only appears when creating a new link (not for returning visitors)
- **Form Reordering**: Result and QR sections appear above naming guidance
- **Input Icons**: Link icon for destination, lightbulb button for custom key

---

## Testing

### Unit Tests
- **Framework**: Vitest with jsdom
- **Coverage**: 49 passing tests
  - 27 tests for storage service
  - 22 tests for returning visitor logic

### Test Files
- `tests/storage-service.test.js` - Storage operations, expiration, session management
- `tests/returning-visitor.test.js` - Validation scenarios, form states, edge cases

### Running Tests
```bash
npm test                 # Run all tests
npm run test:ui          # Run with UI
npm run test:coverage    # Generate coverage report
```

---

## Architecture

### File Structure
```
tp-link-shortener-plugin/
├── assets/
│   ├── js/
│   │   ├── frontend.js                    # Main frontend logic
│   │   ├── storage-service.js             # ES module version (for tests)
│   │   └── storage-service-standalone.js  # WordPress-compatible version
│   └── css/
│       └── frontend.css                   # Styles including animations
├── includes/
│   ├── class-tp-api-handler.php           # AJAX handlers
│   ├── class-tp-assets.php                # Script/style enqueuing
│   └── TrafficPortal/
│       └── TrafficPortalApiClient.php     # API client with validation
├── templates/
│   └── shortcode-template.php             # Form HTML structure
├── tests/
│   ├── setup.js                           # Vitest configuration
│   ├── storage-service.test.js            # Storage tests
│   └── returning-visitor.test.js          # Visitor logic tests
└── vitest.config.js                       # Test framework config
```

### Key Components

#### 1. Storage Service (`storage-service-standalone.js`)
- **Purpose**: Manage localStorage operations
- **Methods**:
  - `saveShortcodeData()` - Store link data
  - `getShortcodeData()` - Retrieve link data
  - `isExpired()` - Check expiration status
  - `getTimeRemaining()` - Calculate remaining time
  - `clearShortcodeData()` - Clear link data
  - `generateSessionId()` - Create unique session ID

#### 2. Frontend Logic (`frontend.js`)
- **Purpose**: Handle UI interactions and returning visitor detection
- **Key Functions**:
  - `checkReturningVisitor()` - Detect stored links on page load
  - `showStoredLink()` - Display stored link with appropriate UI
  - `disableForm()` / `enableForm()` - Control form state
  - `startCountdown()` - Run expiration timer
  - `generateQRCode()` - Create and animate QR code display

#### 3. API Validation (`class-tp-api-handler.php`)
- **Purpose**: Validate stored keys (optional, not currently used)
- **Endpoint**: `ajax_validate_key`
- **Note**: Current implementation trusts local storage without API validation

---

## Design Decisions

### 1. No API Validation on Page Load
**Decision**: Display stored links without validating against the API

**Reasoning**:
- Valid keys are not publicly queryable (security by design)
- Reduces unnecessary API calls
- Trusts local storage data
- Better performance and UX
- API validation endpoint exists but is not used for returning visitors

### 2. Different Behavior for Logged-In vs Trial Users
**Decision**: Disable form for trial users, enable for logged-in users

**Reasoning**:
- Trial users limited to one active link (24-hour expiration)
- Logged-in users get unlimited link creation
- Encourages registration while providing trial value
- Clear value proposition for premium membership

### 3. Countdown Timer Only for Trial Users
**Decision**: Show countdown only for non-logged-in users

**Reasoning**:
- Logged-in users' links don't expire
- Countdown creates urgency for trial users
- Reinforces the benefit of registration

### 4. Client-Side Storage Instead of Database
**Decision**: Use localStorage instead of server-side storage for trial users

**Reasoning**:
- No authentication required for trial users
- Faster, no server round-trips
- Works offline
- Automatic cleanup when expired
- Reduces database load

---

## Commits Made (in order)

1. `Add Vitest testing framework configuration` - Set up testing infrastructure
2. `Implement local storage service for shortcode data` - Core storage functionality
3. `Add validation API endpoint for stored keys` - API validation (available but unused)
4. `Implement returning visitor detection and UI updates` - Main feature logic
5. `Add comprehensive unit tests for returning visitor logic` - Complete test coverage
6. `Add node_modules to gitignore` - Clean git tracking
7. `Fix UID handling to preserve local storage on page refresh` - Bug fix for storage persistence
8. `Simplify returning visitor logic - remove unnecessary API validation` - Streamlined approach
9. `Add animated QR code section below form` - UX enhancement
10. `Move result and QR sections above naming guidance` - UI reorganization
11. `Disable form when valid link exists in local storage` - Trial user restrictions
12. `Fix spacing in returning visitor message` - Visual polish
13. `Use Bootstrap margin classes for proper spacing in message` - Consistent styling
14. `Remove 'generate a new key' option from returning visitor message` - Simplified UX
15. `Move success message into its own separate div` - Component separation
16. `Show success message only when creating new link` - Context-aware messaging
17. `Remove 'QR Code' label text from QR section` - Minimalist design
18. `Pre-fill form inputs with stored link data for returning visitors` - Transparency
19. `Add lightbulb icon to custom shortcode input` - Visual consistency
20. `Convert lightbulb icon to clickable button` - Interactive element
21. `Move lightbulb button to left side of custom key input` - Layout adjustment
22. `Enable form inputs for logged-in users with stored links` - Premium user experience

---

## Future Enhancements

### Potential Improvements
1. **Lightbulb Button Functionality**
   - Add click handler to suggest shortcode ideas
   - Use AI/algorithm to generate relevant suggestions based on destination URL

2. **Multi-Link Support for Logged-In Users**
   - Store multiple links in localStorage
   - Display list of recent links
   - Quick access to previously created links

3. **Analytics Integration**
   - Track returning visitor rates
   - Monitor conversion from trial to registered
   - Measure countdown timer effectiveness

4. **Progressive Enhancement**
   - Graceful degradation if localStorage unavailable
   - Alternative storage methods (cookies, sessionStorage)

5. **Link Management Dashboard**
   - View all active links
   - Edit/delete links
   - Analytics per link

---

## Troubleshooting

### Common Issues

**Issue**: Local storage data disappears on page refresh
- **Cause**: UID mismatch in validation
- **Solution**: Ensure UID is properly stored and retrieved (fixed in commit 7)

**Issue**: Form stays disabled for logged-in users
- **Cause**: Login status not passed to JavaScript
- **Solution**: Added `isLoggedIn` to localized script (fixed in commit 22)

**Issue**: QR code doesn't animate
- **Cause**: CSS classes not applied or timing issue
- **Solution**: Check `tp-slide-down` class and verify CSS is loaded

**Issue**: Countdown timer not updating
- **Cause**: Expiration timestamp format issue
- **Solution**: Ensure timestamps are stored as milliseconds (parseInt)

---

## Contributing

When adding new features:
1. Create a new feature branch
2. Write tests first (TDD approach when possible)
3. Implement the feature
4. Commit after each logical change
5. Run tests before committing: `npm test`
6. Write descriptive commit messages
7. Update this documentation if needed

---

## Resources

- [Vitest Documentation](https://vitest.dev/)
- [Bootstrap 5 Utilities](https://getbootstrap.com/docs/5.3/utilities/)
- [WordPress AJAX](https://developer.wordpress.org/plugins/javascript/ajax/)
- [localStorage API](https://developer.mozilla.org/en-US/docs/Web/API/Window/localStorage)
