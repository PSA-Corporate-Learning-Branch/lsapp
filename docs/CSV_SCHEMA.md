# CSV Data Schema

This document describes the structure of the CSV data files used by LSApp.

## courses.csv

Course metadata and configuration.

### Header

```
CourseID,Status,CourseName,CourseShort,ItemCode,ClassTimes,ClassDays,ELM,PreWork,PostWork,
CourseOwner,MinMax,CourseNotes,Requested,RequestedBy,EffectiveDate,CourseDescription,CourseAbstract,
Prerequisites,Keywords,Category,Method,elearning,WeShip,ProjectNumber,Responsibility,ServiceLine,STOB,MinEnroll,MaxEnroll
```

### Column Index Mapping

| Index | Column Name | Description |
|-------|-------------|-------------|
| 0 | CourseID | Unique course identifier |
| 1 | Status | Course status |
| 2 | CourseName | Full course name |
| 3 | CourseShort | Abbreviated course name |
| 4 | ItemCode | Item code for ELM |
| 5 | ClassTimes | Default class times |
| 6 | ClassDays | Default class duration in days |
| 7 | ELM | ELM reference |
| 8 | PreWork | Pre-work requirements |
| 9 | PostWork | Post-work requirements |
| 10 | CourseOwner | Course owner |
| 11 | MinMax | Min/Max enrollment (legacy) |
| 12 | CourseNotes | Course notes |
| 13 | Requested | Request date |
| 14 | RequestedBy | Requester name |
| 15 | EffectiveDate | Effective date |
| 16 | CourseDescription | Full course description |
| 17 | CourseAbstract | Course abstract |
| 18 | Prerequisites | Prerequisites |
| 19 | Keywords | Search keywords |
| 20 | Categories | Course categories |
| 21 | Method | Delivery method |
| 22 | elearning | eLearning flag |
| 23 | WeShip | Shipping required flag |
| 24 | ProjectNumber | Project number |
| 25 | Responsibility | Responsibility assignment |
| 26 | ServiceLine | Service line |
| 27 | STOB | STOB code |
| 28 | MinEnroll | Minimum enrollment |
| 29 | MaxEnroll | Maximum enrollment |
| 30 | StartTime | Default start time |
| 31 | EndTime | Default end time |
| 32 | Color | Display color |
| 33 | Featured | Featured course flag |
| 34 | Developer | Course developer |
| 35 | EvaluationsLink | Link to evaluations |
| 36 | LearningHubPartner | Learning Hub partner |
| 37 | Alchemer | Alchemer survey ID |
| 38 | Topics | Course topics |
| 39 | Audience | Target audience |
| 40 | Levels | Difficulty levels |
| 41 | Reporting | Reporting category |
| 42 | PathLAN | LAN file path |
| 43 | PathStaging | Staging file path |
| 44 | PathLive | Live file path |
| 45 | PathNIK | NIK path |
| 46 | CHEFSFormID | CHEFS form ID |
| 47 | isMoodle | Moodle course flag |
| 48 | TaxProcessed | Tax processed flag |
| 49 | TaxProcessedBy | Tax processed by |
| 50 | ELMCourseID | ELM course ID |
| 51 | Modified | Last modified date |
| 52 | Platform | Delivery platform |
| 53 | HUBInclude | Include in Hub flag |
| 54 | RegistrationLink | Registration URL |
| 55 | CourseNameSlug | URL-friendly course name |
| 56 | HubExpirationDate | Hub expiration date |
| 57 | OpenAccessOptin | Open access opt-in flag |
| 58 | HubIncludeSync | Hub include sync flag |
| 59 | HubIncludePersist | Hub include persist flag |
| 60 | HubPersistMessage | Hub persist message |
| 61 | HubIncludePersistState | Hub include persist state |
| 62 | ModifiedBy | Last modified by |

### Planned Additions

The following fields are planned for future implementation:

- `ReminderDate` - Days before start date for reminder
- `LastEnrollDate` - Last enrollment date offset
- `LastWaitlistEnroll` - Last waitlist enrollment offset
- `LastDropDate` - Last drop date offset

---

## classes.csv

Class session data and scheduling information.

### Header

```
ClassID,Status,Requested,RequestedBy,Dedicated,CourseID,CourseName,ItemCode,StartDate,EndDate,Times,MinEnroll,MaxEnroll,ShipDate,
Facilitating,WebinarLink,WebinarDate,CourseDays,Enrolled,ReservedSeats,PendingApproval,Waitlisted,Dropped,VenueID,VenueName,VenueCity,
VenueAddress,VenuePostalCode,VenueContactName,VenuePhone,VenueEmail,VenueAttention,RequestNotes,Shipper,Boxes,Weight,Courier,TrackingOut,
TrackingIn,AttendanceReturned,EvaluationsReturned,VenueNotified,Modified,ModifiedBy,Assigned,DeliveryMethod,CourseCategory,Region,
CheckedBy,ShippingStatus,PickupIn
```

### Column Index Mapping

| Index | Column Name | Description |
|-------|-------------|-------------|
| 0 | ClassID | Unique class identifier |
| 1 | Status | Class status |
| 2 | RequestedOn | Date requested |
| 3 | RequestedBy | Requester name |
| 4 | Dedicated | Dedicated class flag |
| 5 | CourseID | Associated course ID |
| 6 | CourseName | Course name |
| 7 | ItemCode | Item code |
| 8 | ClassDate | Start date |
| 9 | EndDate | End date |
| 10 | ClassTimes | Class times |
| 11 | MinEnroll | Minimum enrollment |
| 12 | MaxEnroll | Maximum enrollment |
| 13 | ShipDate | Ship date for materials |
| 14 | Facilitating | Facilitator(s) |
| 15 | WebinarLink | Webinar URL |
| 16 | WebinarDate | Webinar date |
| 17 | ClassDays | Duration in days |
| 18 | Enrollment | Current enrollment count |
| 19 | ReservedSeats | Reserved seats count |
| 20 | pendingApproval | Pending approval count |
| 21 | Waitlisted | Waitlist count |
| 22 | Dropped | Dropped count |
| 23 | VenueID | Venue identifier |
| 24 | Venue | Venue name |
| 25 | City | Venue city |
| 26 | Address | Venue address |
| 27 | ZIPPostal | Postal code |
| 28 | ContactName | Venue contact name |
| 29 | BusinessPhone | Venue phone |
| 30 | email | Venue email |
| 31 | VenueAttention | Attention notes |
| 32 | Notes | Class notes |
| 33 | Shipper | Shipper name |
| 34 | Boxes | Number of boxes |
| 35 | Weight | Shipment weight |
| 36 | Courier | Courier service |
| 37 | TrackingOut | Outbound tracking number |
| 38 | TrackingIn | Inbound tracking number |
| 39 | Attendance | Attendance returned flag |
| 40 | EvaluationsReturned | Evaluations returned flag |
| 41 | VenueNotified | Venue notified flag |
| 42 | Modified | Last modified date |
| 43 | ModifiedBy | Last modified by |
| 44 | Assigned | Assigned to |
| 45 | DeliveryMethod | Delivery method |
| 46 | CourseCategory | Course category |
| 47 | Region | Geographic region |
| 48 | CheckedBy | Checked by |
| 49 | ShippingStatus | Shipping status |
| 50 | PickupIn | Pickup information |
| 51 | avAssigned | AV assigned |
| 52 | venueCost | Venue cost |
| 53 | venueBEO | Venue BEO |
| 54 | StartTime | Start time |
| 55 | EndTime | End time |
| 56 | CourseColor | Display color |

---

## ELM.csv

Import data from the Enterprise Learning Management system.

### Column Index Mapping

| Index | Column Name | Description |
|-------|-------------|-------------|
| 0 | Course Name | Course name from ELM |
| 1 | Class | Class identifier |
| 2 | Start Date | Class start date |
| 3 | Type | Class type |
| 4 | Facility | Facility/venue |
| 5 | Class Status | Status in ELM |
| 6 | Min Enroll | Minimum enrollment |
| 7 | Max Enroll | Maximum enrollment |
| 8 | Enrolled | Current enrollment |
| 9 | Reserved Seats | Reserved seats |
| 10 | Pending Approval | Pending approvals |
| 11 | Waitlisted | Waitlist count |
| 12 | Dropped | Dropped count |
| 13 | Denied | Denied count |
| 14 | Completed | Completed count |
| 15 | Not Completed | Not completed count |
| 16 | In-Progress | In progress count |
| 17 | Planned | Planned count |
| 18 | Waived | Waived count |
| 19 | City | City |

---

## venues.csv

Venue information for in-person training sessions.

### Column Index Mapping

| Index | Column Name | Description |
|-------|-------------|-------------|
| 0 | VenueID | Unique venue identifier |
| 1 | VenueName | Venue name |
| 2 | ContactName | Contact person |
| 3 | BusinessPhone | Phone number |
| 4 | Address | Street address |
| 5 | City | City |
| 6 | StateProvince | Province |
| 7 | ZIPPostal | Postal code |
| 8 | email | Contact email |
| 9 | Notes | Venue notes |
| 10 | Active | Active status |
| 11 | Union | Union venue flag |
| 12 | Region | Geographic region |

---

## changes-course.csv

Course change request tracking.

### Column Index Mapping

| Index | Column Name | Description |
|-------|-------------|-------------|
| 0 | creqID | Change request ID |
| 1 | CourseID | Associated course ID |
| 2 | CourseName | Course name |
| 3 | DateRequested | Request date |
| 4 | RequestedBy | Requester |
| 5 | Status | Request status |
| 6 | CompletedBy | Completed by |
| 7 | CompletedDate | Completion date |
| 8 | Request | Request details |
