#!/bin/bash
# Deployment Validation Script for Prism Wallet
# This script helps validate the Docker setup before deploying to Render

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}===========================================${NC}"
echo -e "${GREEN}Prism Wallet Deployment Validation${NC}"
echo -e "${GREEN}===========================================${NC}"
echo ""

# Check if Docker is running
echo -e "${YELLOW}[1/8] Checking Docker...${NC}"
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}✗ Docker is not running. Please start Docker first.${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Docker is running${NC}"
echo ""

# Build the image
echo -e "${YELLOW}[2/8] Building Docker image...${NC}"
if docker build -t prism-wallet:test . ; then
    echo -e "${GREEN}✓ Image built successfully${NC}"
else
    echo -e "${RED}✗ Image build failed${NC}"
    exit 1
fi
echo ""

# Check for vulnerabilities (optional but recommended)
echo -e "${YELLOW}[3/8] Checking for base image info...${NC}"
docker inspect php:8.2-fpm-alpine | grep -A 5 "Created" || true
echo ""

# Validate Nginx configuration in the image
echo -e "${YELLOW}[4/8] Validating Nginx configuration...${NC}"
if docker run --rm prism-wallet:test nginx -t 2>&1 | tee /tmp/nginx-test.log; then
    echo -e "${GREEN}✓ Nginx configuration is valid${NC}"
else
    echo -e "${RED}✗ Nginx configuration has errors:${NC}"
    cat /tmp/nginx-test.log
    exit 1
fi
echo ""

# Validate PHP-FPM configuration in the image
echo -e "${YELLOW}[5/8] Validating PHP-FPM configuration...${NC}"
if docker run --rm prism-wallet:test php-fpm -t 2>&1 | tee /tmp/phpfpm-test.log; then
    echo -e "${GREEN}✓ PHP-FPM configuration is valid${NC}"
else
    echo -e "${RED}✗ PHP-FPM configuration has errors:${NC}"
    cat /tmp/phpfpm-test.log
    exit 1
fi
echo ""

# Check that the www.conf file is properly configured
echo -e "${YELLOW}[6/8] Checking PHP-FPM pool configuration...${NC}"
docker run --rm prism-wallet:test cat /usr/local/etc/php-fpm.d/www.conf | grep -E "(listen|pm\.|user|group)" | head -15
echo ""

# Check that nginx configs are in place
echo -e "${YELLOW}[7/8] Checking Nginx configuration files...${NC}"
echo "Main nginx.conf fastcgi_pass settings:"
docker run --rm prism-wallet:test cat /etc/nginx/nginx.conf | grep fastcgi_pass || echo "  (none in main config)"
echo ""
echo "Default conf fastcgi_pass settings:"
docker run --rm prism-wallet:test cat /etc/nginx/conf.d/default.conf | grep fastcgi_pass || echo "  (none found)"
echo ""

# Start the container with environment variables
echo -e "${YELLOW}[8/8] Starting container for runtime test...${NC}"
CONTAINER_ID=$(docker run -d \
    -p 8080:80 \
    -e DB_HOST=test-db \
    -e DB_PORT=5432 \
    -e DB_NAME=test_db \
    -e DB_USER=test_user \
    -e DB_PASSWORD=test_pass \
    -e AUTH_PROVIDER=local \
    -e APP_URL=http://localhost:8080 \
    prism-wallet:test)

echo -e "${GREEN}✓ Container started: $CONTAINER_ID${NC}"
echo ""

# Wait for container to be healthy
echo "Waiting for container to start (30 seconds)..."
sleep 5

# Show startup logs
echo -e "${YELLOW}Startup logs:${NC}"
docker logs $CONTAINER_ID 2>&1 | head -50
echo ""

# Check if PHP-FPM is listening
echo -e "${YELLOW}Checking PHP-FPM process...${NC}"
docker exec $CONTAINER_ID ps aux | grep php-fpm | grep -v grep || echo -e "${RED}✗ PHP-FPM not running${NC}"
echo ""

# Check if Nginx is listening
echo -e "${YELLOW}Checking Nginx process...${NC}"
docker exec $CONTAINER_ID ps aux | grep nginx | grep -v grep || echo -e "${RED}✗ Nginx not running${NC}"
echo ""

# Check if PHP-FPM is actually listening on 127.0.0.1:9000
echo -e "${YELLOW}Checking PHP-FPM socket...${NC}"
docker exec $CONTAINER_ID netstat -tlnp | grep 9000 || echo -e "${RED}✗ PHP-FPM not listening on port 9000${NC}"
echo ""

# Test the health endpoint
echo -e "${YELLOW}Testing health endpoint...${NC}"
sleep 3
if curl -f -s http://localhost:8080/health.php > /tmp/health-response.txt 2>&1; then
    echo -e "${GREEN}✓ Health endpoint responded:${NC}"
    cat /tmp/health-response.txt
else
    echo -e "${RED}✗ Health endpoint failed:${NC}"
    cat /tmp/health-response.txt || true
fi
echo ""

# Show recent logs
echo -e "${YELLOW}Recent container logs:${NC}"
docker logs --tail 50 $CONTAINER_ID 2>&1
echo ""

# Cleanup option
echo -e "${YELLOW}Container is still running for your inspection.${NC}"
echo -e "Container ID: ${GREEN}$CONTAINER_ID${NC}"
echo ""
echo "Options:"
echo "  1. View logs:        docker logs -f $CONTAINER_ID"
echo "  2. Enter container:  docker exec -it $CONTAINER_ID /bin/sh"
echo "  3. Test endpoint:    curl http://localhost:8080/health.php"
echo "  4. Stop container:   docker stop $CONTAINER_ID"
echo "  5. Stop and remove:  docker stop $CONTAINER_ID && docker rm $CONTAINER_ID"
echo ""
echo -e "${GREEN}Validation complete!${NC}"
echo ""
read -p "Press enter to stop and remove the test container, or Ctrl+C to keep it running..."
docker stop $CONTAINER_ID
docker rm $CONTAINER_ID
echo -e "${GREEN}✓ Test container cleaned up${NC}"
