<?php

require_once __DIR__ . '/vendor/autoload.php';

const VERSION = '0.1.0-beta';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Slimbot\AgentFactory;

$app = new Application('slimbot', VERSION);

function sendToServer(string $message, string $sessionId = 'default', ?string $imagePath = null): ?string
{
    $host = '127.0.0.1';
    $port = 8080;
    $socket = @fsockopen($host, $port, $errno, $errstr, 2);
    if (!$socket) {
        return null;
    }
    $data = json_encode([
        'action' => 'chat',
        'message' => $message,
        'session_id' => $sessionId,
        'image_path' => $imagePath
    ]);
    fwrite($socket, $data);
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket, 1024);
    }
    fclose($socket);
    $result = json_decode($response, true);
    return $result['response'] ?? null;
}

$app->register('chat')
    ->addArgument('message', InputArgument::REQUIRED, 'The message to send to the agent')
    ->addOption('image', 'i', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Path to an image file')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $message = $input->getArgument('message');
        $imagePath = $input->getOption('image');

        $response = sendToServer($message, 'default', $imagePath);
        if ($response === null) {
            try {
                $agent = AgentFactory::create(__DIR__);
                $response = $agent->chat($message, $imagePath);
            } catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return 1;
            }
        }

        $output->writeln("\n<info>Agent:</info> " . $response);
        return 0;
    });

$app->register('interactive')
    ->setDescription('Start an interactive chat session')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $sessionId = 'interactive';
        $usingServer = sendToServer('ping', $sessionId) !== null;

        if (!$usingServer) {
            try {
                $agent = AgentFactory::create(__DIR__, $sessionId);
            } catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return 1;
            }
        }

        $output->writeln('<info>Slimbot Interactive Mode</info>');
        $output->writeln('Type <comment>exit</comment> to quit.');

        while (true) {
            $output->write("\n<question>You:</question> ");
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            if ($line === false)
                break;

            $message = trim($line);
            if (strtolower($message) === 'exit')
                break;
            if (empty($message))
                continue;

            $output->write("<info>Agent is thinking...</info>\r");

            if ($usingServer) {
                $response = sendToServer($message, $sessionId);
            } else {
                $response = $agent->chat($message);
            }

            // Clear the "thinking" line
            $output->write(str_repeat(' ', 30) . "\r");
            $output->writeln("<info>Agent:</info> " . $response);
        }

        $output->writeln('<info>Goodbye!</info>');
        return 0;
    });

$app->register('server')
    ->setDescription('Start the independent agent server')
    ->addArgument('port', InputArgument::OPTIONAL, 'Port to listen on', 8080)
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $port = (int) $input->getArgument('port');
        $output->writeln("<info>Starting server on port $port...</info>");
        $server = new \Slimbot\AgentServer(__DIR__, $port);
        $server->start();
        return 0;
    });

$app->run();
