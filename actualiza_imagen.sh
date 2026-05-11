#!/bin/bash
                        #sed -i '4cExec=sh -c '\''cd /home/pi/A108;sudo sh actualizar.sh'\''' /home/pi/.config/autostart/actualizar.desktop
                        git config --global --add safe.directory /home/pi/PHPPLUS
                        cd /home/pi/PHPPLUS                                             
                        git pull --force                      
                        sudo rm -R /home/pi/A108
                        mkdir /home/pi/A108                                                
                        cp -R /home/pi/PHPPLUS/* /home/pi/A108
                        cp -R /home/pi/PHPPLUS/html/ /var/www/
                        sleep 6                                             
                        sudo chmod 777 -R /home/pi/A108   
                        sudo chmod 777 -R /var/www/html
                         
                         