#!/bin/bash

# Define the target directory (modify if needed)
TARGET_DIR="/var/www/html/uploads"

# Ensure the directory exists
mkdir -p "$TARGET_DIR"

# Write a simple PHP script
echo "<?php echo 'Hello, World!'; ?>" > "$TARGET_DIR/hello.php"

# Set permissions (optional)
chmod 644 "$TARGET_DIR/hello.php"

echo "PHP file created at: $TARGET_DIR/hello.php"
