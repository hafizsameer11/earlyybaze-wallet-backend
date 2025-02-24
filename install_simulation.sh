#!/bin/bash

clear
echo "Starting installation..."
sleep 1

# Simulating package1 installation (100% completed)
echo -ne "Installing package1: [####################] 100%\n"
sleep 1

# Simulating package2 configuration (gradual increase to 57%)
echo -ne "Configuring package2: [                    ] 0%\r"
sleep 1
for i in {1..57}; do
    progress_bar=$(printf "%-${i}s" "#")
    echo -ne "Configuring package2: [$progress_bar] $i%\r"
    sleep 0.1
done

echo -e "\nConfiguration is continue  at 57%"
