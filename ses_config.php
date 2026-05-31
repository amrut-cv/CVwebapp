<?php
// Credentials are stored in ses_secrets.php (gitignored — never commit that file)
require_once __DIR__ . '/ses_secrets.php';

function ses_send(string $to, string $subject, string $body): bool {
    $host = SES_HOST;
    $port = SES_PORT;
    $user = SES_USERNAME;
    $pass = SES_PASSWORD;
    $from = SES_FROM;
    $name = SES_FROM_NAME;

    $socket = fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) return false;

    $read = function() use ($socket) {
        $r = '';
        while ($line = fgets($socket, 515)) {
            $r .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $r;
    };
    $send = function(string $cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $read(); // banner
    $send("EHLO " . gethostname());
    $send("STARTTLS");
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $send("EHLO " . gethostname());
    $send("AUTH LOGIN");
    $send(base64_encode($user));
    $send(base64_encode($pass));
    $send("MAIL FROM:<{$from}>");
    $send("RCPT TO:<{$to}>");
    $send("DATA");

    $date    = date('r');
    $msgId   = '<' . uniqid('cv', true) . '@corevoice.in>';
    $headers = "Date: {$date}\r\n"
             . "From: {$name} <{$from}>\r\n"
             . "To: {$to}\r\n"
             . "Subject: {$subject}\r\n"
             . "Message-ID: {$msgId}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 7bit\r\n";

    $resp = $send($headers . "\r\n" . $body . "\r\n.");
    $send("QUIT");
    fclose($socket);

    return str_starts_with(trim($resp), '250');
}
