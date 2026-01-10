# LeanScale Platform - Railway Deployment Guide

## Prerequisites

- Railway account (https://railway.app)
- GitHub repository connected to Railway

## Quick Deploy

1. **Create a new Railway project**
   - Go to Railway dashboard
   - Click "New Project" → "Deploy from GitHub repo"
   - Select the `LeanScaleTeam/solidtime` repository

2. **Add PostgreSQL database**
   - In the Railway project, click "New" → "Database" → "PostgreSQL"
   - This will automatically create the database connection variables

3. **Configure environment variables**
   - Copy variables from `.env.railway.example`
   - Add them to Railway's "Variables" tab
   - Generate a new APP_KEY: `php artisan key:generate --show`

## Required Environment Variables

### Application
```
APP_NAME="LeanScale Platform"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY
APP_DEBUG=false
APP_URL=https://your-app.up.railway.app
APP_FORCE_HTTPS=true
OCTANE_SERVER=frankenphp
CONTAINER_MODE=http
AUTO_DB_MIGRATE=true
VITE_APP_NAME="LeanScale"
```

### Database (auto-filled by Railway PostgreSQL)
```
DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}
```

### Cache & Queue
```
CACHE_DRIVER=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

### Mail (configure with your provider)
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@leanscale.com"
MAIL_FROM_NAME="LeanScale"
```

## Post-Deployment

1. **Run database seed (optional)**
   ```bash
   railway run php artisan db:seed
   ```

2. **Create admin user**
   Add your email to `SUPER_ADMINS` environment variable

## Troubleshooting

### Container won't start
- Check logs in Railway dashboard
- Verify all required environment variables are set
- Ensure APP_KEY is properly generated

### Database connection issues
- Verify PostgreSQL service is running
- Check that database reference variables are correctly linked

### Static assets not loading
- Ensure `APP_FORCE_HTTPS=true` is set
- Check that `APP_URL` matches your Railway domain

## Architecture

The deployment uses:
- **FrankenPHP**: High-performance PHP application server based on Caddy
- **PostgreSQL**: Database for application data
- **Supervisor**: Process management for web server and workers

## Health Check

The application exposes `/up` endpoint for health checks.
Railway is configured to use this endpoint with a 300-second timeout.
