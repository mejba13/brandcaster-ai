#!/bin/bash

# BrandCaster AI - Database Setup Script
# This script helps create and configure the MySQL database

set -e

echo "============================================"
echo "BrandCaster AI - Database Setup"
echo "============================================"
echo ""

# Configuration
DB_NAME="brandcasterai"
DB_USER="root"
DB_CHARSET="utf8mb4"
DB_COLLATION="utf8mb4_unicode_ci"

# Check if MySQL is installed
if ! command -v mysql &> /dev/null; then
    echo "❌ Error: MySQL is not installed or not in PATH"
    echo ""
    echo "Please install MySQL first:"
    echo "  macOS: brew install mysql"
    echo "  Ubuntu: sudo apt-get install mysql-server"
    echo ""
    exit 1
fi

echo "✓ MySQL is installed"
echo ""

# Prompt for MySQL password
read -sp "Enter MySQL root password (press Enter if no password): " MYSQL_PASSWORD
echo ""
echo ""

# Create database
echo "Creating database '$DB_NAME'..."

if [ -z "$MYSQL_PASSWORD" ]; then
    # No password
    mysql -u "$DB_USER" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET $DB_CHARSET COLLATE $DB_COLLATION;" 2>/dev/null
else
    # With password
    mysql -u "$DB_USER" -p"$MYSQL_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET $DB_CHARSET COLLATE $DB_COLLATION;" 2>/dev/null
fi

if [ $? -eq 0 ]; then
    echo "✓ Database '$DB_NAME' created successfully"
else
    echo "❌ Failed to create database"
    exit 1
fi

echo ""

# Optional: Create dedicated user
read -p "Do you want to create a dedicated database user? (recommended for production) [y/N]: " CREATE_USER

if [[ $CREATE_USER =~ ^[Yy]$ ]]; then
    echo ""
    read -p "Enter new database username [brandcaster]: " NEW_DB_USER
    NEW_DB_USER=${NEW_DB_USER:-brandcaster}

    read -sp "Enter password for new user: " NEW_DB_PASSWORD
    echo ""

    if [ -z "$MYSQL_PASSWORD" ]; then
        mysql -u "$DB_USER" -e "CREATE USER IF NOT EXISTS '$NEW_DB_USER'@'localhost' IDENTIFIED BY '$NEW_DB_PASSWORD';"
        mysql -u "$DB_USER" -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$NEW_DB_USER'@'localhost';"
        mysql -u "$DB_USER" -e "FLUSH PRIVILEGES;"
    else
        mysql -u "$DB_USER" -p"$MYSQL_PASSWORD" -e "CREATE USER IF NOT EXISTS '$NEW_DB_USER'@'localhost' IDENTIFIED BY '$NEW_DB_PASSWORD';"
        mysql -u "$DB_USER" -p"$MYSQL_PASSWORD" -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$NEW_DB_USER'@'localhost';"
        mysql -u "$DB_USER" -p"$MYSQL_PASSWORD" -e "FLUSH PRIVILEGES;"
    fi

    echo ""
    echo "✓ User '$NEW_DB_USER' created with full privileges on '$DB_NAME'"
    echo ""
    echo "Update your .env file with:"
    echo "  DB_USERNAME=$NEW_DB_USER"
    echo "  DB_PASSWORD=$NEW_DB_PASSWORD"
else
    echo ""
    echo "Using root user. Your .env file should have:"
    echo "  DB_USERNAME=root"
    if [ -z "$MYSQL_PASSWORD" ]; then
        echo "  DB_PASSWORD="
    else
        echo "  DB_PASSWORD=your_root_password"
    fi
fi

echo ""
echo "============================================"
echo "✓ Database setup complete!"
echo "============================================"
echo ""
echo "Next steps:"
echo "  1. Update your .env file with database credentials"
echo "  2. Run: php artisan migrate:fresh --seed"
echo "  3. Start the queue worker: php artisan horizon"
echo "  4. Start the dev server: php artisan serve"
echo ""
echo "Default login credentials:"
echo "  Email: admin@brandcaster.ai"
echo "  Password: password"
echo ""
