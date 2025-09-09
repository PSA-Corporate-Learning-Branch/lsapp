# CHEFs API Integration Assessment

**Date:** August 28, 2025  
**Purpose:** Evaluate feasibility of programmatic form creation via CHEFs API  
**Status:** FEASIBLE - OAuth2 credentials required from CHEFs administrators

---

## Executive Summary

**✅ CONFIRMED POSSIBLE:** Programmatic form creation via CHEFs API is fully supported and technically feasible with existing infrastructure.

**🔑 BLOCKER:** Requires OAuth2 credentials from CHEFs system administrators (cannot self-register).

**📋 RECOMMENDATION:** Contact CHEFs team to request administrative API access for automated form creation capabilities.

---

## Technical Feasibility Analysis

### 1. API Endpoint Availability ✅

**Form Creation Endpoint:**
- `POST /forms` - Creates new forms programmatically
- Accepts combined `FormBasic + FormSchema` structure
- Returns form UUID and metadata needed for newsletter configuration
- **Status:** Available and documented

### 2. Template Compatibility ✅

**Existing Template Assessment:**
- File: `learn__work_week_2025_newsletter_schema.json`
- **Result:** Fully compatible with CHEFs FormIO schema requirements
- Contains all required components:
  - Email field with validation
  - Subscribe/Unsubscribe radio options
  - Submit button configuration
- **Status:** Ready to use without modification

### 3. Authentication Requirements 🔑

**Current System:**
- Uses Basic Authentication (username/password)
- Works for form submission retrieval and basic operations

**Required for Form Creation:**
- **BearerAuth** with OpenID Connect token
- **Admin scope** required: `OpenID: [admin]`
- **Grant Type:** Likely `client_credentials` for server-to-server

**Impact:** Need OAuth2 implementation alongside existing Basic Auth

---

## Implementation Approach

### Phase 1: OAuth2 Integration
```php
// Additional fields needed in newsletter configuration
- oauth2_client_id
- oauth2_client_secret  
- oauth2_token_endpoint
- oauth2_scope (default: "admin")
```

### Phase 2: Enhanced Newsletter Creation
```php
// In newsletter_edit.php - add form creation option:
[ ] Create new CHEFs form automatically
[x] Use existing CHEFs form (current default)

// Automated workflow when "create new" selected:
1. Get OAuth2 access token using client credentials
2. POST form schema to /forms endpoint using template
3. Extract returned form UUID
4. Auto-populate form_id field
5. Optionally retrieve API credentials for new form
```

### Phase 3: Template Customization
```php
// Dynamic template generation:
- Configurable form title/description
- Newsletter-specific branding
- Optional additional fields (name, preferences, etc.)
- Customizable validation rules
```

---

## Where to Get OAuth2 Credentials

### CHEFs System Administrators Required

Looking at the CHEFs API spec, the OAuth2 credentials come from **CHEFs system administrators** - this isn't something you can self-register for.

**Why Admin-Level Access is Required:**
The API spec shows that creating forms requires **`admin` scope**:
```yaml
security:
  - BearerAuth: []
    OpenID:
      - admin  # <-- This scope required for form creation
```

This is **enterprise-level access** that individual users can't self-register for - it must be granted by CHEFs administrators.

### Contact Information for Credentials:

**Primary Contact:**
- **Email:** submit.digital@gov.bc.ca (from the API spec)
- **Team:** "Forminators" (CHEFs development team)
- **Project:** https://github.com/bcgov/common-hosted-form-service

### What to Request:

**Required OAuth2 Credentials:**
1. **OAuth2 Client ID** - Public identifier for your application
2. **OAuth2 Client Secret** - Secret key for authentication
3. **Token Endpoint URL** - Where to obtain access tokens
4. **Required Scopes/Permissions** - Specifically need `admin` scope for form creation

**OpenID Connect Details:**
- Authorization server endpoints
- Supported grant types (likely `client_credentials` for server-to-server)
- Required permissions/roles for form creation

**Use Case Justification:**
- Streamline newsletter setup process
- Reduce manual form creation steps
- Improve user experience for newsletter administrators
- Maintain security through proper OAuth2 implementation

---

## Security Considerations

### Current Security Status: ✅ Excellent
- Passwords already encrypted using AES-256-GCM
- Parameterized SQL queries prevent injection
- Form validation and sanitization implemented

### OAuth2 Security Implementation:
```php
// Secure credential storage (using existing encryption)
$encryptedClientSecret = EncryptionHelper::encrypt($oauth2_client_secret);

// Token management
- Access tokens cached with expiration
- Refresh tokens stored securely if provided
- Token validation before each API call
- Fallback to Basic Auth for existing functionality
```

---

## Implementation Impact

### Minimal Changes Required:
1. **Add OAuth2 fields** to newsletter configuration table
2. **Implement token management** class for OAuth2 flow
3. **Add form creation checkbox** to newsletter_edit.php
4. **Create API integration function** using existing cURL infrastructure
5. **Maintain backward compatibility** with manual workflow

### Database Schema Addition:
```sql
ALTER TABLE newsletters ADD COLUMN oauth2_client_id TEXT;
ALTER TABLE newsletters ADD COLUMN oauth2_client_secret TEXT;
ALTER TABLE newsletters ADD COLUMN oauth2_token_endpoint TEXT;
ALTER TABLE newsletters ADD COLUMN auto_create_forms BOOLEAN DEFAULT 0;
```

### Code Integration Points:
- **newsletter_edit.php**: Form creation interface
- **inc/ches_client.php**: OAuth2 token management
- **existing encryption**: Secure credential storage
- **existing error handling**: API failure management

---

## Benefits Analysis

### User Experience Improvements:
- **Streamlined Setup:** Single-step newsletter creation
- **Reduced Errors:** No manual form ID copying
- **Consistent Templates:** Standardized form structure
- **Time Savings:** Eliminate CHEFs web interface steps

### Technical Benefits:
- **Automated Workflow:** End-to-end newsletter setup
- **Template Control:** Programmatic form customization
- **API Integration:** Leverage full CHEFs capabilities
- **Scalability:** Support multiple newsletter creation

### Risk Mitigation:
- **Backward Compatibility:** Manual workflow preserved
- **Graceful Degradation:** Falls back to existing process
- **Error Handling:** Clear feedback for API failures
- **Security Maintained:** OAuth2 industry standards

---

## Alternative Implementation Strategy

### If OAuth2 Credentials Are Difficult to Obtain:

If getting OAuth2 credentials proves difficult, you could implement a **hybrid approach**:

1. **Keep current manual workflow** as primary method
2. **Add API integration** as optional enhancement for organizations that obtain credentials  
3. **Implement both options** in newsletter_edit.php with a toggle

This way the feature remains usable even without OAuth2 access, but can take advantage of automation when credentials are available.

**Bottom line:** You need to request these credentials directly from the CHEFs team at BC Government.

---

### Hybrid Approach (Recommended):

**Option A: Full OAuth2 Integration**
- For organizations with CHEFs admin credentials
- Automated form creation and management
- Enhanced user experience

**Option B: Enhanced Manual Process**
- Current workflow with improved UI/UX
- Better validation and error handling
- Template suggestions and examples

**Implementation:**
```php
if (hasOAuth2Credentials($newsletter_id)) {
    // Show automated creation option
    renderAutomatedFormCreation();
} else {
    // Show enhanced manual process
    renderManualFormCreation();
    showOAuth2RequestInstructions();
}
```

---

## Next Steps

### Immediate Actions:
1. **Contact CHEFs Team** - Request OAuth2 credentials
   - Email: submit.digital@gov.bc.ca
   - Include technical requirements and use case
   - Request admin scope permissions

2. **Prepare Implementation** - While awaiting credentials:
   - Design OAuth2 integration architecture
   - Create form template customization system
   - Implement hybrid workflow interface

3. **Testing Strategy** - Once credentials obtained:
   - Test form creation in CHEFs development environment
   - Validate template compatibility
   - Verify OAuth2 token management

### Success Criteria:
- [ ] OAuth2 credentials obtained from CHEFs administrators
- [ ] Successful form creation via API in test environment
- [ ] Template compatibility validated
- [ ] Security audit passed for OAuth2 implementation
- [ ] Backward compatibility maintained for existing newsletters

---

## Conclusion

**CHEFs API integration for automated form creation is technically sound and implementationally straightforward.** The primary requirement is obtaining OAuth2 credentials from CHEFs system administrators.

**Benefits significantly outweigh implementation costs,** providing streamlined user experience while maintaining security and compatibility.

**Recommended approach:** Contact CHEFs team immediately to begin credential request process while preparing hybrid implementation that supports both automated and manual workflows.

---

**Assessment Status:** COMPLETE  
**Technical Feasibility:** CONFIRMED  
**Business Impact:** HIGH VALUE  
**Implementation Complexity:** LOW-MODERATE  
**Security Risk:** LOW (with proper OAuth2 implementation)