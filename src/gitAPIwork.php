<?php
namespace ierusalim\gitAPIwork;

require_once '../vendor/autoload.php';

$h = new GitHookUpdater('', 'D:\\work\\actions');
$rawURL = "https://api.github.com/repos/ierusalim/HashFiler/git/blobs/1e3aaf491be56470a5a2830c2594e4a9758de9a1";
$fileContent = $h->url_get_contents($rawURL);
$file_json = json_decode($fileContent);
$content64 = $file_json->content;
$sha = sha1($content64);
$fileContent = base64_decode($content64);
$git_blob = 'blob' . ' ' . strlen($fileContent) . "\0" . $fileContent;
$sha = sha1($git_blob);

$rawURL = $h->make_raw_git_url('', 'ierusalim/HashFiler', 'master', 'composer.json');

$rawURL = "https://api.github.com/repos/ierusalim/HashFiler/git/blobs/86ea729dfbea2ece9fef3ae88b56b027b6551adb";
$fileContent = $h->url_get_contents($rawURL);
$file_json = json_decode($fileContent);
$file_content = base64_decode($file_json->content);

$sha = sha1($fileContent);

$x = $h->check_local_file(
    "D:\\work\\GitHub\\ierusalim\\HashFiler\\composer.json",
    378,
    "86ea729dfbea2ece9fef3ae88b56b027b6551adb"
    );
print_r($h->git_repo_download('ierusalim/HashFiler', 'master'));

class gitAPIwork {
    public function make_repo_list_json_git_url(
        $owner_and_repo_name,
        $branch = 'master'
    ) {
        return
             'https://api.github.com/repos/'
            . $owner_and_repo_name
            . '/git/trees/'
            . $branch
            . '?recursive=1'
        ;
    }
    public function git_repo_local_compare(
         $owner_and_repo_name,
         $branch = 'master'      
    ) {
        $path_arr = $this->git_repo_get_all_path_arr( 
            $owner_and_repo_name,
            $branch
        );
        print_r($path_arr);
    }
    function git_check_local_file($fileName, $ExpectedSize, $ExpectedGitHash) {
        $fileContent = @file_get_contents($fileName);
        if($fileContent === false) return false;
        if(strlen($fileContent) != $ExpectedSize) return false;
        $git_hash = sha1(
             'blob' 
            . ' '
            . strlen($fileContent)
            . "\0"
            . $fileContent
        );
        return ($git_hash == $ExpectedGitHash);
    }
    
    public function get_repo_list_git_arr(
         $owner_and_repo_name,
         $branch = 'master'      
    ) {
        $repo_json_url = $this->make_repo_list_json_git_url($owner_and_repo_name,$branch);
        $raw_json = $this->https_get_contents($repo_json_url);
        $repo_list_arr = json_decode($raw_json , true);
        return $repo_list_arr;
    }
    public function git_repo_get_all_path_arr( 
        $owner_and_repo_name,
        $branch = 'master'      
    ) {
        $repo_list_arr = $this->get_repo_list_git_arr(
            $owner_and_repo_name,
            $branch
        );
        if(!isset($repo_list_arr['tree'])) {
            throw New \Exception("Not found file-tree in $owner_and_repo_name / $ branch");
        }
        $repo_tree = $repo_list_arr['tree'];
        $path_arr = [];
        foreach($repo_tree as $el) {
            $path_arr[]=[
                'path'=>$el['path'],
                'size'=>$el['size'],
                'sha'=>$el['sha']
            ];
        }
        return $path_arr; 
    }
    
    public function https_get_contents($url,$ua = 'curl/7.26.0') {
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_USERAGENT, $ua);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         $data = curl_exec($ch);
         curl_close($ch);
         return $data;
    }
    public function url_get_contents(
        $url,
        $http_opt_arr = ['user_agent' => 'curl/7.26.0']
    ) {
        return \file_get_contents($url, false, \stream_context_create([
            'http' => $http_opt_arr
        ]));
    }
    
}
