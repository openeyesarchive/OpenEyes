#!/bin/sh


sudo docker run -d --name $1 -p 3306 openeyesdb

#Fix on Monday ;)
mysqlport=`sudo docker ps -a | grep $1 | grep -Po '(\d+)->' | grep -o "[0-9]*"`

sudo sed -i.bak 's/port=3306/port='$mysqlport'/g' protected/config/local/common.php