#!/bin/bash

if [[ $EUID > 0 ]]; then
  echo "Please run as root/sudo"
  exit 1
fi

useradd -r -s /bin/false midnite-modbusd

mkdir -p /var/lib/midnite-modbusd
chown midnite-modbusd:midnite-modbusd /var/lib/midnite-modbusd

cp etc/midnite-modbusd.service /lib/systemd/system
cp etc/midnite-modbusd.conf.sample /etc

cp dist/bin/midnite-modbusd /usr/local/bin
cp dist/bin/newmodbus /usr/local/bin
cp dist/bin/midnite-classic-data.phar /usr/local/bin/midnite-classic-data

chmod +x /usr/local/bin/midnite-modbusd
chmod +x /usr/local/bin/newmodbus
chmod +x /usr/local/bin/midnite-classic-data

echo "Copy /etc/midnite-modbusd.conf.sample to /etc/midnite-modbusd.conf and configure it"
echo "Then systemctl start midnite-modbusd.service"
exit 0