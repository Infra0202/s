#!/bin/bash

# Find the web root (common paths)
WEB_ROOTS=("/var/www/html" "/usr/share/nginx/html" "/srv/http" "/opt/lampp/htdocs")

# Loop through common web root locations
for DIR in "${WEB_ROOTS[@]}"; do
    if [ -d "$DIR" ]; then
        echo "<?php echo 'Hello, World!'; ?>" > "$DIR/hello.php"
        chmod 644 "$DIR/hello.php"
        echo "PHP file created at: $DIR/hello.php"
        exit 0
    fi
done

echo "Failed to find the web root."
