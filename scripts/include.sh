#!/bin/bash

# Test is the anwser of the user is "yes". If it's "yes", the script will continue otherwise it stop
f_testyes()
{
echo -ne "$1 [yes]"
read rep
if [ -n "$rep" ]; then
    if [ "$rep" != "yes" ]; then
        if [ -z $2 ]; then
            echo "So, make you sure, it's ok. This program have to quit" >&2
            exit
        fi
        return "0"
    fi
fi
return "1"
}

f_prompt()
{
echo -ne "$1 [yes]"
read rep
if [ -n "$rep" ]; then
    return $rep
fi
return "1"
}

f_title()
{
    echo "##############################################" >&2
    echo -e "         $1"                                    >&2
    echo "##############################################" >&2
}

f_launch_remote()
{
    echo ""
    echo -e "Launching remote [\033[32m$REMOTE_SERVER_USERHOST:$REMOTE_PATH\033[0m] command: \033[32m$1\033[0m" | tee -a $LOG_FILE
    ssh $REMOTE_SERVER_USERHOST "cd $REMOTE_PATH && $1" | tee -a $LOG_FILE
    if [ "$?" -ne "0" ]; then
        echo -e "command \033[31m $1 \033[0m \033[32mFAILED.\033[0m Exit !!";
        exit 1;
    fi
}


f_launch_cmd()
{
    echo ""
    echo -e "Launching command: \033[32m$1\033[0m" | tee -a $LOG_FILE
    $1 | tee -a $LOG_FILE
    if [ "$?" -ne "0" ]; then
        echo -e "command \033[31m $1 \033[0m \033[32mFAILED.\033[0m Exit !!";
        exit 1;
    fi
}

f_init_version()
{
    VERSION=`git log --pretty=oneline |  head -n 1 | cut -c1-7`

    LAST_REV_FILE="releases/$TARGET/last_rev"

    if [ -e "$LAST_REV_FILE" ]; then
        LAST_REV=`cat $LAST_REV_FILE`
    fi

    DATE=`date '+%y-%m-%d'`
    LOG_DIR="releases/$TARGET/logs/$DATE"

    mkdir -p $LOG_DIR

    LOG_FILE="$LOG_DIR/$VERSION.log"
    CHANGE_LOG_FILE="releases/$TARGET/changelog"
}
