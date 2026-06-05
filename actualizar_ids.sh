#!/bin/bash
                      
                        # Cambio realizado el 31-05-2026 para actualizar los IDS en MMDVMHost 
                        
                        sudo chmod 777 -R /home/pi/MMDVMHost
                        sudo chmod 777 -R /home/pi/MMDVM_CM
                        cd /home/pi/MMDVMHost
                        curl --fail -o DMRIds.dat -s http://www.pistar.uk/downloads/DMRIds.dat
                        cp /home/pi/MMDVMHost/DMRIds.dat /home/pi/MMDVM_CM/DMR2YSF/DMRIds.dat
                        cp /home/pi/MMDVMHost/DMRIds.dat /home/pi/MMDVM_CM/YSF2DMR/DMRIds.dat
                        
                        echo "*********************************************"
                        echo "         IDS actualizadas correctamente"
                        echo "*********************************************"
                        sleep 3

                        
