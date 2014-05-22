#!/bin/bash

for i in {1..7}
do
    echo "Run $i"
    /usr/bin/php /home/sites/seih/htdocs/moctest/aggregationCommands/hourlyAggregation.php
done