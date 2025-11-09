#!/bin/bash
# Cron-Job: Automatisches Update des PayPal Pool Betrags
# Läuft alle 10 Minuten

curl -s https://pushingp.de/api/get_paypal_pool.php > /dev/null 2>&1
