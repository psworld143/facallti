#!/bin/bash

# Script to fix upload directory permissions for Apache
# Usage: ./fix_upload_permissions.sh [directory_path]

# Default directory if none specified
DEFAULT_DIR="/Applications/XAMPP/xamppfiles/htdocs/seait/uploads"

# Function to fix permissions
fix_permissions() {
    local dir="$1"
    
    if [ ! -d "$dir" ]; then
        echo "❌ Directory does not exist: $dir"
        return 1
    fi
    
    echo "🔧 Fixing permissions for: $dir"
    
    # Change ownership to daemon:daemon
    sudo chown -R daemon:daemon "$dir"
    
    # Set permissions to 755 for directories, 644 for files
    sudo find "$dir" -type d -exec chmod 755 {} \;
    sudo find "$dir" -type f -exec chmod 644 {} \;
    
    # Make sure the main directory is writable by daemon
    sudo chmod 755 "$dir"
    
    echo "✅ Permissions fixed for: $dir"
    echo "📁 Owner: $(ls -ld "$dir" | awk '{print $3":"$4}')"
    echo "🔐 Permissions: $(ls -ld "$dir" | awk '{print $1}')"
}

# Main execution
if [ $# -eq 0 ]; then
    echo "🔧 Fixing permissions for default directory: $DEFAULT_DIR"
    fix_permissions "$DEFAULT_DIR"
else
    for dir in "$@"; do
        fix_permissions "$dir"
    done
fi

echo "🎉 Permission fix completed!"
