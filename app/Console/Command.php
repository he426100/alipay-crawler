<?php

namespace App\Console;

use League\Plates\Engine;
use Psr\Log\LoggerInterface;
use PHPMailer\PHPMailer\PHPMailer;

abstract class Command
{

    public $logger;
    public $view;

    public function __construct(Engine $view, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->view = $view;
    }

    /**
     * 发送邮件
     *
     * @param string $to
     * @param string $subject
     * @param string $content
     * @return void
     */
    public function mail($to, $subject, $content = '')
    {
        if (empty($to)) {
            return
            "\nEntered console command with params: \n".
            "to= {$to}\n";
        }
        $app = app();
        $mail = $app->resolve(PHPMailer::class);
        try {
            $mail->addAddress($to);     // Add a recipient
            $mail->Subject = $subject;
            $mail->Body    = $content;
            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
        }
    }
}
