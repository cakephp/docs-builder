def final AUTHORIZATION_REPO_NAME = 'cakephp/authorization'
def final AUTHENTICATION_REPO_NAME = 'cakephp/authentication'
def final DEBUGKIT_REPO_NAME = 'cakephp/debug_kit'
def final CHRONOS_REPO_NAME = 'cakephp/chronos'

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

job('Book - Deploy Authentication docs') {
  description('Deploy the authentication docs when changes are pushed.')
  scm {
    github(AUTHENTICATION_REPO_NAME, 'master')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="authentication-11" SEARCH_URL_PREFIX="/authentication/1.1"
cd ..

# Push to dokku
git remote | grep dokku || git remote add dokku dokku@new.cakephp.org:authentication-docs
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

job('Book - Deploy DebugKit docs') {
  description('Deploy the debugkit docs when changes are pushed.')
  scm {
    github(DEBUGKIT_REPO_NAME, 'master')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="debugkit-3" SEARCH_URL_PREFIX="/debugkit/3.x"
cd ..

# Push to dokku
git remote | grep dokku || git remote add dokku dokku@new.cakephp.org:debugkit-docs
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

job('Book - Deploy Chronos docs') {
  description('Deploy the chronos docs when changes are pushed.')
  scm {
    github(CHRONOS_REPO_NAME, 'master')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="chronos-1" SEARCH_URL_PREFIX="/chronos/1.x"
cd ..

# Push to dokku
git remote | grep dokku || git remote add dokku dokku@new.cakephp.org:chronos-docs
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
