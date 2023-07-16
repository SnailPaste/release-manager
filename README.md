Snail Paste Release Manager
=======================================
Release Manager is a platform for managing and tracking software project releases. It will provide a web interface for
uploading and managing software releases. Analytics are recorded for each release asset.

Feature Progress
--------
* [X] Efficiently sent downloads by using X-Accel-Redirect
* [X] Per-file statistics endpoint that returns JSON or HTML
* [ ] Index generation
* [ ] Administration area for managing releases:
  * [ ] Create directories and upload new files
  * [ ] Delete files and directories (with trash can to allow restoring files)
  * [ ] 2FA support/enforcement
  * [ ] Detailed analytics

Requirements
------------
* Nginx
* PHP 7.4 or later (ideally the FPM variant), with the following extensions:
  * PDO
  * SQLite3
  * Fileinfo
  * JSON
* [Composer](https://getcomposer.org/download/)

Installation
------------

This assumes that the website will be stored in ```/var/www/download```, where
```/var/www/download/README.md``` would be this file.

```shell
git clone https://github.com/SnailPaste/release-manager /var/www/download
cd /var/www/download
composer install
```

The Nginx web server should be configured similar to below:
```nginx
server {
        listen 80;
        listen [::]:80;

        server_name download.yoursite.com;

        # Absolute path to the public directory
        root /var/www/download/public/;
        index index.php;

        location /files/ {
                internal;
                # Absolute path to where the downloads are stored 
                alias /var/www/download/files/;
        }

        location / {
                # Try to serve file directly, then fallback to index.php
                try_files $uri /index.php$is_args$args;
        }

        location ~ ^/index\.php(/|$) {
                fastcgi_pass unix:/var/run/php/php-fpm.sock;
                fastcgi_split_path_info ^(.+\.php)(/.*)$;

                include fastcgi_params;

                fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
                fastcgi_param DOCUMENT_ROOT $realpath_root;

                # Mitigate https://httpoxy.org/ vulnerabilities
                fastcgi_param HTTP_PROXY "";

                internal;
        }
}
```
