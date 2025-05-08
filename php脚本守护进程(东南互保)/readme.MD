## php脚本守护进程

## 用途

不依赖系统本身的crontab计划任务，实现对一条命令行的任务维护，一旦发现就会重新拉起进程。

## 功能介绍

里面有两个组件，

第一个组件是bypassui.php，这个组件提供了一个ui，可以直接执行命令并返回结果，用来测试是否可以在服务器上执行命令。目前使用的方法是

LD_PRELOAD绕过，这是使用php限制disable_function后绕过的方法，如果服务器没有限制执行命令，应当直接使用shell_exec或者system函数来执行。

反正这个东西是webshell改的，但是没有做混淆，不适合拿来搞渗透，用的人自己依据场景使用，后果自负，本人只做工具分享，学习使用。

那个so文件我记得应该是下面这个项目下载的。

[yangyangwithgnu/bypass_disablefunc_via_LD_PRELOAD: bypass disable_functions via LD_PRELOA (no need /usr/sbin/sendmail)](https://github.com/yangyangwithgnu/bypass_disablefunc_via_LD_PRELOAD)

第二个组件管理任务，只有两个输入，第一个输入是任务执行的内容，第二个是任务检查频率（多少秒检查任务一次）。

这个工具原理很简单，就是有两个shell脚本相互守护，a拉起b，b再拉起a，无限循环，用途就是一些没有开放corntab的虚拟主机可能有用，拉起任务的方法仍然是也是用LD_PRELOAD。
