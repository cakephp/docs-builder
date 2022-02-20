FROM docs-build-test/builder as builder

ARG REPOSITORY

COPY docs /data/docs
RUN make --version
RUN if [ "$REPOSITORY" == "cakephp/docs" ] ; then \
      cd /data/docs && \
      pip install -r requirements.txt && \
      make html-en ; \
    else \
      cd /data/docs-builder && \
      pip install git+https://github.com/sphinx-contrib/video.git@master && \
      make html-en SOURCE=/data/docs DEST=/data/docs/build ; \
    fi

FROM docs-build-test/runtime as runtime

ENV LANGS="en"
ENV SEARCH_SOURCE="/data/docs/build/html"
ENV SEARCH_URL_PREFIX="/test"
ENV ELASTICSEARCH_URL="http://127.0.0.1:9200"

COPY --from=builder /data/docs /data/docs
WORKDIR /data
RUN sh update-es.sh
