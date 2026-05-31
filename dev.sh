#!/usr/bin/env bash
# Run a command inside the Docker PHP container, or open an interactive shell.
# Usage:
#   ./dev.sh                        # open interactive shell
#   ./dev.sh "make test"            # run make target
#   ./dev.sh "cd packages/ordinary/log && vendor/bin/phpunit"

set -euo pipefail

# Detect host environment and resolve the docker binary.
#   MSYSTEM is set by Git Bash / MSYS2 on Windows (value: MINGW64, MINGW32, MSYS)
#   WSL_DISTRO_NAME is set inside WSL2 distributions
#   Anything else is treated as native Linux (CI, VMs)
if [[ "${MSYSTEM:-}" == MINGW* ]] || [[ "${MSYSTEM:-}" == MSYS* ]]; then
    DOCKER="/c/Program Files/Docker/Docker/resources/bin/docker.exe"
elif [[ -n "${WSL_DISTRO_NAME:-}" ]] || grep -qi microsoft /proc/version 2>/dev/null; then
    DOCKER="docker"
else
    DOCKER="docker"
fi

if ! command -v "${DOCKER}" &>/dev/null && [[ ! -x "${DOCKER}" ]]; then
    echo "error: docker not found (resolved to: ${DOCKER})" >&2
    exit 1
fi

if [[ $# -eq 0 ]]; then
    exec "${DOCKER}" compose run --rm php sh
else
    exec "${DOCKER}" compose run --rm php sh -c "$*"
fi
