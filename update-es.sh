#!/bin/sh
# Called from the dokku postdeploy script hook
# Updates the elasticsearch index for this documentation site.

composer self-update && composer update --no-dev --no-interaction --working-dir=/data/console
code=$?; if [ $code -ne 0 ]; then exit $code; fi

for lang in ${LANGS}
do
    php /data/console/bin/console.php index:populate \
      --source="$SEARCH_SOURCE/$lang/$*" --lang="$lang" --host="$ELASTICSEARCH_URL" --url-prefix="$SEARCH_URL_PREFIX"

    code=$?; if [ $code -ne 0 ]; then exit $code; fi
done
