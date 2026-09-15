# ---- Build Stage ----
FROM node:22.22.0-alpine3.22 AS builder

# Set the working directory in the container to /console
WORKDIR /console

# Create the pnpm directory and set the PNPM_HOME environment variable
RUN mkdir -p ~/.pnpm
ENV PNPM_HOME=/root/.pnpm

# Set environment
ARG ENVIRONMENT=production

# Attribution
ARG DISABLE_FLEETBASE_ATTRIBUTION=false
ENV DISABLE_FLEETBASE_ATTRIBUTION=$DISABLE_FLEETBASE_ATTRIBUTION

# Add the pnpm global bin to the PATH
ENV PATH=/root/.pnpm/bin:$PATH

# Copy package metadata first for better dependency caching
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./

# Copy over .npmrc if applicable
COPY .npmr[c] ./

# Install global dependencies
RUN npm install -g ember-cli pnpm 

# Install git
RUN apk update && apk add git openssh-client

# Trust GitHub's RSA host key
RUN mkdir -p -m 0600 ~/.ssh && ssh-keyscan github.com >> ~/.ssh/known_hosts

# Install app dependencies
RUN pnpm install --frozen-lockfile

# Copy the console directory contents into the container at /console
COPY . .

# Overlay local Fleet-Ops engine patches (map settings: custom Leaflet tile URL)
# on top of the published @fleetbase/fleetops-engine npm package. This is additive
# only -- it does not modify the published package on npm, just this build's copy.
RUN cp -rv patches/fleetops-engine/addon/. node_modules/@fleetbase/fleetops-engine/addon/

# Overlay local ember-core patches (host-services whitelist extension for the
# Orchestrator engine services) on top of the published @fleetbase/ember-core
# npm package. Additive only -- does not modify the published package on npm.
RUN cp -rv patches/ember-core/addon/. node_modules/@fleetbase/ember-core/addon/

# Overlay local ember-ui patches (Leaflet map tile source fix: replace hardcoded
# CartoDB/Stadia tile URLs with Mapbox in coordinates-input.js) on top of the
# published @fleetbase/ember-ui npm package. Additive only -- does not modify
# the published package on npm.
RUN cp -rv patches/ember-ui/addon/. node_modules/@fleetbase/ember-ui/addon/

# Overlay local customer-portal-engine patches (Leaflet map tile source fix:
# replace hardcoded CartoDB/Stadia tile URLs with Mapbox, same fix as
# fleetops-engine/ember-ui) on top of the published @fleetbase/customer-portal-engine
# npm package. Additive only -- does not modify the published package on npm.
RUN cp -rv patches/customer-portal-engine/addon/. node_modules/@fleetbase/customer-portal-engine/addon/

# Build the application
RUN pnpm build --environment $ENVIRONMENT

# ---- Serve Stage ----
FROM nginx:alpine

# Copy the built app to our served directory
COPY --from=builder /console/dist /usr/share/nginx/html

# Expose the port nginx is bound to
EXPOSE 4200

# Use custom nginx.conf
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Start Nginx server
CMD ["nginx", "-g", "daemon off;"]
