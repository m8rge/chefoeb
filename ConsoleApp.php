<?php

/**
 * Class ConsoleApp
 * @version 0.5
 */
class ConsoleApp
{
    /**
     * @param string $command
     * @return string[]
     */
    public function exec($command)
    {
        echo "> $command\n";
        ob_start();
        passthru($command, $return_var);
        if ($return_var) {
            exit($return_var);
        }
        $output = ob_get_flush();

        // add missing new line
        if (!empty($output) && substr($output, -1, 1) !== "\n") {
            echo "\n";
        }

        $output = rtrim($output, PHP_EOL);
        return empty($output) ? array() : explode(PHP_EOL, $output);
    }

    /**
     * @param string $text
     * @param int $returnCode
     */
    public function writeError($text, $returnCode = 1)
    {
        file_put_contents('php://stderr', $text . "\n", FILE_APPEND);
        exit($returnCode);
    }

    /**
     * @param string $text
     */
    public function write($text)
    {
        echo '==' . $text . "\n";
    }
}
