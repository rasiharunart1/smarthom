#!/bin/bash
set -e

echo "🚀 Starting Deployment..."

# 1. Pull latest code
# git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Update database
php artisan migrate --force

# 4. Build assets
npm install
npm run build

# 5. Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icon:cache

# 6. Restart Queue & Services
php artisan queue:restart
# If using Supervisor, it might automatically restart workers, 
# but we can manually restart if needed via aaPanel Supervisor Manager.

echo "✅ Deployment Successful!"
