#!/bin/sh
# Installs WordPress + WooCommerce in the `shop` volume and writes the REST API
# credentials SyncHub uses to /output/woocommerce.json
# (storage/app/private/woocommerce.json in the project). Idempotent.
set -eu

echo "Waiting for the WordPress files..."
for _ in $(seq 1 60); do
    [ -f /var/www/html/wp-config.php ] && break
    sleep 2
done

echo "Waiting for the database..."
for _ in $(seq 1 60); do
    wp db check --quiet >/dev/null 2>&1 && break
    sleep 2
done

if ! wp core is-installed 2>/dev/null; then
    wp core install \
        --url="$SHOP_URL" \
        --title="Demo Store" \
        --admin_user=admin \
        --admin_password=admin \
        --admin_email=admin@example.test \
        --skip-email
fi

if ! wp plugin is-active woocommerce 2>/dev/null; then
    wp plugin install woocommerce --activate
fi

# Pretty permalinks make /wp-json/ available (the REST API's canonical path).
wp rewrite structure '/%postname%/' --hard >/dev/null
wp option update woocommerce_currency BRL >/dev/null
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json >/dev/null

# A fresh application password on every run: WordPress never shows an
# existing one again, so the file is rewritten together with it.
wp user application-password delete admin --all >/dev/null 2>&1 || true
password=$(wp user application-password create admin synchub --porcelain)

cat > /output/woocommerce.json <<JSON
{
    "base_url": "http://shop",
    "username": "admin",
    "application_password": "$password"
}
JSON

echo "WooCommerce ready at $SHOP_URL (admin / admin). Credentials written to storage/app/private/woocommerce.json."
