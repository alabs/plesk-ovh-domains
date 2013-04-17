#!/bin/bash

# Nos aseguramos que nos encontramos en el directorio donde está el script
SCRIPTPATH=$( cd $(dirname $0) ; pwd -P)
cd "$SCRIPTPATH"

# Copiamos el modulo
cp ovh.php /opt/plesk-billing/lib/lib-mbapi/include/modules/registrar/

