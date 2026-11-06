# Déploiement de l’application de vote

État constaté le 21 octobre 2026 : Laravel 12 / PHP ^8.2, environnement local en PostgreSQL (`vote_electronique`), deux sauvegardes PostgreSQL et aucun dépôt Git dans ce dossier. Les sauvegardes `sauvegarde_avant_nettoyage.sql` et `sauvegarde_avant_separation.sql` ne décrivent pas le même schéma. La configuration de ce document vise Ubuntu 24.04, Nginx, PHP-FPM 8.3 et PostgreSQL ; adaptez le socket PHP si la version installée diffère.

## 1. Transférer le projet et restaurer la base

Choisir `sauvegarde_avant_nettoyage.sql` uniquement après validation fonctionnelle : son schéma de bulletins correspond à la séparation électeur/bulletin (il ne relie pas le bulletin à `id_electeur`), alors que l’autre sauvegarde conserve cette relation et une contrainte d’unicité différente. Ne restaurez pas l’une puis l’autre dans la même base. Ce sont des dumps PostgreSQL texte avec données réelles ; gardez-les hors du dépôt et transférez-les par un canal privé.

Sur le poste de développement, créer une archive de code en excluant les données, les secrets et les dépendances réinstallables :

```powershell
tar -czf vote-code.tar.gz --exclude=vendor --exclude=node_modules --exclude=.env --exclude='*.sql' --exclude='electeurs_test.*' .
scp .\vote-code.tar.gz admin@SERVEUR:/tmp/
scp .\sauvegarde_avant_nettoyage.sql admin@SERVEUR:/tmp/vote-db.sql
```

Sur le serveur, installer PHP 8.3 avec les extensions `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl` et `gd`, Composer, Node.js/npm, PostgreSQL, Nginx et Certbot. Préparer le répertoire et décompresser le projet :

```bash
sudo mkdir -p /var/www/vote/current
sudo tar -xzf /tmp/vote-code.tar.gz -C /var/www/vote/current
cd /var/www/vote/current
composer install --no-dev --prefer-dist --optimize-autoloader
npm install
npm run build
```

Le dossier ne contient pas encore `package-lock.json`; `npm install` évite un échec de déploiement, mais il faudra ajouter et valider ce fichier pour rendre les versions front reproductibles.

Sauvegarder d’abord la base cible si elle existe. Restaurer dans une base neuve, en s’arrêtant à la première erreur :

```bash
sudo -u postgres createdb vote_electronique_prod
sudo -u postgres psql -v ON_ERROR_STOP=1 -d vote_electronique_prod -f /tmp/vote-db.sql
sudo -u postgres psql -d vote_electronique_prod -c "CREATE ROLE vote_app LOGIN PASSWORD 'REMPLACER_PAR_UN_SECRET_LONG';"
sudo -u postgres psql -d vote_electronique_prod -c "GRANT CONNECT ON DATABASE vote_electronique_prod TO vote_app; GRANT USAGE, CREATE ON SCHEMA public TO vote_app; GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO vote_app; GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO vote_app; ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO vote_app; ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO vote_app;"
```

La sauvegarde indique le propriétaire PostgreSQL `postgres` ; le restore doit donc être exécuté par le rôle système `postgres`, pas par `vote_app`. Contrôler ensuite les tables, les contraintes, les séquences et les volumes par table sans afficher les enregistrements. Garder la sauvegarde jusqu’à validation, puis supprimer les copies temporaires protégées.

Créer `.env` directement sur le serveur et le protéger (`chmod 600 .env`). Renseigner notamment `APP_NAME="Système de vote électronique sécurisé"`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://vote.example.org`, `APP_TIMEZONE=Africa/Bujumbura`, `APP_LOCALE=fr`, `APP_KEY` (conserver la clé correspondant aux sessions/chiffrements existants), `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=vote_electronique_prod`, `DB_USERNAME=vote_app`, `DB_PASSWORD=...`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, puis un mailer SMTP opérationnel pour l’OTP et `CLE_CHIFFREMENT_SCRUTIN`. Ne pas générer une nouvelle clé d’application si les données déjà chiffrées doivent rester lisibles. Ne jamais lancer `db:seed` en production : le seeder crée un utilisateur de démonstration.

La clé `CLE_CHIFFREMENT_SCRUTIN` doit commencer par `base64:` et encoder exactement 32 octets. Générez-la une fois avec `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`, stockez-la hors du code et gardez une copie de secours protégée. Configurez-la avant la migration ci-dessous.

La migration `2026_10_21_000000_protect_ballot_choice` rechiffre les choix à partir de la colonne historique, copie les émargements si l’ancienne sauvegarde les conservait uniquement dans les bulletins, puis supprime les colonnes qui relient l’électeur ou le candidat au bulletin. Elle ne peut pas être annulée par `migrate:rollback`. Répétez-la d’abord sur une copie isolée et comparez le nombre de bulletins, le nombre d’émargements et les totaux avant/après. Ne l’appliquez en production qu’après validation de cette répétition et sauvegarde vérifiée.

Les migrations fournies ne créent toujours pas le schéma métier complet du scrutin ; elles s’appliquent à une base restaurée contenant déjà les tables métier attendues.

Permissions Laravel et cache de production :

```bash
sudo chown -R root:www-data /var/www/vote/current
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate:status
```

Ne lancer `php artisan migrate --force` qu’après avoir comparé les migrations du code aux migrations de la base restaurée. Les migrations fournies ne créent que les tables Laravel standard ; elles ne documentent pas le schéma métier du scrutin.

## 2–3. Production avec Nginx et PHP-FPM

Copier `deploy/nginx-vote.conf` vers `/etc/nginx/sites-available/vote`, remplacer `vote.example.org` et vérifier le socket PHP-FPM. Activer le site, vérifier puis recharger Nginx :

```bash
sudo ln -s /etc/nginx/sites-available/vote /etc/nginx/sites-enabled/vote
sudo nginx -t
sudo systemctl enable --now php8.3-fpm nginx
sudo systemctl reload nginx
```

La racine web doit rester `/var/www/vote/current/public`, jamais la racine du projet. L’application est servie par Nginx/PHP-FPM ; `php artisan serve` est réservé au développement.

## 4. UFW et Fail2ban

Avant d’activer UFW à distance, confirmer que le port SSH réellement utilisé est autorisé afin de ne pas perdre la session :

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

Ne pas ouvrir les ports PostgreSQL (5432) ou PHP-FPM sur Internet. Installer Fail2ban, copier `deploy/jail.local` dans `/etc/fail2ban/jail.d/vote.local`, puis :

```bash
sudo systemctl enable --now fail2ban
sudo fail2ban-client status
sudo fail2ban-client status sshd
```

## 5. HTTPS

Faire pointer le DNS A/AAAA vers le serveur et autoriser 80/443 avant de demander le certificat. Avec le serveur HTTP Nginx activé :

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d vote.example.org
sudo certbot renew --dry-run
```

Vérifier la redirection HTTP vers HTTPS et le cookie de session Secure. Ne configurer HSTS qu’après avoir confirmé que tous les sous-domaines concernés servent HTTPS.

## 6. Vérification sur téléphone

Après DNS et certificat actifs, ouvrir `https://vote.example.org` sur Android et iOS, en Wi-Fi puis en données mobiles. Capturer l’écran d’accueil, le parcours OTP, la liste des candidats, le reçu de vote et le tableau responsable uniquement avec des comptes et scrutins fictifs. Vérifier l’absence de contenu mixte, le clavier mobile, l’affichage étroit, les erreurs réseau et le renouvellement de session. Ne pas voter avec un électeur réel lors de la recette.

## 7. Pentest et dossier de preuves

Ne tester que le domaine et le serveur qui vous appartiennent, avec une fenêtre de test convenue et des comptes/données fictifs. Le rapport statique préliminaire est dans `PENTEST.md`. Les captures réseau réelles ne peuvent être produites qu’une fois le domaine accessible ; enregistrer date, URL, navigateur/appareil, scénario, résultat, et masquer tout nom, matricule, courriel, OTP, cookie et jeton dans les captures.

## Contrôles avant ouverture du scrutin

- Valider la restauration sur une copie isolée et comparer le schéma exact avec l’application.
- Résoudre les constatations de `PENTEST.md`, en particulier le lien bulletin-candidat et l’absence de protections testées de bout en bout.
- Vérifier le SMTP OTP, les limites de tentatives, les cookies de session, la sauvegarde/restauration, le certificat et la procédure de retour arrière.
- Ne pas utiliser cette application pour une élection réelle tant que l’anonymat, l’intégrité, la disponibilité et le processus de vérification indépendante n’ont pas été validés par un audit spécialisé.
