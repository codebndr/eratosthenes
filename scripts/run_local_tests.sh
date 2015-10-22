#!/bin/bash

# Ask user to make sure we want to run this
echo "NEVER run this script in production. It will purge your database to a clean state"
read -r -p "Are you sure you want to run this? [y/N] " response
case $response in
    [yY][eE][sS]|[yY])
        # User accepted
        ;;
    *)
        # Abort
        exit
        ;;
esac

# Changing directory to Symfony, regardless where we are
cd "$(git rev-parse --show-toplevel)/Symfony"

# Print the commands along their output (so that we know which one is being executed)
set -x
# Make sure the script stops on the first error
set -e
pwd

# Create the directory where the tests results will be stored
sudo mkdir -p build/logs

../scripts/install_composer.sh

../scripts/configure_system.sh

../scripts/clear_cache.sh

../scripts/warmup_cache.sh

../scripts/run_tests.sh

echo "Done!"
