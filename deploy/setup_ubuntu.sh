#!/usr/bin/env bash
# ------------------------------------------------------------------------------------------------------------------
# ONE-SCRIPT INSTALL on a fresh Ubuntu 22.04 / 24.04 server (for example a free Azure for Students VM).
# It installs Apache + PHP + MariaDB, creates the database, imports the data and puts the system online — the system and its
# database live on the SAME machine, so no Firebase or other database service is needed.
#
#   1. Copy the whole attrition-system folder to the server (scp / SFTP / git).
#   2. Optional: put your OWN database export (phpMyAdmin > Export) in this folder as  my_database.sql  (accounts, classes, grades).
#   3. On the server:   sudo bash deploy/setup_ubuntu.sh
# ------------------------------------------------------------------------------------------------------------------
set -euo pipefail
if [ "$(id -u)" -ne 0 ]; then echo "Run it with sudo:  sudo bash deploy/setup_ubuntu.sh"; exit 1; fi

SRC="$(cd "$(dirname "$0")/.." && pwd)"
APP_DIR="/var/www/html/attrition"
DB_NAME="ccs_attrition_system"
DB_USER="ccs_app"
DB_PASS="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | cut -c1-20)"

echo ">> 1/6 Installing Apache, PHP and MariaDB ..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y apache2 mariadb-server php libapache2-mod-php php-mysql php-mbstring php-xml php-zip php-gd unzip curl openssl
systemctl enable --now apache2 mariadb

echo ">> 2/6 Creating the database and its user ..."
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo ">> 3/6 Copying the system to ${APP_DIR} ..."
mkdir -p "${APP_DIR}"
cp -a "${SRC}/." "${APP_DIR}/"
cat > "${APP_DIR}/config/db.local.php" <<PHP
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', '${DB_NAME}');
define('DB_USER', '${DB_USER}');
define('DB_PASS', '${DB_PASS}');
define('DB_PORT', '3306');
PHP
mkdir -p "${APP_DIR}/uploads/avatars" "${APP_DIR}/storage/outbox"
chown -R www-data:www-data "${APP_DIR}/uploads" "${APP_DIR}/storage"
chmod -R 775 "${APP_DIR}/uploads" "${APP_DIR}/storage"
chmod 640 "${APP_DIR}/config/db.local.php"; chown root:www-data "${APP_DIR}/config/db.local.php"

echo ">> 4/6 Importing the database ..."
if [ -f "${SRC}/my_database.sql" ]; then
    echo "   using your own export: my_database.sql"
    mysql "${DB_NAME}" < "${SRC}/my_database.sql"
else
    echo "   fresh install: schema + the dummy students (create the first Admin at /create_admin.php afterwards)"
    mysql "${DB_NAME}" < "${APP_DIR}/database/hosting/1_schema_hosting.sql"
    mysql "${DB_NAME}" < "${APP_DIR}/database/hosting/2_dummy_data.sql"
fi

rm -f "${APP_DIR}/my_database.sql"   # the export is not left inside the web folder
echo ">> 5/6 Letting Apache read the .htaccess protections ..."
cat > /etc/apache2/conf-available/attrition.conf <<CONF
<Directory ${APP_DIR}>
    AllowOverride All
    Require all granted
</Directory>
CONF
a2enconf attrition >/dev/null
systemctl restart apache2

echo ">> 6/6 Done."
IP="$(curl -s --max-time 5 https://ifconfig.me || echo 'YOUR-SERVER-IP')"
echo
echo "   Open:   http://${IP}/attrition/check_install.php   (everything must be green)"
echo "   Then:   http://${IP}/attrition/                    (the log-in page)"
echo "   If it does not open: in the Azure portal add an inbound rule for port 80 (HTTP) on the VM's Network security group."
echo "   Finally delete   ${APP_DIR}/check_install.php   and   ${APP_DIR}/create_admin.php"
echo "   The database password was saved only in ${APP_DIR}/config/db.local.php"
