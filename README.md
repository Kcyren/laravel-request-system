# Laravel Request System
DevOps Laboratory Activity - Lab 1

**Student Name:** Cyrene Jane Cayetano  
**Course, Year, & Section:** BSIT 4-3

## Software Requirements
- PHP >= 8.2
- Composer
- MariaDB / MySQL Server
- Git

## Installation Instructions
1. Clone the repository:
   git clone https://github.com/Kcyren/laravel-request-system.git
   cd laravel-request-system
2. Install dependencies:
   composer install
3. Copy environment configuration:
   cp .env.example .env
   php artisan key:generate
4. Configure database in .env:
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laravel_request_system_db
   DB_USERNAME=root
   DB_PASSWORD=
5. Run database migrations:
   php artisan migrate
6. Start development server:
   php artisan serve

**Repository Link:** https://github.com/Kcyren/laravel-request-system

