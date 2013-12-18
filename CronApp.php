<?php

/**
 * Class CronApp
 * @version 0.6
 */
class CronApp
{
    const LEVEL_INFO = 'info';
    const LEVEL_WARN = 'warning';
    const LEVEL_ERROR = 'error';

    /**
     * @var string
     */
    protected $defaultAction = 'index';
    /**
     * @var string
     */
    public $logFile = './app.log';
    /**
     * @var resource
     */
    protected $openedLogFile;

    public function init()
    {
        $this->openedLogFile = fopen($this->logFile, 'a');
        date('r');
    }

    function __destruct()
    {
        if (is_resource($this->openedLogFile)) {
            fclose($this->openedLogFile);
        }
    }

    /**
     * @param string $text
     * @param string $level
     */
    public function log($text, $level = self::LEVEL_INFO)
    {
        if (!empty($text)) {
            $CR = '';
            // add missing new line
            if (substr($text, -1, 1) !== "\n") {
                $CR = "\n";
            }
            fwrite($this->openedLogFile, date('r') . ' ' . strtoupper($level) . ': ' . $text . $CR);
        }
    }

    /**
     * @param string $command
     * @throws Exception
     * @return string[]
     */
    public function exec($command)
    {
        $this->log("> $command");
        ob_start();
        passthru($command, $return_var);
        $output = ob_get_clean();
        $this->log($output);
        if ($return_var) {
            $this->onError("$command returned $return_var");
        }

        $output = rtrim($output, PHP_EOL);
        return empty($output) ? array() : explode(PHP_EOL, $output);
    }

    public function onError($message)
    {
        $this->log($message, self::LEVEL_ERROR);
        throw new Exception($message);
    }

    public function actionHelp()
    {
        echo $this->getHelp();
    }

    /**
     * Executes the command.
     * The default implementation will parse the input parameters and
     * dispatch the command request to an appropriate action with the corresponding
     * option values
     * @param array $args command line parameters for this command.
     * @return integer application exit code, which is returned by the invoked action. 0 if the action did not return anything.
     * (return value is available since version 1.1.11)
     */
    public function run($args)
    {
        list($action, $options, $args) = $this->resolveRequest($args);
        $methodName = 'action' . $action;
        if (!preg_match('/^\w+$/', $action) || !method_exists($this, $methodName)) {
            $this->usageError("Unknown action: " . $action);
        }

        $method = new ReflectionMethod($this, $methodName);
        $params = array();
        // named and unnamed options
        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (isset($options[$name])) {
                if ($param->isArray()) {
                    $params[] = is_array($options[$name]) ? $options[$name] : array($options[$name]);
                } elseif (!is_array($options[$name])) {
                    $params[] = $options[$name];
                } else {
                    $this->usageError("Option --$name requires a scalar. Array is given.");
                }
            } elseif ($name === 'args') {
                $params[] = $args;
            } elseif ($param->isDefaultValueAvailable()) {
                $params[] = $param->getDefaultValue();
            } else {
                $this->usageError("Missing required option --$name.");
            }
            unset($options[$name]);
        }

        // try global options
        if (!empty($options)) {
            $class = new ReflectionClass(get_class($this));
            foreach ($options as $name => $value) {
                if ($class->hasProperty($name)) {
                    $property = $class->getProperty($name);
                    if ($property->isPublic() && !$property->isStatic()) {
                        $this->$name = $value;
                        unset($options[$name]);
                    }
                }
            }
        }

        if (!empty($options)) {
            $this->usageError("Unknown options: " . implode(', ', array_keys($options)));
        }

        $this->init();
        $exitCode = $method->invokeArgs($this, $params);
        return $exitCode;
    }

    /**
     * Parses the command line arguments and determines which action to perform.
     * @param array $args command line arguments
     * @return array the action name, named options (name=>value), and unnamed options
     * @since 1.1.5
     */
    protected function resolveRequest($args)
    {
        $options = array(); // named parameters
        $params = array(); // unnamed parameters
        foreach ($args as $arg) {
            if (preg_match('/^--(\w+)(=(.*))?$/', $arg, $matches)) // an option
            {
                $name = $matches[1];
                $value = isset($matches[3]) ? $matches[3] : true;
                if (isset($options[$name])) {
                    if (!is_array($options[$name])) {
                        $options[$name] = array($options[$name]);
                    }
                    $options[$name][] = $value;
                } else {
                    $options[$name] = $value;
                }
            } elseif (isset($action)) {
                $params[] = $arg;
            } else {
                $action = $arg;
            }
        }
        if (!isset($action)) {
            $action = $this->defaultAction;
        }

        return array($action, $options, $params);
    }

    /**
     * Provides the command description.
     * This method may be overridden to return the actual command description.
     * @return string the command description. Defaults to 'Usage: php entry-script.php command-name'.
     */
    public function getHelp()
    {
        $help = 'Usage: ' . basename(__FILE__) . " <action>";
        $help .= $this->getClassHelp();
        $options = $this->getOptionHelp();
        if (empty($options)) {
            return $help;
        }
        if (count($options) === 1) {
            return $help . ' ' . $options[0];
        }
        $help .= "\nActions:\n";
        foreach ($options as $option) {
            $help .= '    ' . $option . "\n";
        }
        return $help;
    }

    public function getClassHelp()
    {
        $help = '';
        $class = new ReflectionClass(get_class($this));
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC ^ ReflectionProperty::IS_STATIC);
        foreach ($properties as $property) {

            $optional = $property->isDefault();
            $defaultValue = $optional ? $property->getValue($this) : null;
            if (is_array($defaultValue)) {
                $defaultValue = str_replace(array("\r\n", "\n", "\r"), "", print_r($defaultValue, true));
            }
            $name = $property->getName();

            if ($optional) {
                $help .= " [--$name=$defaultValue]";
            } else {
                $help .= " --$name=value";
            }
        }

        return $help;
    }

    /**
     * Provides the command option help information.
     * The default implementation will return all available actions together with their
     * corresponding option information.
     * @return array the command option help information. Each array element describes
     * the help information for a single action.
     * @since 1.1.5
     */
    public function getOptionHelp()
    {
        $options = array();
        $class = new ReflectionClass(get_class($this));
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if (!strncasecmp($name, 'action', 6) && strlen($name) > 6) {
                $name = substr($name, 6);
                $name[0] = strtolower($name[0]);
                $help = $name;

                foreach ($method->getParameters() as $param) {
                    $optional = $param->isDefaultValueAvailable();
                    $defaultValue = $optional ? $param->getDefaultValue() : null;
                    if (is_array($defaultValue)) {
                        $defaultValue = str_replace(array("\r\n", "\n", "\r"), "", print_r($defaultValue, true));
                    }
                    $name = $param->getName();

                    if ($name === 'args') {
                        continue;
                    }

                    if ($optional) {
                        $help .= " [--$name=$defaultValue]";
                    } else {
                        $help .= " --$name=value";
                    }
                }
                $options[] = $help;
            }
        }
        return $options;
    }

    /**
     * Displays a usage error.
     * This method will then terminate the execution of the current application.
     * @param string $message the error message
     * @param int $returnCode
     */
    public function usageError($message, $returnCode = 1)
    {
        echo "Error: $message\n\n" . $this->getHelp() . "\n";
        exit($returnCode);
    }
}
