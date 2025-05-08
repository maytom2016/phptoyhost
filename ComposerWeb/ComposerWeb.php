<?php
// Composer Web UI
// 安全提示：此脚本应在受控环境中使用，不建议直接暴露在公网

// 基本配置
$composer_path = './composer.phar'; // composer.phar路径
$project_dir = __DIR__; // 项目目录，可根据需要修改
$allowed_commands = [
    'install' => '安装依赖',
    'update' => '更新依赖',
    'require' => '添加包',
    'remove' => '移除包',
    'show' => '显示已安装包',
    'info' => '包信息',
    'outdated' => '检查过时包',
    'validate' => '验证composer.json',
    'dump-autoload' => '重建自动加载',
];

// 处理表单提交
$output = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['command'])) {
        $output = '<div class="alert alert-danger">未指定命令</div>';
    } else {
        $command = $_POST['command'];
        $package = isset($_POST['package']) ? escapeshellarg($_POST['package']) : '';
        
        // 验证命令是否允许
        if (!array_key_exists($command, $allowed_commands) && $command !== 'custom') {
            $output = '<div class="alert alert-danger">不允许的命令</div>';
        } else {
            // 构建完整命令
            $full_command = '';
            if ($command === 'custom') {
                $custom_cmd = isset($_POST['custom_cmd']) ? $_POST['custom_cmd'] : '';
                $full_command = "php {$composer_path} " . escapeshellcmd($custom_cmd);
            } elseif ($command === 'require' || $command === 'remove') {
                if (empty($package)) {
                    $output = '<div class="alert alert-danger">请指定包名称</div>';
                } else {
                    $full_command = "php {$composer_path} {$command} {$package}";
                }
            } else {
                $full_command = "php {$composer_path} {$command}";
            }
            
            // 执行命令
            if ($full_command) {
                chdir($project_dir);
                $output = '<div class="alert alert-info">执行: ' . htmlspecialchars($full_command) . '</div>';
                $output .= '<pre>' . htmlspecialchars(shell_exec("{$full_command} 2>&1")) . '</pre>';
            }
        }
    }
}

// 获取项目信息
function getProjectInfo($composer_path) {
    $info = [];
    
    // 检查composer.json是否存在
    if (file_exists('composer.json')) {
        $composer_json = json_decode(file_get_contents('composer.json'), true);
        $info['name'] = $composer_json['name'] ?? '未命名项目';
        $info['description'] = $composer_json['description'] ?? '无描述';
        $info['require'] = $composer_json['require'] ?? [];
        $info['require-dev'] = $composer_json['require-dev'] ?? [];
    } else {
        $info['error'] = '当前目录未找到composer.json';
    }
    
    // 获取已安装包信息
    chdir(dirname($composer_path));
    $installed = shell_exec("php {$composer_path} show -f json 2>&1");
    $info['installed'] = json_decode($installed, true) ?? [];
    
    return $info;
}

$project_info = getProjectInfo($composer_path);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Composer Web UI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .command-btn { margin-bottom: 5px; }
        .project-info { background-color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .package-list { margin-top: 15px; }
        .package-item { padding: 10px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Composer Web UI</h1>
        
        <?php if (isset($project_info['error'])): ?>
            <div class="alert alert-danger"><?= $project_info['error'] ?></div>
        <?php else: ?>
            <div class="project-info">
                <h3><?= htmlspecialchars($project_info['name']) ?></h3>
                <p><?= htmlspecialchars($project_info['description']) ?></p>
                
                <h5 class="mt-4">依赖包</h5>
                <div class="package-list">
                    <?php foreach ($project_info['require'] as $pkg => $version): ?>
                        <div class="package-item">
                            <strong><?= htmlspecialchars($pkg) ?></strong>: <?= htmlspecialchars($version) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($project_info['require-dev'])): ?>
                    <h5 class="mt-4">开发依赖包</h5>
                    <div class="package-list">
                        <?php foreach ($project_info['require-dev'] as $pkg => $version): ?>
                            <div class="package-item">
                                <strong><?= htmlspecialchars($pkg) ?></strong>: <?= htmlspecialchars($version) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">Composer 命令</div>
            <div class="card-body">
                <form method="post">
                    <div class="row mb-3">
                        <?php foreach ($allowed_commands as $cmd => $desc): ?>
                            <div class="col-md-3 col-6">
                                <button type="submit" name="command" value="<?= $cmd ?>" class="btn btn-primary w-100 command-btn">
                                    <?= $desc ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <input type="text" name="package" class="form-control" placeholder="包名称 (例如: monolog/monolog)">
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex">
                                <button type="submit" name="command" value="require" class="btn btn-success me-2 flex-grow-1">添加包</button>
                                <button type="submit" name="command" value="remove" class="btn btn-danger flex-grow-1">移除包</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-9">
                            <input type="text" name="custom_cmd" class="form-control" placeholder="自定义 composer 命令">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" name="command" value="custom" class="btn btn-secondary w-100">执行</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($output): ?>
            <div class="card">
                <div class="card-header">命令输出</div>
                <div class="card-body">
                    <?= $output ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>