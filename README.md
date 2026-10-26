# WikiBS

Custom docker image for deploying a mediawiki instance tailored for the Brigade de Sauvabelin.
Users log in with their netBS account through OpenID Connect
([PluggableAuth](https://www.mediawiki.org/wiki/Extension:PluggableAuth) +
[OpenIDConnect](https://www.mediawiki.org/wiki/Extension:OpenID_Connect)).

## OIDC client

Create a client in the netBS admin panel (Administration -> Authentification -> Clients) with:
- redirect URI: `<PUBLIC_URI>/index.php/Sp%C3%A9cial:PluggableAuthLogin`
- scopes: `openid profile email groups`

On first login, an existing wiki account whose name matches the netBS username is linked to the
netBS identity (`OIDC_MIGRATE_USERS_BY_USERNAME`).

## Admin rights

Wiki admins are managed in netBS with the `ROLE_WIKI_ADMIN` role. With the `groups` scope, netBS
puts the user's role groups in the ID token (`"groups": ["wiki-admins"]`). On every login the
wiki adds users in `OIDC_ADMIN_GROUP` to `sysop` and `bureaucrat`, and removes everyone else from
those two groups. Role changes apply at the user's next login. Set `OIDC_ADMIN_GROUP` to an empty
string to turn the sync off and manage admins by hand in `Spécial:Droits_des_utilisateurs`.

## Environment variables

This image works with the following environment variables:
- PUBLIC_URI: https://wiki.sauvabelin.ch
- DB_TYPE: mysql
- DB_SERVER: localhost
- DB_NAME: wiki
- DB_USER: root
- DB_PASS: root
- OIDC_PROVIDER_URL: issuer URL, e.g. https://auth.sauvabelin.ch
- OIDC_CLIENT_ID: client id from the netBS admin panel
- OIDC_CLIENT_SECRET: client secret from the netBS admin panel
- OIDC_SCOPE: `openid profile email groups` (space separated)
- OIDC_ADMIN_GROUP: `wiki-admins` (empty disables the admin sync)
- OIDC_AUTH_METHOD: `client_secret_post`
- OIDC_BUTTON_LABEL: `Se connecter avec netBS`
- OIDC_AUTO_LOGIN: `false` (redirect to netBS without showing the login page)
- OIDC_LOCAL_LOGIN: `false` (also show the local username/password form)
- OIDC_MIGRATE_USERS_BY_USERNAME: `true`
