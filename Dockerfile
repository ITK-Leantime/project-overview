FROM itkdev/php8.3-fpm:latest

USER root

# Add rsync
RUN apt-get update && apt-get --yes install rsync

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Cf. `docker image inspect --format '{{.Config.User}}' itkdev/php8.3-fpm:latest`
USER deploy
