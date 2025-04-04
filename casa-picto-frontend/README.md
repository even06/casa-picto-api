# Casa Picto - Healthcare Management System

Casa Picto is a comprehensive web-based platform designed for healthcare centers, clinics, and independent practitioners to streamline the management of therapy sessions, patient records, and scheduling.

## Features

- User authentication and role-based access
- Professional management and availability tracking
- Patient record management
- Session scheduling with recurring appointments
- Payment tracking and financial reporting

## Technology Stack

- **Frontend**: AngularJS, Bootstrap 5, Vite
- **Backend**: PHP with RESTful API
- **Database**: MySQL

## Prerequisites

- Node.js (v14+)
- NPM (v6+)
- PHP (v7.4+)
- MySQL (v5.7+)

## Installation

### Frontend Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/your-org/casa-picto.git
   cd casa-picto-frontend
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Start the development server:
   ```bash
   npm run dev
   ```

4. Build for production:
   ```bash
   npm run build
   ```

### Backend Setup

1. Set up the database:
   ```sql
   CREATE DATABASE casa_picto_v2;
   ```

2. Import the database schema:
   ```bash
   mysql -u username -p casa_picto_v2 < setup/schema.sql
   ```

3. Configure database connection:
   - Edit `includes/database.php` with your database credentials

4. Create initial admin user:
   ```bash
   php setup/create_admin.php
   ```

5. Set up a web server (Apache/Nginx) to serve the PHP files and point to the `/api` directory

## API Documentation

The backend implements a RESTful API with the following endpoints:

- Authentication: `/api/auth/*`
- Users: `/api/users/*`
- Professionals: `/api/professionals/*`
- Patients: `/api/patients/*`
- Schedules: `/api/schedules/*`
- Reports: `/api/reports/*`

For detailed API documentation, refer to `docs/api.md`.

## Development

### Directory Structure

```
casa-picto/
├── frontend/             # AngularJS frontend code
│   ├── src/              # Source code
│   │   ├── app/          # Application code
│   │   │   ├── components/  # AngularJS components
│   │   │   └── services/    # AngularJS services
│   │   ├── assets/       # Static assets
│   │   └── index.html    # Entry point
│   └── package.json      # Frontend dependencies
├── api/                  # PHP backend code
│   ├── auth/             # Authentication endpoints
│   ├── users/            # User management endpoints
│   ├── professionals/    # Professional management endpoints
│   ├── patients/         # Patient management endpoints
│   ├── schedules/        # Scheduling endpoints
│   └── reports/          # Reporting endpoints
├── includes/             # Shared PHP code
├── setup/                # Database and setup scripts
└── README.md             # This file
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -am 'Add my feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- [AngularJS](https://angularjs.org/)
- [Bootstrap](https://getbootstrap.com/)
- [Vite](https://vitejs.dev/)

# API Environment Configuration Guide

This document explains how to configure Casa Picto frontend to work with different API environments.

## Configuration Options

The application can access the API in two ways:

1. **Using Vite's Proxy** (default for development)
2. **Directly accessing the API URL** (useful for production builds)

## How to Configure the API Environment

### Option 1: Using Vite's Proxy (for Development)

This is the default configuration. The application sends requests to relative URLs (e.g., `/api/auth/login`), and Vite's development server proxies these requests to the actual API server.

To use this method:

1. In `src/app/services/api-config.service.js`, set:
   ```javascript
   apiConfig.useProxy = true;
   ```

2. In `vite.config.js`, configure the proxy target:
   ```javascript
   server: {
     proxy: {
       '/api': {
         target: 'https://www.casapicto.com/casapictov2',
         changeOrigin: true,
         secure: true
       }
     }
   }
   ```

This approach is convenient for development as it avoids CORS issues.

### Option 2: Direct API Access (for Production)

For production builds or when you don't want to use the proxy:

1. In `src/app/services/api-config.service.js`, set:
   ```javascript
   apiConfig.useProxy = false;
   apiConfig.apiUrl = 'https://www.casapicto.com/casapictov2/api';
   ```

This directly points the application to the production API endpoint.

## Switching Between Environments

For local development with a remote API:
- Keep `useProxy = true`
- Update the `target` in `vite.config.js` to point to your remote API

For local development with a local API:
- Keep `useProxy = true`
- Update the `target` in `vite.config.js` to point to your local API (e.g., `http://localhost:8000`)

For production:
- Set `useProxy = false`
- Make sure `apiUrl` points to the correct production API URL

## Building for Production

When building for production:

1. Set the appropriate API configuration in `api-config.service.js`
2. Run:
   ```bash
   npm run build
   ```

The build process will create optimized files in the `dist` directory, which you can then deploy to your web server.