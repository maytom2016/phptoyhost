# phptoyhost

## 序言

收集好玩的php项目，针对的是低权限的虚拟php主机，拓展原有虚拟小机应用范围，

本项目只列出好部署的，也就是上传到服务器内基本就可以使用的项目，不需要额外去处理Composer依赖关系的项目。

因为基本上php虚拟主机都不给ssh登录权限。

有什么推荐项目可以推荐pull request，本项目主要是清单，如果没有改版不会放原项目代码，尊重原作者权益。

## 目录

| 项目名          | 来源                                                                              | 说明                                                                                                                                                         | 编号 |
| --------------- | --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---- |
| php脚本守护进程 | 本项目                                                                            | 脱离crontab的任务守护                                                                                                                                        | 1    |
| php文件下载器   | 本项目                                                                            | 下载文件到服务器还能下回本地                                                                                                                                 | 2    |
| downfileapi     | 本项目                                                                            | 服务器在线下载文件流式传回本地                                                                                                                               | 3    |
| tinyfilemanager | https://tinyfilemanager.github.io/                                                | WEB在线文件管理，本项目有其改版使用cookie认证，绕过服务器会话(session)限制时间，<br />session会话时间限制会让网页容易超时登出，因为清空的session的认证信息。         | 4    |
| FileaAdmin      | https://fa.nlrdev.top/                                                            | WEB文件在线管理，此项目很好，难得精品，强烈推荐。                                                                                                              | 5    |
| SpeedTest       | https://github.com/librespeed/speedtest                                           | 网速测试，这东西居然是php写的。                                                                                                                              | 6    |
| EasyImages      | https://github.com/icret/EasyImages2.0                                            | 图床，很好部署，检查不通过的话又没有ssh权限的话，可以使用#1项目的功能执行授权命令。                                                                              | 7    |
| shudong         | https://github.com/HFO4/shudong-share                                             | 网盘程序。                                                                                                                                                   | 8    |
| php-proxy       | https://github.com/koalabearguo/php-proxy/tree/main                               | 可以使用服务器代理网页                                                                                                                                       | 9    |
| ComposerWeb     | 本项目                                                                            | Composer在web管理。                                                                                                                                          | 10   |
| CensorDodge     | https://github.com/k0nsl/CensorDodge                                              | 网页代理访问                                                                                                                                                 | 11   |
| x探针           | https://github.com/kmvan/x-prober?tab=readme-ov-file			                       | 用来显示服务器各项信息                                                                                                                                       | 12   |
| 雅黑PHP探针     | https://github.com/Neil-Huang/php-probe/tree/master				                       | 用来显示服务器各项信息                                                                                                                                       | 13   |
|phpEasyVCS-zh_CN | https://github.com/cyqsd/phpEasyVCS-zh_CN                                        | phpEasyVCS汉化版本，据说可以开webdav。                                                                                                                       | 14 |  
## 后言

如果有什么别的有趣php项目一定推荐一下，放入清单，大家一起玩。
