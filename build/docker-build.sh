#!/usr/bin/env bash

# Make sure Docker Desktop is running
DOCKER_DEAD=$(docker info 2>&1 | grep "docker daemon run")

if [ ! -z "$DOCKER_DEAD" ]
then
	echo "Docker is not running"
	exit 1
fi

# Check for a cross-platform builder. If there's none, create it
MYBUILDER=$(docker buildx ls 2>/dev/null | grep mybuilder | head -n1 )

if [ -z "$MYBUILDER" ]
then
	# See https://cloudolife.com/2022/03/05/Infrastructure-as-Code-IaC/Container/Docker/Docker-buildx-support-multiple-architectures-images/
	docker buildx create --name mybuilder
fi

# Make sure build.properties exists

if [ ! -f "../../build.properties" ]
then
	echo "Cannot find build.properties"
	exit 2
fi

CR_PAT=$(cat ../../build.properties | grep "github.token" | cut -d "=" -f 2)
CR_USERNAME=$(cat ../../build.properties | grep "github.username" | cut -d "=" -f 2)

if [ -z "$CR_PAT" ]
then
	echo "build.properties must set github.token"
	exit 3
fi

if [ -z "$CR_USERNAME" ]
then
	echo "build.properties must set github.username"
	exit 3
fi

echo $CR_PAT | docker login ghcr.io -u $CR_USERNAME --password-stdin

if [ -z "$PANOPTICON_LATEST_TAG" ]
then
	export PANOPTICON_LATEST_TAG=`git describe --abbrev=0`
fi

docker rmi ghcr.io/akeeba/panopticon:latest
docker rmi ghcr.io/akeeba/panopticon:$PANOPTICON_LATEST_TAG
docker buildx use mybuilder
docker buildx build -t ghcr.io/akeeba/panopticon:latest --no-cache --platform=linux/amd64,linux/arm64 --push .
docker buildx build -t ghcr.io/akeeba/panopticon:$PANOPTICON_LATEST_TAG --platform=linux/amd64,linux/arm64 --push .
docker buildx stop
docker buildx use default
docker push ghcr.io/akeeba/panopticon:latest
docker push ghcr.io/akeeba/panopticon:$PANOPTICON_LATEST_TAG
