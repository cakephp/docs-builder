# This image provides the ability to build docs for CakePHP plugins
# Plugins should use a multi-stage Dockerfile so that they only deploy
# nginx without python and other build tooling.
FROM python:3.8-alpine

LABEL Description="Create an image to deploy the CakePHP plugin docs"

# If we need to publish PDFs for plugins also install
# Add texlive-latex-recommended texlive-latex-extra for PDF support
# texlive-fonts-recommended
# texlive-lang-all
# latexmk
RUN apk add --update git make

COPY . /data/docs-builder

RUN cd /data/docs-builder \
 && pip install -r requirements.txt

WORKDIR /data
