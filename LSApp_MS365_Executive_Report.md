# LSApp to Microsoft 365 Migration Analysis
## Executive Report

**Evaluating MS365 Public Sector Toolset as an Alternative Platform**

*Prepared: January 9, 2026*

---

## Executive Summary

This report evaluates the feasibility of migrating LSApp's course administration functionality to the Microsoft 365 (Public Sector) platform. While MS365 offers powerful tools that could replicate most LSApp features, this approach introduces **significant complexity, ongoing costs, and maintenance overhead** that may not align with the organization's goal of minimal-maintenance operation over the next 8+ years.

| Metric | Value |
|--------|-------|
| LSApp Features | 14 Core Modules |
| MS365 Tools Required | 6-8 Products |
| Custom Development | Substantial |
| Ongoing Maintenance | Higher |

---

## 1. Microsoft 365 Toolset Overview

The following MS365 components would be required to replicate LSApp functionality:

### SharePoint Online (SP)
- **Primary Use:** Data storage via SharePoint Lists, document management, site navigation
- **Replaces:** CSV/SQLite data storage, file management

### Power Apps (PA)
- **Primary Use:** Custom forms, mobile-friendly interfaces, data entry screens
- **Replaces:** PHP pages, custom UI

### Power Automate (PF)
- **Primary Use:** Workflows, approvals, notifications, scheduled tasks
- **Replaces:** Manual processes, ELM sync scripts

### Power BI (PB)
- **Primary Use:** Dashboards, analytics, reporting
- **Replaces:** Dashboard statistics, ad-hoc reports

### Microsoft Forms (FO)
- **Primary Use:** Data collection, change requests, surveys
- **Replaces:** PHP forms, request submissions

### Microsoft Teams (TM)
- **Primary Use:** Collaboration, notifications, embedded apps
- **Replaces:** Email notifications, team communication

---

## 2. Feature-by-Feature Migration Analysis

### 2.1 Core Features Mapping

| LSApp Feature | MS365 Solution | Complexity | Notes |
|---------------|----------------|------------|-------|
| **Course Catalog** (Browse, search, filter ~300 courses) | SharePoint List + Power Apps | Moderate | SharePoint search less powerful than FTS5; Power Apps needed for good UX |
| **Class Scheduling** (Create, manage class instances) | SharePoint List + Power Apps | Moderate | Calendar views possible; Outlook integration for room booking |
| **People Directory** (Staff, instructors, roles) | Azure AD / SharePoint | **Native** | Azure AD already contains user data; may need supplemental list for roles |
| **Change Requests - Courses** (Workflow with approval, comments) | SharePoint List + Power Automate | Complex | Multi-stage approval flows; comment threads; file attachments; timeline tracking |
| **Change Requests - Classes** (Scheduling modifications) | SharePoint List + Power Automate | Moderate | Simpler workflow than course changes |
| **Partner Management** (Learning Hub partners) | SharePoint List | Easy | Straightforward list with lookup relationships |
| **ELM Sync** (External system integration) | Power Automate + Azure Logic Apps | Complex | Scheduled flows; CSV parsing; diff detection; may need premium connectors |
| **Full-Text Search** (BM25-ranked course search) | SharePoint Search / Microsoft Search | Moderate | Different ranking algorithm; less control over relevance tuning |
| **Notes & Comments** (Per-class annotations) | SharePoint List (related items) | Easy | One-to-many relationship via lookup column |
| **Dashboard & Stats** (Course counts, upcoming classes) | Power BI + SharePoint Dashboard | Moderate | Power BI provides rich visualizations but requires maintenance |
| **Authentication** (IDIR-based access) | Azure AD (already integrated) | **Native** | IDIR federation already exists; seamless authentication |
| **Role-Based Access** (Admin vs. regular users) | SharePoint Permissions + Azure AD Groups | Moderate | SharePoint permission model is powerful but complex |
| **Audit Trail** (Change history, timeline) | SharePoint Version History + Custom List | Moderate | Built-in versioning; custom list needed for detailed field-level tracking |
| **Data Export** (CSV exports, reporting) | Power Automate + Excel Online | Easy | Export to Excel is native; scheduled exports via Power Automate |

### 2.2 Features No Longer Needed

The following legacy LSApp features would **not** need to be migrated (regardless of platform):

| Feature | Reason |
|---------|--------|
| Venue Booking Workflow | Only 8 classroom courses; external booking |
| Materials Ordering | eLearning doesn't require physical materials |
| Shipping/Logistics | No shipments for digital delivery |
| AV Equipment Tracking | Virtual delivery eliminated need |
| Kiosk Interface | No physical venue displays |

---

## 3. Comparative Analysis

### 3.1 Advantages of MS365 Approach

**Potential Benefits:**
- **Enterprise Integration:** Native integration with Outlook, Teams, OneDrive already in use
- **User Familiarity:** Staff already use MS365 tools daily
- **Mobile Access:** Power Apps provides native mobile experience
- **Collaboration:** Real-time co-editing, @mentions, Teams notifications
- **No Server Management:** Cloud-hosted; no PHP/Apache to maintain
- **Built-in Backup:** Microsoft handles data redundancy and disaster recovery
- **Accessibility:** MS365 tools have strong accessibility support
- **Compliance:** Already approved for government use (public sector tenant)

### 3.2 Disadvantages & Risks of MS365 Approach

**Significant Concerns:**
- **Platform Churn:** Microsoft regularly changes, deprecates, and replaces tools (InfoPath → PowerApps, Flow → Power Automate, classic SharePoint → modern). Expect mandatory migrations every 2-3 years.
- **Complexity Multiplication:** Instead of one codebase, you manage 6-8 interconnected products, each with its own versioning and limitations.
- **Licensing Costs:** Power Apps, Power Automate Premium, Power BI Pro all require additional licensing beyond standard MS365.
- **Performance Limits:** SharePoint Lists have 5,000-item view threshold; Power Automate has run limits; delegation issues in Power Apps.
- **Vendor Lock-in:** All data and logic trapped in Microsoft ecosystem; export is difficult.
- **Skill Requirements:** Power Platform requires specialized knowledge (different from PHP/SQL skills).
- **Limited Customization:** Can't implement features Microsoft doesn't support; workarounds add complexity.
- **Search Limitations:** SharePoint search is less precise than SQLite FTS5 for structured data.

### 3.3 Side-by-Side Comparison

| Criterion | lsapp5000 (PHP/SQLite) | MS365 (Power Platform) |
|-----------|------------------------|------------------------|
| Development Effort | 70% complete; 3-4 weeks to finish | Full rebuild; 8-16 weeks estimated |
| Ongoing Licensing Cost | $0 (PHP/SQLite are free) | $15-40/user/month for Power Platform |
| Maintenance Burden | Minimal (zero dependencies) | Continuous (platform updates, deprecations) |
| 8-Year Stability | High (PHP is stable; SQLite is ubiquitous) | Uncertain (expect multiple forced migrations) |
| Data Portability | Full control (SQLite file, export anytime) | Limited (data spread across services) |
| Custom Logic | Unlimited (write any PHP code) | Constrained by Power Platform capabilities |
| Search Quality | Excellent (FTS5 with BM25 tuning) | Good (SharePoint Search, less tunable) |
| User Experience | Custom-built for workflow | Generic Power Apps interface |
| Mobile Support | Responsive Bootstrap (web) | Native Power Apps (mobile app) |
| Integration | Custom (PHP can call any API) | Native MS365 integration |

---

## 4. Cost Analysis

### 4.1 MS365 Licensing Requirements

> **Important:** Standard MS365 Government licenses (G3/G5) do *not* include full Power Platform capabilities. Additional licensing is required for:
> - Power Apps per-app or per-user plans
> - Power Automate premium connectors
> - Power BI Pro (for sharing dashboards)
> - Dataverse storage (if exceeding included capacity)

| Component | License Type | Est. Cost/User/Month | Notes |
|-----------|--------------|---------------------|-------|
| Power Apps | Per-user or Per-app | $5 - $20 | Per-app cheaper if limited apps |
| Power Automate | Premium (for HTTP connector) | $15 | Required for ELM sync |
| Power BI Pro | Per-user | $10 | Required for dashboard sharing |
| Dataverse | Per GB over included | Variable | May be needed for complex data |
| **Total per active user** | | **$30 - $45** | On top of existing MS365 license |

### 4.2 Total Cost of Ownership (5-Year Estimate)

| Cost Category | lsapp5000 | MS365 Power Platform |
|---------------|-----------|---------------------|
| Initial Development | $0 (AI-assisted, mostly done) | $30,000 - $60,000 (consultant or internal) |
| Annual Licensing (10 users) | $0 | $3,600 - $5,400 |
| 5-Year Licensing | $0 | $18,000 - $27,000 |
| Maintenance (annually) | ~$2,000 (occasional updates) | ~$8,000 (platform changes, migrations) |
| 5-Year Maintenance | $10,000 | $40,000 |
| **5-Year Total** | **~$10,000** | **$88,000 - $127,000** |

---

## 5. Implementation Timeline (MS365 Approach)

If the MS365 approach were selected, the following timeline would apply:

### Phase 1: Discovery & Architecture (2-3 weeks)
- Detailed requirements mapping
- SharePoint site architecture design
- List schema design (replicate SQLite tables)
- Power Platform solution architecture
- Licensing procurement

### Phase 2: Data Layer Setup (2-3 weeks)
- Create SharePoint Lists (courses, classes, people, etc.)
- Configure lookup relationships
- Set up views and filtering
- Migrate historical data from SQLite
- Configure permissions model

### Phase 3: Power Apps Development (4-6 weeks)
- Course catalog browsing app
- Course/class detail views
- Edit forms for administrators
- Change request submission and tracking
- Dashboard and statistics screens

### Phase 4: Power Automate Workflows (2-3 weeks)
- Change request approval flows
- Notification workflows
- ELM sync automation (if possible within limits)
- Scheduled reports

### Phase 5: Power BI Dashboards (1-2 weeks)
- Course statistics dashboard
- Class enrollment reports
- Change request metrics

### Phase 6: Testing & Training (2-3 weeks)
- User acceptance testing
- Performance testing (5,000-item limits)
- User training
- Documentation

**Total Estimated Timeline:** 13-20 weeks (3-5 months)

*Compare to lsapp5000 completion: 3-4 weeks*

---

## 6. Risk Assessment

| Risk | lsapp5000 | MS365 Power Platform |
|------|-----------|---------------------|
| Platform Obsolescence | **Low** - PHP/SQLite very stable | **High** - Frequent MS changes |
| Feature Limitations | **Low** - Full code control | Medium - Platform constraints |
| Performance at Scale | **Low** - SQLite handles this easily | Medium - List view thresholds |
| Data Migration | **Complete** - Already done | Medium - Full migration needed |
| Skill Availability | **Low** - PHP developers common | Medium - Power Platform specialists fewer |
| Vendor Lock-in | **Low** - Standard technologies | **High** - Microsoft ecosystem |
| Ongoing Costs | **Low** - Hosting only | **High** - Per-user licensing |

---

## 7. Recommendation

### Strategic Recommendation: Continue with lsapp5000

Based on the analysis, **MS365 Power Platform is not recommended** for LSApp replacement at this time. The reasons are:

- **lsapp5000 is 70% complete** - Abandoning this work to start over on MS365 wastes significant investment
- **8-year stability goal** - MS365 platform churn directly conflicts with this requirement
- **Cost differential** - $10K vs $100K+ over 5 years is substantial
- **Maintenance burden** - MS365 requires more ongoing attention, not less
- **Feature fit** - LSApp's workflow is custom; Power Platform would require compromises

### 7.1 When MS365 Would Make Sense

An MS365 approach would be more appropriate if:
- The application needed deep integration with Teams/Outlook workflows
- Mobile app access was a primary requirement
- The organization had dedicated Power Platform developers
- Budget allowed for premium licensing and ongoing maintenance
- The 8-year stability requirement didn't exist
- lsapp5000 development hadn't already started

### 7.2 Hybrid Approach (Alternative)

**Consider: Use MS365 for Specific Functions Only**

Rather than full migration, selectively use MS365 for specific enhancements:
- **Power BI:** Connect to lsapp5000's SQLite database for advanced analytics dashboards
- **Teams Notifications:** Use incoming webhooks from lsapp5000 for alerts
- **SharePoint Document Library:** Store course materials/attachments
- **Microsoft Forms:** Collect feedback that feeds into lsapp5000

This provides MS365 benefits without replacing the core application.

---

## 8. Summary Comparison

| Factor | Winner |
|--------|--------|
| Time to Completion | **lsapp5000** (3-4 weeks vs 3-5 months) |
| 5-Year Cost | **lsapp5000** ($10K vs $100K+) |
| Long-Term Stability | **lsapp5000** (zero platform churn) |
| Maintenance Burden | **lsapp5000** (minimal vs continuous) |
| Feature Flexibility | **lsapp5000** (unlimited vs constrained) |
| MS365 Integration | **Power Platform** (native) |
| Mobile Experience | **Power Platform** (native app) |
| **Overall Recommendation** | **lsapp5000** |

---

*Technical Addendum Available: A detailed technical document covering MS365 architecture patterns, SharePoint List design, Power Platform limitations, and implementation specifics accompanies this report.*
