<?php

namespace App\Console;

use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Process\Process;
use Vectorface\Whip\Whip;

class Test extends Command
{
    public function method($a, $b = 'foobar')
    {
        $this->logger->info("logging a message");
        return
            "\nEntered console command with params: \n".
            "a= {$a}\n".
            "b= {$b}\n";
    }

    public function process()
    {
        $process = new Process('php cli.php test mail to=1090195622@qq.com');
        $process->start();
        
        echo '我先干点别的事';

        while ($process->isRunning()) {
            // waiting for process to finish / 等待进程完成
        }
        echo $process->getOutput();
    }
}
