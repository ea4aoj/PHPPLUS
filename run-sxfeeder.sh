#!/bin/bash
SERIAL=$(cat /sys/firmware/devicetree/base/serial-number | tr -d '\0')

docker run \
  --name sxfeeder \
  --privileged \
  --network host \
  -v /etc/sxfeeder.ini:/etc/sxfeeder.ini \
  -v /sys/firmware/devicetree/base/serial-number:/host_serial:ro \
  -v /var/run/sxfeeder:/var/run/sxfeeder \
  -v /var/log/sxfeeder.log:/var/log/sxfeeder.log \
  sxfeeder-raspberry \
  /bin/bash -c "SERIAL='$SERIAL'; cat > /tmp/cpuinfo_simple << EOF
processor	: 0
BogoMIPS	: 108.00
Features	: half thumb fastmult vfp edsp neon vfpv3 tls vfpv4 idiva idivt vfpd32 lpae evtstrm crc32 
CPU implementer	: 0x41
CPU architecture: 7
CPU variant	: 0x0
CPU part	: 0xd08
CPU revision	: 3

Hardware	: BCM2835
Revision	: c03111
Serial		: $SERIAL
Model		: Raspberry Pi 4 Model B Rev 1.1
EOF
echo -n 'Raspberry Pi 4 Model B Rev 1.1' > /tmp/fake_model
echo -n '$SERIAL' > /tmp/fake_serial
mount --make-private /proc
mount --bind /tmp/cpuinfo_simple /proc/cpuinfo
mount --bind /tmp/fake_model /proc/device-tree/model
mount --bind /tmp/fake_serial /proc/device-tree/serial-number
mount --bind /tmp/fake_serial /sys/firmware/devicetree/base/serial-number
mkdir -p /var/run/sxfeeder
exec /usr/bin/sxfeeder"
