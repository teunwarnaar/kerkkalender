FROM php:8.2-cli-alpine

WORKDIR /app

COPY lib lib
COPY data data
COPY get_kerkkalender.php .
RUN mkdir temp

STOPSIGNAL SIGINT

CMD ["php", "-d", "variables_order=EGPCS", "-S", "0.0.0.0:5000", "-t", "/app"]

