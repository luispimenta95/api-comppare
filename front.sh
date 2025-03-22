
#!/bin/bash

# Define Laravel Artisan executable
SEPARATOR="======================================"

# Function to check if Artisan exists

# Main function to run commands
run_deploy() {
    echo $SEPARATOR
    echo "Processo iniciado"
    echo $SEPARATOR

    # Verificar se o diretório de backups existe
    if [ ! -d "backups" ]; then
        echo "A pasta 'backups' não existe. Criando..."
        mkdir backups
    fi

    # Verificar se o arquivo 'build.zip' existe
    if [ ! -f "build.zip" ]; then
        echo "O arquivo 'build.zip' não foi encontrado. Abortando atualização."
        exit 1
    fi

    echo "Iniciando backup"
    data_atual=$(date +'%Y-%m-%d')

    # Nome do arquivo zip com base na data atual
    arquivo_zip="backup_$data_atual.zip"

    # Verificar se existem arquivos para fazer backup
    if [ "$(find ./ -mindepth 1 -not -path "./api-comppare/*" -not -path "./backups/*" -print -quit)" ]; then
        # Cria o arquivo zip e adiciona as pastas (exceto as pastas 'api-comppare' e 'backups')
        zip -r "$arquivo_zip" ./ -x "./api-comppare/*" -x "./backups/*"

        # Move o arquivo zip para a pasta 'backups'
        mv "$arquivo_zip" backups/

        # Mensagem de confirmação do backup
        echo "Backup criado: backups/$arquivo_zip"
    else
        echo "Nenhum arquivo para backup encontrado. Saltando backup..."
    fi

    echo $SEPARATOR
    echo "Iniciando atualização"

    # Verificar se existem pastas além de 'api-comppare' e 'backups'
    for dir in */; do
        if [[ "$dir" != "api-comppare/" && "$dir" != "backups/" ]]; then
            # Verificar se a pasta existe e removê-la
            if [ -d "$dir" ]; then
                echo "Removendo a pasta: $dir"
                rm -rf "$dir"
            fi
        fi
    done

    # Extrair o conteúdo de 'build.zip' para a raiz
    unzip build.zip
    cd build
    mv * ..
    cd ..
    rm -rf build.zip

    # Atualização do diretório 'web'
    cd web
    mv * ..
    cd ..

    echo $SEPARATOR
    echo "Processo de atualização concluído"
}

# Check if Artisan exists and execute commands
run_deploy
