<?php
namespace ierusalim\applyActions;

class applyActions {
    private $dir;
    private $ext = '.cmt';
    public $fn_file_save = __CLASS__ . '::save_target_file';
    public $fn_file_remove = __CLASS__ . '::remove_target_file';

    public function __construct(
        $dir, 
        $ext = NULL
    ) {
        $this->dir = $dir;
        if(!is_null($ext)) $this->ext = $ext;
    }
    
    public function time_worker($time_limit_sec = 20) {
        $exit_time = time(true) + $time_limit_sec;
        
        $l=strlen($this->ext);
        foreach($this->all_in_dir_ext($this->dir,$this->ext) as $fil_arr) {
            extract($fil_arr); //$in_path, $fileName
            $md_name = substr($fileName,0,-$l);
            $fullName = $in_path . $fileName;
            $act_arr = $this->read_head_body_file($fullName, 1);
            if($this->cmt_validate($md_name,$act_arr)) {
                if($this->try_apply_cmt($act_arr)) {
                    unlink($fullName);
                }
            } else {
                $fullBadName = $in_path . $md_name . '-' . rand(1000,999) . '.bad';
                @rename($fullName, $fullBadName);
            }
            if(time()>$exit_time) break;
        }
    }
    public function try_apply_cmt($act_arr) {
        $last_action = explode(' ',$act_arr['last_body_str'])[0];
        $file_content=file_get_contents($act_arr['raw_url']);
        if($file_content === false) {
            if ($last_action == 'removed') {
                //remove target file
                return call_user_func(
                    $this->fn_file_remove,
                    $act_arr
                );
            }
        } else {
            if (
                ($last_action === 'modified') ||
                ($last_action === 'added')
            ){
                //save target file
                return call_user_func(
                    $this->fn_file_save,
                    $act_arr,
                    $file_content
                );
            }
        }
        return false;
    }
    
    public function cmt_validate($md_name,$act_arr) {
        foreach(['repo_url','branch','raw_url','to_path','to_file','last_body_str']
            as $key) {
                if(!isset($act_arr[$key])) return false;
            $$key = $act_arr[$key];
        }
        $name_md = md5($repo_url . $branch . $to_file);
        return ($md_name === $name_md);
    }

    public function read_head_body_file(
        $fullFileName, 
        $read_body=2, 
        $exploder=": "
    ) {
        $f = fopen($fullFileName,'r');
        if(!$f) return false;
        $ret_arr=[];
        while(($last_str = stream_get_line($f, 4096,"\n")) !== false) {
            if(empty($last_str)) break;
            list($key,$val) = explode($exploder,$last_str);
            $ret_arr[$key]=$val;
        }
        if($read_body) {
            $body_arr=[];
            while($st !== false) {
                $last_str=$st;
                $st = stream_get_line($f, 4096, "\n");
                if($read_body<2)continue;
                $body_arr[]=$last_str;
            }
        }
        fclose($f);
        $ret_arr['last_body_str']=$last_str;
        if($read_body>1) $ret_arr['body_arr']=$body_arr;
        return $ret_arr;
    }

    public function all_in_dir_ext($in_path,$ext) {
        $l=strlen($ext);
        //adding DS
        $in_path = dirname($in_path . DIRECTORY_SEPARATOR . 'a') . DIRECTORY_SEPARATOR;
        $act_arr = scandir($in_path);
        foreach($act_arr as $fileName) {
            if(substr($fileName,-$l) !== $ext) continue;
            yield compact('in_path','fileName');
        }
    }
    
    public function save_target_file($act_arr,$file_content) {
        $targetFile = $act_arr['to_file'];
        $targetPath = $act_arr['to_path'];
        $targetFullName = $targetPath . $targetFile;
        $ret = file_put_contents($targetFullName,$file_content);
        return ($ret !== false);
    }
    
    public function remove_target_file($act_arr) {
        $targetFile = $act_arr['to_file'];
        $targetPath = $act_arr['to_path'];
        $targetFullName = $targetPath . $targetFile;
        return unlink($targetFullName);
    }
}
