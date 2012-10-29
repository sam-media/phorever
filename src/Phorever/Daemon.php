<?php
namespace Phorever;

class Daemon
{
    const RUNNING_OK = "running_ok";
    const STOPPED_BUT_PID_PRESENT = "stopped_but_pid_present";
    const STOPPED_OK = "stopped_ok";

    /**
     * @var bool
     */
    protected $isChild;

    /**
     * @var int
     */
    protected $pid;

    /**
     * @var string
     */
    protected $pidfile;

    /**
     * @var callback
     */
    protected $callback;

    public function __construct($pidfilepath) {
        $this->setPidFilePath($pidfilepath);
    }

    public function setPidFilePath($file) {
        $this->pidfile = $file;
    }

    public function getPidFilePath() {
        return $this->pidfile;
    }

    public function start($callback) {
        if ($this->getPid())
            throw new \Exception("Process already started");

        $pid = pcntl_fork();

        if ($pid == -1) {
            throw new \Exception("Unable to fork");
        } else if ($pid > 0) {
            $this->writePid($pid);
        } else {
            $this->isChild = true;
            call_user_func_array($callback, array());
            $this->clearPid();
        }
    }

    public function stop() {
        if (!$this->getPid())
            throw new \Exception("Process not running");

        foreach(range(1,25) as $i) {
            switch ($i) {
                case 1:
                    posix_kill($this->getPid(), SIGTERM);

                    if (posix_get_last_error() == SOCKET_EPERM)
                        throw new \Exception("You do not have permission to stop this process");

                    if ($this->status() != self::RUNNING_OK) {
                        break 2;
                    }

                    break;
                case 25:
                    posix_kill($this->getPid(), SIGKILL);

                    if ($this->status() != self::RUNNING_OK) {
                        break 2;
                    }

                    break;
                default:
                    posix_kill($this->getPid(), SIGTERM);

                    if ($this->status() != self::RUNNING_OK) {
                        break 2;
                    }

                    break;
            }
            sleep(1);
        }

        if ($this->status() == self::RUNNING_OK)
            throw new \Exception("There was an error attempting to end the process");

        $this->clearPid();
    }

    public function status() {
        if ($pid = $this->getPid(true)) {
            $output = array();
            $result = 0;

            exec("ps $pid", $output, $result);

            // check the number of lines that were returned
            if(count($output) >= 2){
                return self::RUNNING_OK;
            } else {
                return self::STOPPED_BUT_PID_PRESENT;
            }
        } else {
            return self::STOPPED_OK;
        }
    }

    public function getPid($force_file = false) {
        if ($force_file) $this->pid = null;

        if (!$this->pid && file_exists($this->pidfile))
            $this->pid = (int)file_get_contents($this->pidfile);

        return $this->pid;
    }

    public function writePid($pid) {
        $this->pid = $pid;
        file_put_contents($this->pidfile, $this->pid, LOCK_EX);
    }

    public function clearPid() {
        $this->pid = null;
        unlink($this->pidfile);
    }
}