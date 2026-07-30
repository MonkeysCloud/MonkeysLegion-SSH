<?php

require_once __DIR__ . '/vendor/autoload.php';

use MonkeysLegion\SSH\Core\ConnectionBuilder;

$connection = (new ConnectionBuilder())
    ->to('127.0.0.1')
    ->port(2222)
    ->as('integration')
    ->withPassword('integration-password')
    ->timeout(10)
    ->connect();

$shell = $connection->shell('xterm-256color', 120, 40);
\usleep(500000);
$shell->readAll();

// Disable remote echo + set up colored environment
$shell->write("stty -echo\n");
$shell->write("export TERM=xterm-256color\n");
$shell->write('export PS1="\n\\[\\e[1;32m\\]\\u@\\h\\[\\e[0m\\]:\\[\\e[1;34m\\]\\w\\[\\e[0m\\]\\$ "' . "\n");
$shell->write("alias ls='ls --color=auto'\n");
$shell->write("alias grep='grep --color=auto'\n");
\usleep(400000);
$shell->readAll();

\fwrite(\STDOUT, "--- Interactive SSH shell ---\n");
\fwrite(\STDOUT, "Type commands.  exit to quit.\n\n");

while (true) {
    $input = \fgets(\STDIN);
    if ($input === false) {
        break;
    }

    $input = \trim($input);

    if ($input === 'exit' || $input === 'quit') {
        $shell->write("stty echo\nexit\n");
        \usleep(300000);
        break;
    }

    if ($input === '') {
        continue;
    }

    $shell->write($input . "\n");
    \usleep(400000);
    \fwrite(\STDOUT, $shell->readAll());
}

$shell->close();
$connection->disconnect();
