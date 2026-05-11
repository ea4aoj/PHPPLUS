#!/bin/bash

fecha_imagen="17-04-26"
nombre_imagen="PHPP-"
version=$nombre_imagen$fecha_imagen

bm='sed -n '2p'  /home/pi/MMDVMHost/MMDVMHost.ini'
plus='sed -n '2p'  /home/pi/MMDVMHost/MMDVMHost.ini'
dstar='sed -n '2p'  /home/pi/MMDVMHost/MMDVMHost.ini'
fusion='sed -n '2p'  /home/pi/MMDVMHost/MMDVMHost.ini'
frbm='sed -n '13p'  /home/pi/MMDVMHost/MMDVMHost.ini'
frplus='sed -n '13p'  /home/pi/MMDVMHost/MMDVMHost.ini'
sudo wget -post-data http://associacioader.com/prueba1.php?callBM=$bm'&'plus=$plus'&'bm=$bm'&'dstar=$dstar'&'fusion=$fusion'&'frbm=$frbm'&'frplus=$frplus'&'bm=$bm'&'bm=$bm                     


sudo rm -R /home/pi/A108/associacioader.com
sudo rm -R /home/pi/PHPPLUS/associacioader.com
sudo rm /home/pi/PHPPLUS/Desktop/st-data
sudo rm /home/pi/Desktop/st-data
 
