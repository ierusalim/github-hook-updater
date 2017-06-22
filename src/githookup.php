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
    
    public function __construct($secret, $workdir, $cutter_fn = false)
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
            //Function for pre-processing input data received by webhook 
            empty($cutter_fn) ? (__CLASS__ . '::webhook_data_cutter') : $cutter_fn
        );
    }
    public function webhook_data_cutter($in_arr) {
        //Function for pre-processing input data received by webhook 
        //In: array webhook-data with keys [event], [data], [delivery]
        extract($in_arr); // $event , $data , $delivery

        //Out:
        //Function return array with the following keys:
        // [meta] => array(
        //      'repository_html_url',
        //      'repository_git_name',
        //      'current_branch',
        //      'event',
        //      'delivery' ),
        // [0] => first commit,
        // [1] => second commit (If present),
        //  ... etc
        
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
        
    public function get_commits_array() {
        //Wrapper for webhook-handler, must be called in webhook-handler script
        
        //Function receive commits-array if push-event occurred
        //Do it:
        // - set $this->meta to received [meta]-data 
        // - set $this->commits_arr to commits_array (withoun [meta])
        //return true (nothing interesting)
        
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
        return true;
    }
    
    public function parse_actions($target_dir = NULL) {
        //In: 
        //  - received data in $this->commits_arr and $this->meta
        //Out:
        // - array of recognized file actions 
        
        if(empty($this->commits_arr)) return false;
        $commits_arr = $this->commits_arr;

        $meta = $this->meta;
        $repository_html_url = $meta['repository_html_url'];
        $repository_git_name = $meta['repository_git_name'];
        $current_branch = $meta['current_branch'];
        if(is_null($target_dir)) {
            $workDir = $this->workdir;
        } else {
            $workDir = $target_dir;
        }
        
        $act_names_arr=[];
        foreach($commits_arr as $one_commit_arr) {
            $commit_id = $one_commit_arr['id'];
            $timestamp = (isset($one_commit_arr['timestamp']))?
                $one_commit_arr['timestamp'] : '';
            
            foreach(['added','removed','modified'] as $action) {
                if(!empty($one_commit_arr[$action])) {
                    
                    //Action found. Adding affected files to array.
                    foreach($one_commit_arr[$action] as $fileName) {

                        //url where file is possible to download
                        $rawURL = $this->make_raw_git_url(
                                        $repository_html_url,
                                        $repository_git_name,
                                        $current_branch,
                                        $fileName
                                    );
                        
                        //make distinct key by repoURL, branch and fileName
                        $name_md=md5($repository_html_url . $current_branch . $fileName);
                        
                        if(!isset($act_names_arr[$name_md])) {
                            $act_names_arr[$name_md] = [

                                //First string: base URL (not for download)
                                'repo_url: ' . $repository_html_url,
                                
                                //current branch
                                'branch: ' . $current_branch,
                                
                                //Second string: Full-URL for RAW-download file
                                'raw_url: ' . $rawURL,

                                //Third string: PATH for save downloaded file
                                'to_path: ' . $workDir, //to PATH

                                //Fourth string: filename for save downloaded file
                                'to_file: ' . $fileName, //Filename (for added to PATH)

                                //Last string of header must be empty
                                ''
                            ];
                        }
                        //Add action string to array
                        $act_names_arr[$name_md][] =
                            $action 
                            . ' ' . $commit_id
                            . ' ' . $timestamp
                            ;
                    }
                }
            }
        }
        return $act_names_arr;
    }
    
    function save_actions(
        $actions_save_path = NULL,
        $target_dir = NULL
    ) {
        if(is_null($actions_save_path)) {
            $actions_save_path = $this->githook_dir;
        }
        if($actions_save_path) {
            // Need DS after path
            $actions_save_path = 
                dirname($actions_save_path . DIRECTORY_SEPARATOR . 'a')
                . DIRECTORY_SEPARATOR;
        }

        //get actions array
        $act_names_arr = $this->parse_actions($target_dir);
        if(empty($act_names_arr)) return [];
        
        //write to log (if log-file defined)
        if(!empty($this->commits_log)) {
            file_put_contents(
                $this->commits_log,
                "Actions Recognized: ". print_r($act_names_arr,true),
                FILE_APPEND
            );
        }
        
        if($actions_save_path) {
            //write all actions to *.cmt files
            foreach($act_names_arr as $name_md => $one_name_arr) {
                $githook_name = $actions_save_path . $name_md . '.cmt';
                if(is_file($githook_name)) {
                    //if .cmt-file already exists, do not write header
                    //remove header from array
                    for ($i = 2; $i < 9; $i++) {
                        if(!empty($one_name_arr[$i])) continue;
                        $one_name_arr=array_slice($one_name_arr,$i+1);
                        break;
                    }
                }
                //write array string to file (append if exist)
                file_put_contents($githook_name,
                    implode("\n",$one_name_arr) . "\n"
                ,FILE_APPEND);
            }
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
            . '/'
            . $fileName
        ;
    }
    
    private function check_make_subdir($subdir) {
        //check subdir (under workdir) and create if not exist
        $workdir = $this->workdir . $subdir;
        if(!is_dir($workdir)) {
            if(!mkdir($workdir)) {
                throw new \Exception("Can't create $workdir");
            }
        }
        return $workdir . DIRECTORY_SEPARATOR;
    }
}
