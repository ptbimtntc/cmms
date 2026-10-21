FROM mcr.microsoft.com/devcontainers/universal:2

# This image ships a broken apt source for Yarn (/etc/apt/sources.list.d/yarn.list)
# whose signing key (NO_PUBKEY 62D54FD4003F6525) can no longer be fetched. That makes
# every `apt-get update` fail, including the one the docker-in-docker feature runs
# during container build, which aborts the whole devcontainer build. Yarn/Node are
# already provided via nvm in this image, so the apt repo is unused — drop it.
RUN rm -f /etc/apt/sources.list.d/yarn.list
