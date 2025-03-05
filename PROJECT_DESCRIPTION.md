# Casa Picto - System Requirements Document

## Project Overview
Casa Picto is a web-based platform designed to streamline the management of therapy sessions between healthcare professionals and patients. The system provides an intuitive interface for scheduling, patient record management, and tracking therapy sessions, making it an essential tool for healthcare centers, clinics, and independent practitioners.

## User Roles
- **Administrators**: Full system access, manage professionals, patients, and system configurations
- **Professionals**: Access to their schedules, patient information, and session management
- *Future Implementation*: Patient portal access

## Core Features

### 1. Session Management & Scheduling

#### Session Types
- **Regular Sessions**
  - Default duration: 40 minutes
  - Operating hours: 8:00 AM to 7:00 PM
  - Recurring weekly schedule
  - Automatically generated for each occurrence
  - Track attendance (show/no-show status)
  
- **Special Sessions**
  - Configurable duration
  - One-time admissions
  - Non-recurring appointments

#### Schedule Management
- Weekly recurring appointments
- Multiple therapy types per patient on different days
- Professional availability tracking
- Session status tracking (confirmed, pending, canceled)
- Attendance tracking per session

### 2. Patient Management

#### Patient Information
- Basic Details:
  - Name
  - Contact phone number
  - Email address
  - Emergency contact information
  
- Medical Information:
  - CUD (Certificado Unico de Discapacidad) status and type
  - Insurance details
  - Assigned professionals (multiple allowed)
  - Medical history
  
#### Insurance Management
- Insurance company selection from predefined list
- Insurance number tracking
- Insurance company ABM (Alta, Baja, Modificación) system
- File attachment support for documentation

### 3. Professional Management

#### Professional Profile
- Single specialty per professional
- Session assignment and tracking
- Daily and weekly session overview

#### Professional Availability
- Configurable working hours per day of the week
- Ability to set multiple time slots per day
- System enforces scheduling only within available hours
- Override capability for administrators in special cases
- Availability history tracking for schedule changes

#### Reports
- Daily patient summary
- Weekly summary
- Payment tracking (insurance vs. direct payment)
- Attendance records

### 4. Payment System

#### Payment Methods
- Insurance coverage (default if patient has insurance)
- Cash
- Bank transfer

#### Pricing Management
- Configurable default session prices
- Price override capability per session
- System-wide price updates without affecting historical sessions
- Different prices for different therapy types

#### Payment Tracking
- Session payment status
- Payment method recording
- Insurance vs. direct payment tracking

### 5. Technical Requirements

#### Session Instance Tracking
- Individual session records generated from recurring schedules
- Unique tracking of attendance, payments, and status
- Historical record maintenance

#### Price Management
- Default pricing per therapy type
- Valid date ranges for prices
- Automatic application of current prices to new sessions
- Historical price maintenance for past sessions

#### System Configuration
- Insurance company management
- Default session duration settings
- Price configuration
- Operating hours management

## User Experience Requirements

### Interface Requirements
- Clean, professional design
- Intuitive navigation
- Responsive layout for different devices
- Quick access to commonly used functions

### Workflow Optimization
- Streamlined session booking process
- Efficient patient record access
- Quick attendance and payment marking
- Easy access to daily and weekly schedules

## Reporting Requirements

### Professional Reports
- Daily session summary
- Weekly schedule overview
- Payment status tracking
- Attendance records

### Administrative Reports
- Financial summaries
- Attendance statistics
- Insurance vs. direct payment breakdown
- Patient session history

## Data Management

### Patient Records
- Centralized patient database
- Searchable patient list
- File attachment capability
- Medical history tracking

### Session Records
- Individual session tracking
- Attendance history
- Payment status
- Historical data maintenance

## Future Considerations
- Patient portal implementation
- Additional payment gateway integrations
- Enhanced reporting capabilities
- Mobile application development

## System Architecture
The system uses a relational database structure with the following core entities:
- Patients
- Professionals
- ProfessionalAvailability
- PatientProfessional (junction table)
- RecurringSchedule
- SessionInstance
- InsuranceCompany
- TherapyPricing

### Professional Availability Management
The system implements strict availability management:
1. Each professional can configure their working hours for each day of the week
2. Session scheduling is only allowed within the professional's available hours
3. The system validates all new session requests against professional availability
4. Administrators have the ability to override availability restrictions when necessary
5. Changes to professional availability are tracked historically to maintain schedule integrity
6. The system prevents double-booking of professionals within their available hours

Each entity maintains appropriate relationships and constraints to ensure data integrity and system functionality.