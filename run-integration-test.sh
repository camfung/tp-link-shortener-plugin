#!/bin/bash

# Integration Test Runner for SnapCapture API Client
# This script runs the integration tests that make actual API calls

echo "=========================================="
echo "SnapCapture API Integration Test Runner"
echo "=========================================="
echo ""

# Check if API key is set
if [ -z "$SNAPCAPTURE_API_KEY" ]; then
    echo "Error: SNAPCAPTURE_API_KEY environment variable is not set"
    echo ""
    echo "To run integration tests, you need a RapidAPI key for SnapCapture."
    echo "Get your key at: https://rapidapi.com/thebluesoftware-development/api/snapcapture1"
    echo ""
    echo "Then run:"
    echo "  export SNAPCAPTURE_API_KEY=your-api-key-here"
    echo "  ./run-integration-test.sh"
    echo ""
    echo "Or run directly:"
    echo "  SNAPCAPTURE_API_KEY=your-api-key-here ./run-integration-test.sh"
    echo ""
    exit 1
fi

echo "API key found: ${SNAPCAPTURE_API_KEY:0:10}..."
echo ""

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install
    echo ""
fi

# Create screenshots directory
mkdir -p tests/screenshots

echo "Running integration tests..."
echo "Screenshots will be saved to: tests/screenshots/"
echo ""

# Run integration tests
./vendor/bin/phpunit --testsuite Integration --colors=always

EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "=========================================="
    echo "All integration tests passed!"
    echo "=========================================="
    echo ""
    echo "Screenshots saved to: tests/screenshots/"
    echo ""
    echo "Verify the following files:"
    ls -lh tests/screenshots/ 2>/dev/null || echo "No screenshots found"
else
    echo "=========================================="
    echo "Integration tests failed!"
    echo "=========================================="
    echo ""
    echo "Check the error messages above for details."
fi

exit $EXIT_CODE
