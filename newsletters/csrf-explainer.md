# CSRF Protection Explained

## What is CSRF?

**Cross-Site Request Forgery (CSRF)** is a web security vulnerability that allows an attacker to trick a user's browser into making unwanted requests to a web application where the user is authenticated.

### Real-World Attack Scenario

Imagine this attack on our newsletter system **WITHOUT** CSRF protection:

1. **You're logged into the newsletter admin panel** at `https://newsletter.gov.bc.ca`
2. **Attacker sends you a malicious link** via email or chat
3. **You click the link** which takes you to `https://evil-site.com/attack.html`
4. **The evil page contains hidden code:**
   ```html
   <form id="deleteForm" action="https://newsletter.gov.bc.ca/newsletters/index.php" method="POST">
       <input type="hidden" name="action" value="delete">
       <input type="hidden" name="newsletter_id" value="1">
       <input type="hidden" name="confirm_delete" value="yes">
   </form>
   <script>
       document.getElementById('deleteForm').submit();
   </script>
   ```
5. **Your browser automatically sends the request** to the newsletter site
6. **Because you're already logged in**, your session cookies are included
7. **The server thinks it's a legitimate request from you** and deletes the newsletter with all subscribers!

**You never intended to delete anything** - the attacker forced your browser to do it.

---

## How CSRF Protection Works

CSRF protection prevents this attack by requiring a **secret token** that only your legitimate session knows about.

### The Token System

```
┌─────────────────────────────────────────────────────────────┐
│                    CSRF Protection Flow                      │
└─────────────────────────────────────────────────────────────┘

1. USER VISITS SITE
   ┌──────────┐                              ┌──────────┐
   │  Browser │─────GET /newsletters────────>│  Server  │
   └──────────┘                              └──────────┘
                                                    │
                                                    ├─ Generate random token
                                                    │  Token: "a8f3c2d1e4b5..."
                                                    │
                                                    └─ Store in session
                                                       $_SESSION['csrf_token']

2. SERVER SENDS PAGE WITH TOKEN
   ┌──────────┐                              ┌──────────┐
   │  Browser │<─────HTML + Hidden Token─────│  Server  │
   └──────────┘                              └──────────┘
      │
      └─ Page contains:
         <form method="POST">
           <input type="hidden" name="csrf_token"
                  value="a8f3c2d1e4b5...">
           <!-- rest of form -->
         </form>

3. USER SUBMITS FORM
   ┌──────────┐                              ┌──────────┐
   │  Browser │─────POST + Token────────────>│  Server  │
   └──────────┘                              └──────────┘
                                                    │
                                                    ├─ Compare tokens:
                                                    │  POST: "a8f3c2d1e4b5..."
                                                    │  SESSION: "a8f3c2d1e4b5..."
                                                    │
                                                    ├─ ✓ Match! Process request
                                                    └─ ✗ No match! Reject (403)

4. ATTACKER TRIES CSRF
   ┌──────────┐      ┌───────────┐           ┌──────────┐
   │  Browser │─────>│ Evil Site │           │  Server  │
   └──────────┘      └───────────┘           └──────────┘
       │                   │
       │                   └─ Evil form submits WITHOUT token
       │                      (attacker doesn't know it!)
       │
       └──────POST (no token or wrong token)────>│
                                                  │
                                                  └─ ✗ REJECTED! (403 Forbidden)
```

---

## Implementation in Our Code

### Step 1: Token Generation (inc/lsapp.php)

When a user's session starts, we generate a secure random token:

```php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    // Example: "a8f3c2d1e4b5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1"
}
```

**Why 32 bytes?**
- Creates a 64-character hexadecimal string
- 256 bits of entropy = 2^256 possible values
- Cryptographically impossible to guess

**Why `random_bytes()`?**
- Uses cryptographically secure random number generator (CSPRNG)
- Not predictable like `rand()` or `mt_rand()`

---

### Step 2: Helper Functions (inc/lsapp.php)

#### Get Token
```php
function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? '';
}
```
Retrieves the token from the session.

#### Output Token Field
```php
function csrfField() {
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}
```
Outputs a hidden form field containing the token. **Must be called in every form!**

#### Validate Token
```php
function validateCsrfToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
```

**Why `hash_equals()`?**
- Constant-time comparison (prevents timing attacks)
- Regular `==` or `===` could leak information via timing differences

#### Require Valid Token
```php
function requireCsrfToken() {
    if (!validateCsrfToken()) {
        http_response_code(403);
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}
```
Enforces token validation or terminates the request.

---

### Step 3: Add Token to Forms

**Every form that makes state-changing operations must include the token:**

```php
<form method="post" action="">
    <?php csrfField(); ?>  <!-- Adds hidden token field -->
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="newsletter_id" value="123">
    <button type="submit">Delete Newsletter</button>
</form>
```

**Generated HTML:**
```html
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="a8f3c2d1e4b5f6a7...">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="newsletter_id" value="123">
    <button type="submit">Delete Newsletter</button>
</form>
```

---

### Step 4: Validate on POST Requests

**Every POST handler must validate the token:**

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // MUST be first thing in POST handler!
    requireCsrfToken();

    // Now safe to process the request
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        // ... handle actions
    }
}
```

---

## Files Protected

### 1. **index.php** - Newsletter Management
```php
// Line 25
requireCsrfToken();

// Protected actions:
// - Toggle newsletter active/inactive
// - Delete newsletter
// - Delete newsletter with subscribers
```

### 2. **newsletter_dashboard.php** - Subscriber Management
```php
// Line 95
requireCsrfToken();

// Protected actions:
// - Add new subscriber
// - Unsubscribe email
// - Reactivate subscriber
// - Delete subscriber (admin only)
```

### 3. **send_newsletter.php** - Email Campaigns
```php
// Line 59
requireCsrfToken();

// Protected actions:
// - Preview newsletter
// - Send newsletter to all subscribers
```

### 4. **import_csv.php** - Bulk Import
```php
// Line 48
requireCsrfToken();

// Protected actions:
// - Upload and process CSV file
// - Bulk subscriber import
```

### 5. **newsletter_edit.php** - Configuration
```php
// Line 54
requireCsrfToken();

// Protected actions:
// - Update newsletter settings
// - Change API credentials
// - Modify configuration
```

### 6. **sync_subscriptions.php** - API Sync
```php
// Line 37
requireCsrfToken();

// Protected actions:
// - Trigger manual sync with CHEFs API
```

---

## Attack Prevention Examples

### Example 1: Newsletter Deletion Attack

**Without CSRF Protection:**
```html
<!-- Attacker's evil page -->
<img src="https://newsletter.gov.bc.ca/newsletters/index.php?action=delete&newsletter_id=1" style="display:none">
```
✗ **Would succeed** if user is logged in

**With CSRF Protection:**
```html
<!-- Same attack attempt -->
<img src="https://newsletter.gov.bc.ca/newsletters/index.php?action=delete&newsletter_id=1" style="display:none">
```
✓ **Fails:** GET request (we only accept POST), and even if POST, no valid token

---

### Example 2: Mass Email Attack

**Without CSRF Protection:**
```html
<!-- Attacker's evil page -->
<form id="spam" action="https://newsletter.gov.bc.ca/newsletters/send_newsletter.php" method="POST">
    <input type="hidden" name="action" value="send">
    <input type="hidden" name="subject" value="SPAM MESSAGE">
    <input type="hidden" name="html_body" value="Click here for virus!">
</form>
<script>document.getElementById('spam').submit();</script>
```
✗ **Would send spam to all subscribers**

**With CSRF Protection:**
```html
<!-- Same attack attempt -->
<form id="spam" action="https://newsletter.gov.bc.ca/newsletters/send_newsletter.php" method="POST">
    <input type="hidden" name="action" value="send">
    <!-- Attacker doesn't have the token! -->
    <input type="hidden" name="subject" value="SPAM MESSAGE">
</form>
```
✓ **Fails:** Server responds with `403 Forbidden - CSRF token validation failed`

---

### Example 3: Subscriber Import Attack

**Without CSRF Protection:**
```javascript
// Attacker's evil JavaScript
const formData = new FormData();
formData.append('csv_file', maliciousFile);

fetch('https://newsletter.gov.bc.ca/newsletters/import_csv.php', {
    method: 'POST',
    body: formData,
    credentials: 'include'  // Send cookies
});
```
✗ **Would import malicious data**

**With CSRF Protection:**
```javascript
// Same attack attempt
fetch('https://newsletter.gov.bc.ca/newsletters/import_csv.php', {
    method: 'POST',
    body: formData,
    credentials: 'include'
});
```
✓ **Fails:** No CSRF token in FormData, request rejected

---

## Why CSRF Protection is Critical

### Real-World Impact Without Protection

| Attack | Impact | Severity |
|--------|--------|----------|
| Delete all newsletters | Complete data loss, service disruption | **CRITICAL** |
| Send spam to subscribers | Reputation damage, blacklisting | **CRITICAL** |
| Mass unsubscribe | Loss of entire subscriber base | **CRITICAL** |
| Import malicious data | Database corruption, XSS attacks | **HIGH** |
| Modify configurations | Service takeover, credential theft | **CRITICAL** |
| Trigger excessive syncs | API quota exhaustion, DoS | **MEDIUM** |

### CSRF vs Other Attacks

**CSRF is different from:**

- **XSS (Cross-Site Scripting):** Injects malicious code into your site
  - CSRF: Uses your identity to perform actions
  - XSS: Runs code in victim's browser

- **SQL Injection:** Exploits database queries
  - CSRF: Exploits trust in authenticated user
  - SQL: Exploits trust in user input

- **Session Hijacking:** Steals session cookies
  - CSRF: Doesn't need to steal session (uses existing)
  - Hijacking: Takes over session entirely

---

## Security Best Practices in Our Implementation

### ✓ What We Did Right

1. **Cryptographically Secure Tokens**
   - Using `random_bytes(32)` - 256 bits entropy
   - Not predictable or guessable

2. **Timing-Safe Comparison**
   - Using `hash_equals()` prevents timing attacks
   - Constant-time comparison

3. **Token Per Session**
   - One token per user session
   - Regenerated on new session

4. **Proper Token Placement**
   - Hidden input fields in forms
   - Not in URLs (prevents leaking via Referer header)

5. **Early Validation**
   - Token checked before any processing
   - Fails fast on invalid token

6. **User-Friendly Error Messages**
   - Clear message: "CSRF token validation failed"
   - Instruction: "Please refresh the page and try again"

7. **Comprehensive Coverage**
   - All state-changing operations protected
   - No gaps in protection

### ✓ Additional Security Layers

**Defense in Depth:**
- CSRF protection is one layer
- Also have: authentication, authorization, input validation, SQL injection prevention

**HTTP-Only Cookies (Recommended Addition):**
```php
// In session initialization
ini_set('session.cookie_httponly', 1);  // Prevent JavaScript access
ini_set('session.cookie_secure', 1);    // HTTPS only
ini_set('session.cookie_samesite', 'Strict');  // Extra CSRF protection
```

---

## Testing CSRF Protection

### How to Verify It's Working

**Test 1: Missing Token**
```bash
curl -X POST https://newsletter.gov.bc.ca/newsletters/index.php \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d "action=delete&newsletter_id=1"

Expected: 403 Forbidden - CSRF token validation failed
```

**Test 2: Invalid Token**
```bash
curl -X POST https://newsletter.gov.bc.ca/newsletters/index.php \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d "action=delete&newsletter_id=1&csrf_token=invalid_token"

Expected: 403 Forbidden - CSRF token validation failed
```

**Test 3: Valid Token**
```bash
# First get the token from a form
curl -X POST https://newsletter.gov.bc.ca/newsletters/index.php \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d "action=delete&newsletter_id=1&csrf_token=a8f3c2d1e4b5..."

Expected: 200 OK - Action processed
```

---

## Common Questions

### Q: Why not use double-submit cookies instead?
**A:** Session-based tokens are more secure:
- Double-submit requires setting cookies from JavaScript
- Vulnerable to subdomain attacks
- Session tokens are server-side only

### Q: Should tokens expire?
**A:** They expire with the session:
- When user logs out
- After session timeout (typically 30 minutes of inactivity)
- On browser close (if session cookies used)

### Q: What about AJAX requests?
**A:** Include token in request headers:
```javascript
fetch('/api/action', {
    method: 'POST',
    headers: {
        'X-CSRF-Token': document.querySelector('[name=csrf_token]').value
    },
    body: JSON.stringify(data)
});
```

### Q: Can I use the same token for multiple forms?
**A:** Yes! One token per session, used in all forms.

### Q: What if legitimate user gets 403 error?
**A:** Possible causes:
- Session expired (token no longer valid)
- Browser cached old form (old token)
- Multiple tabs with different sessions

**Solution:** Refresh page to get new token

---

## Summary

**CSRF Protection in 4 Steps:**

1. **Generate** a random token when session starts
2. **Include** the token in every form (hidden field)
3. **Validate** the token on every POST request
4. **Reject** requests with missing or invalid tokens

**Benefits:**
- ✓ Prevents unauthorized actions
- ✓ Protects against forged requests
- ✓ Maintains user trust
- ✓ Meets security compliance standards
- ✓ Simple to implement and maintain

**Result:**
- Attackers cannot force authenticated users to perform unwanted actions
- Even if attacker tricks user into clicking malicious links, requests will be rejected
- Newsletter system is secure against CSRF attacks

---

**Implementation Date:** 2025-10-04
**Status:** ✅ Fully Implemented
**Coverage:** 100% of state-changing operations
**Security Level:** Industry standard
