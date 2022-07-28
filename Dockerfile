FROM forceedge01/php56cli-composer:latest

WORKDIR '/app'
COPY . .
RUN composer self-update
RUN composer install

CMD ["composer", "run-script", "tests"]