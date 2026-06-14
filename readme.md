# Los Santos Roleplay Community Portal

Modern roleplay community portal with React frontend and Native PHP backend.

## 🚀 Quick Start

### Prerequisites

- PHP 8.1+ with PDO MySQL extension
- MySQL 8.0+ or MariaDB 10.5+
- Apache with mod_rewrite OR Nginx
- Node.js 18+ (for frontend development)

### Database Setup

1. **Create Database**
```sql
CREATE DATABASE lspd_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **Run Schema**
```bash
mysql -u root -p lspd_portal < database/schema/setup.sql
```

3. **Run Seed**
```bash
php database/seed.php
```

### Configuration

The API uses environment variables or defaults:

```bash
DB_HOST=localhost
DB_NAME=lspd_portal
DB_USER=root
DB_PASS=
```

### API Endpoints

#### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | User login |
| POST | `/api/auth/logout` | User logout |
| GET | `/api/auth/me` | Get current user |
| POST | `/api/auth/refresh` | Refresh access token |
| POST | `/api/auth/forgot` | Request password reset |
| POST | `/api/auth/reset` | Reset password |

#### Users
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/users/:uuid` | Get user profile |
| PUT | `/api/users/profile` | Update profile |
| POST | `/api/users/avatar` | Upload avatar |
| POST | `/api/users/cover` | Upload cover photo |
| PUT | `/api/users/password` | Change password |
| GET | `/api/users/activity` | Get activity logs |
| GET | `/api/users/permissions` | Get user permissions |
| GET | `/api/users/search` | Search users |
| GET | `/api/users/list` | List users (admin) |

#### Departments
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/departments` | List all departments |
| GET | `/api/departments/:slug` | Get department |
| GET | `/api/departments/:id/members` | Get members |
| GET | `/api/departments/:id/staff` | Get staff directory |
| GET | `/api/departments/:id/stats` | Get statistics |

#### Roles
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/roles` | List roles |
| GET | `/api/roles/:id` | Get role |
| POST | `/api/roles/create` | Create role |
| PUT | `/api/roles/:id` | Update role |
| DELETE | `/api/roles/:id` | Delete role |
| POST | `/api/roles/assign` | Assign role |
| DELETE | `/api/roles/remove` | Remove role |

#### Portal
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/portal` | Homepage data |
| GET | `/api/portal/stats` | Portal statistics |
| GET | `/api/portal/departments` | Departments data |
| GET | `/api/portal/activity` | Recent activity |
| GET | `/api/portal/members` | Recent members |

### Default Login

```
Username: admin
Password: admin123
```

⚠️ **Change this password in production!**

### Directory Structure

```
lspd-portal/
├── api/
│   ├── config.php           # API Configuration
│   ├── index.php            # Main entry point
│   ├── helpers/
│   │   ├── database.php     # Database utilities
│   │   ├── jwt.php          # JWT authentication
│   │   └── logging.php      # Activity logging
│   ├── middleware/
│   │   └── auth.php         # Authentication middleware
│   └── modules/
│       ├── auth/            # Authentication endpoints
│       ├── users/           # User endpoints
│       ├── departments/     # Department endpoints
│       ├── roles/           # Role management
│       ├── permissions/    # Permission management
│       └── portal/          # Homepage API
├── database/
│   ├── schema/
│   │   └── setup.sql       # Database schema
│   └── seed.php             # Seed data
├── assets/
│   ├── images/
│   │   ├── avatars/        # User avatars
│   │   ├── banners/        # User banners
│   │   └── logos/          # Department logos
│   └── icons/              # Custom icons
└── client/                  # React frontend (Tahap 2+)
```

### Color System

```css
:root {
    /* Portal */
    --portal: #9925EB;

    /* Departments */
    --lspd: #2563EB;
    --lssd: #92400E;
    --lsfd: #DC2626;
    --lsn: #EA580C;

    /* Base */
    --dark: #0F172A;
    --dark-secondary: #1E293B;
    --light: #F8FAFC;
    --gray: #94A3B8;
    --border: #334155;
}
```

## 📋 Development Phases

### Phase 1 ✅ (Current)
- [x] Database Schema
- [x] JWT Authentication
- [x] User Profile
- [x] Dynamic Role System
- [x] Permission System
- [x] Portal Homepage API

### Phase 2 📋
- [ ] Department Pages (LSPD, LSSD, LSFD, LSN)
- [ ] Forum System
- [ ] Categories & Topics
- [ ] Posts & BBCode Parser

### Phase 3 📋
- [ ] Recruitment System
- [ ] Notifications
- [ ] Private Messages
- [ ] Staff Directory
- [ ] Medals System

### Phase 4 📋
- [ ] Administrator Panel
- [ ] Department Leader Panel
- [ ] Logs System
- [ ] Advanced Permissions
- [ ] Settings Management

## 🔒 Security Features

- JWT Access + Refresh Tokens
- Password Hashing (bcrypt)
- SQL Injection Prevention (PDO Prepared Statements)
- XSS Protection
- Rate Limiting
- RBAC (Role-Based Access Control)
- CSRF Protection

## 📝 License

Copyright © 2026 LSPD Community. All rights reserved.
