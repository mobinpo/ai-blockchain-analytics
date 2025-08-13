#!/bin/bash

echo "🚀 Testing PDF Generation from Vue Views"
echo "========================================"

# Set the base URL
BASE_URL="http://localhost:8000"

# Test 1: Check PDF service status
echo
echo "📊 1. Checking PDF service status..."
curl -s -X GET "$BASE_URL/enhanced-pdf/status" \
  -H "Accept: application/json" | jq '.' 2>/dev/null || echo "Service status check failed"

# Test 2: Test DomPDF generation
echo
echo "📄 2. Testing DomPDF generation..."
curl -s -X POST "$BASE_URL/enhanced-pdf/generate/route" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "route": "email.preferences",
    "data": {
      "preferences": {
        "marketing_emails": true,
        "product_updates": true,
        "security_alerts": true,
        "onboarding_emails": false,
        "weekly_digest": true,
        "frequency": "normal"
      },
      "stats": {
        "total_sent": 150,
        "delivered": 142,
        "opened": 89,
        "clicked": 23,
        "open_rate": 62.7,
        "click_rate": 16.2
      },
      "pdf_mode": true
    },
    "options": {
      "engine": "dompdf",
      "format": "A4",
      "orientation": "portrait",
      "filename": "test-email-preferences-dompdf.pdf"
    }
  }' | jq '.' 2>/dev/null || echo "DomPDF test failed"

# Test 3: Test Browserless generation (if available)
echo
echo "🌐 3. Testing Browserless generation..."
curl -s -X POST "$BASE_URL/enhanced-pdf/generate/route" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "route": "email.preferences",
    "data": {
      "preferences": {
        "marketing_emails": true,
        "product_updates": false,
        "security_alerts": true,
        "onboarding_emails": true,
        "weekly_digest": false,
        "frequency": "high"
      },
      "stats": {
        "total_sent": 89,
        "delivered": 85,
        "opened": 56,
        "clicked": 12,
        "open_rate": 65.9,
        "click_rate": 14.1
      },
      "pdf_mode": true
    },
    "options": {
      "engine": "browserless",
      "format": "A4",
      "orientation": "portrait",
      "filename": "test-email-preferences-browserless.pdf"
    }
  }' | jq '.' 2>/dev/null || echo "Browserless test failed"

# Test 4: Test Auto engine selection
echo
echo "🔄 4. Testing Auto engine selection..."
curl -s -X POST "$BASE_URL/enhanced-pdf/generate/route" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "route": "email.preferences", 
    "data": {
      "preferences": {
        "marketing_emails": false,
        "product_updates": true,
        "security_alerts": true,
        "onboarding_emails": true,
        "weekly_digest": true,
        "frequency": "low"
      },
      "stats": {
        "total_sent": 203,
        "delivered": 195,
        "opened": 127,
        "clicked": 34,
        "open_rate": 65.1,
        "click_rate": 17.4
      },
      "pdf_mode": true
    },
    "options": {
      "engine": "auto",
      "format": "A4",
      "orientation": "portrait",
      "filename": "test-email-preferences-auto.pdf"
    }
  }' | jq '.' 2>/dev/null || echo "Auto engine test failed"

# Test 5: List generated files
echo
echo "📋 5. Listing generated PDF files..."
curl -s -X GET "$BASE_URL/enhanced-pdf/files" \
  -H "Accept: application/json" | jq '.data.files[] | {filename: .filename, method: .method, size: .size_formatted}' 2>/dev/null || echo "File listing failed"

echo
echo "✅ PDF Generation Testing Complete!"
echo
echo "Next steps:"
echo "1. Check the generated PDF files in storage/app/public/pdfs/"
echo "2. Test the download URLs provided in the responses"
echo "3. Verify both browserless and DomPDF engines work correctly"