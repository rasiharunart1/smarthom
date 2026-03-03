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

# 6. Restart Queue & Services via Supervisor
echo "🔄 Restarting Supervisor processes..."
if command -v supervisorctl &> /dev/null; then
    sudo supervisorctl restart smarthom:*
    echo "✅ Supervisor processes restarted."
else
    echo "⚠️  supervisorctl not found. Restart manually if needed."
    # Fallback: signal queue workers to restart gracefully
    php artisan queue:restart
fi

echo "✅ Deployment Successful!"
