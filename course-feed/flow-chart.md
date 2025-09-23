# Course Feed System - Execution Flow

## Overview
This document visualizes the execution flow of the course-feed system, which synchronizes courses from BC Government's ELM system with the LearningHUB platform.

## Main Process Flow

```mermaid
flowchart TD
    Start([User Starts Process]) --> Index[index.php<br/>Upload Form UI]
    Index --> Upload{Upload CSV Files}
    Upload --> Controller[controller.php<br/>Move & Rename Files]
    
    Controller --> Process[process.php<br/>Process Keywords & Merge Data]
    Process --> |Creates courses.csv| ElmSync[elm-course-sync.php<br/>Sync with LSApp Database]
    
    ElmSync --> |Updates master DB| FeedCreate[feed-create.php<br/>Generate JSON Feed]
    FeedCreate --> |Copies to public server| OpenAccess[course-openaccess-publish.php<br/>Publish Open Access Pages]
    
    OpenAccess --> RequestedRSS[requested-courses-rss.php<br/>Generate Requested Courses RSS]
    RequestedRSS --> ChangesRSS[course-changes-rss.php<br/>Generate Course Changes RSS]
    ChangesRSS --> PartnerRSS[partner-requests-rss.php<br/>Generate Partner Requests RSS]
    
    PartnerRSS --> Success[index.php?message=Success]
    
    %% Data Sources
    ELM[(ELM System<br/>CSV Exports)] -.->|Manual Export| Upload
    CoursesDB[(LSApp courses.csv<br/>Master Database)] <--> ElmSync
    ClassesDB[(classes.csv<br/>Schedule Data)] --> OpenAccess
    PartnersDB[(partners.json)] --> PartnerRSS
```

## Data Flow Detail

```mermaid
flowchart LR
    subgraph Input ["Input Files"]
        CSV1[GBC_LEARNINGHUB_SYNC2.csv]
        CSV2[GBC_ATWORK_CATALOG_KEYWORDS.csv]
    end
    
    subgraph Processing ["Data Processing"]
        Merge[Merge & Filter]
        Compare[Compare with Existing]
        Update[Update Records]
    end
    
    subgraph Output ["Output Formats"]
        JSON[JSON Feed<br/>bcps-corporate-learning-courses.json]
        RSS1[Requested Courses RSS]
        RSS2[Course Changes RSS]
        RSS3[Partner Requests RSS]
        PHP[Open Access PHP Pages]
    end
    
    subgraph Storage ["Data Storage"]
        MasterDB[courses.csv<br/>Master Database]
        Logs[Sync Logs]
    end
    
    CSV1 --> Merge
    CSV2 --> Merge
    Merge --> Compare
    Compare --> Update
    Update --> MasterDB
    Update --> Logs
    
    MasterDB --> JSON
    MasterDB --> RSS1
    MasterDB --> RSS2
    MasterDB --> RSS3
    MasterDB --> PHP
```

## File Processing Chain

```mermaid
sequenceDiagram
    participant User
    participant index.php
    participant controller.php
    participant process.php
    participant elm-course-sync.php
    participant feed-create.php
    participant course-openaccess-publish.php
    participant RSS Generators
    participant Public Server
    
    User->>index.php: Upload CSV files
    index.php->>controller.php: Submit form
    controller.php->>controller.php: Move & rename files
    controller.php->>process.php: Redirect
    
    process.php->>process.php: Process keywords
    process.php->>process.php: Merge data
    process.php->>process.php: Filter by learner groups
    process.php->>process.php: Create courses.csv
    process.php->>elm-course-sync.php: Redirect
    
    elm-course-sync.php->>elm-course-sync.php: Compare with LSApp DB
    elm-course-sync.php->>elm-course-sync.php: Update existing courses
    elm-course-sync.php->>elm-course-sync.php: Add new courses
    elm-course-sync.php->>elm-course-sync.php: Log changes
    elm-course-sync.php->>feed-create.php: Redirect
    
    feed-create.php->>feed-create.php: Generate JSON feed
    feed-create.php->>Public Server: Copy JSON file
    feed-create.php->>course-openaccess-publish.php: Redirect
    
    course-openaccess-publish.php->>course-openaccess-publish.php: Generate PHP pages
    course-openaccess-publish.php->>Public Server: Write PHP files
    course-openaccess-publish.php->>RSS Generators: Redirect
    
    RSS Generators->>RSS Generators: Generate 3 RSS feeds
    RSS Generators->>Public Server: Copy RSS files
    RSS Generators->>index.php: Redirect with success
    
    index.php->>User: Display success message
```

## Key Components and Their Responsibilities

```mermaid
graph TB
    subgraph "Data Import Layer"
        A[controller.php<br/>File Management]
        B[process.php<br/>Data Transformation]
    end
    
    subgraph "Synchronization Layer"
        C[elm-course-sync.php<br/>Database Sync]
        D[Logging System<br/>Change Tracking]
    end
    
    subgraph "Publishing Layer"
        E[feed-create.php<br/>JSON Feed]
        F[course-openaccess-publish.php<br/>Web Pages]
        G[RSS Generators<br/>3 Different Feeds]
    end
    
    subgraph "Storage"
        H[(courses.csv)]
        I[(classes.csv)]
        J[(partners.json)]
        K[/Sync Logs/]
    end
    
    A --> B
    B --> C
    C --> D
    C --> H
    D --> K
    H --> E
    H --> F
    I --> F
    H --> G
    J --> G
```

## Alternative/Utility Scripts

```mermaid
graph LR
    subgraph "Utility Scripts"
        Legacy1[lhub-course-sync.php<br/>Legacy Sync]
        Legacy2[jsonfeed.php<br/>Old JSON Generator]
        Gen[generate_site.php<br/>Static HTML Generator]
        WP[wp-import.php<br/>WordPress Importer]
    end
    
    subgraph "Data Sources"
        DB[(courses.csv)]
        XML[WordPress XML]
    end
    
    DB --> Legacy1
    DB --> Legacy2
    DB --> Gen
    XML --> WP
    WP --> DB
```

## Error Handling Flow

```mermaid
flowchart TD
    Process[Any Processing Step] --> Check{Error?}
    Check -->|No| Continue[Continue to Next Step]
    Check -->|Yes| Log[Log Error]
    Log --> Display[Display Error Message]
    Display --> Stop[Stop Execution]
    
    Continue --> Next[Next Script in Chain]
```

## Notes

- **Manual Process**: The entire flow is triggered manually through web interface
- **Sequential Execution**: Each script redirects to the next using HTTP headers
- **No Cron Jobs**: No automated scheduling found
- **Logging**: Comprehensive logging at each stage, especially during sync
- **Public Server**: Files are copied to `E:/WebSites/NonSSOLearning/` for public access
- **Access Control**: Open access pages include access code verification
- **Data Persistence**: HUBInclude and HUBPersist flags manage course visibility