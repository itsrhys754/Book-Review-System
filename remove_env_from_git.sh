#!/bin/bash

# Make sure .env is in .gitignore (it already is in your case)
echo "Checking if .env is in .gitignore..."
if grep -q "/.env" .gitignore; then
  echo ".env is already in .gitignore. Good!"
else
  echo "Adding .env to .gitignore..."
  echo "/.env" >> .gitignore
  git add .gitignore
  git commit -m "Add .env to .gitignore"
fi

# Remove .env from git tracking without deleting the file
echo "Removing .env from git tracking..."
git rm --cached .env
git commit -m "Remove .env file with sensitive information from git tracking"

# Create .env.example if it doesn't exist (we already created this)
echo "Creating .env.example as a template..."
git add .env.example
git commit -m "Add .env.example as a template"

echo ""
echo "Changes committed locally. Now you need to force push these changes to GitHub."
echo "WARNING: This will overwrite the remote repository history."
echo "Run the following command to push the changes:"
echo "git push -f origin main"
echo ""
echo "After pushing, your sensitive information will no longer be in the repository history."
