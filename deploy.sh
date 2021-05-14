#!/bin/bash
echo "= Deploy"

function error {
	echo
	echo "# $1"
	exit
}

# REMOTE_USER=user
# REMOTE_HOST=my-server.com
# REMOTE_DIR=./api
CONFIG=deploy.conf

[ ! -f $CONFIG ] && error "File '$CONFIG' not found."

. ./$CONFIG

ping -c1 $REMOTE_HOST || error "Server unreachable '$REMOTE_HOST'."

LOCAL_TARGET="lang public vendor .env.php"

rsync -aruvzh --delete --progress $LOCAL_TARGET $REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR || error "Error updating the server '$REMOTE_HOST'."

echo
echo "- Successful operation, '$REMOTE_HOST' updated."
