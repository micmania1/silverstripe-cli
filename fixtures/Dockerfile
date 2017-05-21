FROM debian

RUN echo "mariadb-server mysql-server/root_password password rootpass" | debconf-set-selections
RUN echo "mariadb-server mysql-server/root_password_again password rootpass" | debconf-set-selections

# Web server config
RUN apt-get update -qq
RUN apt-get install -qqy apache2 mariadb-server
RUN apt-get install -qqy libapache2-mod-php5 php5-cli php5-common php5-tidy php5-gd php5-intl php5-apcu php5-curl php5-xdebug php5-xhprof php5-mcrypt php5-mysql

# Other useful stuff
RUN apt-get install -qqy vim lynx git-core

# Copy our startup script and set it to run
COPY ./conf/docker-startup /opt/docker-startup
RUN chmod +x /opt/docker-startup

# Setup Apache
RUN a2enmod rewrite
RUN sed -i 's/;date.timezone =/date.timezone = Pacific\/Auckland/' /etc/php5/apache2/php.ini
RUN sed -i 's/;date.timezone =/date.timezone = Pacific\/Auckland/' /etc/php5/cli/php.ini

# Setup out website code
RUN mkdir -p /var/www/mysite
ADD . /var/www/mysite
RUN chown -R www-data:www-data /var/www/mysite

# Setup apache mysite vhost
RUN a2dissite 000-default.conf
COPY conf/mysite.apache.conf /etc/apache2/sites-available/mysite.conf
RUN a2ensite mysite.conf

# Expose ports internally
EXPOSE 80
EXPOSE 443

CMD /opt/docker-startup
