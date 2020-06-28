FROM forceedge01/php71cli-composer:latest

WORKDIR '/app'
COPY . .
RUN composer install

CMD ["composer", "run-script", "tests"]