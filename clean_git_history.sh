#!/bin/bash

# Create a backup of your repository (just in case)
echo "Creating a backup of your repository..."
cd ..
if [ ! -d "awd_backup_$(date +%Y%m%d)" ]; then
  cp -r awd "awd_backup_$(date +%Y%m%d)"
  echo "Backup created at ../awd_backup_$(date +%Y%m%d)"
fi
cd awd

# Use git filter-branch to remove .env from all commits
echo "Removing .env file from all commits in history..."
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .env" \
  --prune-empty --tag-name-filter cat -- --all

# Force garbage collection to remove the old objects
echo "Cleaning up repository..."
git for-each-ref --format="delete %(refname)" refs/original | git update-ref --stdin
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# Add .env.example if it doesn't exist yet
if [ ! -f ".env.example" ]; then
  echo "Creating .env.example template..."
  cat > .env.example << EOL
# This is an example .env file
# Copy this to .env and fill in your actual values
# But NEVER commit the actual .env file with real credentials

# Database configuration
DATABASE_URL=mysql://db_user:db_password@127.0.0.1:3306/db_name

# App settings
APP_ENV=dev
APP_SECRET=your_app_secret_here

# Google OAuth (REPLACE WITH YOUR ACTUAL CREDENTIALS WHEN USING)
GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
EOL
  git add .env.example
  git commit -m "Add .env.example as a template"
fi

echo ""
echo "History has been rewritten to remove .env file."
echo "To push these changes to GitHub, run:"
echo "git push -f origin main"
echo ""
echo "IMPORTANT: Anyone else working on this repository will need to clone it again"
echo "or run 'git pull --rebase' to sync with these changes."
