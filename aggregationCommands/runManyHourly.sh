#!/usr/local/bin/bash

for i in {1..7}
do
    echo "Run $i"
    /usr/local/bin/php /dana/data/seih.dk/docs/api_org/aggregationCommands/hourlyAggregation.php
done