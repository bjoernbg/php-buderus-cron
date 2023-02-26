FROM php:7.4-cli

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN apt-get update && apt-get install -y curl cron nano libmcrypt-dev
RUN pecl install mcrypt-1.0.3
RUN echo "extension=mcrypt.so" >> "$PHP_INI_DIR/php.ini"

## Add crontab file in the cron directory
ADD crontab /etc/cron.d/simple-cron

# Add shell script and grant execution rights
ADD script.sh /script.sh
RUN chmod +x /script.sh

# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/simple-cron

# Create the log file to be able to run tail
RUN touch /var/log/cron.log

VOLUME [ "/app" ]

# Run the command on container startup
CMD cron && tail -f /var/log/cron.log