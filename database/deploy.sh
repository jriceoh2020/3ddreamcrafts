#!/bin/bash
# Deployment script for 3DDreamCrafts database
# Run this script on the target server with SQLite3 installed

DB_FILE="craftsite.db"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "3DDreamCrafts Database Deployment"
echo "================================="

# Check if SQLite3 is available
if ! command -v sqlite3 &> /dev/null; then
    echo "Error: sqlite3 command not found. Please install SQLite3."
    exit 1
fi

# Remove existing database if it exists
if [ -f "$SCRIPT_DIR/$DB_FILE" ]; then
    echo "Removing existing database..."
    rm "$SCRIPT_DIR/$DB_FILE"
fi

echo "Creating new database..."

# Create database and run schema
sqlite3 "$SCRIPT_DIR/$DB_FILE" < "$SCRIPT_DIR/schema.sql"

if [ $? -eq 0 ]; then
    echo "Database schema created successfully!"
else
    echo "Error creating database schema!"
    exit 1
fi

# Insert sample data
sqlite3 "$SCRIPT_DIR/$DB_FILE" < "$SCRIPT_DIR/sample_data.sql"

if [ $? -eq 0 ]; then
    echo "Sample data inserted successfully!"
else
    echo "Error inserting sample data!"
    exit 1
fi

# Set appropriate permissions
chmod 664 "$SCRIPT_DIR/$DB_FILE"

echo ""
echo "Database deployment complete!"
echo "Database file: $SCRIPT_DIR/$DB_FILE"
echo ""
echo "Default admin credentials:"
echo "Username: admin"
echo "Password: admin123"
echo ""
echo "IMPORTANT: Change the default password after first login!"