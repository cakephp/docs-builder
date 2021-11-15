# Build a small nginx container with just the static site in it.
FROM nginx:1.15-alpine

# We also need PHP to update elastic search.
RUN apk add --update bash php php-curl php-json

WORKDIR /data

# Copy the run script (for backwards compat with existing plugin sites),
# a script to update the site langauages based on environment variables
# and a search index build tool to slice the source docs up and insert
# into elastic search.
COPY app.json /data/app.json
COPY run.sh /data/run.sh
COPY update-es.sh /data/update-es.sh
COPY scripts/populate_search_index.php /data/populate_search_index.php
