#!/bin/bash

# path usuario
usuario="/home/pi"
usuario="$usuario"
fecha_imagen="11-05-26"
nombre_imagen="Opi3-"
version=$nombre_imagen$fecha_imagen


bm=$(sed -n '2p'  $usuario/MMDVMHost/MMDVMHost.ini)
plus=$(sed -n '2p'  $usuario/MMDVMHost/MMDVMHost.ini)
dstar=$(sed -n '2p'  $usuario/MMDVMHost/MMDVMHost.ini)
fusion=$(sed -n '2p'  $usuario/MMDVMHost/MMDVMHost.ini)
frbm=$(sed -n '13p'  $usuario/MMDVMHost/MMDVMHost.ini)
frplus=$(sed -n '13p'  $usuario/MMDVMHost/MMDVMHost.ini)

wget -qO- --post-data="callBM=$bm&callPLUS=$plus&masterBM=$masterbm&masterPLUS=$masterplus&radio=$masterradio&version=$version&ESPECIAL=$masterespecial&YSFGateway=$masterYSFGateway" \
http://associacioader.com/prueba1.php

#sudo rm -R /home/pi/associacioader.com
#sudo rm -R /home/pi/A108/associacioader.com
#sudo rm /home/pi/Desktop/st-data
