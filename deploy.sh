#!/bin/bash

# Define Laravel Artisan executable
ARTISAN="./artisan"

# Exit script on any error
set -e

# Print a separator for better readability
SEPARATOR="======================================"

# Function to check if Artisan exists
check_artisan_exists() {
    if [ ! -f "$ARTISAN" ]; then
        echo "Artisan file not found! Ensure you're in the Laravel project's root directory."
        exit 1
    fi
}

# Function to overwrite .htaccess with CORS rules
overwrite_htaccess() {
    echo $SEPARATOR
    echo "Sobrescrevendo public/.htaccess com regras de CORS"
    echo $SEPARATOR

    cat <<'EOF' > public/.htaccess
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# =============================
# Libera CORS para arquivos públicos do /storage
<IfModule mod_headers.c>
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|bmp|ico|css|js|ttf|woff|woff2|eot|otf)$">
        SetEnvIf Request_URI "^/storage/" CORS_ALLOWED
        Header set Access-Control-Allow-Origin "*" env=CORS_ALLOWED
        Header set Access-Control-Allow-Methods "GET, OPTIONS" env=CORS_ALLOWED
        Header set Access-Control-Allow-Headers "Origin, Content-Type, Accept, Authorization" env=CORS_ALLOWED
    </FilesMatch>
</IfModule>
# =============================
EOF
}

# Main function to run commands
run_artisan_commands() {
    echo $SEPARATOR
    echo "Limpando os caches da aplicação"
    echo $SEPARATOR

    php $ARTISAN config:clear
    php $ARTISAN route:clear
    php $ARTISAN view:clear

    echo $SEPARATOR
    echo "Aplicação atualizada com sucesso"
    echo $SEPARATOR
    echo "Iniciando a atualização do banco de dados"
    echo $SEPARATOR

    php $ARTISAN migrate --force

    echo $SEPARATOR
    echo "Processo finalizado com sucesso!"
}

# Execução
check_artisan_exists
overwrite_htaccess
run_artisan_commands
