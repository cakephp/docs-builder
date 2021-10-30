# Build a small nginx container with just the static site in it.
FROM nginx:1.15-alpine

# Janky but we could extract this into an image we re-use.
RUN apk add --update php php-curl php-json

# Copy the run script and search index build tool
COPY run.sh /data/run.sh
COPY scripts/populate_search_index.php /data/populate_search_index.php

CMD ["/data/run.sh"]
