# CakePHP Plugin Docs Builder

This repository provides common tools for building documentation sites for
plugins maintained by the CakePHP team.


## Building docs with these tools

To use these tools your plugin should create a docker image based on
`markstory/cakephp-docs-builder`. It should add your plugin's docs to
`/data/docs` and then use the tools provided in the base image to build the site
using sphinx. An example of this is:

```dockerfile
FROM markstory/cakephp-docs-builder

COPY docs /data/docs

RUN cd /data/docs-builder && \
  make website SOURCE=/data/docs DEST=/data/website INDEX_PREFIX='myplugin-11' && \
  make move-website DEST=/var/www/html/1.1
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

# The search index version.
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
* All documents indexed into the elasticsearch cluster available at `ES_HOST`.
  This option needs to be provided to `make website`. If it is undefined
  `ci.cakephp.org:9200` will be used.
