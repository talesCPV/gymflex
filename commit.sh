#!/bin/bash
# Upload files to Github - git@github.com:talesCPV/gymflex.git

read -p "Are you sure to commit gymflex to GitHub ? (Y/n)" -n 1 -r
echo    # (optional) move to a new line
if [[ $REPLY =~ ^[Yy]$ ]]
then

    cp ~/Documentos/SQL/gymflex/*.sql sql/

    git init

    git add readme.md
    git add commit.sh
    
    git commit -m "by_script"

#    git branch -M main
#    git remote add origin git@github.com:talesCPV/gymflex.git
    git remote set-url origin git@github.com:talesCPV/gymflex.git

    git push -u -f origin main

fi