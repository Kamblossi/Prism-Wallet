#!/bin/bash
# Quick setup script to make all scripts executable and prepare for testing

echo "Making scripts executable..."
chmod +x validate-deployment.sh
chmod +x debug-startup.sh
chmod +x startup.sh

echo ""
echo "✓ Scripts are now executable"
echo ""
echo "Available commands:"
echo "  1. ./validate-deployment.sh    - Full deployment validation"
echo "  2. ./debug-startup.sh          - Run diagnostics (in container)"
echo ""
echo "Ready to test! Run: ./validate-deployment.sh"
