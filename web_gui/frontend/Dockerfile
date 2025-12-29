# web_gui/frontend/Dockerfile

# Stage 1: Build the React application
FROM node:20-alpine as builder

WORKDIR /app/frontend

COPY web_gui/frontend/package.json web_gui/frontend/package-lock.json ./
RUN npm ci --no-audit --progress=false

COPY web_gui/frontend/ ./
RUN npm run build

# Stage 2: Serve the application with Nginx
FROM nginx:stable-alpine

COPY --from=builder /app/frontend/dist /usr/share/nginx/html

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
