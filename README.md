# WikiBS

Custom docker image for deploying a mediawiki instance tailored for the Brigade de Sauvabelin,
including the custom authentication extension. This wiki extension was first created to allow
netBS users to log into the wikiBS.

Passwords must be hashed using the sha512 algorithm and base64 encoded

## Environment variables

This image works with the following environment variables:
- PUBLIC_URI: https://wiki.sauvabelin.ch
- DB_TYPE: mysql
- DB_SERVER: localhost
- DB_NAME: netbs
- DB_USER: root
- DB_PASS: root
- AUTH_DB_NAME: netbs
- AUTH_TABLE_NAME: wiki_users
- DB_USERNAME_COL: username
- DB_PASS_COL: password
- DB_SALT_COL: salt
- DB_ADMIN_COL: wiki_admin
- BCRYPT_COST: 5000