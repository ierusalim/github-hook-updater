<?php
namespace ierusalim\GitHookUpdater;

use ierusalim\GitHubWebhook\Handler;

class GitHookUpdater {
    const githook_subdir = '.githook';
    public $githook_dir;
    private $workdir;
    private $webhook_handler;
    public $webhook_log = false;
    public $commits_arr = [];
    
    public function __construct($secret, $workdir)
    {
        $workdir =  dirname( $workdir . DIRECTORY_SEPARATOR . 'a');
        if (!is_dir($workdir)) {
            throw new \Exception("Work directory not exist $workdir");
        }
        $workdir .= DIRECTORY_SEPARATOR;
        $this->workdir = $workdir;
        
        $this->githook_dir = $this->check_make_subdir(self::githook_subdir);
        
        //remove it if not need webhook-debug-log
        $webhook_log=$this->githook_dir . 'webhook.log';
        $this->webhook_log = $webhook_log;
        
        //make Webhook-Receiver
        $this->webhook_handler = new Handler( $secret,
            function ($in_arr) use ($webhook_log) {
            	extract($in_arr); // $event , $data , $delivery
                //get commits-array if present
                if(isset($data['commits'])) {
                    $commits_arr = $data['commits'];
                } else {
                    $commits_arr = [];
                }

                //Write webhook-debug-log if log-file defined
                if(!empty($webhook_log)) {
                    file_put_contents(
                        $webhook_log,
                        $event . ' ' . $delivery . print_r($data,true),
                        FILE_APPEND
                    );
                }
                return compact('event','delivery','commits_arr');
            }
        );
    }
    
    private function check_make_subdir($subdir) {
        $workdir = $this->workdir . $subdir;
        if(!is_dir($workdir)) {
            if(!mkdir($workdir)) {
                throw new \Exception("Can't create $workdir");
            }
        }
        return $workdir . DIRECTORY_SEPARATOR;
    }
    
    public function get_commits_array() {
        $webhook_log = $this->webhook_log;
        if($commits_arr = $this->webhook_handler->handle()) {
            if(!empty($commits_arr)) {
                //Write webhook-debug-log if log-file defined
                if(!empty($webhook_log)) {
                    file_put_contents(
                        $webhook_log,
                        "Commits_array received:". print_r($commits_arr,true),
                        FILE_APPEND
                    );
                }
            }
            return $commits_arr;
       }
       return [];
    }
}