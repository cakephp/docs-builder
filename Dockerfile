FROM debian:jessie

ENV DEBIAN_FRONTEND noninteractive

LABEL Description="Create an image to deploy the authentication plugin docs"

RUN apt-get update && apt-get install -y \
    python-pip \
    # Add texlive-latex-recommended texlive-latex-extra for PDF support
    texlive-fonts-recommended \
    texlive-lang-all \
    latexmk \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

RUN apt-get update \
  && apt-get install -y git nginx curl php5 php5-curl \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /data

COPY . /data/docs-builder

RUN cd /data/docs-builder \
 && pip install -r requirements.txt

RUN mv /data/docs-builder/nginx.conf /etc/nginx/sites-enabled/default

# forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
  && ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
