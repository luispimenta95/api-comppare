#!/bin/bash

# Define Laravel Artisan executable
ARTISAN="./artisan"

# Exit script on any error
#Update sed : sed -i -e 's/\r$//' nome-script.sh
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

# Main function to run commands
run_artisan_commands() {
    echo $SEPARATOR
    echo "Limpando os caches da aplicação"
  echo $SEPARATOR
    # Clear config cache
    php $ARTISAN config:clear

    # Clear routing cache
    php $ARTISAN route:clear

    # Clear compiled views
    php $ARTISAN view:clear
    echo $SEPARATOR
    echo "Aplicação atualizada com sucesso"
    echo $SEPARATOR
    echo "Iniciando a atualização do banco de dados"
  echo $SEPARATOR
    # Run database migrations
    php $ARTISAN migrate --force
    echo $SEPARATOR
    echo "Processo finalizado com sucesso!"
}

# Check if Artisan exists and execute commands
check_artisan_exists
run_artisan_commands
