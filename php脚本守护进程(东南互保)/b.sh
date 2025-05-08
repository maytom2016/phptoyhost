#!/bin/bash

# 检查参数
check_arguments() {
    if [ $# -lt 4 ]; then
        log "Usage: $0 -c \"command\" -t task_id"
        echo "Usage: $0 -c \"command\" -t task_id" >&2
        exit 1
    fi
}
# 解析参数
parse_arguments() {
    while getopts "c:i:t:" opt; do
        case $opt in
            c) COMMAND="$OPTARG" ;;
            i) TASK_ID="$OPTARG" ;;
            t) INTERVAL="$OPTARG" ;;
            *) log "Invalid option: -$OPTARG" 
               echo "Invalid option: -$OPTARG" >&2
               exit 1 ;;
        esac
    done
}

# 检查参数
check_arguments "$@"

# 解析参数
parse_arguments "$@"

# ====================== 可配置参数 ======================
# 脚本名称配置
SELF="b.sh"
CALL_SHELL="a.sh"

# 守护进程日志文件路径
LOG_FILE="./task_log_b.txt"

# 任务目录
TASK_DIR="./task"

# 命令进程日志文件路径

CMD_LOG_FILE="${TASK_DIR}/${TASK_ID}.log"

# nohup路径配置
NOHUP_CMD="./nohup"

# 超时设置（秒）
# 启动另一守护脚本的时间
INITIAL_TIMEOUT=10
# 定期检查服务是否更改的时间，若停止则不再继续循环。
FINAL_TIMEOUT=300

# ====================== 函数定义 ======================
full_cmd="./${CALL_SHELL} -c $COMMAND -i $TASK_ID -t $INTERVAL"
self_cmd="./${SELF} -c $COMMAND -i $TASK_ID -t $INTERVAL"

# 检查进程是否在运行
is_process_running() {
    local process_name="$1"
    if pgrep -f -a "$process_name" | grep -v "symlink"; then
        #log "Process is running: $process_name"
        return 0  # 进程存在
    else
        #log "Process is not running: $process_name"
        return 1  # 进程不存在
    fi
}

# 检查进程是否在运行,全字匹配，用来匹配子命令
is_process_runningA() {
    local process_name="$1"
    if pgrep -f -a "$process_name$" | grep -v "symlink" ; then
        #log "Process is running: $process_name"
        return 0  # 进程存在
    else
        #log "Process is not running: $process_name"
        return 1  # 进程不存在
    fi
}

#防止二次启动实例,此处不是绝对可靠，当速度太快时会无法响应到。
check_self_launched() {
    local process_name="$1"
    local cmdlist=$(ps -ef | grep -F "$process_name" | grep -v "grep" | grep -v "symlink")
    local process_count=$(echo "$cmdlist" | wc -l)
    # 由于wc -l会计算空行，需要处理空输出情况
    process_count=$((process_count - 0))  # 确保是数字

    if [ $process_count -gt 2 ]; then
        # 打印运行的进程信息
        echo "检测到已有进程在运行：" >> "$LOG_FILE" 
        ps -ef | grep "$process_name" | grep -v "grep" | grep -v "symlink" >> "$LOG_FILE" 
        
        # 获取进程PID列表
        local pids=$(ps -ef | grep "$process_name" | grep -v "grep" | grep -v "symlink" | awk '{print $2}')
        
        echo "已有以下实例在运行(PID: $pids)，当前脚本将退出" >> "$LOG_FILE" 
        echo "错误：$process_name 已在运行(PID: $pids)" >&2
        
        # 直接退出脚本
        exit 1
    else
        echo "没有检测到运行的 $process_name 进程，继续执行" >> "$LOG_FILE" 
        return 0
    fi
}

# 防止启动两个实例

check_self_launched "$self_cmd"

# 记录日志函数
log() {
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
    echo "[$timestamp] [${SELF}] $1" >> "$LOG_FILE"
}



# 获取任务ID文件路径
get_task_file() {
    echo "${TASK_DIR}/${TASK_ID}"
}

# 检查任务ID文件内容是否为0
check_task_id() {
    local task_file=$(get_task_file)
    if [ ! -f "$task_file" ]; then
        log "Task ID file not found: $task_file"
        echo "Task ID file not found: $task_file" >&2
        exit 1
    fi
    
    local content=$(tr -d '[:space:]' < "$task_file")
    # log "Task ID file content: $content"
    if [ "$content" = "0" ]; then
        # log "Task ID content is 0"
        return 1  # 为0返回1表示失败
    else
        # log "Task ID content is not 0"
        return 0  # 不为0返回0表示成功
    fi
}


# 执行命令
execute_command() {
    log "Executing command: $COMMAND"
    eval "$COMMAND" > "$CMD_LOG_FILE" 2>&1 </dev/null
    local status=$?
    if [ $status -ne 0 ]; then
        log "Command execution failed with exit status: $status" 
    else
        log "Command executed successfully"
    fi
    return $status
}

# 启动b.sh
start_b_script() {
    shellcmd=(
    "./${CALL_SHELL}"
    -c "$COMMAND"
    -i "$TASK_ID"
    -t "$INTERVAL"
    )
    # local full_cmd="./${CALL_SHELL} -c $COMMAND -i $TASK_ID -t $INTERVAL"
    # echo "shellcmd:$full_cmd">> "$LOG_FILE"
    if ! is_process_running "$full_cmd"; then
        log "Starting ${CALL_SHELL} with parameters: -c \"$COMMAND\" -i $TASK_ID -t $INTERVAL"
        ${NOHUP_CMD} "${shellcmd[@]}" & >> "$LOG_FILE" 2>&1 
    else
        echo "${CALL_SHELL} is already running, skipping startup" > /dev/null 2>&1 &
    fi
}

# 主循环
main_loop() {
    local start_time=$(date +%s)
    local elapsed=0
    local task_gap=0

    # 首次执行命令
    if ! is_process_runningA "$COMMAND" ; then
        execute_command
    fi

    while true; do
        # 检查任务ID文件是否为0
        if ! check_task_id; then
            log "Task ID is 0, exiting..."
            break
        fi
        
        # 检查进程是否在运行
        if [ $task_gap -ge $INTERVAL ] && ! is_process_runningA "$COMMAND"; then
            execute_command
            task_gap=0
        fi
        
        # 计算经过的时间
        local current_time=$(date +%s)
        elapsed=$((current_time - start_time))
        task_gap=$((task_gap + 1))
        # echo "task_gap:$task_gap"
        # local full_cmd="./${CALL_SHELL} -c $COMMAND -i $TASK_ID -t $INTERVAL"
        # 间隔启动另外一个守护脚本
        if [ $elapsed -ge $INITIAL_TIMEOUT ] && [ $elapsed -lt $FINAL_TIMEOUT ] && ! is_process_running "$full_cmd"; then
            log "${INITIAL_TIMEOUT} seconds passed, attempting to start ${CALL_SHELL}"
            start_b_script
        fi
        
        # 一定时间内检查服务状态
        if [ $elapsed -ge $FINAL_TIMEOUT ]; then
            log "${FINAL_TIMEOUT} seconds passed, checking task ID again"
            if ! check_task_id; then
                log "Task ID is 0 after ${FINAL_TIMEOUT} seconds, exiting..."
                break
            fi
            # 重置计时器以继续循环
            start_time=$(date +%s)
            elapsed=0
            log "Reset timer and continue monitoring"
        fi
        
        sleep 1
    done
}

# ====================== 主程序 ======================

# 初始化日志文件
: > "$LOG_FILE"
log "=== Script ${SELF} started ==="


log "Parameters: command='$COMMAND', task_id=$TASK_ID, interval=${INTERVAL}s"

# 创建任务目录（如果不存在）
mkdir -p "$TASK_DIR"

# 主程序
log "Starting main program"
if check_task_id; then
    log "Starting monitoring for task $TASK_ID"
    main_loop
else
    log "Task ID is 0 initially, exiting"
fi

log "Script ${SELF} finished"