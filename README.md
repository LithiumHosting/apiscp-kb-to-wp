# ApisCP KB Markdown Import to Wordpress
The ApisCP KB is built in markdown and stored in /usr/local/apnscp/docs on your ApisCP Server.
Copy that docs folder to your wordpress site and use this script to build out the articles.

## Usage
```bash
cd /path/to/wordpress
git clone https://github.com/LithiumHosting/apiscp-kb-to-wp.git importer
cp -R /usr/local/apnscp/docs /home/virtual/siteXX/var/www/html/
chown adminxx: /home/virtual/siteXX/var/www/html/docs -R

cd importer
composer install
```

Edit the file import.php in the importer directory, update the `$config` section with relevant variables.
Then just run `php import.php`

## Contributing

Submit a PR and have fun!