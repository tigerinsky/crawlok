################# basic conf ###########
MYSQL=mysql
PYTHON=python
#BASE_DIR需要是绝对路径
BASE_DIR="/home/meihua/dingchuan/gitnice/crawlNice"
SQL_DIR="${BASE_DIR}/sql/"
BIN_DIR="${BASE_DIR}/bin/"
SCRIPT_DIR="${BASE_DIR}/script/"
DATA_DIR="${BASE_DIR}/data/"
WORK_DIR="${BASE_DIR}/data/work/"
WORK_BAK_DIR="${BASE_DIR}/data/work.bak"
LOG_DIR="${BASE_DIR}/log"
INDEX_DIR="${BASE_DIR}/index/"

################# DB conf ###########
DB_HOST=""
DB_PORT=""
DB_USER=""
DB_PWD=""
DB_ARGS="--default-character-set=utf8"


################# file ###########
CRAWLED_AVATAR_CONF="${DATA_DIR}/crawled_user_avatar"
CRAWLED_PIC_CONF="${DATA_DIR}/crawled_user_pic"
TWEET="${WORK_DIR}/tweet"
ZAN="${WORK_DIR}/zan"
TAG="${WORK_DIR}/tag"
FLAG="${BASE_DIR}/makeindex_flag"

################# makeindex ###########
MAKEINDEX_DIR="./"
SE_INDEX_CONF="/home/meihua/jinkaifeng/github/se/output/conf/index.conf"
SE_VERSION_FILE="/home/meihua/jinkaifeng/github/se/output/var/version"
