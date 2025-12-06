#!/bin/bash

# Get current date and time for beta versioning
CURRENT_DATE=$(date +"%Y%m%d")
CURRENT_TIME=$(date +"%H%M")
COMMIT_HASH=$(git rev-parse --short HEAD 2>/dev/null || echo "dev")

# Create beta version string
BETA_VERSION="1.0.0-beta.${CURRENT_DATE}.${CURRENT_TIME}-${COMMIT_HASH}"

echo "Updating plugin version to: ${BETA_VERSION}"

# Update version in main plugin file
sed -i "s/Version: [0-9.]*/Version: ${BETA_VERSION}/" alloia-woocommerce.php
sed -i "s/@version [0-9.]*/@version ${BETA_VERSION}/" alloia-woocommerce.php
sed -i "s/define('ALLOIA_VERSION', '[^']*');/define('ALLOIA_VERSION', '${BETA_VERSION}');/" alloia-woocommerce.php

# Update version in class file
sed -i "s/public \$version = '[^']*';/public \$version = '${BETA_VERSION}';/" alloia-woocommerce.php

echo "Version updated successfully!"
echo "New version: ${BETA_VERSION}"
