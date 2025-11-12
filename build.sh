#!/bin/bash

# Source directory
SOURCE_DIR=".wordpress-org/"

# Destination directory
DEST_DIR="../login-and-logout-redirect/"

# Use rsync to copy files
rsync -avz "$SOURCE_DIR" "$DEST_DIR"

echo "Build complete!"
