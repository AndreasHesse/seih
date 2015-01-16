#!/usr/local/bin/bash

for i in {1..5}
do
    echo "Run $i"
    /usr/local/bin/php /dana/data/seih.dk/docs/api_org/aggregationCommands/dailyAggregation.php
done