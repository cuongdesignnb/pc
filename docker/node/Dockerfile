FROM node:22-alpine

# Install git and other dependencies
RUN apk add --no-cache git

# Set working directory
WORKDIR /app

# Expose port
EXPOSE 3000

# Default command (will be overridden by docker-compose)
CMD ["npm", "run", "dev"]
