def final AUTHORIZATION_REPO_NAME = 'cakephp/authorization'
def final AUTHENTICATION_REPO_NAME = 'cakephp/authentication'
def final BAKE_REPO_NAME = 'cakephp/bake'
def final CHRONOS_REPO_NAME = 'cakephp/chronos'
def final DEBUGKIT_REPO_NAME = 'cakephp/debug_kit'
def final ELASTICSEARCH_REPO_NAME = 'cakephp/elastic-search'
def final MIGRATIONS_REPO_NAME = 'cakephp/migrations'
def final PHINX_REPO_NAME = 'cakephp/phinx'

job('Book - Deploy Authorization 1.x docs') {
  description('Deploy the authorization 1.x docs when changes are pushed.')
  scm {
    github(AUTHORIZATION_REPO_NAME, '1.x')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="authorization-11" SEARCH_URL_PREFIX="/authorization/1"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:authorization-docs
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

job('Book - Deploy Authorization 2.x docs') {
  description('Deploy the authorization 2.x docs when changes are pushed.')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="authorization-2" SEARCH_URL_PREFIX="/authorization/2"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:authorization-docs-2
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

job('Book - Deploy Authentication 1.x docs') {
  description('Deploy the authentication 1.x docs when changes are pushed.')
  scm {
    github(AUTHENTICATION_REPO_NAME, '1.x')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="authentication-11" SEARCH_URL_PREFIX="/authentication/1"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:authentication-docs
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

job('Book - Deploy Authentication 2.x docs') {
  description('Deploy the authentication 2.x docs when changes are pushed.')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="authentication-2" SEARCH_URL_PREFIX="/authentication/2"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:authentication-docs-2
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

job('Book - Deploy DebugKit 3.x docs') {
  description('Deploy the debugkit 3.x docs when changes are pushed.')
  scm {
    github(DEBUGKIT_REPO_NAME, '3.x')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="debugkit-3" SEARCH_URL_PREFIX="/debugkit/3"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:debugkit-docs
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

job('Book - Deploy DebugKit 4.x docs') {
  description('Deploy the debugkit 4.x docs when changes are pushed.')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="debugkit-4" SEARCH_URL_PREFIX="/debugkit/4"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:debugkit-docs-4
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

job('Book - Deploy Chronos 1.x docs') {
  description('Deploy the chronos 1.x docs when changes are pushed.')
  scm {
    github(CHRONOS_REPO_NAME, '1.x')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="chronos-1" SEARCH_URL_PREFIX="/chronos/1"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:chronos-docs
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


job('Book - Deploy Chronos 2.x docs') {
  description('Deploy the chronos 2.x docs when changes are pushed.')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="chronos-2" SEARCH_URL_PREFIX="/chronos/2"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:chronos-docs-2
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

job('Book - Deploy Bake 1.x docs') {
  description('Deploy the bake 1.x docs when changes are pushed.')
  scm {
    github(BAKE_REPO_NAME, '1.x')
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

# Build index
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="bake-1" SEARCH_URL_PREFIX="/bake/1"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:bake-docs
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

job('Book - Deploy Bake 2.x docs') {
  description('Deploy the bake 2.x docs when changes are pushed.')
  scm {
    github(BAKE_REPO_NAME, 'master')
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

# Build index
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="bake-2" SEARCH_URL_PREFIX="/bake/2"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:bake-docs-2
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

job('Book - Deploy elasticsearch docs') {
  description('Deploy the elasticsearch docs when changes are pushed.')
  scm {
    github(ELASTICSEARCH_REPO_NAME, 'master')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="elasticsearch-2" SEARCH_URL_PREFIX="/elasticsearch/2"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:elasticsearch-docs
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

job('Book - Deploy migrations docs') {
  description('Deploy the migrations docs when changes are pushed.')
  scm {
    github(MIGRATIONS_REPO_NAME, 'master')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="migrations-2" SEARCH_URL_PREFIX="/migrations/2"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:migrations-docs
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

job('Book - Deploy migrations 3.x docs') {
  description('Deploy the migrations docs when changes are pushed.')
  scm {
    github(MIGRATIONS_REPO_NAME, '4.x')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="migrations-3" SEARCH_URL_PREFIX="/migrations/3"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:migrations-docs-3
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

job('Book - Deploy phinx master docs') {
  description('Deploy the phinx docs when changes are pushed.')
  scm {
    github(PHINX_REPO_NAME, 'master')
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
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" SEARCH_INDEX_NAME="phinx-0" SEARCH_URL_PREFIX="/phinx/0"
cd ..

# Push to dokku
git remote rm dokku || true
git remote add dokku dokku@apps.cakephp.org:phinx-docs
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
