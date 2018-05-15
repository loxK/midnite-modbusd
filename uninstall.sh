#!/bin/bash

if [[ $EUID > 0 ]]; then
  echo "Please run as root/sudo"
  exit 1
fi

systemctl stop midnite-modbusd.service

rm -fr /var/lib/midnite-modbusd

rm -f /lib/systemd/system/midnite-modbusd.service
rm -f /etc/midnite-modbusd.conf.sample

rm -f /usr/local/bin/midnite-modbusd
rm -f /usr/local/bin/newmodbus
rm -f /usr/local/bin/midnite-classic-data

userdel midnite-modbusd

echo "Uninstalled. /etc/midnite-modbusd.conf left intact if it existed."
exit 0