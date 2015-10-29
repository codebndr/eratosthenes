#!/bin/bash

set -x
set -e

echo "Running Tests"

if [[ $TRAVIS ]]; then
    # Just run PHPUnit tests if on Travis CI environment
    # `build` directory should already have been created
    bin/phpunit -c app/ --verbose --coverage-clover build/logs/clover.xml
else
    # Otherwise, run mess and copy-paste detectors too
    bin/phpunit -c app/ --verbose --coverage-clover build/logs/clover.xml --coverage-html=coverage/

    set +e

    echo "Running Copy-Paste-Detector"
    bin/phpcpd --log-pmd build/pmd-cpd.xml --exclude app --exclude vendor --names-exclude *Test.php, -n .

    echo "Running Mess-Detector"
    bin/phpmd src/Codebender/ xml app/phpmd-rule.xml --exclude *Test.php --reportfile build/pmd.xml

    set -e
fi
