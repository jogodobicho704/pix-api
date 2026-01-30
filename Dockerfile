FROM php:8.2-cli

WORKDIR /app
COPY . .

ENV PORT=8080
EXPOSE 8080

CMD php -S 0.0.0.0:$PORT index.php
