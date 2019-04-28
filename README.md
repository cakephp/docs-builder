# CakePHP Plugin Docs Builder

This repository provides common tools for building documentation sites for
plugins maintained by the CakePHP team.


## Building docs with these tools

To use these tools your plugin should create a docker image based on
`markstory/cakephp-docs-builder`. It should add your plugin's docs to
`/data/docs` and then use the tools provided in the base image to build the site
using sphinx. An example of this is:

```dockerfile
# Generate the HTML output.
FROM markstory/cakephp-docs-builder as builder

COPY docs /data/docs

RUN cd /data/docs-builder && \
  # In the future repeat website for each version
  make website SOURCE=/data/docs DEST=/data/website/1.1


# Build a small nginx container with just the static site in it.
FROM nginx:1.15-alpine

COPY --from=builder /data/website /data/website
COPY --from=builder /data/docs-builder/nginx.conf /etc/nginx/conf.d/default.conf

# Move each version into place
RUN mv /data/website/1.1/html/ /usr/share/nginx/html/1.1
```

Your plugin's docs will need to define a minimal sphinx configuration. You'll
need at least the following:

* An `index.rst` that builds a `toctree` for all documents.
* A `conf.py` file that configures sphinx.

An example `conf.py` is as follows:

```python
# Global configuration information used across all the
# translations of documentation.
#
# Import the base theme configuration
from cakephpsphinx.config.all import *

# The version info for the project you're documenting, acts as replacement for
# |version| and |release|, also used in various other places throughout the
# built documents.
#

# The full version, including alpha/beta/rc tags.
release = '1.1'

# The search index version. This needs to match
# the INDEX_PREFIX variable used when `make populate-index` is called.
search_version = 'authorization-11'

# The marketing display name for the book.
version_name = ''

# Project name shown in the black header bar
project = 'CakePHP Authorization'

# Other versions that display in the version picker menu.
version_list = [
    {'name': '1.1', 'number': '1.1', 'title': '1.1.x', 'current': True},
]

# Languages available.
languages = ['en']

# The GitHub branch name for this version of the docs
# for edit links to point at.
branch = 'master'

# Current version being built
version = '1.1'

# Language in use for this directory.
language = 'en'
```

## What these tools build

After defining a docker file for your plugin you and building the image, you'll
get the following:

* A static HTML site built with sphinx, using the cakephp-sphinxtheme
* Nginx serving out of `/var/www/html`.

## What else you'll need to do

When doing a deploy, the elasticsearch index will need to be rebuilt. You can
do this by adding the following to your jenkins job task script:

```bash
# Get docs-builder to populate index
git clone https:\/\/github.com/cakephp/docs-builder
pushd docs-builder
make populate-index SOURCE="$WORKSPACE" ES_HOST="$ELASTICSEARCH_URL" INDEX_PREFIX="myplugin-11"
popd
```

## Adding a translation to a plugin's docs

The languages offered by a plugin are stored in a few places and each needs to
be updated separately:

* The `conf.py` file contains a `languages` list.
* Each translation needs to set `language` in its configuration file.
* The `docs.Dockerfile` in your plugin needs to pass `LANGS` to each make task
  in `docs-builder` that is called.
* You need to update the jenkins deploy scripts in this repository to pass
  `LANGS` when rebuilding elasticsearch indexes.
* Update build jobs in jenkins.


# Pushing update of this project's docker image

When you make changes to either cakephp/cakephpsphinx or this repository you
need to publish a new docker image and update the cakephp server.

1. `docker build -t markstory/cakephp-docs-builder .`
2. `docker push markstory/cakephp-docs-builder`
3. ssh into new.cakephp.org
4. `docker pull markstory/cakephp-docs-builder` to get the new image.

Plugins will use the new base image when they next have their docs deployed.
