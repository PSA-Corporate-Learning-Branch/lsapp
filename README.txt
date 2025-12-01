# LSApp
## Learning Support Administration Application

 **        ********     **                    
/**       **//////     ****    ******  ****** 
/**      /**          **//**  /**///**/**///**
/**      /*********  **  //** /**  /**/**  /**
/**      ////////** **********/****** /****** 
/**             /**/**//////**/**///  /**///  
/******** ******** /**     /**/**     /**     
//////// ////////  //      // //      //      


010011001010010100000011100001110000 

"Meta-ELM" It's not an LMS/ELM. It doesn't manage registration, course content delivery, or any interface for
the learner. It does contain all of the data associated with courses and class sessions that is necessary to 
be able to input that information into the LMS (ELM).

LSApp integrates Development, Delivery, and Operations with a central repository of meta data and communications 
for all aspects of course creation and delivery.

LSApp answers questions. It exposes the database that operations uses. Any and all bits of information that are 
associated with that course/class are at your finger tips. If you look at a class page, and there's an important 
bit of information missing or incorrect?  You can bring it to LSA attention, and have the record of it maintained.

- Custom Content Management System created by Allan
- I didn't write an LMS, I wrote "Learning Centre" software
- Web-based (Kepler! where all the course content lives) and mobile-friendly
- Focused around people and tracking the things they contribute
	- Integrated with Single Sign-On (no new accounts/passwords)
	- Simple Access Control List
		- Roles: super, admin, internal, external
- Each course gets its own page, listing _all_ associated meta data, materials, checklists, and upcoming classes
- Each class gets its own page listing _everything_ about that class WWWWWhy, including notes and change history
- Changes are submitted and tracked on the course/class pages, in context
- Basic work flow for entering into the Learning System
- Basic venues management
- Integrates with Learning System (weekly course stats import):
	- Status updates
	- Enrollment numbers
	- Audit tool
- Dashboards
	- Upcoming classes
	- Incomplete change requests
	- Unclaimed service requests
	- Person dashboards list every pertinent thing for that person

## Technical History

LSApp was born as 8 or more naive Excel spreadsheets spread across a LAN. VBScripts were scripts were introduced
for data validation and normalization. Eventually Excel yeilded to Access which merged the 8 spreadsheets into a 
single data model. After a year operating in Access, it was noticed that the web server that we use for hosting 
elearning materials had PHP enabled on it. It was PHP 5.4 with almost zero modules enabled (no SQLite for example)
but it was PHP and it could access a REMOTE_USER environmental variable that contained the logged in users IDIR.

After writing a prototype version of LSApp in CakePHP using MySQL as a database, it was discovered that it would
more political sense if the application was written within the existing constraints and without any need of 
further resources needed for a database or upgrades to PHP.

CSV files were exported from Access and the most basic, old school PHP you can imagine was written to manage them.
No framework; flat file database. I deliberating took as simple approach as could, even eschewing OOP in favor
of a stack of simple functions and directly manipulating the files wherever possible. I definitely made numerous 
mistakes that continue to plague the project, but by and large it came out well enough that it was an easy sell to 
leadership. It went live in 2019.

In 2019 the vast majority of the courses that we delivered were in person. We booked physical space at dozens of 
venues across the province and shipped physical course materials (e.g. manuals and AV equipment) to meet the 
in person facilitators wherever they were. Along with basic course information, LSApp was focussed on class offerings
and offered various administrative workflows and shortcuts, designed to make entering service requests into ELM easy,
while also providing dashboards for booking venues and ordering and shipping materials with built-in documentation.

LSApp simplified onboarding and training across roles on the operations team and reduced error rates dramatically.

Since 2019, obviously a lot has happened. In person training is represents less the 5% of the courses we now offer,
with eLearning and Webinars accounting for the vast majority of the catalog. Many new features have been added and
some are ready to be removed.

## Installation

LSApp is super-duper portable!

- Clone the repo and cd into it
- Copy the data folder into the files (example files coming soon, but see header structure/schema below)
- docker build -t lsapp .
- docker run -d -p 8080:8080 --name php-container lsapp

Voila! You're up and running; no builds; no other containers needed. I'll likely migrate the data to a PVC soon, which 
will complicate things slightly, but docker compose ... should be easy too.


# DATA FILE HEADERS and number maps
-----------------courses.csv--------------------
CourseID,Status,CourseName,CourseShort,ItemCode,ClassTimes,ClassDays,ELM,PreWork,PostWork,
CourseOwner,MinMax,CourseNotes,Requested,RequestedBy,EffectiveDate,CourseDescription,CourseAbstract,
Prerequisites,Keywords,Category,Method,elearning,WeShip,ProjectNumber,Responsibility,ServiceLine,STOB,MinEnroll,MaxEnroll

// TO ADD: ReminderDate,LastEnrollDate,LastWaitlistEnroll,LastDropDate
// The above fields should be set as a simple number of days e.g. 7 to indicate that 
// the Reminder should go out 7 days before the start date


//0-CourseID,1-Status,2-CourseName,3-CourseShort,4-ItemCode,5-ClassTimes,6-ClassDays,7-ELM,8-PreWork,9-PostWork,10-CourseOwner,
//11-MinMax,12-CourseNotes,13-Requested,14-RequestedBy,15-EffectiveDate,16-CourseDescription,17-CourseAbstract,18-Prerequisites,
//19-Keywords,20-Categories,21-Method,22-elearning,23-WeShip,24-ProjectNumber,25-Responsibility,26-ServiceLine,
//27-STOB,28-MinEnroll,29-MaxEnroll,30-StartTime,31-EndTime,32-Color
//33-Featured,34-Developer,35-EvaluationsLink,36-LearningHubPartner,37-Alchemer,
//38-Topics,39-Audience,40-Levels,41-Reporting
//42-PathLAN,43-PathStaging,44-PathLive,45-PathNIK,46-CHEFSFormID
// 47-isMoodle,48-TaxProcessed,49-TaxProcessedBy,50-ELMCourseID,51-Modified
// 52-Platform, 53-HUBInclude, 54-RegistrationLink, 55-CourseNameSlug, 56-HubExpirationDate, 57-OpenAccessOptin
// 58-HubIncludeSync, 59-HubIncludePersist, 60-HubPersistMessage, 
// 61-ModifiedBy



--------------------classes.csv--------------------
ClassID,Status,Requested,RequestedBy,Dedicated,CourseID,CourseName,ItemCode,StartDate,EndDate,Times,MinEnroll,MaxEnroll,ShipDate,
Facilitating,WebinarLink,WebinarDate,CourseDays,Enrolled,ReservedSeats,PendingApproval,Waitlisted,Dropped,VenueID,VenueName,VenueCity,
VenueAddress,VenuePostalCode,VenueContactName,VenuePhone,VenueEmail,VenueAttention,RequestNotes,Shipper,Boxes,Weight,Courier,TrackingOut,
TrackingIn,AttendanceReturned,EvaluationsReturned,VenueNotified,Modified,ModifiedBy,Assigned,DeliveryMethod,CourseCategory,Region,
CheckedBy,ShippingStatus,PickupIn

// As of 2019-10-30
// 0-ClassID,1-Status,2-RequestedOn,3-RequestedBy,4-Dedicated,5-CourseID,6-CourseName,7-ItemCode,8-ClassDate,9-EndDate,10-ClassTimes,
// 11-MinEnroll,12-MaxEnroll,13-ShipDate,14-Facilitating,
// 15-WebinarLink,16-WebinarDate,17-ClassDays,18-Enrollment,19-ReservedSeats,20-pendingApproval,21-Waitlisted,22-Dropped,
// 23-VenueID,24-Venue,25-City,26-Address,27-ZIPPostal,28-ContactName,29-BusinessPhone,30-email,
// 31-VenueAttention,32-Notes,33- Shipper,34-Boxes,35-Weight,36-Courier,37-TrackingOut,38-TrackingIn,
// 39-Attendance,40-EvaluationsReturned,41-VenueNotified,42-Modified,43-ModifiedBy,44-Assigned,
// 45-DeliveryMethod,46-CourseCategory,47-tblClasses.Region,
// 48-CheckedBy,49-ShippingStatus,50-PickupIn,
// 51-avAssigned,venueCost,venueBEO,StartTime,55-EndTime,56-CourseColor



--------------------ELM.csv:--------------------
// 0-"Course Name",1-Class,2-"Start Date",3-Type,4-Facility,5-"Class Status",6-"Min Enroll",
// 7-"Max Enroll",8-Enrolled,9-"Reserved Seats",10-"Pending Approval",11-Waitlisted,12-Dropped,
// 13-Denied,14-Completed,15-"Not Completed",16-"In-Progress",17-"Planned",18-"Waived",19-"City"


--------------------venues.csv--------------------
0-VenueID,1-VenueName,2-ContactName,3-BusinessPhone,4-Address,5-City,6-StateProvince,7-ZIPPostal,8-email,9-Notes,10-Active,11-Union,12-Region



-------------------changes-course.csv-------------------

0-creqID,1-CourseID,2-CourseName,3-DateRequested,4-RequestedBy,5-Status,6-CompletedBy,7-CompletedDate,8-Request
