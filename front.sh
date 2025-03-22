#!/bin/bash

# Define Laravel Artisan executable

SEPARATOR="======================================"

# Function to check if Artisan exists


# Main function to run commands
run_deploy() {
    echo $SEPARATOR
    echo "Processo iniciado"
  echo $SEPARATOR
  echo "Iniciando backup"
  data_atual=$(date +'%Y-%m-%d')

# Nome do arquivo zip com base na data atual
arquivo_zip="backup_$data_atual.zip"


# Cria o arquivo zip e adiciona as pastas (exceto as pastas 'api' e 'backup')
zip -r "$arquivo_zip" / -x "api/" -x "backups/*"

# Move o arquivo zip para a pasta 'backups'
mv "$arquivo_zip" backups/

# Mensagem de confirmação do backup
echo "Backup criado: backups/$arquivo_zip"
  
    echo $SEPARATOR
  echo "Iniciando atualização"
  unzip build.zip
  cd build;
  mv * ..
  cd ..;
  cd web;
  mv * ..
  rm ../build.zip
  echo $SEPARATOR
  echo "Processo de atualização concluído"
 
}

# Check if Artisan exists and execute commands
run_deploy
