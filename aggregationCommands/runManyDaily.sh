#!/bin/bash

for i in {1..5}
do
    echo "Run $i"
    /usr/bin/php /home/sites/seih/htdocs/moctest/aggregationCommands/dailyAggregation.php
done