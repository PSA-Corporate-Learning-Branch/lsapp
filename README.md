# LSApp

**Learning Support Administration Application**

A "Meta-ELM" system for managing course and class session metadata. LSApp integrates Development, Delivery, and Operations with a central repository of metadata and communications for all aspects of course creation and delivery.

## Overview

LSApp is not an LMS/ELM - it doesn't manage registration, course content delivery, or any learner interface. Instead, it contains all the data associated with courses and class sessions necessary to input that information into the LMS (ELM).

## Features

- **Course Management** - Each course gets its own page listing all associated metadata, materials, checklists, and upcoming classes
- **Class Management** - Each class gets its own page listing everything about that class, including notes and change history
- **Change Tracking** - Changes are submitted and tracked on course/class pages with full context
- **Venue Management** - Basic venue management for in-person sessions
- **ELM Integration** - Weekly course stats import with status updates, enrollment numbers, and audit tools
- **Dashboards**
  - Upcoming classes
  - Incomplete change requests
  - Unclaimed service requests
  - Person-specific dashboards

### Access Control

- Integrated with Single Sign-On (IDIR)
- Simple role-based ACL: `super`, `admin`, `internal`, `external`

## Requirements

- PHP 5.4+ (runs with minimal modules)
- Web server with `REMOTE_USER` environment variable support
- Docker (optional, for containerized deployment)

## Installation

### Docker (Recommended)

```bash
git clone <repository-url>
cd lsapp
cp -r /path/to/data ./data
docker build -t lsapp .
docker run -d -p 8080:8080 --name lsapp-container lsapp
```

### Manual

1. Clone the repository to your web server document root
2. Copy the data folder with CSV files (see [CSV Schema](docs/CSV_SCHEMA.md) for structure)
3. Configure your web server to serve PHP files
4. Ensure `REMOTE_USER` is populated by your authentication layer

## Data Structure

LSApp uses CSV files as its flat-file database. See [docs/CSV_SCHEMA.md](docs/CSV_SCHEMA.md) for complete documentation of all data file structures and column mappings.

## Technical History

LSApp evolved through several stages:

1. **Excel Era** - Started as 8+ Excel spreadsheets with VBScripts for validation
2. **Access Era** - Migrated to Access, merging spreadsheets into a single data model
3. **PHP Era** - Rebuilt in PHP to run on existing infrastructure with SSO support

The decision to use CSV files and simple procedural PHP was deliberate - it allowed deployment within existing constraints without requiring database servers or PHP upgrades. The application went live in 2019.

### Evolution

Originally focused on in-person training with physical venues and material shipping. Since 2020, the focus has shifted to eLearning and webinars, which now account for the vast majority of the catalog.

## Project Structure

```
lsapp/
├── data/           # CSV data files
├── docs/           # Documentation
├── course-change/  # Course change request handling
├── class-change/   # Class change request handling
├── includes/       # Shared PHP includes
└── ...
```

## Contributing

This is an internal application. Contact the development team for contribution guidelines.

## License

Internal use only.
