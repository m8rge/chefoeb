<?php

require_once 'CronApp.php';

/**
 * Class Chefoeb
 * @version 0.2
 */
class Chefoeb extends CronApp
{
    /**
     * @var string
     */
    public $sentryDsn;
    /**
     * @var Raven_Client
     */
    protected $ravenClient;

    /**
     * @var string
     */
    public $hipChatAuthToken;
    /**
     * @var string
     */
    public $hipChatRoomId;
    /**
     * @var string
     */
    public $hipChatColor = 'yellow';

    public function actionVersion()
    {
        echo "Chefoeb v0.2\n";
    }

    public function init()
    {
        parent::init();

        if (!empty($this->sentryDsn)) {
            $this->ravenClient = new Raven_Client($this->sentryDsn);

            $error_handler = new Raven_ErrorHandler($this->ravenClient);
            $error_handler->registerExceptionHandler();
            $error_handler->registerErrorHandler();
            $error_handler->registerShutdownFunction();
        }
    }

    public function actionIndex($fromCommit = null)
    {
        $this->exec('which git');
        $this->exec('which knife');
        if (is_null($fromCommit)) {
            $commits = $this->exec('git log -1 --pretty=format:"%H"');
            $fromCommit = reset($commits);

            $this->exec('git pull origin master');
            $this->exec('git submodule sync');
            $this->exec('git submodule update --init');
        }

        $diffStatus = $this->exec("git diff --name-status $fromCommit HEAD");

        $this->sendUpdatesToChefServer($diffStatus);
    }

    /**
     * @param $diffStatus
     */
    protected function sendUpdatesToChefServer($diffStatus)
    {
        $folderMapSubCommand = array(
            'environments' => 'environment',
            'roles' => 'role',
            'nodes' => 'node',
        );
        $changes = array();
        foreach ($diffStatus as $line) {
            if (preg_match('#(\w)\s+(([^/]+).+)#', $line, $matches)) {
                if ($matches[3] == 'cookbooks' || $matches[1] != 'D') {
                    $changes[$matches[3]][] = $matches[2];
                }
            }
        }

        foreach ($changes as $folder => $files) {
            if (in_array($folder, array('environments', 'roles', 'nodes'))) {
                $this->applyFromFile($folderMapSubCommand[$folder], $files);
            } elseif ($folder == 'cookbooks') {
                $this->applyCookbooks($files);
            } elseif ($folder == 'data_bags') {
                $this->applyDataBags($files);
            }
        }
    }

    /**
     * @param string[] $files
     * @throws Exception
     */
    protected function applyCookbooks($files)
    {
        $changedCookbooks = array();
        foreach ($files as $file) {
            if (preg_match('#cookbooks/([^/]+)#', $file, $matches)) {
                if (!in_array($matches[1], $changedCookbooks)) {
                    $changedCookbooks[] = $matches[1];
                }
            } else {
                throw new Exception("preg_match failed while parsing cookbook name on file: $file");
            }
        }

        $this->exec("knife cookbook upload " . implode(' ', $changedCookbooks));
    }

    /**
     * @param string[] $files
     * @throws Exception
     */
    protected function applyDataBags($files)
    {
        foreach ($files as $file) {
            if (preg_match('#data_bags/([^/]+)/(.+)#', $file, $matches)) {
                $this->exec("knife data bag from file $matches[1] $matches[2]");
            } else {
                throw new Exception("preg_match failed while parsing data bag name on file: $file");
            }
        }
    }

    /**
     * @param string $subCommand
     * @param string[] $files
     */
    protected function applyFromFile($subCommand, $files)
    {
        foreach ($files as $filename) {
            $this->exec("knife $subCommand from file $filename");
        }
    }
}
