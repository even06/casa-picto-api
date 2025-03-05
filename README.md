# Casa Picto API Documentation

## Common Patterns

### Authentication
All endpoints except /auth/login require a valid JWT token in the Authorization header:
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Response Format
All endpoints follow this standard response format:

Success Response:
```json
{
  "success": true,
  "data": {}
}
```

Error Response:
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERROR_CODE",
    "message": "Error description"
  }
}
```

## API Endpoints

### Authentication

#### POST /api/auth/login
Authenticate user and get token.

Request:
```json
{
  "username": "john.doe",
  "password": "securepass123"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "username": "john.doe",
      "role": "professional",
      "name": "John Doe",
      "professionalId": 1
    }
  }
}
```

#### POST /api/auth/logout
Invalidate current token.

Request:
```
POST /api/auth/logout
Headers:
  Authorization: Bearer your_token_here
```

Response:
```json
{
  "success": true,
  "data": {
    "message": "Successfully logged out"
  }
}
```

### User Management

#### GET /api/users/list
Get list of users. Admin only.

Query Parameters:
- search?: string (search by name/username)
- page?: number (default: 1)
- limit?: number (default: 10)
- role?: string (admin/professional)

Response:
```json
{
  "success": true,
  "data": {
    "users": [{
      "id": 1,
      "username": "sarah.wilson",
      "role": "professional",
      "created_at": "2025-02-03T10:00:00Z",
      "professional": {
        "id": 1,
        "name": "Sarah Wilson",
        "specialty": "Speech Therapy",
        "is_active": true
      }
    }],
    "pagination": {
      "total": 50,
      "page": 1,
      "limit": 10,
      "total_pages": 5
    }
  }
}
```

#### POST /api/users/create
Create new user. Admin only.

Request:
```json
{
  "username": "sarah.wilson",
  "password": "securepass123",
  "role": "professional",
  "name": "Sarah Wilson",
  "specialty": "Speech Therapy"  // Required for professionals
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "username": "sarah.wilson",
    "role": "professional",
    "name": "Sarah Wilson",
    "professional_id": 1,
    "specialty": "Speech Therapy"
  }
}
```

#### PUT /api/users/update
Update user information. Admin only.

Request:
```json
{
  "password": "newpassword",  // Optional
  "name": "Sarah Wilson",     // For professionals
  "specialty": "Speech Therapy",  // For professionals
  "is_active": true          // For professionals
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "username": "sarah.wilson",
    "role": "professional",
    "professional": {
      "id": 1,
      "name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "is_active": true
    }
  }
}
```

#### DELETE /api/users/delete
Delete user. Admin only.

Request:
```
DELETE /api/users/delete?id=1
```

Response:
```json
{
  "success": true,
  "data": {
    "message": "User deleted successfully"
  }
}
```

### Professional Management

#### GET /api/professionals/list
Get list of professionals.

Query Parameters:
- search?: string (search in name or specialty)
- page?: number (default: 1)
- limit?: number (default: 10)
- specialty?: string
- isActive?: boolean

Response:
```json
{
  "success": true,
  "data": {
    "professionals": [{
      "id": 1,
      "name": "Sarah Wilson",
      "username": "sarah.wilson",
      "specialty": "Speech Therapy",
      "is_active": true,
      "active_patients": 5,
      "created_at": "2025-01-01T10:00:00Z",
      "updated_at": "2025-01-01T10:00:00Z"
    }],
    "specialties": [
      "Speech Therapy",
      "Physical Therapy",
      "Occupational Therapy"
    ],
    "pagination": {
      "total": 20,
      "page": 1,
      "limit": 10,
      "total_pages": 2
    }
  }
}
```

#### GET /api/professionals/get
Get professional details with availability and patients.

Request:
```
GET /api/professionals/get?id=1
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Sarah Wilson",
    "username": "sarah.wilson",
    "specialty": "Speech Therapy",
    "is_active": true,
    "active_patients": 5,
    "created_at": "2025-02-03T10:00:00Z",
    "updated_at": "2025-02-03T10:00:00Z",
    "availability": [{
      "id": 1,
      "day_of_week": "MONDAY",
      "start_time": "09:00:00",
      "end_time": "17:00:00",
      "valid_from": "2025-01-01",
      "valid_to": null,
      "is_active": true
    }],
    "exceptions": [{
      "id": 1,
      "date": "2025-02-14",
      "start_time": null,
      "end_time": null,
      "is_available": false,
      "reason": "Holiday"
    }],
    "patients": [{
      "id": 1,
      "name": "John Smith",
      "start_date": "2025-01-01"
    }]
  }
}
```

#### POST /api/professionals/availability/update
Update professional's regular availability.

Request:
```json
{
  "day_of_week": "MONDAY",
  "start_time": "09:00:00",
  "end_time": "17:00:00",
  "valid_from": "2025-01-01",
  "valid_to": null
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "day_of_week": "MONDAY",
    "start_time": "09:00:00",
    "end_time": "17:00:00",
    "valid_from": "2025-01-01",
    "valid_to": null,
    "is_active": true
  }
}
```

#### POST /api/professionals/availability/exception
Add availability exception (holiday, time off, etc.).

Request:
```json
{
  "exception_date": "2025-02-14",
  "is_available": false,
  "start_time": null,
  "end_time": null,
  "reason": "Holiday"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "exception_date": "2025-02-14",
    "is_available": false,
    "start_time": null,
    "end_time": null,
    "reason": "Holiday"
  }
}
```

### Patient Management

#### GET /api/patients/list
Get list of patients.

Query Parameters:
- page?: number (default: 1)
- limit?: number (default: 10)
- search?: string (search in name/phone/email)
- isActive?: boolean
- insuranceId?: number
- professionalId?: number

Response:
```json
{
  "success": true,
  "data": {
    "patients": [{
      "id": 1,
      "name": "John Smith",
      "email": "john.smith@email.com",
      "phone": "1234567890",
      "emergency_contact_name": "Jane Smith",
      "emergency_contact_phone": "0987654321",
      "insurance_company": {
        "id": 1,
        "name": "Insurance Co"
      },
      "insurance_number": "INS123456",
      "cud_type": "Type A",
      "has_cud": true,
      "is_active": true,
      "created_at": "2025-02-03T10:00:00Z",
      "professionals": [{
        "id": 1,
        "name": "Sarah Wilson",
        "specialty": "Speech Therapy"
      }]
    }],
    "pagination": {
      "total": 50,
      "page": 1,
      "limit": 10,
      "total_pages": 5
    }
  }
}
```

#### POST /api/patients/create
Create new patient.

Request:
```json
{
  "name": "John Smith",
  "email": "john.smith@email.com",
  "phone": "1234567890",
  "emergency_contact_name": "Jane Smith",
  "emergency_contact_phone": "0987654321",
  "insurance_company_id": 1,
  "insurance_number": "INS123456",
  "cud_type": "Type A",
  "has_cud": true,
  "professionals": [1, 2]  // Array of professional IDs to assign
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Smith",
    "email": "john.smith@email.com",
    "phone": "1234567890",
    "emergency_contact_name": "Jane Smith",
    "emergency_contact_phone": "0987654321",
    "insurance_company": {
      "id": 1,
      "name": "Insurance Co"
    },
    "insurance_number": "INS123456",
    "cud_type": "Type A",
    "has_cud": true,
    "is_active": true,
    "created_at": "2025-02-03T10:00:00Z",
    "professionals": [{
      "id": 1,
      "name": "Sarah Wilson",
      "specialty": "Speech Therapy"
    }]
  }
}
```

#### GET /api/patients/get
Get single patient details.

Request:
```
GET /api/patients/get?id=1
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Smith",
    "email": "john.smith@email.com",
    "phone": "1234567890",
    "emergency_contact_name": "Jane Smith",
    "emergency_contact_phone": "0987654321",
    "insurance_company": {
      "id": 1,
      "name": "Insurance Co"
    },
    "insurance_number": "INS123456",
    "cud_type": "Type A",
    "has_cud": true,
    "is_active": true,
    "created_at": "2025-02-03T10:00:00Z",
    "updated_at": "2025-02-03T10:00:00Z",
    "professionals": [{
      "id": 1,
      "name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "start_date": "2025-01-01"
    }],
    "recent_sessions": [{
      "id": 1,
      "session_date": "2025-02-03",
      "session_time": "10:00:00",
      "duration": 40,
      "status": "SCHEDULED",
      "payment_status": "PENDING",
      "payment_type": "INSURANCE",
      "professional_name": "Sarah Wilson",
      "professional_specialty": "Speech Therapy",
      "is_recurring": true,
      "day_of_week": "MONDAY"
    }]
  }
}
```

#### PUT /api/patients/update
Update patient information.

Request:
```json
{
  "name": "John Smith Jr",
  "email": "john.smith.jr@email.com",
  "phone": "1234567890",
  "emergency_contact_name": "Jane Smith",
  "emergency_contact_phone": "0987654321",
  "insurance_company_id": 1,
  "insurance_number": "INS123456",
  "cud_type": "Type A",
  "has_cud": true,
  "professionals": [1, 2]  // Optional: Updates professional assignments
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Smith Jr",
    "email": "john.smith.jr@email.com",
    "phone": "1234567890",
    "emergency_contact_name": "Jane Smith",
    "emergency_contact_phone": "0987654321",
    "insurance_company": {
      "id": 1,
      "name": "Insurance Co"
    },
    "insurance_number": "INS123456",
    "cud_type": "Type A",
    "has_cud": true,
    "is_active": true,
    "updated_at": "2025-02-03T11:00:00Z",
    "professionals": [{
      "id": 1,
      "name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "start_date": "2025-01-01"
    }]
  }
}
```

#### GET /api/patients/attendance-history
Get complete attendance history for a patient.

Request:
```
GET /api/patients/attendance-history?id=1&start_date=2025-01-01&end_date=2025-12-31
```

Response:
```json
{
  "success": true,
  "data": {
    "patient": {
      "id": 1,
      "name": "John Smith",
      "is_active": true
    },
    "period": {
      "start_date": "2025-01-01",
      "end_date": "2025-12-31"
    },
    "overall_stats": {
      "total_sessions": 50,
      "completed_sessions": 45,
      "no_shows": 3,
      "cancelled_sessions": 2,
      "attendance_rate": 90.0,
      "no_show_rate": 6.0
    },
    "by_professional": [{
      "professional_id": 1,
      "professional_name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "total_sessions": 30,
      "completed_sessions": 28,
      "no_shows": 1,
      "cancelled_sessions": 1
    }],
    "by_day_of_week": [{
      "day_of_week": "MONDAY",
      "total_sessions": 10,
      "completed_sessions": 9,
      "no_shows": 1
    }],
    "patterns": {
      "max_consecutive_no_shows": 2,
      "no_show_dates": ["2025-02-03", "2025-02-10"]
    },
    "session_history": [{
      "session_date": "2025-02-03",
      "session_time": "10:00:00",
      "status": "COMPLETED",
      "professional_name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "is_recurring": true
    }]
  }
}
```

#### GET /api/patients/payment-history
Get complete payment history for a patient.

Request:
```
GET /api/patients/payment-history?id=1&start_date=2025-01-01&end_date=2025-12-31
```

Response:
```json
{
  "success": true,
  "data": {
    "patient": {
      "id": 1,
      "name": "John Smith",
      "insurance_company": {
        "id": 1,
        "name": "Insurance Co"
      },
      "insurance_number": "INS123456"
    },
    "period": {
      "start_date": "2025-01-01",
      "end_date": "2025-12-31"
    },
    "summary": {
      "total_sessions": 50,
      "insurance_sessions": 40,
      "direct_payment_sessions": 10,
      "total_paid": 1000.00,
      "total_pending": 200.00
    },
    "by_professional": [{
      "professional_id": 1,
      "professional_name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "total_sessions": 30,
      "insurance_sessions": 25,
      "direct_payment_sessions": 5,
      "total_paid": 500.00,
      "total_pending": 100.00
    }],
    "monthly_patterns": [{
      "month": "2025-01",
      "total_sessions": 8,
      "paid_sessions": 7,
      "total_amount": 700.00
    }],
    "pending_payments": [{
      "session_id": 1,
      "session_date": "2025-02-03",
      "session_time": "10:00:00",
      "professional_name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "pending_amount": 100.00
    }],
    "payment_history": [{
      "session_id": 1,
      "session_date": "2025-02-03",
      "session_time": "10:00:00",
      "professional_name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "status": "COMPLETED",
      "payment_type": "CASH",
      "payment_status": "PAID",
      "payment_amount": 100.00,
      "payment_date": "2025-02-03",
      "expected_amount": 100.00
    }]
  }
}
```

#### POST /api/patients/:id/files
Upload file for a patient.

Request:
```
POST /api/patients/1/files
Content-Type: multipart/form-data

file: [binary data]
description: "Medical report from initial evaluation"
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "file_name": "medical_report.pdf",
    "file_path": "uploads/patients/1/medical_report.pdf",
    "file_type": "application/pdf",
    "description": "Medical report from initial evaluation",
    "uploaded_at": "2025-02-03T10:00:00Z"
  }
}
```

#### GET /api/patients/:id/files
Get list of patient files.

Request:
```
GET /api/patients/1/files
```

Response:
```json
{
  "success": true,
  "data": {
    "files": [{
      "id": 1,
      "file_name": "medical_report.pdf",
      "file_path": "uploads/patients/1/medical_report.pdf",
      "file_type": "application/pdf",
      "description": "Medical report from initial evaluation",
      "uploaded_at": "2025-02-03T10:00:00Z"
    }]
  }
}
```

### Schedule Management

#### GET /api/schedules/conflicts
Check availability and conflicts for a specific date.

Request:
```
GET /api/schedules/conflicts?professional_id=1&date=2025-02-03&duration=40
```

Response:
```json
{
  "success": true,
  "data": {
    "date": "2025-02-03",
    "is_available": true,
    "working_hours": {
      "start": "09:00:00",
      "end": "17:00:00"
    },
    "existing_sessions": [{
      "session_time": "10:00:00",
      "duration": 40,
      "patient_name": "John Smith"
    }],
    "available_slots": [{
      "time": "09:00:00",
      "end_time": "09:40:00"
    }]
  }
}
```

#### GET /api/schedules/daily-schedule
Get schedule for a specific day.

Request:
```
GET /api/schedules/daily-schedule?date=2025-02-03&professional_id=1
```

Response:
```json
{
  "success": true,
  "data": {
    "date": "2025-02-03",
    "day_of_week": "MONDAY",
    "schedule": [{
      "id": 1,
      "time": "09:00:00",
      "duration": 40,
      "status": "SCHEDULED",
      "payment_status": "PENDING",
      "payment_type": "INSURANCE",
      "is_recurring": true,
      "patient": {
        "id": 1,
        "name": "John Smith",
        "phone": "1234567890"
      },
      "professional": {
        "id": 1,
        "name": "Dr. Sarah Wilson",
        "specialty": "Speech Therapy"
      }
    }],
    "exceptions": [{
      "professional_id": 1,
      "is_available": false,
      "start_time": "13:00:00",
      "end_time": "14:00:00",
      "reason": "Lunch break"
    }],
    "availability": [{
      "professional_id": 1,
      "start_time": "09:00:00",
      "end_time": "17:00:00"
    }]
  }
}
```

#### GET /api/schedules/weekly-view
Get full week schedule view.

Request:
```
GET /api/schedules/weekly-view?professional_id=1&date=2025-02-03
```

Response:
```json
{
  "success": true,
  "data": {
    "week_start": "2025-02-03",
    "week_end": "2025-02-09",
    "schedule": [{
      "date": "2025-02-03",
      "day_of_week": "MONDAY",
      "sessions": [{
        "id": 1,
        "time": "09:00:00",
        "duration": 40,
        "status": "SCHEDULED",
        "payment_status": "PENDING",
        "payment_type": "INSURANCE",
        "is_recurring": true,
        "patient": {
          "id": 1,
          "name": "John Smith",
          "phone": "1234567890"
        },
        "professional": {
          "id": 1,
          "name": "Sarah Wilson",
          "specialty": "Speech Therapy"
        }
      }],
      "exceptions": [],
      "availability": [{
        "professional_id": 1,
        "start_time": "09:00:00",
        "end_time": "17:00:00"
      }]
    }]
  }
}
```

#### POST /api/schedules/create
Create new session (recurring or one-time).

Request for recurring session:
```json
{
  "type": "recurring",
  "patient_id": 1,
  "professional_id": 1,
  "day_of_week": "MONDAY",
  "session_time": "10:00:00",
  "duration": 40,
  "payment_type": "INSURANCE"
}
```

Request for one-time session:
```json
{
  "type": "one-time",
  "patient_id": 1,
  "professional_id": 1,
  "session_date": "2025-02-10",
  "session_time": "10:00:00",
  "duration": 40,
  "payment_type": "CASH"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "session_date": "2025-02-10",
    "session_time": "10:00:00",
    "duration": 40,
    "status": "SCHEDULED",
    "payment_type": "CASH",
    "payment_status": "PENDING",
    "is_recurring": false,
    "day_of_week": "MONDAY",
    "patient_name": "John Smith",
    "professional_name": "Sarah Wilson"
  }
}
```

#### PUT /api/sessions/:id/status
Update session status and payment information.

Request:
```json
{
  "status": "COMPLETED",
  "payment_status": "PAID",
  "payment_type": "CASH",
  "payment_amount": 100.00
}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "COMPLETED",
    "payment_status": "PAID",
    "payment_type": "CASH",
    "payment_amount": 100.00,
    "payment_date": "2025-02-03T11:00:00Z"
  }
}
```

#### DELETE /api/schedules/delete
Delete session (single or recurring).

Request:
```
DELETE /api/schedules/delete?id=1&type=recurring&from_date=2025-02-10
```

Response:
```json
{
  "success": true,
  "data": {
    "message": "All future recurring sessions deleted"
  }
}
```

### Reports

#### GET /api/reports/professional-summary
Get professional's summary report.

Request:
```
GET /api/reports/professional-summary?professional_id=1&start_date=2025-02-01&end_date=2025-02-28
```

Response:
```json
{
  "success": true,
  "data": {
    "professional": {
      "name": "Sarah Wilson",
      "specialty": "Speech Therapy"
    },
    "period": {
      "start_date": "2025-02-01",
      "end_date": "2025-02-28"
    },
    "summary": {
      "total_sessions": 50,
      "completed_sessions": 45,
      "no_shows": 3,
      "cancelled_sessions": 2,
      "attendance_rate": 90.0,
      "no_show_rate": 6.0,
      "total_patients": 10,
      "recurring_patients": 8
    },
    "payments": {
      "cash_total": 1500.00,
      "transfer_total": 1000.00,
      "total_collected": 2500.00
    },
    "attendance_by_day": [{
      "day_name": "MONDAY",
      "total_sessions": 10,
      "completed_sessions": 9,
      "no_shows": 1
    }],
    "no_show_patients": [{
      "patient_id": 1,
      "patient_name": "John Smith",
      "total_sessions": 10,
      "no_shows": 2,
      "no_show_rate": 20.0
    }],
    "pending_payments": [{
      "patient_id": 1,
      "patient_name": "John Smith",
      "pending_sessions": 2,
      "pending_amount": 200.00
    }]
  }
}
```

#### GET /api/reports/financial
Get financial report. Admin only.

Request:
```
GET /api/reports/financial?start_date=2025-02-01&end_date=2025-02-28&professional_id=1
```

Response:
```json
{
  "success": true,
  "data": {
    "period": {
      "start_date": "2025-02-01",
      "end_date": "2025-02-28"
    },
    "summary": {
      "total_sessions": 100,
      "completed_sessions": 90,
      "insurance_sessions": 70,
      "direct_payment_sessions": 30,
      "total_patients": 20,
      "total_professionals": 5,
      "payments": {
        "cash": 2000.00,
        "transfer": 1500.00,
        "total": 3500.00
      }
    },
    "professional_summary": [{
      "professional_id": 1,
      "professional_name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "total_patients": 10,
      "total_sessions": 50,
      "total_collected": 2500.00,
      "pending_amount": 500.00
    }],
    "daily_payments": [{
      "date": "2025-02-03",
      "total_sessions": 10,
      "cash_amount": 500.00,
      "transfer_amount": 300.00
    }],
    "insurance_summary": [{
      "insurance_company_id": 1,
      "insurance_company_name": "Insurance Co",
      "total_patients": 5,
      "total_sessions": 30
    }],
    "pending_payments": [{
      "patient_id": 1,
      "patient_name": "John Smith",
      "professional_id": 1,
      "professional_name": "Sarah Wilson",
      "specialty": "Speech Therapy",
      "pending_sessions": 2,
      "pending_amount": 200.00
    }]
  }
}
```

### Standard Error Responses

#### 400 Bad Request
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "INVALID_INPUT",
    "message": "Description of what's wrong with the input"
  }
}
```

#### 401 Unauthorized
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "UNAUTHORIZED",
    "message": "No authorization token provided"
  }
}
```

#### 403 Forbidden
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "FORBIDDEN",
    "message": "You don't have permission to access this resource"
  }
}
```

#### 404 Not Found
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "NOT_FOUND",
    "message": "Resource not found"
  }
}
```

#### 500 Internal Server Error
```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "An unexpected error occurred"
  }
}
```

### Common Error Codes

- `INVALID_INPUT`: Request data is missing required fields or contains invalid values
- `UNAUTHORIZED`: Missing or invalid authentication token
- `FORBIDDEN`: User doesn't have required permissions for the action
- `NOT_FOUND`: Requested resource doesn't exist
- `METHOD_NOT_ALLOWED`: HTTP method not supported for this endpoint
- `DUPLICATE_NAME`: Name already exists (for insurance companies, etc.)
- `USERNAME_EXISTS`: Username already taken
- `CREATE_USER_ERROR`: Error creating new user
- `UPDATE_USER_ERROR`: Error updating user
- `DELETE_USER_ERROR`: Error deleting user
- `CREATE_PATIENT_ERROR`: Error creating new patient
- `UPDATE_PATIENT_ERROR`: Error updating patient
- `UPLOAD_ERROR`: Error uploading file
- `CREATE_SESSION_ERROR`: Error creating session
- `UPDATE_SESSION_ERROR`: Error updating session
- `DELETE_SESSION_ERROR`: Error deleting session
- `CONFLICTS_CHECK_ERROR`: Error checking schedule conflicts
- `REPORT_ERROR`: Error generating report