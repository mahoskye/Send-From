#!/bin/bash

# WordPress.org SVN Deployment Script for Send From Plugin
# Version 2.3

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

# Configuration
PLUGIN_SLUG="send-from"
VERSION="2.3"
PLUGIN_DIR="$PROJECT_ROOT/plugin"
SVN_DIR="$PROJECT_ROOT/send-from"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}WordPress.org Plugin Deployment${NC}"
echo -e "${GREEN}Plugin: Send From${NC}"
echo -e "${GREEN}Version: ${VERSION}${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Step 1: Update SVN working copy
echo -e "${YELLOW}Step 1: Updating SVN working copy...${NC}"
cd "$SVN_DIR"
svn update

# Step 2: Copy files to trunk
echo -e "${YELLOW}Step 2: Copying plugin files to trunk...${NC}"
cp "$PLUGIN_DIR/send-from.php" trunk/
cp "$PLUGIN_DIR/readme.txt" trunk/
cp "$PLUGIN_DIR/LICENSE" trunk/

# Step 3: Copy screenshot to assets
echo -e "${YELLOW}Step 3: Updating assets...${NC}"
cp "$PLUGIN_DIR/screenshot-1.png" assets/

# Step 4: Show what changed
echo -e "${YELLOW}Step 4: Checking SVN status...${NC}"
svn status

echo ""
echo -e "${GREEN}Files ready for commit!${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Review the changes above"
echo "2. Run: cd send-from && svn ci -m 'Update to version 2.3 - Security fix for CVE-2025-46469 (Stored XSS)'"
echo "3. Create tag: svn cp trunk tags/2.3"
echo "4. Commit tag: svn ci -m 'Tagging version 2.3'"
echo ""
echo -e "${RED}WARNING: This script does NOT commit automatically.${NC}"
echo -e "${RED}Review changes carefully before committing!${NC}"
