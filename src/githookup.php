<?php
namespace ierusalim\GitHookUpdater;

use ierusalim\GitHubWebhook\Handler;

class GitHookUpdater {
    const githook_subdir = '.githook';
    public $githook_dir;
    public $repository_html_url;
    private $workdir;
    private $webhook_handler;
    public $commits_log = false;
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
        $this->commits_log = $this->githook_dir . 'commits-push.log';
        
        //make Webhook-Receiver
        $this->webhook_handler = new Handler( $secret,
            function ($in_arr) {
            	extract($in_arr); // $event , $data , $delivery

                //get base repository html-url
                $repository_html_url =
                    (isset($data['repository']['html_url']))?
                        $data['repository']['html_url'] : '';

                //return commits-array if it present and if push-event
                if(isset($data['commits']) && ($event=='push')) {
                    return array_merge([
                        'meta'=>compact(
                            'repository_html_url',
                            'event',
                            'delivery'
                        ),
                    ], $data['commits']);
                }
                return [];
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
        //its not empty only if received webhook-push-event
        $this->commits_arr = $this->webhook_handler->handle();
        if(empty($this->commits_arr)) return [];

        //Write commits-received-debug-log (if log-file defined)
        if(!empty($this->commits_log)) {
            file_put_contents(
                $this->commits_log,
                "Received push-commits:". print_r($this->commits_arr,true),
                FILE_APPEND
            );
        }
        return $commits_arr;
    }
    
    public function save_commits() {
        $act_names_arr=[];
        $commits_arr = $this->commits_arr;
        if(empty($commits_arr)) return false;
        $meta = $commits_arr['meta'];
        unset( $commits_arr['meta'] );
        $this->repository_html_url = $meta['repository_html_url'];
        foreach($commits_arr as $one_commit_arr) {
            $commit_id = ' ' . $one_commit_arr['id'];
            $timestamp = (isset($one_commit_arr['timestamp']))?
                ' '.$one_commit_arr['timestamp'] : '';
            foreach(['added','removed','modified'] as $action) {
                if(!empty($one_commit_arr[$action])) {
                    foreach($one_commit_arr[$action] as $fileName) {
                        $srcURL=$this->repository_html_url . '/' . $fileName;
                        $name_md=md5($srcURL);
                        if(!isset($act_names_arr[$name_md])) {
                            $act_names_arr[$name_md] = [
                                $this->repository_html_url,
                                $this->workdir,
                                $fileName, ''
                            ];
                        }
                        $act_names_arr[$name_md][]=$action . $commit_id . $timestamp;
                    }
                }
            }
        }
        if(!empty($this->commits_log)) {
            file_put_contents(
                $this->commits_log,
                "Actions received:". print_r($act_names_arr,true),
                FILE_APPEND
            );
        }
        foreach($act_names_arr as $name_md => $one_name_arr) {
            $githook_name = $this->githook_dir . $name_md . '.cmt';
            if(is_file($githook_name)) {
                for ($i = 0; $i < 10; $i++) {
                    if(!empty($one_name_arr[$i])) continue;
                    $one_name_arr[$i]=array_slice($one_name_arr,$i+1);
                    break;
                }
            }
            file_put_contents($githook_name,
                implode(\PHP_EOL,$one_name_arr) . \PHP_EOL
            ,FILE_APPEND);
        }
        return $act_names_arr;
    }
}
