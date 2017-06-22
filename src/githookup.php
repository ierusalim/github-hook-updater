<?php
namespace ierusalim\GitHookUpdater;

use ierusalim\GitHubWebhook\Handler;

class GitHookUpdater {
    const githook_subdir = '.githook';
    public $githook_dir;
    
    public $meta; //[repository_html_url], [repository_git_name], [current_branch], [event]
    
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
                
                //get repository_git_name (from ['repository']['full_name']
                $repository_git_name =
                    (isset($data['repository']['full_name']))?
                        $data['repository']['full_name'] : '';
                
                //get current branch from ['repository']['default_branch']
                $current_branch =
                    (isset($data['repository']['default_branch']))?
                        $data['repository']['default_branch'] : 'master';

                //return commits-array if it present and if push-event
                if(isset($data['commits']) && ($event=='push')) {
                    return array_merge([
                        'meta'=>compact(
                            'repository_html_url',
                            'repository_git_name',
                            'current_branch',
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
        $commits_arr = $this->webhook_handler->handle();
        if(empty($commits_arr)) return [];

        //Write commits-received-debug-log (if log-file defined)
        if(!empty($this->commits_log)) {
            file_put_contents(
                $this->commits_log,
                "Received push-commits:". print_r($commits_arr,true),
                FILE_APPEND
            );
        }
        //get [meta] and remove [meta] from commits_arr
        $this->meta = $commits_arr['meta'];
        unset( $commits_arr['meta'] );

        $this->commits_arr = $commits_arr;
        return $this->commits_arr;
    }
    
    public function save_commits() {
        if(empty($this->commits_arr)) return false;
        $commits_arr = $this->commits_arr;

        $meta = $this->meta;
        $repository_html_url = $meta['repository_html_url'];
        $repository_git_name = $meta['repository_git_name'];
        $current_branch = $meta['current_branch'];
        
        $act_names_arr=[];
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

                                //First string: base URL (not for download)
                                'base_url=' . $srcURL,

                                //Second string: Full-URL for RAW-download file
                                'from_url=' . $this->make_raw_git_url(
                                        $srcURL,
                                        $repository_git_name,
                                        $current_branch,
                                        $fileName
                                    ),

                                //Third string: PATH for save downloaded file
                                'to_path=' . $this->workdir, //to PATH

                                //Fourth string: filename for save downloaded file
                                'to_file=' . $fileName, //Filename (for added to PATH)

                                //Last string of header must be empty
                                ''
                            ];
                        }
                        //Add action string
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
                    $one_name_arr=array_slice($one_name_arr,$i+1);
                    break;
                }
            }
            file_put_contents($githook_name,
                implode(\PHP_EOL,$one_name_arr) . \PHP_EOL
            ,FILE_APPEND);
        }
        return $act_names_arr;
    }
    
    public function make_raw_git_url( 
        $srcURL,
        $repository_git_name,
        $current_branch,
        $fileName
    ) {
        return
             'https://raw.githubusercontent.com/'
            . $repository_git_name
            . '/'
            . $current_branch
            . $fileName
        ;
    }
}
