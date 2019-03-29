def final AUTHORIZATION_REPO_NAME = 'cakephp/authorization'

job('Book - Deploy Authorization docs') {
  description('Deploy the authorization docs when changes are pushed.')
  scm {
    github(AUTHORIZATION_REPO_NAME, 'master')
  }
  triggers {
    scm('H/5 * * * *')
  }
  logRotator {
    daysToKeep(30)
  }
  steps {
    shell('''\
# Get docs-builder to populate index
rm -rf docs-builder
git clone https://github.com/cakephp/docs-builder
cd docs-builder
# Build index for each version.
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="authorization-11" SEARCH_URL_PREFIX="/authorization/1.1"
cd ..

# Push to dokku
git remote | grep dokku || git remote add dokku dokku@new.cakephp.org:authorization-docs
git push -fv dokku HEAD:refs/heads/master
    ''')
  }
  publishers {
    slackNotifier {
      room('#dev')
      notifyFailure(true)
      notifyRepeatedFailure(true)
    }
  }
}

