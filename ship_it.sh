#!/bin/bash

# Stop the script if anything goes wrong
set -e

echo "🇬🇧 Right, let's get this sorted..."

# 1. FIX PERMISSIONS (The "Big Hammer" Approach)
# We do this first so Git doesn't whine about 'Permission denied' on lock files.
echo "🧹 Tidying up permissions..."

# Ensure current user owns files to prevent "permission denied"
sudo chown -R $USER:www-data .

# Set write permissions for web server folders
sudo chmod -R 775 storage bootstrap/cache

# Apply "Sticky Bit" so future files created by Docker inherit group permissions automatically
sudo chmod -R g+s storage bootstrap/cache

# 2. LARAVEL HOUSECLEANING
echo "✨ Scrubbing the cache..."
# We use the docker container to run this command so it runs as the correct internal user.
# This is safer than running it on your host machine because it prevents creating root-owned cache files.
docker exec chatbridge-app php artisan optimize:clear

# 3. GIT OPERATIONS
echo "📦 Packaging your brilliance..."

# Stage all changes (new files, modified files, deletions)
git add .

# Check if there are actually changes to commit
if git diff-index --quiet HEAD --; then
    echo "☕ No changes to commit, darling. You're already up to date!"
else
    # Ask for a commit message (defaults to a generic one if you just hit Enter)
    read -p "📝 Enter commit message (Default: 'Update and Fixes'): " COMMIT_MSG
    COMMIT_MSG=${COMMIT_MSG:-"Update and Fixes"}

    git commit -m "$COMMIT_MSG"
    
    echo "⬇️  Pulling latest changes (just in case)..."
    # Pull with rebase to keep your history clean
    git pull --rebase

    echo "🚀 Pushing to repository..."
    git push
fi

echo "✅ All done! Bob's your uncle."
