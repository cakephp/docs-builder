#!/bin/sh
# Called from the dokku postdeploy script hook
# Updates the elasticsearch index for this documentation site.

# Update elasticsearch indexes.
for lang in ${LANGS}
do
    php /data/populate_search_index.php --source="$SEARCH_SOURCE/$*" --lang="$lang" --host="$ELASTICSEARCH_URL" --url-prefix="$SEARCH_URL_PREFIX"
done
