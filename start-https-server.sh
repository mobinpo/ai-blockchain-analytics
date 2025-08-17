#!/bin/bash

# Create SSL certificates for local development
echo "Setting up HTTPS for local development..."

# Create certificates directory
mkdir -p storage/ssl

# Generate self-signed SSL certificate
openssl req -x509 -out storage/ssl/localhost.crt -keyout storage/ssl/localhost.key \
  -newkey rsa:2048 -nodes -sha256 \
  -subj '/CN=localhost' -extensions EXT -config <( \
   printf "[dn]\nCN=localhost\n[req]\ndistinguished_name = dn\n[EXT]\nsubjectAltName=DNS:localhost,DNS:127.0.0.1,IP:127.0.0.1\nkeyUsage=keyEncipherment,dataEncipherment\nextendedKeyUsage=serverAuth")

echo "SSL certificates generated in storage/ssl/"

# Start Laravel development server with HTTPS
echo "Starting HTTPS development server on https://localhost:8003"
php artisan serve --host=0.0.0.0 --port=8003 --ssl-cert=storage/ssl/localhost.crt --ssl-key=storage/ssl/localhost.key
